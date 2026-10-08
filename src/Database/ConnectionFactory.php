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
	 * Create a MySQLi connection with utf8mb4 charset.
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

		} catch ( \Throwable $e ) {
			// Initialization failures must not leave a successfully opened connection behind.
			if ( isset( $mysqli ) ) { try { $mysqli->close(); } catch ( \Throwable $ignored ) { /* Preserve the original failure. */ } }
			if ( $e instanceof mysqli_sql_exception ) { throw new Exception( 'MySQL connection failed: ' . $e->getMessage() ); }
			throw $e;
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
		$status = [ 'host' => false, 'user' => false, 'pass' => false ];
		$mysqli = null;
		try {
			$mysqli = $this->connect( [ 'strictMode' => false ] );
			$status = CredentialStatus::fromError( $mysqli->connect_errno ? (string) $mysqli->connect_error : null );
		} catch ( \Throwable $error ) {
			// Unavailable drivers and initialization failures keep all indicators invalid.
		} finally {
			if ( $mysqli !== null ) { try { $mysqli->close(); } catch ( \Throwable $error ) { /* Keep the observed status. */ } }
		}
		return $key !== null ? ( $status[$key] ?? false ) : $status;
	}
}
