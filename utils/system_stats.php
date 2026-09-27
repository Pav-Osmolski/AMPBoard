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
 * - `$config['ui']['flags']['systemStats']`
 * - `$config['ui']['flags']['useAjaxForStats']`
 *
 * @var array<string, mixed> $config
 * @var \AMPBoard\System\Statistics $systemStatistics
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

$stats = $systemStatistics->snapshot();
$cpu = $stats['cpu'];
$memory = $stats['memory'];
$disk = $stats['disk'];

if ( $config['ui']['flags']['useAjaxForStats'] ) {
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	echo json_encode( [
		"cpu"    => $cpu,
		"memory" => $memory,
		"disk"   => $disk
	] );
} else {
	echo "
        <h3 id='system-monitor-title'>System Stats</h3>
        <p>CPU Load: <span id='cpu-load' aria-live='polite'>{$cpu}%</span></p>
        <p>RAM Usage: <span id='memory-usage' aria-live='polite'>" . $memory . " MB</span></p>
        <p>Disk Space: <span id='disk-space' aria-live='polite'>" . $disk . "%</span></p>";
}
?>
