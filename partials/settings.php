<?php
/**
 * Settings Panel Interface
 *
 * Renders a multi-section settings dashboard for user configuration of:
 * - Environment paths (Apache, PHP, HTDocs)
 * - Database credentials (host, user, password)
 * - UI feature toggles (clock, search, stats, error log)
 * - PHP error handling options (display, log, level)
 * - Folder column layout and filtering
 * - Link templates for folder rendering
 * - Dock item management (order, labels, links)
 * - Apache control (restart support if `toggle_apache.php` is present)
 * - vHosts manager integration
 * - Resetting saved UI settings from localStorage
 *
 * Accessibility and UX Enhancements:
 * - Tabbable, keyboard-operable accordion triggers
 * - ARIA-complete wiring via helper functions
 * - Tooltips via `renderHeadingTooltip()` using keys from `$tooltips` with fallback support
 * - Dynamic theme metadata injected into JS context
 *
 * Dependencies:
 * - `config.php` for helpers, theme detection, display flags, path constants, tooltip data, access control
 * - `utils/vhosts_manager.php` for virtual host listing
 * - `utils/export_files.php` for export features
 *
 * Outputs:
 * - Dynamic HTML form with grouped setting panels
 * - JavaScript values for theme interaction
 * - Inline server path validation indicators
 *
 * Security Notes:
 * - CSRF token output with `csrf_get_token()`
 * - Sensitive values are obfuscated for display via `obfuscate_value()`
 *
 * @author  Pawel Osmolski
 * @version 3.2
 */

/**
 * @var array $themeTypes Theme type metadata for client-side use
 * @var string $currentTheme Active theme key
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 */

require_once __DIR__ . '/../config/config.php';

$ampPathsHeading        = renderHeadingTooltip( 'amp_paths', $tooltips, $defaultTooltipMessage, 'h3', 'Database & Paths' );
$userInterfaceHeading   = renderHeadingTooltip( 'user_interface', $tooltips, $defaultTooltipMessage, 'h3', 'User Interface' );
$phpErrorHeading        = renderHeadingTooltip( 'php_error', $tooltips, $defaultTooltipMessage, 'h3', 'PHP Error Handling & Logging' ) . ( $phpPathValid ? '' : ' &nbsp;❕' );
$foldersConfigHeading   = renderHeadingTooltip( 'folders_config', $tooltips, $defaultTooltipMessage, 'h3', 'Folders Configuration' );
$linkTemplatesHeading   = renderHeadingTooltip( 'link_templates', $tooltips, $defaultTooltipMessage, 'h3', 'Folder Link Templates' );
$dockConfigHeading      = renderHeadingTooltip( 'dock_config', $tooltips, $defaultTooltipMessage, 'h3', 'Dock Configuration' );
$vhostsManagerHeading   = renderHeadingTooltip( 'vhosts_manager', $tooltips, $defaultTooltipMessage, 'h3', 'Virtual Hosts Manager' ) . ( $apachePathValid ? '' : ' &nbsp;❕' );
$exportFilesHeading     = renderHeadingTooltip( 'export_files', $tooltips, $defaultTooltipMessage, 'h3', 'Export Files & Database' ) . ( $phpPathValid ? '' : ' &nbsp;❕' );
$apacheControlHeading   = renderHeadingTooltip( 'apache_control', $tooltips, $defaultTooltipMessage, 'h3', 'Apache Control' ) . ( $apachePathValid ? '' : ' &nbsp;❕' );
$settingsManagerHeading = renderHeadingTooltip( 'settings_manager', $tooltips, $defaultTooltipMessage, 'h3', 'Settings Manager' );
?>

<script>
	const themeTypes = <?= json_encode( $themeTypes ) ?>;
	const serverTheme = <?= json_encode( $currentTheme ) ?>;
</script>

<?php if ( isset( $_GET['saved'] ) ): ?>
	<div class="post-confirmation-container <?= $_GET['saved'] === '1' ? 'success' : 'failure' ?>" role="status"
	     aria-live="polite">
		<div class="post-confirmation-message">
			<?= $_GET['saved'] === '1' ? '✔️ User Settings saved successfully.' : '⚠️ Demo Mode says no! Changes weren’t saved.' ?>
		</div>
	</div>
<?php endif; ?>

<div id="settings-view">
	<div class="heading">
		<?= renderHeadingTooltip( 'user_config', $tooltips, $defaultTooltipMessage, 'h2', 'User Configuration', false, false, true ) ?>
	</div>

	<?= renderWidthControls( 'width_settings', 'Accordion', 'accordion-controls' ); ?>

	<?php if ( defined( 'DEMO_MODE' ) && DEMO_MODE ): ?>
		<div class="demo-mode" role="alert">
			<p><strong>Demo Mode:</strong> Saving is disabled and credentials are obfuscated in this environment.</p>
			<br>
		</div>
	<?php endif; ?>

	<div class="settings width-resizable" data-width-key="width_settings">
		<form method="post" action="" accept-charset="UTF-8" autocomplete="off">
			<input type="hidden" name="csrf" value="<?= htmlspecialchars( csrf_get_token() ) ?>">

			<?php require_once __DIR__ . '/settings/amp_paths.php';
			renderSeparatorLine(); ?>
			<?php require_once __DIR__ . '/settings/user_interface.php';
			renderSeparatorLine(); ?>
			<?php require_once __DIR__ . '/settings/php_error.php';
			renderSeparatorLine(); ?>
			<?php require_once __DIR__ . '/settings/folders_config.php';
			renderSeparatorLine(); ?>
			<?php require_once __DIR__ . '/settings/link_templates.php';
			renderSeparatorLine(); ?>
			<?php require_once __DIR__ . '/settings/dock_config.php';
			renderSeparatorLine(); ?>
		</form>

		<?php require_once __DIR__ . '/settings/vhosts_manager.php';
		renderSeparatorLine(); ?>
		<?php require_once __DIR__ . '/settings/export_files.php';
		renderSeparatorLine(); ?>
		<?php require_once __DIR__ . '/settings/apache_control.php';
		renderSeparatorLine(); ?>
		<?php require_once __DIR__ . '/settings/settings_manager.php'; ?>
	</div>
</div>
