<?php
/**
 * Complete compatibility composition for existing PHP integrations.
 * Existing profile files and constants remain supported during the migration.
 *
 * @var array<string, mixed> $config
 * @var \AMPBoard\Http\RequestOrigin $requestOrigin
 * @var \AMPBoard\Http\SessionStore $session
 * @var \AMPBoard\Security\CsrfToken $csrfTokens
 * @var \AMPBoard\System\Identity $identity
 * @var \AMPBoard\Filesystem\JsonReader $jsonReader
 * @var \AMPBoard\Filesystem\FolderOpener $folderOpener
 * @var \AMPBoard\Filesystem\DirectoryCatalog $directories
 * @var \AMPBoard\Database\ConnectionFactory $database
 * @var \AMPBoard\Database\Inspector $mysqlInspector
 * @var \AMPBoard\Ui\DemoMask $demoMask
 * @var \AMPBoard\Ui\Renderer $ui
 * @var \AMPBoard\Ui\FolderPresenter $folderPresenter
 * @var \AMPBoard\Apache\CommandRunner $apacheCommands
 * @var \AMPBoard\Apache\Controller $apacheControl
 * @var \AMPBoard\Certificates\Generator $certificates
 * @var \AMPBoard\Apache\VhostCatalog $vhosts
 * @var \AMPBoard\Php\InfoPage $phpInfo
 * @var \AMPBoard\Php\IniFile $phpIni
 * @var \AMPBoard\System\ServerInspector $serverInspector
 * @var \AMPBoard\System\Statistics $systemStatistics
 * @var \AMPBoard\Logs\Viewer $apacheLog
 * @var \AMPBoard\Logs\Viewer $phpLog
 */

// Existing integrations receive the complete historical composition.
require_once __DIR__ . "/legacy.php";
require_once __DIR__ . "/services-request.php";
require_once __DIR__ . "/services-database.php";
require_once __DIR__ . "/services-ui.php";
require_once __DIR__ . "/services-apache-commands.php";
require_once __DIR__ . "/services-apache-control.php";
require_once __DIR__ . "/services-vhosts.php";
require_once __DIR__ . "/services-exports.php";
require_once __DIR__ . "/services-php-info.php";
require_once __DIR__ . "/services-php-ini.php";
require_once __DIR__ . "/services-server.php";
require_once __DIR__ . "/services-statistics.php";
require_once __DIR__ . "/services-apache-log.php";
require_once __DIR__ . "/services-php-log.php";
require_once __DIR__ . "/services-mysql.php";
require_once __DIR__ . "/services-certificates.php";
require_once __DIR__ . "/services-folders.php";
require_once __DIR__ . "/services-folder-opener.php";
require_once __DIR__ . "/services-diagnostics.php";
