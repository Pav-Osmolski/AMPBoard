<?php
namespace AMPBoard\System;

/** Fixed identity fallback; never accepts request-supplied commands. */
final class UserDiscovery {
	private $execute;
	public function __construct( ?callable $execute = null ) {
		$this->execute = $execute ?? static function (): ?string {
			return function_exists( 'shell_exec' ) ? shell_exec( 'whoami' ) : null;
		};
	}
	public function discover(): ?string {
		try { return ( $this->execute )(); }
		catch ( \Throwable $error ) { return null; }
	}
}
