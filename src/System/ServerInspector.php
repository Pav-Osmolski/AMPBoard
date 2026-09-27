<?php
namespace AMPBoard\System;

use AMPBoard\Apache\VersionProbe;
use AMPBoard\Database\ServerVersion;
use Closure;
use Throwable;

/** Collects header status independently of rendering; constructed without performing probes. */
final class ServerInspector {
	private VersionProbe $apache;
	private Closure $connect;
	private array $runtime;
	public function __construct( VersionProbe $apache, callable $connect, array $runtime ) {
		$this->apache = $apache; $this->connect = Closure::fromCallable( $connect ); $this->runtime = $runtime;
	}
	public function inspect(): array {
		$connection = null;
		try {
			$connection = ($this->connect)();
			if ( $connection->connect_error ) { throw new \RuntimeException( $connection->connect_error ); }
			$database = [ 'available' => true, 'label' => ServerVersion::normalise( $connection->server_info ) ];
		} catch ( Throwable $error ) {
			$database = [ 'available' => false, 'label' => $error->getMessage() ];
		} finally {
			if ( $connection !== null ) {
				try { $connection->close(); } catch ( Throwable $error ) { /* Status was already collected. */ }
			}
		}
		return [ 'apache' => $this->apache->inspect(), 'php' => $this->runtime, 'database' => $database ];
	}
}
