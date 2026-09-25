<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$failed = false;
passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/apache.php' ), $code );
$failed = $code !== 0;
foreach ( [ 'database', 'renderer', 'config-default', 'config-user', 'config-local' ] as $scenario ) {
	passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/scenario.php' ) . ' ' . escapeshellarg( $scenario ), $code );
	$failed = $failed || $code !== 0;
}
foreach ( [
	'index.php' => 'id="main-content"',
	'partials/settings.php' => 'id="settings-view"',
	'partials/folders.php' => 'folders',
	'utils/export_files.php' => 'page-view',
	'utils/export_files.php:valid' => 'id="export-files-form"',
	'utils/vhosts_manager.php' => 'page-view',
	'utils/phpinfo.php' => 'page-view',
	'utils/mysql_inspector.php' => 'Fixture database unavailable',
	'utils/apache_inspector.php' => 'Apache Inspector',
	'utils/read_config.php' => 'dir',
] as $entry => $marker ) {
	$output = [];
	exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/request.php' ) . ' ' . escapeshellarg( $entry ) . ' 2>&1', $output, $code );
	$html = implode( "\n", $output );
	$ok = $code === 0 && str_contains( $html, $marker ) && ! preg_match( '/^(?:Fatal error|Warning|Deprecated|Notice):/m', $html );
	if ( $entry === 'index.php' ) {
		$ok = $ok && ! str_contains( $html, 'class="page-view"' );
	}
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $entry . "\n";
	if ( ! $ok ) { echo substr( $html, 0, 1500 ) . "\n"; }
	$failed = $failed || ! $ok;
}
exit( $failed ? 1 : 0 );
