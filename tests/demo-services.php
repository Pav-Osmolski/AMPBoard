<?php
/** Independent demo policies and actual panels, without procedural masking helpers. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Ui\DemoMask;
if ( ( $argv[1] ?? '' ) === 'legacy-normal' ) {
	require __DIR__ . '/../config/helpers/security.php';
	if ( obfuscate_value( 'visible' ) !== 'visible' ) { exit( 1 ); }
	define( 'DEMO_MODE', false );
	if ( obfuscate_value( 'visible' ) !== 'visible' ) { exit( 1 ); }
	echo "PASS legacy masking with absent/false demo constant\n"; exit;
}

function checkDemo( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
$normal = new DemoMask( false ); $masked = new DemoMask( true );
foreach ( [ '' => '****', 'abcd' => '****', 'abcde' => '************',
	'abcdefghijkl' => '************', 'abcdefghijklm' => '****************' ] as $value => $expected ) {
	checkDemo( $masked->value( $value ) === $expected && $normal->value( $value ) === $value, 'Mask length and normal mode: ' . strlen( $value ) );
}
checkDemo( $masked->value( str_repeat( "é", 3 ) ) === '************', 'Historical byte-length policy' );
checkDemo( $normal->value( '<&"' ) === '<&"', 'Masking does not escape HTML' );
checkDemo( ! function_exists( 'obfuscate_value' ), 'Application fixtures run without legacy mask helper' );
define( 'DEMO_MODE', true );
checkDemo( $normal->value( 'visible' ) === 'visible', 'Constant does not change existing service mode' );
$ui = new class {
	public function renderAccordionSectionStart( ...$args ): void {}
	public function renderAccordionSectionEnd(): void {}
	public function renderHeading( ...$args ): string { return 'Fixture heading'; }
	public function injectSvgWithUniqueIds( ...$args ): string { return ''; }
	public function renderSeparatorLine( ...$args ): void {}
	public function renderButtonBlock( ...$args ): void {}
	public function buildPageViewClasses( ...$args ): string { return 'page-view'; }
	public function renderVersionedAssetsWithBase(): string { return ''; }
	public function renderWidthControls( ...$args ): string { return ''; }
};
$config = [
	'user' => [ 'isDemo' => false ],
	'paths' => [ 'assets' => '/fixture-assets', 'apache' => '/fixture-apache', 'htdocs' => '/fixture-htdocs', 'php' => '/fixture-php' ],
	'db' => [ 'host' => 'fixture-host', 'user' => 'u<&', 'pass' => 'fixture-pass' ],
	'status' => [ 'mySqlHostValid' => false, 'mySqlUserValid' => false, 'mySqlPassValid' => false,
		'apachePathValid' => false, 'phpPathValid' => false, 'apacheToggleAvailable' => false ],
	'ui' => [ 'flags' => [] ],
];
foreach ( [ false, true, false ] as $demo ) {
	$config['user']['isDemo'] = $demo; $demoMask = new DemoMask( $demo );
	ob_start(); require __DIR__ . '/../partials/settings/amp_paths.php'; $html = ob_get_clean();
	checkDemo( str_contains( $html, 'name="DB_HOST" value="' . ( $demo ? '************' : 'fixture-host' ) . '"' ), 'Database field follows supplied mode' );
	checkDemo( str_contains( $html, 'name="DB_USER" value="' . ( $demo ? '************' : 'u&lt;&amp;' ) . '"' ), 'Username keeps escaping-before-mask order' );
	checkDemo( str_contains( $html, 'name="APACHE_PATH" value="' . ( $demo ? '****************' : '/fixture-apache' ) . '"' ), 'Path mask follows supplied mode' );
	ob_start(); require __DIR__ . '/../partials/settings/apache_control.php'; $html = ob_get_clean();
	checkDemo( str_contains( $html, '<code>' . ( $demo ? '****************' : '/fixture-apache' ) . '</code>' ), 'Unavailable Apache path display' );
}

$root = sys_get_temp_dir() . '/ampboard-demo-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/utils', 0700, true ); mkdir( $root . '/config' );
mkdir( $root . '/partials/settings', 0700, true );
copy( __DIR__ . '/../utils/vhosts_manager.php', $root . '/utils/vhosts_manager.php' );
copy( __DIR__ . '/../partials/settings.php', $root . '/partials/settings.php' );
$fixturePanels = [ 'amp_paths', 'user_interface', 'php_manager', 'folders_config', 'link_templates', 'dock_config',
	'vhosts_manager', 'export_files', 'apache_control', 'settings_manager' ];
foreach ( $fixturePanels as $panel ) { file_put_contents( $root . '/partials/settings/' . $panel . '.php', '<?php /* Panels tested independently. */' ); }
file_put_contents( $root . '/config/config.php', '<?php /* Dependencies supplied by fixture. */' );
file_put_contents( $root . '/config/entry-settings.php', '<?php require_once __DIR__ . "/config.php";' );
file_put_contents( $root . '/config/entry-vhosts.php', '<?php require_once __DIR__ . "/config.php";' );
file_put_contents( $root . '/vhosts.conf', 'fixture' );
$vhosts = new class( $root ) {
	private string $root;
	public bool $missing = false;
	public function __construct( string $root ) { $this->root = $root; }
	public function path(): string { return $this->root . ( $this->missing ? '/missing.conf' : '/vhosts.conf' ); }
	public function getVhostServerData(): array {
		return [ 'fixture.test' => [ 'ssl' => true, 'certValid' => false, 'valid' => false, 'docRoot' => '/visible-root' ] ];
	}
};
try {
	foreach ( [ false, true, false ] as $demo ) {
		$config['user']['isDemo'] = $demo; $demoMask = new DemoMask( $demo );
		$config['ui']['themes'] = [ 'types' => [], 'currentTheme' => 'default' ];
		$csrfTokens = new class { public function token(): string { return 'fixture-token'; } };
		ob_start(); require $root . '/partials/settings.php'; $html = ob_get_clean();
		checkDemo( str_contains( $html, 'class="demo-mode"' ) === $demo, 'Settings notice follows config despite DEMO_MODE=true' );
		checkDemo( str_contains( $html, 'name="csrf" value="fixture-token"' ), 'Settings form token retained' );
		$vhosts->missing = false;
		ob_start(); require $root . '/utils/vhosts_manager.php'; $html = ob_get_clean();
		checkDemo( str_contains( $html, 'data-generate-cert=' ) === ! $demo, 'Certificate button follows config despite DEMO_MODE=true' );
		checkDemo( str_contains( $html, 'fixture.test' ) && str_contains( $html, '/visible-root' ) && str_contains( $html, 'class="open-folder"' ), 'Historical vhost display/masking scope retained' );
		$vhosts->missing = true;
		ob_start(); require $root . '/utils/vhosts_manager.php'; $html = ob_get_clean();
		checkDemo( str_contains( $html, '<code>' . ( $demo ? '****************' : htmlspecialchars( $vhosts->path() ) ) . '</code>' ), 'Missing vhost path display' );
	}
} finally {
	foreach ( $fixturePanels as $panel ) { unlink( $root . '/partials/settings/' . $panel . '.php' ); }
	unlink( $root . '/partials/settings.php' ); rmdir( $root . '/partials/settings' ); rmdir( $root . '/partials' );
	unlink( $root . '/vhosts.conf' ); unlink( $root . '/utils/vhosts_manager.php' ); unlink( $root . '/config/entry-settings.php' ); unlink( $root . '/config/entry-vhosts.php' ); unlink( $root . '/config/config.php' );
	rmdir( $root . '/utils' ); rmdir( $root . '/config' ); rmdir( $root );
}
require __DIR__ . '/../config/helpers/security.php';
checkDemo( obfuscate_value( 'short' ) === $masked->value( 'short' ) && $normal->value( 'short' ) === 'short', 'Legacy helper retains constant-based policy' );
echo "PASS explicit demo masking and rendered panels\n";

passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __FILE__ ) . ' legacy-normal', $code );
checkDemo( $code === 0, 'Legacy normal mode' );
