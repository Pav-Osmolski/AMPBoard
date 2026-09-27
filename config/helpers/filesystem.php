<?php
require_once __DIR__ . '/../autoload.php';
/** Compatibility wrappers for trusted legacy profiles and custom integrations. */
function normalise_subdir( string $relative ): array {
	return ( new \AMPBoard\Filesystem\DirectoryCatalog( HTDOCS_PATH ) )->resolve( $relative );
}
function list_subdirs( string $absDir ): array {
	return ( new \AMPBoard\Filesystem\DirectoryCatalog( '' ) )->listDirectories( $absDir );
}
function sanitizeFolderName( string $name ): string { return \AMPBoard\Filesystem\FolderName::sanitise( $name ); }
