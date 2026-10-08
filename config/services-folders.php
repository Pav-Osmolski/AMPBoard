<?php
/** Explicit folders dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-vhosts.php";

$directories = new \AMPBoard\Filesystem\DirectoryCatalog( $config['paths']['htdocs'] );
$folderPresenter = new \AMPBoard\Ui\FolderPresenter( $config['profile']['folders'],
	new \AMPBoard\Folders\LinkTemplates( $config['profile']['linkTemplates'] ), $directories,
	static function ( string $host ) use ( $vhosts ) { return $vhosts->isValidVhostHost( $host ); }
);
