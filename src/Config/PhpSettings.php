<?php
namespace AMPBoard\Config;

/** Compatibility facade; new callers use the focused Php services. */
final class PhpSettings {
	public function apply( array $values ): void { ( new \AMPBoard\Php\Runtime() )->apply( $values ); }
	public function patch( string $path, array $values ): bool { return ( new \AMPBoard\Php\IniFile( $path ) )->patch( $values ); }
}
