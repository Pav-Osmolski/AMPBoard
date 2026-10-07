<?php
/** Compatibility wrappers for trusted legacy profiles and custom integrations. */
require_once __DIR__ . '/../autoload.php';

function validate_and_canonicalise_json( string $raw, bool $allowEmptyArray = true ): string {
	return \AMPBoard\Config\Json::canonicalise( $raw, $allowEmptyArray );
}

function read_json_array_safely( string $path ): array {
	return ( new \AMPBoard\Filesystem\JsonReader() )->readArray( $path );
}
