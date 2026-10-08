<?php
namespace AMPBoard\Database;

/** Historical credential heuristics, independent of connection and display lifetimes. */
final class CredentialStatus {
	public static function fromError( ?string $error ): array {
		if ( $error === null ) { return [ 'host' => true, 'user' => true, 'pass' => true ]; }
		$error = strtolower( $error );
		$status = [ 'host' => false, 'user' => false, 'pass' => false ];
		foreach ( [ 'unknown host', 'no route to host', "can't connect to mysql server", 'connection refused' ] as $message ) {
			if ( strpos( $error, $message ) !== false ) { return $status; }
		}
		$status['host'] = true;
		if ( strpos( $error, 'access denied' ) !== false ) {
			$status['pass'] = strpos( $error, 'using password: no' ) !== false;
			$status['user'] = ! $status['pass'];
		}
		return $status;
	}
}
