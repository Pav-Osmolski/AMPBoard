<?php
/**
 * PHP Error Log Viewer
 *
 * Displays the most recent PHP error log entries
 * either as raw text (for AJAX fetch) or HTML markup.
 *
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.5
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

if ( ! $config['ui']['flags']['phpErrorLog'] ) {
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'error' => 'PHP error log display is disabled.' ] );
	exit;
}

$logContent = $phpLog->content();

if ( $config['ui']['flags']['useAjaxForErrorLog'] ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	echo $logContent;
} else {
	echo "
        <h3 id='php-error-log-title'>
            <button id='toggle-php-error-log' aria-expanded='false' aria-controls='php-error-log'>
            📝 Toggle PHP Error Log
            </button>
        </h3>
        <pre id='php-error-log' aria-live='polite' tabindex='0'>
            <code>"
	     . htmlspecialchars( $logContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . "
            </code>
        </pre>";
}
?>
