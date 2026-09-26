<?php
namespace AMPBoard\Export;

use Closure;
use RuntimeException;

/** Base-table SQL dumps using a supplied connection factory; no credential constants. */
final class DatabaseExporter {
	private Closure $connect;
	/** The callback accepts a database name or null and returns a MySQLi-compatible connection. */
	public function __construct( callable $connect ) { $this->connect = Closure::fromCallable( $connect ); }

	private function query( $connection, string $sql, ?int $mode = null ) {
		$result = $mode === null ? $connection->query( $sql ) : $connection->query( $sql, $mode );
		if ( $result === false ) { throw new RuntimeException( 'Database export query failed: ' . $connection->error ); }
		return $result;
	}

	public function dump( string $dbName ): string {
		$mysqli = ($this->connect)( $dbName );
		try {

			$e = function ( string $s ) {
				return '`' . str_replace( '`', '``', $s ) . '`';
			};

			$out = "-- Dump of database {$e($dbName)}\n";
			$out .= "-- Generated: " . date( 'Y-m-d H:i:s' ) . "\n\n";
			$out .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
			$out .= "SET time_zone = '+00:00';\n";
			$out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

			$tables = [];
			if ( $res = $this->query( $mysqli, "SHOW FULL TABLES WHERE Table_type='BASE TABLE'" ) ) {
				while ( $row = $res->fetch_row() ) {
					$tables[] = (string) $row[0];
				}
				$res->free();
			}

			foreach ( $tables as $table ) {
				if ( $res = $this->query( $mysqli, "SHOW CREATE TABLE " . $e( $table ) ) ) {
					$row    = $res->fetch_array( MYSQLI_NUM );
					$create = $row[1] ?? '';
					if ( $create === '' ) { throw new RuntimeException( 'Missing table definition.' ); }
					$res->free();
				}

				$out .= "DROP TABLE IF EXISTS " . $e( $table ) . ";\n";
				$out .= $create . ";\n\n";

				$columns = [];
				if ( $colsRes = $this->query( $mysqli, "SHOW COLUMNS FROM " . $e( $table ) ) ) {
					while ( $f = $colsRes->fetch_assoc() ) {
						$columns[] = $f['Field'];
					}
					$colsRes->free();
				}

				$res = $this->query( $mysqli, "SELECT * FROM " . $e( $table ), MYSQLI_USE_RESULT );

				$batchSize = 200;
				$values    = [];
				$rowCount  = 0;

				$flush = function () use ( &$out, $table, &$values, $columns, $e ) {
					if ( ! $values ) {
						return;
					}
					$colsSql = $columns ? ( " (" . implode( ',', array_map( $e, $columns ) ) . ")" ) : '';
					$out     .= "INSERT INTO " . $e( $table ) . $colsSql . " VALUES\n" . implode( ",\n", $values ) . ";\n";
					$values  = [];
				};

				while ( $row = $res->fetch_assoc() ) {
					if ( ! $columns ) {
						$columns = array_keys( $row );
					}
					$vals = [];
					foreach ( $columns as $col ) {
						$v      = $row[ $col ] ?? null;
						$vals[] = $v === null ? "NULL" : "'" . $mysqli->real_escape_string( $v ) . "'";
					}
					$values[] = '(' . implode( ',', $vals ) . ')';
					if ( count( $values ) >= $batchSize ) {
						$flush();
					}
					$rowCount ++;
				}
				$res->free();

				if ( $values ) {
					$flush();
				}
				if ( $rowCount > 0 ) {
					$out .= "\n";
				}
			}

			$out .= "SET FOREIGN_KEY_CHECKS=1;\n";


			return $out;
		} finally { $mysqli->close(); }
	}

	public function databases(): array {
		try { $mysqli = ($this->connect)( null ); } catch ( \Throwable $error ) { return []; }
		try {

			$out = [];
			if ( $res = $this->query( $mysqli, 'SHOW DATABASES' ) ) {
				while ( $row = $res->fetch_array( MYSQLI_NUM ) ) {
					$name = (string) $row[0];
					if ( in_array( $name, [ 'information_schema', 'performance_schema', 'mysql', 'sys' ], true ) ) {
						continue;
					}
					$out[] = $name;
				}
				$res->free();
			}

			sort( $out, SORT_NATURAL | SORT_FLAG_CASE );

			return $out;
		} finally { $mysqli->close(); }
	}
}
