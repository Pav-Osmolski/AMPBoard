<?php
/**
 * Apache Error Log Viewer
 *
 * This script displays the most recent Apache error log entries
 * either as JSON (for AJAX consumption) or embedded HTML.
 * It automatically detects the error log location based on the OS
 * and the defined `APACHE_PATH` constant.
 *
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.1
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

if ( ! $config['ui']['flags']['apacheErrorLog'] ) {
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'Apache error log display is disabled.' ] );
	exit;
}

$logFile = '';

switch ( PHP_OS_FAMILY ) {
	case 'Windows':
		$possibleLogs = [
			APACHE_PATH . '\\logs\\error.log',
			'C:\\Program Files\\Ampps\\apache\\logs\\error.log',
			'C:\\Program Files (x86)\\Ampps\\apache\\logs\\error.log'
		];
		foreach ( $possibleLogs as $path ) {
			if ( file_exists( $path ) ) {
				$logFile = $path;
				break;
			}
		}
		break;
	case 'Darwin':
		$possibleLogs = [
			APACHE_PATH . '/logs/error.log',
			'/Applications/MAMP/logs/apache_error.log',
			'/Applications/AMPPS/logs/apache_error.log',
			'/Library/Application Support/appsolute/MAMP PRO/logs/apache_error.log',
			'/opt/homebrew/var/log/httpd/error_log',
			'/usr/local/var/log/httpd/error_log',
			'/usr/local/var/log/apache2/error_log',
			'/home/linuxbrew/.linuxbrew/var/log/httpd/error_log',
			'/opt/lampp/logs/error_log'
		];
		foreach ( $possibleLogs as $path ) {
			if ( file_exists( $path ) ) {
				$logFile = $path;
				break;
			}
		}
		break;
	default:
		$possibleLogs = [
			APACHE_PATH . '/logs/error.log',
			'/var/log/apache2/error.log',
			'/var/log/httpd/error_log',
			'/opt/lampp/logs/error_log'
		];

		if ( isset( $_SERVER['HOME'] ) ) {
			$possibleLogs[] = $_SERVER['HOME'] . '/snap/httpd/common/error.log';
		}

		foreach ( $possibleLogs as $path ) {
			if ( file_exists( $path ) ) {
				$logFile = $path;
				break;
			}
		}
		break;
}

$lines      = 5;
$logContent = '';

if ( file_exists( $logFile ) ) {
	$linesArray = file( $logFile );
	$logContent = implode( "", array_slice( $linesArray, - $lines ) );
} else {
	$logContent = "Apache error log not found or not configured.";
}

if ( $config['ui']['flags']['useAjaxForStats'] ) {
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	echo $logContent;
} else {
	echo "
        <h3 id='apache-error-log-title'>
            <button id='toggle-apache-error-log' aria-expanded='false' aria-controls='apache-error-log'>
            📝 Toggle Apache Error Log
            </button>
        </h3>
        <pre id='apache-error-log' aria-live='polite' tabindex='0'>
        	<code>"
	     . htmlspecialchars( $logContent ) . "
        	</code>
        </pre>";
}
?>
