<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
if ( ( $argv[1] ?? '' ) === 'disabled-discovery' ) {
	exit( ( new \AMPBoard\System\Identity( [], [ new \AMPBoard\System\UserDiscovery(), 'discover' ] ) )->user() === 'Guest' ? 0 : 1 );
}
function checkCompositionPolicy( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
foreach ( [ '1', 1, true, 'true', 'on', 'yes' ] as $value ) {
	checkCompositionPolicy( \AMPBoard\Config\BooleanInput::value( $value ), 'Historical true values' );
}
foreach ( [ null, '', 'TRUE', 'false', 'off', 'no', 0, 1.0, [], new stdClass() ] as $value ) {
	checkCompositionPolicy( ! \AMPBoard\Config\BooleanInput::value( $value ), 'Strict boolean policy' );
}
$input = ( new \AMPBoard\Config\SettingsInput() )->normalise( [ 'displayHeader' => 'on', 'displayFooter' => 'TRUE' ] );
checkCompositionPolicy( $input['settings']['displayHeader'] && ! $input['settings']['displayFooter'] && ! function_exists( 'normalise_bool' ), 'Settings normalization works without helpers' );
$calls = 0;
$discovery = new \AMPBoard\System\UserDiscovery( static function () use ( &$calls ) { ++$calls; return " DOMAIN\\Alice\r\n"; } );
checkCompositionPolicy( ( new \AMPBoard\System\Identity( [], [ $discovery, 'discover' ] ) )->user() === 'Alice' && $calls === 1, 'Discovery preserves profile identity' );
checkCompositionPolicy( ( new \AMPBoard\System\Identity( [ 'USERNAME' => '' ], [ $discovery, 'discover' ] ) )->user() === 'Guest' && $calls === 1, 'Empty supplied identity does not trigger discovery' );
$failed = new \AMPBoard\System\UserDiscovery( static function () { throw new RuntimeException( 'Unavailable process' ); } );
checkCompositionPolicy( ( new \AMPBoard\System\Identity( [], [ $failed, 'discover' ] ) )->user() === 'Guest', 'Failed discovery preserves Guest fallback' );
$contracts = require __DIR__ . '/fixtures/composition-contracts.php';
$entries = array_map( static function ( string $file ): string { return substr( basename( $file ), 6, -4 ); }, glob( __DIR__ . '/../config/entry-*.php' ) );
$covered = array_keys( $contracts );
sort( $entries );
sort( $covered );
checkCompositionPolicy( $entries === $covered, 'Every real entry has an explicit composition contract' );
$modes = array_merge( [ 'modern', 'upgrade', 'compatibility', 'no-helpers', 'legacy-profile', 'embedded', 'embedded-reverse', 'embedded:no-helpers', 'embedded-reverse:no-helpers' ], $covered );
foreach ( $covered as $entry ) { $modes[] = $entry . ':no-helpers'; }
foreach ( $modes as $mode ) {
	passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/composition-request.php' ) . ' ' . escapeshellarg( $mode ), $code );
	checkCompositionPolicy( $code === 0, 'Composition fixture ' . $mode );
}
passthru( escapeshellarg( PHP_BINARY ) . ' -n -d disable_functions=shell_exec ' . escapeshellarg( __FILE__ ) . ' disabled-discovery', $code );
checkCompositionPolicy( $code === 0, 'Disabled native discovery returns Guest without a fatal error' );
echo "PASS composition and helper-independent policies\n";
