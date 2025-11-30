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
 * - vHosts manager integration
 * - Export files and database
 * - Apache control (restart support if `toggle_apache.php` is present)
 * - Resetting saved UI settings from localStorage
 *
 * Accessibility and UX Enhancements:
 * - Tabbable, keyboard-operable accordion triggers
 * - ARIA-complete wiring via helper functions
 * - Dynamic theme metadata injected into JS context
 *
 * Dependencies:
 * - `config.php` for headings, helpers, theme detection, display flags, path constants, tooltip data, access control
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
 * @var array $themeTypes Theme type metadata for client-side use
 * @var string $currentTheme Active theme key
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 *
 * @author  Pawel Osmolski
 * @version 3.4
 */

require_once __DIR__ . '/../config/config.php';
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
		<?= renderHeading( 'User Configuration', 'h2', true ) ?>
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

			<?php
			$settingsFormPanels = [
				'amp_paths',
				'user_interface',
				'php_error',
				'folders_config',
				'link_templates',
				'dock_config'
			];

			foreach ( $settingsFormPanels as $formPanel ) {
				require_once __DIR__ . "/settings/{$formPanel}.php";
				renderSeparatorLine();
			}
			?>
		</form>

			<?php
			$settingsPanels = [
				'vhosts_manager',
				'export_files',
				'apache_control',
				'settings_manager'
			];

			$lastIndex = count( $settingsPanels ) - 1;

			foreach ( $settingsPanels as $index => $panel ) {
				require_once __DIR__ . "/settings/{$panel}.php";

				if ( $index !== $lastIndex ) {
					renderSeparatorLine();
				}
			}
			?>
	</div>
</div>
