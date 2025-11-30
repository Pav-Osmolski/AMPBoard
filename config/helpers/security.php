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

define( 'CRYPTO_KEY_FILE', __DIR__ . '/../../.key' );

/**
 * Generate cryptographically secure random bytes with a portable fallback.
 *
 * Uses PHP's native random_bytes() when available, otherwise falls back to
 * openssl_random_pseudo_bytes().
 *
 * @param int $length Number of bytes to generate.
 *
 * @return string Raw binary string of length $length.
 */
function _secure_random_bytes( int $length ): string {
	if ( function_exists( 'random_bytes' ) ) {
		return random_bytes( $length );
	}

	return openssl_random_pseudo_bytes( $length );
}

/**
 * Read the raw key material from the key file.
 *
 * @return string Raw contents of CRYPTO_KEY_FILE (trimmed), or empty string if missing.
 */
function _read_keyfile(): string {
	if ( ! file_exists( CRYPTO_KEY_FILE ) ) {
		return '';
	}
	$raw = @file_get_contents( CRYPTO_KEY_FILE );

	return $raw === false ? '' : trim( $raw );
}

/**
 * Ensure the encryption key file exists, creating a new-format key if missing.
 *
 * Behaviour:
 * - If CRYPTO_KEY_FILE does not exist, a new 256-bit key is generated and stored
 *   as a 64-character hexadecimal string.
 * - If the file already exists, it is not modified here (validation/healing is handled
 *   inside _get_key_candidates()).
 *
 * Security:
 * - The stored key is immediately chmod()'d to 0600 when supported.
 *
 * @return void
 */
function _ensure_key_exists(): void {
	if ( file_exists( CRYPTO_KEY_FILE ) ) {
		return;
	}
	// New format: 32 random bytes, stored as 64 hex chars
	$hex64 = bin2hex( _secure_random_bytes( 32 ) );
	@file_put_contents( CRYPTO_KEY_FILE, $hex64, LOCK_EX );
	if ( function_exists( 'chmod' ) ) {
		@chmod( CRYPTO_KEY_FILE, 0600 );
	}
}

/**
 * Retrieve the candidate AES-256 key(s) derived from the key file, enforcing new-format only.
 *
 * Behaviour:
 * - If CRYPTO_KEY_FILE contains a valid 64-hex key, it is decoded to a 32-byte binary key
 *   and returned as the sole candidate.
 * - If the file is missing, legacy format, partial, or malformed, a backup is written
 *   (best-effort), and a new 64-hex key is generated and persisted.
 * - This function never throws; it always returns exactly one usable 32-byte key.
 *
 * Guarantees:
 * - All future encryption is performed using the new-format 64-hex key.
 * - Any pre-existing legacy-format key is automatically rotated upon detection.
 *
 * @return array{keys: string[], preferred: int} An array with exactly one binary key at index 0 and preferred=0.
 */
function _get_key_candidates(): array {
	_ensure_key_exists();
	$raw = _read_keyfile();

	// OK: 64 hex chars -> decode to 32 bytes
	if ( $raw !== '' && strlen( $raw ) === 64 && ctype_xdigit( $raw ) ) {
		$bin = @hex2bin( $raw );
		if ( $bin !== false && strlen( $bin ) === 32 ) {
			return [ 'keys' => [ $bin ], 'preferred' => 0 ];
		}
	}

	// Not OK (missing/legacy/malformed): backup (best effort) and regenerate as 64-hex
	if ( $raw !== '' ) {
		$ts = date( 'Ymd-His' );
		@copy( CRYPTO_KEY_FILE, CRYPTO_KEY_FILE . ".bak.$ts" );
		error_log( '[crypto] Non-64hex key detected; backed up and regenerated new 64-hex key.' );
	} else {
		error_log( '[crypto] Key file empty/missing; generated new 64-hex key.' );
	}

	$hex64 = bin2hex( _secure_random_bytes( 32 ) );
	@file_put_contents( CRYPTO_KEY_FILE, $hex64, LOCK_EX );
	if ( function_exists( 'chmod' ) ) {
		@chmod( CRYPTO_KEY_FILE, 0600 );
	}

	$bin = hex2bin( $hex64 ); // always valid here

	return [ 'keys' => [ $bin ], 'preferred' => 0 ];
}

/**
 * Retrieve the active AES-256 encryption key for all cryptographic operations.
 *
 * Behaviour:
 * - Always returns a 32-byte binary key derived from a valid 64-hex key file.
 * - If the key file is missing or malformed, it will be automatically replaced
 *   with a new 64-hex key before returning.
 *
 * Guarantees:
 * - No legacy (32-char ASCII) key format is returned.
 * - Returned key is always suitable for AES-256-CBC encryption.
 *
 * @return string 32-byte binary encryption key.
 */
function getEncryptionKey(): string {
	$kc = _get_key_candidates();

	return $kc['keys'][ $kc['preferred'] ];
}

/**
 * Encrypt a plaintext string using AES-256-CBC and return base64-encoded "IV || ciphertext".
 *
 * Key policy:
 * - Assumes the key file is in the new format only (64-hex → 32-byte binary).
 * - Any legacy or malformed key will have been rotated before this is called.
 *
 * @param string $plaintext The raw value to encrypt.
 *
 * @return string Base64-encoded string containing IV and ciphertext.
 */
function encryptValue( string $plaintext ): string {
	$key = getEncryptionKey();
	$iv  = _secure_random_bytes( 16 );

	$encrypted = openssl_encrypt(
		$plaintext,
		'AES-256-CBC',
		$key,
		OPENSSL_RAW_DATA,
		$iv
	);

	return base64_encode( $iv . $encrypted );
}

/**
 * Decrypt a base64-encoded AES-256-CBC string.
 *
 * Key policy:
 * - Assumes the key has already been rotated to the new 64-hex format
 *   (i.e. getEncryptionKey() always returns a 32-byte binary key).
 * - Legacy key format is no longer supported.
 *
 * Behaviour:
 * - Returns an empty string on failure or invalid input.
 *
 * @param string $encoded The encrypted base64 string in the form base64(IV || ciphertext).
 *
 * @return string Decrypted plaintext, or empty string on failure.
 */
function decryptValue( string $encoded ): string {
	$kc   = _get_key_candidates();
	$data = base64_decode( $encoded, true );
	if ( $data === false || strlen( $data ) < 17 ) {
		return '';
	}
	$iv         = substr( $data, 0, 16 );
	$ciphertext = substr( $data, 16 );

	foreach ( $kc['keys'] as $key ) {
		$plain = openssl_decrypt(
			$ciphertext,
			'AES-256-CBC',
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
		if ( $plain !== false ) {
			return $plain;
		}
	}

	return '';
}

/**
 * Retrieve a decrypted version of a whitelisted constant, or fall back to its raw value.
 *
 * Key policy:
 * - Assumes all encrypted constants were produced using the new-format (64-hex) key.
 * - Legacy-encrypted values will not decrypt once the key has been rotated.
 *
 * Behaviour:
 * - Only decrypts values for whitelisted constant names.
 * - If the constant does not look like an encrypted value, the raw value is returned
 *   when $allowFallback is true.
 *
 * @param string $const The constant name (e.g. 'DB_USER').
 * @param bool $allowFallback If true, returns the raw value when decryption fails.
 *
 * @return string Decrypted value, raw fallback, or empty string if not permitted.
 */
function getDecrypted( string $const, bool $allowFallback = true ): string {
	static $allowed = [ 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_NAME' ];

	if ( ! defined( $const ) || ! in_array( $const, $allowed, true ) ) {
		return '';
	}

	$value = constant( $const );

	// Only attempt decrypt if it looks base64-like and is long enough to contain IV+ciphertext.
	if ( base64_decode( $value, true ) === false || strlen( $value ) < 24 ) {
		return $allowFallback ? $value : '';
	}

	$decrypted = decryptValue( $value );

	return $decrypted !== '' ? $decrypted : ( $allowFallback ? $value : '' );
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
		return str_repeat( '*', strlen( $value ) );
	}

	return $value;
}
