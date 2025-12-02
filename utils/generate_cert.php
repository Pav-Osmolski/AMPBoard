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

require_once __DIR__ . '/../config/config.php';

header( 'Content-Type: text/plain' );

if ( empty( $_GET['name'] ) ) {
	http_response_code( 400 );
	exit( 'Missing domain name.' );
}

if ( defined( 'DEMO_MODE' ) && DEMO_MODE ) {
	http_response_code( 403 );
	exit( 'Certificate generation is disabled in demo mode.' );
}

$domain           = preg_replace( '/[^a-zA-Z0-9.-]/', '', $_GET['name'] );
$os               = PHP_OS_FAMILY;
$crtPath          = APACHE_PATH . DIRECTORY_SEPARATOR . 'crt';
$defaultScriptDir = $config['paths']['crt'];

// Ensure crtDir exists
if ( ! is_dir( $crtPath ) ) {
	mkdir( $crtPath, 0775, true );
}

// Define script variants by OS
$scriptVariants = [
	'Windows' => [ 'make-cert-silent.ps1', 'make-cert-silent.bat', 'make-cert-prompt.ps1', 'make-cert-prompt.bat' ],
	'Linux'   => [ 'make-cert-silent.sh', 'make-cert-prompt.sh' ],
	'Darwin'  => [ 'make-cert-silent.sh', 'make-cert-prompt.sh' ],
];

// Copy fallback scripts if missing or outdated
foreach ( $scriptVariants[ $os ] ?? [] as $script ) {
	$target = $crtPath . DIRECTORY_SEPARATOR . $script;
	$source = $defaultScriptDir . DIRECTORY_SEPARATOR . $script;

	if ( file_exists( $source ) ) {
		$shouldCopy = false;

		if ( ! file_exists( $target ) ) {
			$shouldCopy = true;
			error_log( "[generate_cert] Restoring missing script: $script" );
		} elseif ( filemtime( $source ) > filemtime( $target ) ) {
			$shouldCopy = true;
			error_log( "[generate_cert] Updating outdated script: $script" );
		}

		if ( $shouldCopy ) {
			copy( $source, $target );
		}
	}
}

// Determine which script to run
if ( $os === 'Windows' ) {
	$ps1Path = $crtPath . DIRECTORY_SEPARATOR . 'make-cert-silent.ps1';
	$batPath = $crtPath . DIRECTORY_SEPARATOR . 'make-cert-silent.bat';

	if ( file_exists( $ps1Path ) ) {
		$command = 'powershell -ExecutionPolicy Bypass -File "' . $ps1Path . '" "' . $domain . '"';
	} elseif ( file_exists( $batPath ) ) {
		$command = 'cmd /c "' . $batPath . ' ' . $domain . '"';
	} else {
		http_response_code( 500 );
		exit( "Cannot find PowerShell or BAT script in:\n$crtPath" );
	}
} else {
	$shPath = $crtPath . DIRECTORY_SEPARATOR . 'make-cert-silent.sh';
	if ( file_exists( $shPath ) ) {
		$command = 'bash "' . $shPath . '" "' . $domain . '"';
	} else {
		http_response_code( 500 );
		exit( "Cannot find shell script in:\n$crtPath" );
	}
}

// Run the command and return the output
$output = safe_shell_exec( $command );

if ( $output === null ) {
	echo '❌ Certificate generation failed.';
} else {
	echo $output;
}
