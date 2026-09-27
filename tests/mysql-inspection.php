<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/fixtures/inspection.php';
use AMPBoard\Database\Inspector;
use AMPBoard\Ui\MysqlReport;

function check( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
function inspectFixture( InspectionConnection $connection, bool $fast = false ): array {
	$calls = 0;
	$inspector = new Inspector( static function () use ( $connection, &$calls ) { ++$calls; return $connection; }, 'fixture-client' );
	check( $calls === 0 && $connection->queries === [] && $connection->closed === 0, 'Construction has no database effects' );
	$report = $inspector->inspect( $fast );
	check( $calls === 1 && $connection->closed === 1, 'Connection closed exactly once' );
	foreach ( $connection->results as $result ) { check( $result->freed === 1, 'Every result freed exactly once' ); }
	return $report;
}
$connection = new InspectionConnection();
$report = inspectFixture( $connection );
check( $report['error'] === null && count( $connection->queries ) === 7, 'Full diagnostics' );
check( $report['databases'] === [ [ 'name' => 'odd`<db>', 'sizeMb' => 1.5 ], [ 'name' => 'empty', 'sizeMb' => 0.0 ] ], 'Quoted identifiers, system schemas omitted, null sizes and empty database' );
check( $report['hostInfo'] === 'local <host>' && $report['processes'][0]['Info'] === '<script>query</script>', 'Snapshot contains raw data' );
$renderer = new MysqlReport();
$html = $renderer->render( $report, false, 1.234 );
check( str_contains( $html, 'odd`&lt;db&gt;: 1.5 MB' ) && str_contains( $html, '3661 seconds (1h 1m 1s)' ), 'Familiar sizes and uptime formatting' );
check( str_contains( $html, '&lt;script&gt;query&lt;/script&gt;' ) && ! str_contains( $html, '<script>' ) && str_ends_with( $html, "1.23 seconds\n</pre>" ), 'HTML escaping and complete output' );
$demo = $renderer->render( $report, true, 0 );
check( ! str_contains( $demo, 'alice' ) && ! str_contains( $demo, 'local &lt;host&gt;' ) && ! str_contains( $demo, 'odd`' ), 'Existing identity/name obfuscation' );
check( count( $connection->queries ) === 7, 'Rendering never queries' );
$badUtf = $report; $badUtf['serverVersion'] = "bad\xFF";
check( str_contains( $renderer->render( $badUtf, false, 0 ), "bad\xEF\xBF\xBD" ), 'Invalid UTF-8 substitution' );
$fastConnection = new InspectionConnection(); $fast = inspectFixture( $fastConnection, true );
check( count( $fastConnection->queries ) === 5 && $fast['databases'][0]['sizeMb'] === null, 'Fast mode skips all table status queries' );
check( str_contains( $renderer->render( $fast, false, 0 ), '📁 Databases:' ), 'Fast heading' );
foreach ( [ 'false', 'throw', 'warning', 'fetch' ] as $failure ) {
	$c = new InspectionConnection();
	foreach ( array_keys( $c->data ) as $sql ) { if ( $sql !== 'SHOW DATABASES' ) { $c->failures[$sql] = $failure; } }
	$r = inspectFixture( $c );
	check( $r['error'] === null && $r['user'] === 'Unknown' && $r['databases'][0]['sizeMb'] === null, 'Partial failures: ' . $failure );
	check( $r['variables'] === null && $r['status'] === null && $r['processes'] === null && count( $c->queries ) === 7, 'All sections attempted: ' . $failure );
	$out = $renderer->render( $r, false, 0 );
	check( str_contains( $out, 'N/A (size unavailable)' ) && str_contains( $out, 'Process list unavailable.' ) && ! str_contains( $out, 'private detail' ), 'Unavailable sections do not claim zero or expose query exceptions' );
}
$c = new InspectionConnection(); $c->failures['SHOW DATABASES'] = 'throw'; $r = inspectFixture( $c );
check( $r['databases'] === null && $r['processes'] !== null && count( $c->queries ) === 5, 'Listing failure does not stop later diagnostics' );
check( str_contains( $renderer->render( $r, false, 0 ), 'Failed to list databases.' ), 'Listing warning retained' );
$c = new InspectionConnection(); foreach ( $c->data as &$rows ) { $rows = []; } unset( $rows ); $r = inspectFixture( $c );
check( $r['variables'] === [] && ! str_contains( $renderer->render( $r, false, 0 ), 'unavailable' ), 'Empty results differ from failed queries' );
$c = new InspectionConnection(); $c->connect_error = 'Offline <error>'; $r = inspectFixture( $c );
check( $c->queries === [] && $r['error'] === 'Offline <error>', 'Failed returned connection is closed without queries' );
$r = ( new Inspector( static function () { throw new RuntimeException( 'Refused <host>' ); }, '' ) )->inspect( false );
check( $renderer->render( $r, false, 0 ) === "<pre>❌ Refused &lt;host&gt;\n</pre>", 'Connection exception escaped and markup closed' );

// Execute the actual endpoint with supplied services and request policy.
$root = sys_get_temp_dir() . '/ampboard-mysql-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/utils', 0700, true ); mkdir( $root . '/config' );
copy( __DIR__ . '/../utils/mysql_inspector.php', $root . '/utils/mysql_inspector.php' );
file_put_contents( $root . '/config/config.php', '<?php /* Supplied fixture dependencies. */' );
try {
	foreach ( [ [ false, null, false, false ], [ true, null, false, true ], [ false, '1', false, true ], [ true, '0', false, false ], [ false, '0', true, true ] ] as [ $saved, $query, $demo, $expected ] ) {
		$c = new InspectionConnection();
		$mysqlInspector = new Inspector( static function () use ( $c ) { return $c; }, 'fixture-client' );
		$config = [ 'paths' => [ 'assets' => dirname( __DIR__ ) . '/assets' ], 'ui' => [ 'flags' => [ 'mysqlFastMode' => $saved ] ], 'user' => [ 'isDemo' => $demo ] ];
		$ui = new AMPBoard\Ui\Renderer( $config );
		$_GET = $query === null ? [] : [ 'fast' => $query ];
		ob_start(); require $root . '/utils/mysql_inspector.php'; $out = ob_get_clean();
		check( count( $c->queries ) === ( $expected ? 5 : 7 ) && $c->closed === 1, 'Endpoint saved/query/demo mode and cleanup' );
		check( str_contains( $out, 'MySQL Inspector' ) && str_ends_with( $out, '</pre>' ), 'Endpoint heading and complete report' );
		if ( $demo ) { check( ! str_contains( $out, 'alice' ), 'Endpoint demo masking' ); }
	}
} finally {
	unlink( $root . '/utils/mysql_inspector.php' ); unlink( $root . '/config/config.php' );
	rmdir( $root . '/utils' ); rmdir( $root . '/config' ); rmdir( $root );
}
echo "PASS MySQL inspection services and endpoint\n";
