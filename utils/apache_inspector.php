<?php
/**
 * apache_inspector.php
 * Cross-platform Apache environment inspector (Linux, macOS, Windows)
 *
 * Outputs:
 * - Operating System and Architecture
 * - Apache binary path
 * - Apache version
 * - Apache uptime
 * - Main config path
 * - Included config files
 * - Active Virtual Hosts
 * - Apache environment variables
 * - PHP config info
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.4
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/entry-apache-inspector.php';

// Request policy stays at the HTTP boundary.
$fastMode = $config['ui']['flags']['apacheFastMode'] ?? false;
if ( isset( $_GET['fast'] ) ) { $fastMode = filter_var( $_GET['fast'], FILTER_VALIDATE_BOOLEAN ); }
$demo = $config['user']['isDemo'];
if ( $demo ) { $fastMode = true; }

$apacheInspector = new \AMPBoard\Apache\Inspector( $config['paths']['apache'], $apacheCommands, (bool) $fastMode, '/proc/self/environ', $_SERVER, new \AMPBoard\Apache\NativeRuntimeReader() );
$report = $apacheInspector->inspect( PHP_OS_FAMILY, PHP_INT_SIZE === 8 ? '64-bit' : '32-bit' );
echo '<div class="heading">' . $ui->renderHeading( 'Apache Inspector', 'h2', true ) . '</div>';
echo ( new \AMPBoard\Ui\ApacheReport() )->render( $report, $demo );
