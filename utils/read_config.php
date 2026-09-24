<?php
/**
 * Read-only JSON config endpoint for the UI.
 * Exposes only: folders.json, link_templates.json, dock.json.
 * Uses same-origin checks. No auth tokens required for GET.
 *
 * @var string $activeConfigDir
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.2
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
	http_response_code( 405 );
	echo json_encode( [ 'error' => 'Method not allowed' ] );
	exit;
}

if ( ! function_exists( 'request_is_same_origin' ) || ! request_is_same_origin() ) {
	http_response_code( 403 );
	echo json_encode( [ 'error' => 'Forbidden' ] );
	exit;
}

$map = [ 'folders' => 'folders', 'link_templates' => 'linkTemplates', 'dock' => 'dock' ];
$key = $_GET['file'] ?? '';
if ( ! is_string( $key ) || ! isset( $map[ $key ] ) ) {
	http_response_code( 400 );
	echo json_encode( [ 'error' => 'Unknown file' ] );
	exit;
}
echo json_encode( $config['profile'][ $map[ $key ] ] );
