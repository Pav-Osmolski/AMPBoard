<?php
/**
 * System helpers (no safe_shell_exec defined here)
 *
 * getServerLabel(), getLegacyOSFlags(), resolveCurrentUser()
 *
 * @author  Pawel Osmolski
 * @version 1.1
 */

/**
 * Determine whether AMPBoard is running on a local machine or a remote server.
 *
 * @return string "localhost" or "server"
 */
function getServerLabel(): string {

	$remote = $_SERVER['REMOTE_ADDR'] ?? '';
	$server = $_SERVER['SERVER_ADDR'] ?? '';

	// IPv4 localhost
	$localIPv4 = [
		'127.0.0.1',
	];

	// IPv6 localhost
	$localIPv6 = [
		'::1',
		'0:0:0:0:0:0:0:1',
	];

	// If remote address is empty, this is almost certainly CLI or local server
	if ( $remote === '' ) {
		return 'localhost';
	}

	// Direct localhost matches
	if ( in_array( $remote, $localIPv4, true ) || in_array( $remote, $localIPv6, true ) ) {
		return 'localhost';
	}

	// If server_addr is also localhost, it's local
	if ( in_array( $server, $localIPv4, true ) || in_array( $server, $localIPv6, true ) ) {
		return 'localhost';
	}

	// Local private network ranges (LAN dev, Docker, WSL2)
	if (
		str_starts_with( $remote, '192.168.' ) ||
		str_starts_with( $remote, '10.' ) ||
		( str_starts_with( $remote, '172.' ) && (int) explode( '.', $remote )[1] >= 16 && (int) explode( '.', $remote )[1] <= 31 )
	) {
		return 'localhost';
	}

	// Otherwise, assume remote server
	return 'server';
}

/**
 * Detect legacy OS flags.
 *
 * @return array<string, bool>
 */
function getLegacyOSFlags(): array {
	return [
		'isWindows' => strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN',
		'isLinux'   => strtoupper( substr( PHP_OS, 0, 5 ) ) === 'LINUX',
		'isMac'     => strtoupper( substr( PHP_OS, 0, 6 ) ) === 'DARWIN' || strtoupper( substr( PHP_OS, 0, 3 ) ) === 'MAC',
	];
}

/**
 * Attempt to resolve the current OS user.
 *
 * Uses environment variables first, then whoami via safe_shell_exec (if present),
 * then get_current_user() as a final fallback.
 *
 * @return string
 */
function resolveCurrentUser(): string {
	$user = $_SERVER['USERNAME']
	        ?? $_SERVER['USER']
	           ?? ( function_exists( 'safe_shell_exec' ) ? trim( (string) safe_shell_exec( 'whoami' ) ) : '' )
	              ?? get_current_user();

	if ( strpos( $user, '\\' ) !== false ) {
		$parts = explode( '\\', $user, 2 );
		$user  = $parts[1];
	} elseif ( strpos( $user, '@' ) !== false ) {
		$parts = explode( '@', $user, 2 );
		$user  = $parts[0];
	}

	return $user ?: 'Guest';
}
