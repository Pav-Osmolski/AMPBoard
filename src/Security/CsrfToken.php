<?php
namespace AMPBoard\Security;

use AMPBoard\Http\SessionStore;

/** Session-backed form tokens, rotated after each successful verification. */
final class CsrfToken {
	private SessionStore $session;
	private $random;
	public function __construct( SessionStore $session, ?callable $random = null ) {
		$this->session = $session;
		$this->random = $random ?? 'random_bytes';
	}
	public function token(): string {
		if ( ! $this->session->start() ) { return ''; }
		$token = $this->session->read( 'csrf_token' );
		if ( is_string( $token ) && $token !== '' ) { return $token; }
		$new = $this->generate();
		return $new !== '' && $this->session->write( 'csrf_token', $new ) ? $new : '';
	}
	public function verify( ?string $token ): bool {
		if ( $token === null || $token === '' || ! $this->session->start() ) { return false; }
		$stored = $this->session->read( 'csrf_token' );
		if ( ! is_string( $stored ) || $stored === '' || ! hash_equals( $stored, $token ) ) { return false; }
		$new = $this->generate();
		return $new !== '' && $this->session->write( 'csrf_token', $new );
	}
	private function generate(): string {
		try {
			$bytes = ( $this->random )( 32 );
			if ( ! is_string( $bytes ) || strlen( $bytes ) !== 32 ) { throw new \RuntimeException( 'Invalid random bytes.' ); }
			return bin2hex( $bytes );
		} catch ( \Throwable $error ) {
			error_log( '[AMPBoard CSRF] Unable to generate token.' );
			return '';
		}
	}
}
