<?php
namespace AMPBoard\System;

/** A request identity snapshot. Supplied discovery runs at most once. */
final class Identity {
	private string $user;
	private string $label;
	public function __construct( array $server, callable $whoami ) {
		$this->user = self::resolveUser( $server, $whoami );
		$this->label = self::label( $server );
	}
	public function user(): string { return $this->user; }
	public function serverLabel(): string { return $this->label; }
	public static function label( array $server ): string {

		$remote = $server['REMOTE_ADDR'] ?? '';
		$serverAddress = $server['SERVER_ADDR'] ?? '';

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
		if ( in_array( $serverAddress, $localIPv4, true ) || in_array( $serverAddress, $localIPv6, true ) ) {
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

	public static function resolveUser( array $server, callable $whoami ): string {
		// Preserve null-versus-empty precedence and Guest fallback to avoid moving profiles.
		$user = $server['USERNAME'] ?? $server['USER'] ?? trim( (string) $whoami() );

		if ( strpos( $user, '\\' ) !== false ) {
			$parts = explode( '\\', $user, 2 );
			$user  = $parts[1];
		} elseif ( strpos( $user, '@' ) !== false ) {
			$parts = explode( '@', $user, 2 );
			$user  = $parts[0];
		}

		return $user ?: 'Guest';
	}

}
