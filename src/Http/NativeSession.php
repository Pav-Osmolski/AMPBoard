<?php
namespace AMPBoard\Http;

/** PHP session boundary. Construction never starts a session or sends headers. */
final class NativeSession implements SessionStore {
	private bool $secure;
	public function __construct( bool $secure = false ) { $this->secure = $secure; }

	public function start(): bool {
		if ( session_status() === PHP_SESSION_ACTIVE ) { return true; }
		if ( session_status() === PHP_SESSION_DISABLED || headers_sent() ) { return false; }
		$p = session_get_cookie_params();
		session_set_cookie_params( [
			'lifetime' => $p['lifetime'], 'path' => $p['path'], 'domain' => $p['domain'],
			'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Lax',
		] );
		if ( ! @session_start() ) {
			error_log( '[AMPBoard session] Unable to start session.' );
			return false;
		}
		return session_status() === PHP_SESSION_ACTIVE;
	}

	public function read( string $key ) { return $_SESSION[$key] ?? null; }
	public function write( string $key, $value ): bool {
		if ( session_status() !== PHP_SESSION_ACTIVE ) { return false; }
		$_SESSION[$key] = $value;
		return true;
	}
	public function regenerate(): bool {
		return $this->start() && ! headers_sent() && session_regenerate_id( true );
	}
}
