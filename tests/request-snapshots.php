<?php
/** Request isolation and runtime fallback fixtures; no native commands are executed. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';

use AMPBoard\Apache\Inspector;
use AMPBoard\Apache\NativeRuntimeReader;
use AMPBoard\Ui\Renderer;

function checkRequestSnapshot( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
$commands = new class implements \AMPBoard\Apache\CommandRunner {
	public function run( string $command ): array { throw new RuntimeException( 'No command permitted by this fixture' ); }
};
$runtime = new class implements \AMPBoard\Apache\RuntimeReader {
	public int $reads = 0;
	public bool $apache = false;
	public ?string $version = null;
	public string $modules = '';
	public bool $throws = false;
	private function read(): void { ++$this->reads; if ( $this->throws ) { throw new RuntimeException( 'Unavailable native observation' ); } }
	public function isApache(): bool { $this->read(); return $this->apache; }
	public function apacheVersion(): ?string { $this->read(); return $this->version; }
	public function moduleInfo(): string { $this->read(); return $this->modules; }
	public function environment(): array { $this->read(); return [ 'APACHE_RUN_DIR' => '/fixture/run' ]; }
	public function iniFiles(): array { $this->read(); return [ 'Loaded php.ini' => '/fixture/php.ini', 'Scanned .ini files' => 'N/A' ]; }
};

$_SERVER['SCRIPT_NAME'] = '/captured/utils/phpinfo.php';
$defaultUi = new Renderer( [] );
$rootUi = new Renderer( [], '/utils/phpinfo.php' );
$nestedUi = new Renderer( [], '/project/utils/phpinfo.php' );
$_SERVER['SCRIPT_NAME'] = '/changed/utils/phpinfo.php';
foreach ( [ [ $defaultUi, '/captured/' ], [ $rootUi, '/' ], [ $nestedUi, '/project/' ], [ new Renderer( [], '/index.php' ), '/' ], [ new Renderer( [], '/project/index.php' ), '/project/' ] ] as [ $ui, $base ] ) {
	$assets = $ui->renderVersionedAssetsWithBase();
	checkRequestSnapshot( str_contains( $assets, 'window.BASE_URL || ' . json_encode( $base ) ), 'Independent captured asset base ' . $base );
	checkRequestSnapshot( str_contains( $assets, 'href="' . $base . 'dist/css/style.min.css?v=' ) && str_contains( $assets, 'src="' . $base . 'dist/js/script.min.js?v=' ), 'CSS and JS use the captured base' );
}
$partialUi = new Renderer( [], '/project/partials/settings.php' );
checkRequestSnapshot( str_contains( $partialUi->renderVersionedAssetsWithBase( null, null, null, '/partials' ), json_encode( '/project/' ) ), 'Custom suffix for standalone partials' );
checkRequestSnapshot( str_contains( $nestedUi->renderVersionedAssetsWithBase( null, null, null, '' ), json_encode( '/project/utils/' ) ), 'Empty suffix retains the script directory' );
$emptyUi = new Renderer( [], '' );
checkRequestSnapshot( str_contains( $emptyUi->renderVersionedAssetsWithBase( null, null ), json_encode( '/' ) ), 'Explicit empty script does not fall back to ambient data' );

$_SERVER['SERVER_SOFTWARE'] = 'Apache/captured';
$default = new Inspector( '', $commands, true, '/missing', null, $runtime );
$apache = new Inspector( '', $commands, true, '/missing', [ 'SERVER_SOFTWARE' => 'Apache/fixture' ], $runtime );
$other = new Inspector( '', $commands, true, '/missing', [ 'SERVER_SOFTWARE' => 'nginx/fixture' ], $runtime );
$absent = new Inspector( '', $commands, true, '/missing', [], $runtime );
$empty = new Inspector( '', $commands, true, '/missing', [ 'SERVER_SOFTWARE' => '' ], $runtime );
checkRequestSnapshot( $runtime->reads === 0, 'Construction captures request inputs without native probes' );
$_SERVER['SERVER_SOFTWARE'] = 'Apache/changed';
checkRequestSnapshot( $apache->isApache() && ! $other->isApache(), 'Independent Apache detection from supplied request headers' );
checkRequestSnapshot( $default->getApacheVersion() === 'via SERVER_SOFTWARE: Apache/captured' && $apache->getApacheVersion() === 'via SERVER_SOFTWARE: Apache/fixture' && $other->getApacheVersion() === 'via SERVER_SOFTWARE: nginx/fixture', 'Version labels keep their captured request values' );
checkRequestSnapshot( $empty->getApacheVersion() === 'via SERVER_SOFTWARE: ' && $absent->getApacheVersion() === 'not detected', 'Empty and absent headers retain distinct fallback behavior' );
$runtime->modules = 'apache2handler Apache/2.4.62';
checkRequestSnapshot( $absent->getApacheVersion() === 'via phpinfo: Apache/2.4.62' && $absent->detectApacheSAPI() === 'apache2handler (Apache SAPI)', 'Supplied module output retains version/SAPI fallback labels' );
$runtime->apache = true;
$runtime->version = 'Apache/native';
checkRequestSnapshot( $other->isApache() && $apache->getApacheVersion() === 'via apache_get_version: Apache/native', 'Native Apache detection and version retain precedence' );
checkRequestSnapshot( $apache->getApacheEnvVars() === [ 'APACHE_RUN_DIR' => '/fixture/run' ] && $apache->getIniFilesInfo()['Loaded php.ini'] === '/fixture/php.ini', 'Environment and INI diagnostics come from the supplied reader' );
$runtime->throws = true;
$unavailable = $absent->inspect( 'Fixture', '64-bit' );
checkRequestSnapshot( $unavailable['isApache'] === false && $unavailable['version'] === 'not detected' && $unavailable['sapi'] === null && $unavailable['environment'] === [] && $unavailable['ini'] === [], 'Unavailable native reads retain independent report fallbacks' );

// Native environment reads stay live runtime observations and preserve historical filtering.
$originalEnvironment = getenv( 'APACHE_RUN_DIR' );
try {
	$native = new NativeRuntimeReader();
	putenv( 'APACHE_RUN_DIR=/fixture/native' );
	checkRequestSnapshot( $native->environment()['APACHE_RUN_DIR'] === '/fixture/native', 'Native reader observes the requested environment keys' );
	putenv( 'APACHE_RUN_DIR=0' );
	checkRequestSnapshot( ! isset( $native->environment()['APACHE_RUN_DIR'] ), 'Native environment retains false-value filtering' );
	$level = ob_get_level();
	checkRequestSnapshot( is_string( $native->moduleInfo() ) && ob_get_level() === $level, 'Native module capture restores the output buffer' );
	checkRequestSnapshot( $native->iniFiles()['Loaded php.ini'] === ( php_ini_loaded_file() ?: 'N/A' ), 'Native INI fallback remains unchanged' );
} finally { putenv( $originalEnvironment === false ? 'APACHE_RUN_DIR' : 'APACHE_RUN_DIR=' . $originalEnvironment ); }
echo "PASS explicit request snapshots and native diagnostic boundaries\n";
