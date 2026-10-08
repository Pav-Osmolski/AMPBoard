<?php
/**
 * Apache Error Log Viewer
 *
 * This script displays the most recent Apache error log entries
 * either as raw text (for AJAX consumption) or embedded HTML.
 * It automatically detects the error log location based on the OS
 * and the supplied Apache path.
 *
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.1
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/entry-apache-log.php';

if ( ! $config['ui']['flags']['apacheErrorLog'] ) {
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'Apache error log display is disabled.' ] );
	exit;
}

$logContent = $apacheLog->content();

if ( $config['ui']['flags']['useAjaxForErrorLog'] ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
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
	     . htmlspecialchars( $logContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . "
        	</code>
        </pre>";
}
?>
