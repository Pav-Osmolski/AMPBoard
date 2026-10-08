<?php
/**
 * SSL Certificate Generator Script
 *
 * Automatically runs a local script to generate a self-signed SSL certificate
 * for a given domain name. Supports PowerShell, BAT, and Bash scripts depending
 * on the current OS. Will copy default scripts to the cert directory if missing
 * or outdated.
 *
 * Usage (GET): generate_cert.php?name=example.test
 *
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.2
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/entry-certificates.php';

header( 'Content-Type: text/plain; charset=utf-8' );

$name = $_GET['name'] ?? null;
if ( $name === null || $name === '' ) {
 http_response_code( 400 ); echo 'Missing domain name.'; return;
}
if ( $config['user']['isDemo'] ) {
 http_response_code( 403 ); echo 'Certificate generation is disabled in demo mode.'; return;
}
if ( ! is_string( $name ) ) {
 http_response_code( 400 ); echo 'Invalid domain name.'; return;
}
try {
 $result = $certificates->generate( $name );
 http_response_code( $result['success'] ? 200 : 500 );
 echo $result['success'] ? $result['output'] : "❌ Certificate generation failed.\n" . $result['output'];
} catch ( \InvalidArgumentException $error ) {
 http_response_code( 400 ); echo $error->getMessage();
} catch ( \Throwable $error ) {
 http_response_code( 500 ); echo '❌ Certificate generation failed: ' . $error->getMessage();
}
