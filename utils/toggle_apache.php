<?php
/**
 * Apache Restart Handler
 *
 * This script accepts a POST request with an `action` key (currently only `restart`)
 * and attempts to restart the Apache web server depending on the OS and available tools.
 *
 * Supported Environments:
 * - Windows (XAMPP, AMPPS, manual installations)
 * - macOS (MAMP, brew, system apachectl)
 * - Linux (apache2ctl, apachectl, systemd)
 *
 * Output:
 * - JSON object with success status, message, and any command output.
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.2
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

ob_start();
error_reporting( E_ERROR | E_PARSE );
ini_set( 'display_errors', 0 );

require_once __DIR__ . '/../config/config.php';

header( 'Content-Type: application/json' );

$action     = $_POST['action'] ?? '';

if ( ! in_array( $action, [ 'restart' ] ) ) {
	ob_end_clean();
	echo json_encode( [ 'success' => false, 'message' => 'Invalid action' ] );
	exit;
}

if ( defined( 'DEMO_MODE' ) && DEMO_MODE ) {
	http_response_code( 403 );
	ob_end_clean();
	echo json_encode( [
		'success' => false,
		'message' => 'Apache control is disabled in demo mode'
	] );
	exit;
}

try {
	$result = $apacheControl->restart();
} catch ( \Throwable $error ) {
	error_log( '[toggle_apache] ' . $error->getMessage() );
	$result = [ 'success' => false, 'message' => 'Failed to restart Apache.', 'output' => '' ];
}
ob_end_clean();
echo json_encode( $result );
