<?php
namespace AMPBoard\Http;

/** Origin checks against an explicit request snapshot; proxy headers are not trusted. */
final class RequestOrigin {
	private array $server;
	public function __construct( array $server ) { $this->server = $server; }
	public function isSecure(): bool {
		return ! empty( $this->server['HTTPS'] ) && $this->server['HTTPS'] !== 'off';
	}
	public function isSameOrigin(): bool {
		$scheme = $this->isSecure() ? 'https' : 'http';
		$host = $this->server['HTTP_HOST'] ?? '';
		if ( ! is_string( $host ) || $host === '' ) { return false; }
		$expected = $this->parse( $scheme . '://' . $host );
		if ( $expected === null || isset( $expected['path'] ) || isset( $expected['query'] ) || isset( $expected['fragment'] ) ) { return false; }
		foreach ( [ 'HTTP_ORIGIN', 'HTTP_REFERER' ] as $header ) {
			$value = $this->server[$header] ?? null;
			// Retain the existing policy: either header may be absent; all supplied headers must match.
			if ( $value === null || $value === '' ) { continue; }
			$actual = $this->parse( $value );
			if ( $actual === null || $this->origin( $actual ) !== $this->origin( $expected ) ) { return false; }
		}
		return true;
	}
	private function parse( $url ): ?array {
		if ( ! is_string( $url ) || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) { return null; }
		$parts = @parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! isset( $parts['scheme'] )
			|| ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true )
			|| isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return null; }
		return $parts;
	}
	private function origin( array $parts ): array {
		$scheme = strtolower( $parts['scheme'] );
		return [ $scheme, strtolower( $parts['host'] ), $parts['port'] ?? ( $scheme === 'https' ? 443 : 80 ) ];
	}
}
