<?php
/** Explicit folder-opener dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$folderOpener = new \AMPBoard\Filesystem\FolderOpener( PHP_OS_FAMILY, new \AMPBoard\System\NativeProcessLauncher() );
