<?php
/**
 * mysql_inspector.php
 * Cross-platform MySQL environment inspector (via mysqli)
 *
 * Outputs:
 * - MySQL server version
 * - Connection details
 * - Client library info
 * - Active databases and size (unless fast mode)
 * - Status and configuration variables
 * - Process list
 *
 * Optional: ?fast=1 to skip size aggregation
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.4
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

// Request policy stays at the HTTP boundary; inspection receives the resolved mode.
$fastMode = $config['ui']['flags']['mysqlFastMode'] ?? false;
if ( isset( $_GET['fast'] ) ) { $fastMode = filter_var( $_GET['fast'], FILTER_VALIDATE_BOOLEAN ); }
$demo = $config['user']['isDemo'];
if ( $demo ) { $fastMode = true; }

$start = microtime( true );
$report = $mysqlInspector->inspect( (bool) $fastMode );
echo '<div class="heading">' . $ui->renderHeading( 'MySQL Inspector', 'h2', true ) . '</div>';
echo ( new \AMPBoard\Ui\MysqlReport() )->render( $report, $demo, microtime( true ) - $start );
