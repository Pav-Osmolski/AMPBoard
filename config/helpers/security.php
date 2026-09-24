<?php
/**
 * Security Utilities and Encryption Handler
 *
 * Provides cryptographic functionality for securely storing and retrieving sensitive values,
 * such as database credentials. Also includes a safe shell execution wrapper to restrict
 * command usage to a known list of binaries.
 *
 * Features:
 * - Symmetric encryption using AES-256-CBC with a generated key file (`.key`)
 * - `encryptValue()` and `decryptValue()` for encoding/decoding strings
 * - `getDecrypted()` for secure constant value retrieval (e.g. `DB_USER`)
 * - `safe_shell_exec()` allows execution of whitelisted system commands only
 *
 * Constants:
 * - `CRYPTO_KEY_FILE` defines the encryption key file location
 *
 * Safety:
 * - Prevents execution of untrusted shell commands
 * - Gracefully handles missing or malformed encrypted values
 *
 * @author  Pawel Osmolski
 * @version 1.4
 */

require_once __DIR__ . '/../autoload.php';
if ( ! defined( 'CRYPTO_KEY_FILE' ) ) { define( 'CRYPTO_KEY_FILE', __DIR__ . '/../../.key' ); }

function _secure_random_bytes( int $length ): string { return random_bytes( $length ); }

/** Compatibility wrappers for legacy custom integrations. New code injects CredentialCipher. */
function getEncryptionKey(): string {
	return ( new \AMPBoard\Security\CredentialCipher( CRYPTO_KEY_FILE ) )->key( true );
}
function encryptValue( string $plaintext ): string {
	return ( new \AMPBoard\Security\CredentialCipher( CRYPTO_KEY_FILE ) )->encrypt( $plaintext );
}
function decryptValue( string $encoded ): string {
	try { return ( new \AMPBoard\Security\CredentialCipher( CRYPTO_KEY_FILE ) )->decrypt( $encoded ); }
	catch ( \RuntimeException $error ) { return ''; }
}
function getDecrypted( string $const, bool $allowFallback = true ): string {
	if ( ! defined( $const ) || ! in_array( $const, [ 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_NAME' ], true ) ) { return ''; }
	return ( new \AMPBoard\Security\CredentialCipher( CRYPTO_KEY_FILE ) )->resolveLegacy( constant( $const ), $allowFallback );
}

/**
 * Returns a CSRF token for the current session, creating one if needed.
 *
 * @return string The current CSRF token, or empty string if session cannot start.
 */
function csrf_get_token(): string {
	if ( session_status() !== PHP_SESSION_ACTIVE ) {
		if ( headers_sent() ) {
			error_log( '[csrf_get_token] Headers already sent; session not active.' );

			return '';
		}
		session_start();
	}

	if ( empty( $_SESSION['csrf_token'] ) || ! is_string( $_SESSION['csrf_token'] ) ) {
		$_SESSION['csrf_token'] = bin2hex( _secure_random_bytes( 32 ) );
	}

	return $_SESSION['csrf_token'];
}

/**
 * Verifies a CSRF token from user input against the session token and rotates on success.
 *
 * @param string|null $token The user-supplied token.
 *
 * @return bool True if token is valid; false otherwise.
 */
function csrf_verify( ?string $token ): bool {
	if ( session_status() !== PHP_SESSION_ACTIVE ) {
		session_start();
	}

	$valid = (
		is_string( $token )
		&& isset( $_SESSION['csrf_token'] )
		&& is_string( $_SESSION['csrf_token'] )
		&& hash_equals( $_SESSION['csrf_token'], $token )
	);

	if ( $valid ) {
		$_SESSION['csrf_token'] = bin2hex( _secure_random_bytes( 32 ) );
	}

	return $valid;
}

/**
 * Determine whether the HTTP request was submitted from the same origin.
 *
 * Validates Origin/Referer strictly by decomposing and comparing scheme, host, and port.
 * Fails closed: if headers are malformed, missing or mismatched, returns false.
 *
 * @return bool True only if request originates from the exact same origin.
 */
function request_is_same_origin(): bool {
	$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
	if ( $hostHeader === '' ) {
		return false;
	}

	$scheme       = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	$expectedHost = strtolower( $hostHeader );
	$expectedPort = parse_url( $scheme . '://' . $hostHeader, PHP_URL_PORT );
	if ( $expectedPort === null ) {
		$expectedPort = ( $scheme === 'https' ) ? 443 : 80;
	}

	foreach ( [ $_SERVER['HTTP_ORIGIN'] ?? null, $_SERVER['HTTP_REFERER'] ?? null ] as $h ) {
		if ( ! $h ) {
			continue;
		}
		$parts = @parse_url( $h );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$hScheme = strtolower( $parts['scheme'] ?? '' );
		$hHost   = strtolower( $parts['host'] ?? '' );
		$hPort   = isset( $parts['port'] ) ? (int) $parts['port'] : ( ( $hScheme === 'https' ) ? 443 : 80 );

		if ( $hScheme !== $scheme || $hHost !== $expectedHost || $hPort !== $expectedPort ) {
			return false;
		}
	}

	return true;
}

/**
 * Safely execute a restricted subset of shell commands.
 *
 * Behaviour:
 * - Extracts the invoked binary name (handles quoted full paths).
 * - Compares the *basename* against an allow-list.
 * - Emits error_log() entries for blocked or failed invocations.
 *
 * @param string $cmd The command string to execute (single utility plus args only).
 *
 * @return string|null Command output on success, null if blocked or failed.
 */
function safe_shell_exec( string $cmd ): ?string {
	$allowed = [
		// Web server & system maintenance utilities
		'apachectl',    // Manage Apache on macOS/Linux
		'httpd',        // Apache binary (used for version checks)
		'sc',           // Windows service control
		'sysctl',       // Kernel and hardware info (Linux/macOS)
		'nproc',        // CPU core count (Linux)
		'ps',           // Process list (POSIX)
		'tasklist',     // Process list (Windows)
		'typeperf',     // Windows performance metrics
		'wmic',         // Windows system info utility
		'whoami',       // Current user identity

		// Diagnostic & text utilities
		'grep',         // Pattern search
		'awk',          // Text parsing
		'which',        // Locate executables (POSIX)
		'where',        // Locate executables (Windows)
		'command',      // POSIX built-in command dispatcher

		// Shell environments / process launchers
		'bash',         // POSIX shell
		'cmd',          // Windows command prompt
		'powershell',   // Windows PowerShell
		'start',        // Windows shell launcher
		'explorer',     // Open paths in File Explorer (Windows)
		'open',         // Open paths in Finder (macOS)
		'xdg-open',     // Open paths in default app (Linux desktop)

		// Custom or project-specific maintenance tools
		'auto-make-cert',
		'make-cert',
		'make-cert-silent',
		'make-cert-prompt',

		// External archivers for cross-platform exports
		'7z',           // 7-Zip CLI
		'7za',          // Standalone 7-Zip
		'7zz',          // Newer 7-Zip variant
		'zip',          // Standard zip CLI (Info-ZIP / macOS / Linux / WSL)
	];

	$cmd = trim( (string) $cmd );
	if ( $cmd === '' ) {
		return null;
	}

	// Extract *first* executable token, handling quoted paths.
	$first = $cmd;

	if ( $cmd[0] === '"' || $cmd[0] === "'" ) {
		$quote = $cmd[0];
		$end   = strpos( $cmd, $quote, 1 );
		if ( $end !== false ) {
			// everything inside the first pair of quotes
			$first = substr( $cmd, 1, $end - 1 );
		}
	} else {
		if ( preg_match( '/^\S+/', $cmd, $m ) ) {
			$first = $m[0];
		}
	}

	// Strip any leftover wrapping quotes, just in case.
	$first = trim( $first, "\"'" );

	// Now take the basename as the binary name (works for C:\path\to\7z.exe and /usr/bin/zip)
	$binary = strtolower( pathinfo( $first, PATHINFO_FILENAME ) );

	if ( ! in_array( $binary, $allowed, true ) ) {
		error_log( "[safe_shell_exec] Blocked command (binary={$binary}): $cmd" );

		return null;
	}

	$output = shell_exec( $cmd );
	if ( $output === null ) {
		error_log( "[safe_shell_exec] shell_exec returned null for: $cmd" );
	}

	return $output;
}

/**
 * Returns a PHP define() statement with an encrypted and escaped value.
 *
 * The value is encrypted using encryptValue() and properly escaped for safe
 * inclusion in generated PHP config files.
 *
 * @param string $name The constant name.
 * @param string $value The raw value to encrypt and define.
 *
 * @return string PHP define() code snippet.
 */
function defineEncrypted( string $name, string $value ): string {
	return "define('$name', '" . addslashes( encryptValue( $value ) ) . "');\n";
}

/**
 * Obfuscates a sensitive configuration value when demo mode is active.
 *
 * @param string $value The sensitive value to obfuscate.
 *
 * @return string The obfuscated value if demo mode is enabled, or the original value otherwise.
 */
function obfuscate_value( string $value ): string {
	if ( defined( 'DEMO_MODE' ) && DEMO_MODE ) {
		$len = strlen( $value );

		if ( $len <= 4 )  return '****';
		if ( $len <= 12 ) return '************';
		return '****************';
	}

	return $value;
}
