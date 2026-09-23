<?php

namespace AMPBoard\Database;

use Exception;
use mysqli;
use mysqli_driver;
use mysqli_sql_exception;

/** MySQL connections using explicit credentials, without globals or config constants. */
final class ConnectionFactory {
	private array $credentials;

	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Create a secure MySQLi connection with utf8mb4 charset.
	 *
	 * @param array $opts {
	 *   Optional connection options.
	 *
	 * @type string $user Database username. Defaults to the injected username.
	 * @type string $pass Database password. Defaults to the injected password.
	 * @type string $db Database name to select. Optional.
	 * @type bool $strictMode Enable MYSQLI_REPORT_STRICT temporarily. Default true.
	 * }
	 *
	 * @return mysqli
	 * @throws Exception On connection, charset, or database selection failure (in strict mode).
	 */
	public function connect( array $opts = [] ): mysqli {
		$user       = isset( $opts['user'] ) ? $opts['user'] : $this->credentials['user'];
		$pass       = isset( $opts['pass'] ) ? $opts['pass'] : $this->credentials['pass'];
		$db         = isset( $opts['db'] ) ? $opts['db'] : null;
		$strictMode = array_key_exists( 'strictMode', $opts ) ? (bool) $opts['strictMode'] : true;

		$driver = new mysqli_driver();
		$prevFlags = $driver->report_mode;
		mysqli_report( MYSQLI_REPORT_OFF );
		if ( $strictMode ) {
			mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		}

		try {
			// Quiet constructor in non-strict mode to avoid HTML warnings in output
			$mysqli = $strictMode
				? new mysqli( $this->credentials['host'], $user, $pass )
				: @new mysqli( $this->credentials['host'], $user, $pass );

			// In non-strict mode, if connect failed, return as-is so caller can read ->connect_error
			if ( ! $strictMode && $mysqli->connect_errno ) {
				return $mysqli;
			}

			$mysqli->set_charset( 'utf8mb4' );

			if ( $db !== null ) {
				$mysqli->select_db( $db );
			}

			return $mysqli;

		} catch ( mysqli_sql_exception $e ) {
			throw new Exception( 'MySQL connection failed: ' . $e->getMessage() );
		} finally {
			mysqli_report( $prevFlags );
		}
	}

	/**
	 * Check MySQL credential validity for host, user, and password.
	 *
	 * Runs a non-strict connection and inspects mysqli->connect_error to infer
	 * which parts are valid. Where MySQL does not differentiate, a heuristic is used:
	 * - If error contains "access denied" and "using password: YES", assume user exists and password is wrong.
	 * - If error contains "access denied" and "using password: NO", assume username is wrong or password missing.
	 *
	 * @param string|null $key Optional. One of 'host', 'user', or 'pass'.
	 *                         If provided, returns a boolean for that specific status.
	 *                         If null, returns an associative array with all three.
	 *
	 * @return array|bool {
	 * @type bool $host True if the configured host is reachable.
	 * @type bool $user True if the configured username appears valid.
	 * @type bool $pass True if the configured password appears valid.
	 * }
	 */
	public function credentialStatus( ?string $key = null ): array|bool {
		$status = [
			'host' => false,
			'user' => false,
			'pass' => false,
		];

		try {
			$mysqli = $this->connect( [ 'strictMode' => false ] );

			// Successful connection → all valid
			if ( ! $mysqli->connect_errno ) {
				$status = [ 'host' => true, 'user' => true, 'pass' => true ];
				$mysqli->close();
			} else {
				$error = strtolower( $mysqli->connect_error );

				// Host-level problems
				if (
					strpos( $error, 'unknown host' ) !== false ||
					strpos( $error, 'no route to host' ) !== false ||
					strpos( $error, 'can\'t connect to mysql server' ) !== false ||
					strpos( $error, 'connection refused' ) !== false
				) {
					$status['host'] = false;
				} // Access denied → host reachable. Apply heuristic for user vs pass.
				elseif ( strpos( $error, 'access denied' ) !== false ) {
					$status['host'] = true;

					if ( strpos( $error, 'using password: no' ) !== false ) {
						// Likely bad username, password not used
						$status['user'] = false;
						$status['pass'] = true;
					} else {
						// Password was provided. Assume username exists and password is wrong.
						$status['user'] = true;
						$status['pass'] = false;
					}
				} // Other errors: assume host reachable but credentials suspect
				else {
					$status['host'] = true;
				}
			}

		} catch ( Exception $e ) {
			// Strict mode or unexpected failure
			$status = [ 'host' => false, 'user' => false, 'pass' => false ];
		}

		if ( $key !== null ) {
			return isset( $status[ $key ] ) ? $status[ $key ] : false;
		}

		return $status;
	}
}
