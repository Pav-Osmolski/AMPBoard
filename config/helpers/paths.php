<?php
require_once __DIR__ . '/../autoload.php';
/** Compatibility wrappers for executable legacy profiles. */
function define_path_constant( string $name, string $default ): void {
	if ( ! defined( $name ) ) { define( $name, \AMPBoard\Filesystem\Path::normalise( $default ) ); }
}
function normalise_path( string $path ): string { return \AMPBoard\Filesystem\Path::normalise( $path ); }
