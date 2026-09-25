<?php
/**
 * apache_inspector.php
 * Cross-platform Apache environment inspector (Linux, macOS, Windows)
 *
 * Outputs:
 * - Operating System and Architecture
 * - Apache binary path
 * - Apache version
 * - Apache uptime
 * - Main config path
 * - Included config files
 * - Active Virtual Hosts
 * - Apache environment variables
 * - PHP config info
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.4
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

// Default to config value; override with ?fast=1 or ?fast=0 if provided
$fastMode = $config['ui']['flags']['apacheFastMode'] ?? false;

if ( isset( $_GET['fast'] ) ) {
	$fastMode = filter_var( $_GET['fast'], FILTER_VALIDATE_BOOLEAN );
}

// Force enable fast mode in demo environments
if ( defined( 'DEMO_MODE' ) && DEMO_MODE === true ) {
	$fastMode = true;
}

$apacheInspector = new \AMPBoard\Apache\Inspector( $config['paths']['apache'], $apacheCommands, $fastMode );

// SYSTEM INFO
$os   = PHP_OS_FAMILY;
$arch = ( PHP_INT_SIZE === 8 ) ? '64-bit' : '32-bit';

echo '
<div class="heading">
    ' . $ui->renderHeading( 'Apache Inspector', 'h2', true ) . '
</div>
<pre>';
echo "🖥️ Operating System: $os ($arch)\n";
echo "🚀 Fast Mode: " . ( $fastMode ? 'Enabled (some checks skipped)' : 'Disabled (full inspection)' ) . "\n";

// ==== OUTPUT ====
echo "🧠 Apache Context Detected: " . ( $apacheInspector->isApache() ? 'Yes' : 'No' ) . "\n";
echo "📃 Apache SAPI: " . ( $apacheInspector->detectApacheSAPI() ?? 'Unknown or not Apache' ) . "\n";

$version = $apacheInspector->getApacheVersion();
echo "📦 Apache Version: $version\n";

$binary = $apacheInspector->detectApacheBinary();
echo "📓 Apache Binary: " . ( $binary ?: "Not found" ) . "\n";

if ( ! $fastMode ) {
	$uptime = $apacheInspector->getApacheUptimeEstimate( $os );
	echo "🕒 Apache Uptime (estimated): " . ( $uptime !== 'Unavailable' ? $uptime : 'Not available on this platform or config' ) . "\n";
}

if ( $binary && ! $fastMode ) {
	$apacheConfig = $apacheInspector->getApacheConfigPath( $binary );
	echo "📝 Config File: " . ( $apacheConfig ?: "Not detected" ) . "\n";

	if ( $apacheConfig && file_exists( $apacheConfig ) ) {
		$includes = $apacheInspector->getIncludes( $apacheConfig );
		if ( $includes ) {
			echo "📂 Included Config Files:\n";
			foreach ( $includes as $inc ) {
				echo "  - $inc\n";
			}
		} else {
			echo "ℹ️ No Include directives found.\n";
		}
	}

	$virtualHostOutput = $apacheInspector->getVirtualHosts( $binary );
	if ( $virtualHostOutput ) {
		echo "\n🌐 Active Virtual Hosts:\n$virtualHostOutput\n";
	} else {
		echo "❌ VirtualHost information not available (likely restricted).\n";
	}
} elseif ( $binary ) {
	echo "🔴 Fast mode: Config/VHosts skipped.\n";
} else {
	echo "❌ Apache Binary Not Found. Config/VHosts skipped.\n";
}

// Output Apache environment vars
echo "\n🌱 Apache Environment Variables:\n";
$envVars = $apacheInspector->getApacheEnvVars();
if ( $envVars ) {
	foreach ( $envVars as $k => $v ) {
		echo "  $k: $v\n";
	}
} else {
	echo "  None detected.\n";
}

// Output PHP .ini info
echo "\n⚙️ PHP Configuration:\n";
foreach ( $apacheInspector->getIniFilesInfo() as $k => $v ) {
	if ( $k === 'Loaded php.ini' ) { $v = obfuscate_value( $v ); }
	echo "  $k: $v\n";
}

echo "\n📅 Inspection complete.</pre>";
