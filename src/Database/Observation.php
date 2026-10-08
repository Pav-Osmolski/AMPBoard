<?php
namespace AMPBoard\Database;

use Closure;
use Throwable;

/** One request's diagnostic result, never an open connection or operational cache. */
final class Observation {
	private Closure $connect;
	private ?array $result = null;
	public function __construct( callable $connect ) { $this->connect = Closure::fromCallable( $connect ); }
	public function credentials(): array { return $this->read()['credentials']; }
	public function database(): array { return $this->read()['database']; }
	private function read(): array {
		if ( $this->result !== null ) { return $this->result; }
		$connection = null;
		$credentials = [ 'host' => false, 'user' => false, 'pass' => false ];
		try {
			$connection = ($this->connect)();
			$credentials = CredentialStatus::fromError( $connection->connect_errno ? (string) $connection->connect_error : null );
			if ( $connection->connect_error ) { throw new \RuntimeException( $connection->connect_error ); }
			$database = [ 'available' => true, 'label' => ServerVersion::normalise( $connection->server_info ) ];
		} catch ( Throwable $error ) {
			$database = [ 'available' => false, 'label' => $error->getMessage() ];
		} finally {
			if ( $connection !== null ) { try { $connection->close(); } catch ( Throwable $error ) { /* Keep the collected result. */ } }
		}
		return $this->result = [ 'credentials' => $credentials, 'database' => $database ];
	}
}
