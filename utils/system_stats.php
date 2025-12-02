<?php
/**
 * System Statistics Endpoint
 *
 * Returns basic system statistics including:
 * - CPU usage (platform-specific)
 * - Peak memory usage of PHP process
 * - Disk space usage on the root filesystem
 *
 * Output can be either JSON (for AJAX use) or embedded HTML markup.
 *
 * Configuration is controlled via:
 * - `$displaySystemStats` boolean
 * - `$useAjaxForStats` boolean
 *
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.1
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

if ( ! $config['ui']['flags']['systemStats'] ) {
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'System stats display is disabled.' ] );
	exit;
}

$os = PHP_OS_FAMILY;

// CPU
if ( $os === 'Windows' ) {
	//$cpuLoad = trim( safe_shell_exec( 'wmic cpu get loadpercentage 2>&1' ) );
	$cpuLoad = safe_shell_exec( 'typeperf "\\Processor(_Total)\\% Processor Time" -sc 1' );
	preg_match( '/"[^"]+","([\d.]+)"/', $cpuLoad, $matches );
	$cpu = isset( $matches[1] ) ? round( floatval( $matches[1] ) ) : 'N/A';
} else {
	$load  = sys_getloadavg();
	$cores = (int) safe_shell_exec( "nproc 2>/dev/null || sysctl -n hw.ncpu" );

	if ( ! $cores && isset( $load[0] ) ) {
		// shell_exec failed or returned 0, fall back to raw load average
		$cpu = round( $load[0], 1 );
	} else {
		$cpu = ( isset( $load[0] ) && $cores > 0 )
			? round( $load[0] * 100 / $cores, 1 )
			: 'N/A';
	}
}

// Memory
$memoryUsage     = memory_get_usage( true ) / 1024 / 1024;
$peakMemoryUsage = memory_get_peak_usage( true ) / 1024 / 1024;

// Disk
$diskFree = disk_free_space( "/" ) / disk_total_space( "/" ) * 100;

if ( $config['ui']['flags']['useAjaxForStats'] ) {
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	echo json_encode( [
		"cpu"    => $cpu,
		"memory" => round( $peakMemoryUsage, 1 ),
		"disk"   => round( $diskFree, 1 )
	] );
} else {
	echo "
        <h3 id='system-monitor-title'>System Stats</h3>
        <p>CPU Load: <span id='cpu-load' aria-live='polite'>{$cpu}%</span></p>
        <p>RAM Usage: <span id='memory-usage' aria-live='polite'>" . round( $peakMemoryUsage, 1 ) . " MB</span></p>
        <p>Disk Space: <span id='disk-space' aria-live='polite'>" . round( $diskFree, 1 ) . "%</span></p>";
}
?>
