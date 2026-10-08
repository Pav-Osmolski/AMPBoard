<?php
/**
 * Folder Opener API Endpoint
 *
 * Accepts a POST request with a JSON payload containing a `path`,
 * and attempts to open the folder using the system's default file explorer.
 *
 * Supports:
 * - Windows: via `start`
 * - macOS (Darwin): via `open`
 * - Linux: via `xdg-open`
 *
 * Requires a valid and existing absolute path.
 *
 * Usage (POST JSON): { "path": "C:/xampp/htdocs" }
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.0
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/autoload.php';

// This utility needs no profile, credentials, session, or database connection.
$folderOpener = new \AMPBoard\Filesystem\FolderOpener( PHP_OS_FAMILY, new \AMPBoard\System\NativeProcessLauncher() );
$folderOpenAction = new \AMPBoard\Http\FolderOpenAction( $folderOpener );
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$response = $folderOpenAction->handle( $method, $method === 'POST' ? (string) file_get_contents( 'php://input' ) : '' );
header( 'Content-Type: application/json' );
http_response_code( $response['status'] );
echo json_encode( $response['body'] );
