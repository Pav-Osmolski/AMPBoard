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
 * @version 3.1
 */

/**
 * @var bool $apacheFastMode Fast mode flag for Apache inspector
 * @var bool $mysqlFastMode Fast mode flag for MySQL inspector
 * @var bool $displayHeader UI flag to show header
 * @var bool $displayFooter UI flag to show footer
 * @var bool $displayClock UI flag to show clock
 * @var bool $displaySearch UI flag to show search
 * @var bool $displayTooltips UI flag to show tooltips
 * @var bool $displayFolderBadges UI flag to show Folder Badges
 * @var bool $displayApacheErrorLog UI flag to show Apache error log
 * @var bool $displayPhpErrorLog UI flag to show PHP error log
 * @var bool $displaySystemStats UI flag to show system stats
 * @var bool $useAjaxForStats UI flag to fetch stats via AJAX
 * @var bool $useAjaxForErrorLog UI flag to fetch error logs via AJAX
 * @var bool $apacheToggle True if Apache restart endpoint is available
 * @var bool $apachePathValid Validation state for Apache path
 * @var bool $htdocsPathValid Validation state for HTDocs path
 * @var bool $phpPathValid Validation state for PHP path
 * @var bool $mySqlHostValid Validation state for MySQL Host
 * @var bool $mySqlUserValid Validation state for MySQL Username
 * @var bool $mySqlPassValid Validation state for MySQL Password
 * @var array $themeTypes Theme type metadata for client-side use
 * @var array $tooltips Tooltip copy map
 * @var array $themeOptions Theme options for the select box
 * @var string $currentTheme Active theme key
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var string $dbUser Database user for display (obfuscated on output)
 * @var string $dbPass Database password for display (obfuscated on output)
 * @var string $currentPhpErrorLevel Current PHP error reporting level constant value
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
	<!-- User Settings -->
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

			<?php
			// Database & Paths
			renderAccordionSectionStart(
				'user-settings',
				renderHeadingTooltip( 'user_settings', $tooltips, $defaultTooltipMessage, 'h3', 'Database & Paths' ),
				[
					'expanded'  => false,
					'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
				]
			);
			?>
			<div class="background-logos">
				<?php echo injectSvgWithUniqueIds( __DIR__ . '/../assets/images/Apache.svg', 'Apache2' ); ?>
				<?php echo injectSvgWithUniqueIds( __DIR__ . '/../assets/images/MariaDB.svg', 'MariaDB1' ); ?>
				<?php echo injectSvgWithUniqueIds( __DIR__ . '/../assets/images/PHP.svg', 'PHP2' ); ?>
			</div>
			<div class="settings-container">
				<label>DB Host:
					<input type="text" name="DB_HOST" value="<?= obfuscate_value( DB_HOST ) ?>">
					<?= $mySqlHostValid ? '✔️' : '❌' ?>
				</label>
				<label>DB User:
					<input type="text" name="DB_USER" value="<?= obfuscate_value( htmlspecialchars( $dbUser ) ) ?>">
					<?= $mySqlUserValid ? '✔️' : '❌' ?>
				</label>
				<label>DB Password:
					<input type="password" name="DB_PASSWORD"
					       value="<?= obfuscate_value( htmlspecialchars( $dbPass ) ) ?>">
					<?= $mySqlPassValid ? '✔️' : '❌' ?>
				</label>

				<label>Apache Path:
					<input type="text" name="APACHE_PATH" value="<?= obfuscate_value( APACHE_PATH ) ?>">
					<?= $apachePathValid ? '✔️' : '❌' ?>
				</label>

				<label>HTDocs Path:
					<input type="text" name="HTDOCS_PATH" value="<?= obfuscate_value( HTDOCS_PATH ) ?>">
					<?= $htdocsPathValid ? '✔️' : '❌' ?>
				</label>

				<label>PHP Path:
					<input type="text" name="PHP_PATH" value="<?= obfuscate_value( PHP_PATH ) ?>">
					<?= $phpPathValid ? '✔️' : '❌' ?>
				</label>
				<?php renderSeparatorLine( 'xs' ); ?>
				<fieldset>
					<legend>Inspector settings</legend>

					<label>
						<input type="checkbox"
						       name="apacheFastMode" <?= isset( $apacheFastMode ) && $apacheFastMode ? 'checked' : '' ?>>
						Fast Mode for Apache Inspector
					</label>

					<label>
						<input type="checkbox"
						       name="mysqlFastMode" <?= isset( $mysqlFastMode ) && $mysqlFastMode ? 'checked' : '' ?>>
						Fast Mode for MySQL Inspector
					</label>
				</fieldset>
				<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>
			</div>
			<?php renderAccordionSectionEnd(); ?>

			<?php renderSeparatorLine(); ?>

			<?php
			// User Interface
			renderAccordionSectionStart(
				'user-interface',
				renderHeadingTooltip( 'user_interface', $tooltips, $defaultTooltipMessage, 'h3', 'User Interface' ),
				[
					'expanded'  => false,
					'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
				]
			);
			?>
			<div class="settings-container settings-features">
				<div class="settings-features-group">
					<div class="settings-row">
						<label class="select" for="theme-selector">Theme:</label>
						<select id="theme-selector" name="theme">
							<?php foreach ( $themeOptions as $id => $label ) : ?>
								<option value="<?= htmlspecialchars( $id, ENT_QUOTES, 'UTF-8' ) ?>"
									<?= $currentTheme === $id ? 'selected="selected"' : '' ?>>
									<?= htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<?php renderSeparatorLine( 'sm' ); ?>
				<fieldset class="settings-features-group autofit">
					<legend>Interface settings</legend>

					<div class="settings-row">
						<label>
							Display Header:
							<input type="checkbox" name="displayHeader" <?= $displayHeader ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display Footer:
							<input type="checkbox" name="displayFooter" <?= $displayFooter ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display Clock:
							<input type="checkbox" name="displayClock" <?= $displayClock ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display Search:
							<input type="checkbox" name="displaySearch" <?= $displaySearch ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display Tooltips:
							<input type="checkbox" name="displayTooltips" <?= $displayTooltips ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display Folder Badges:
							<input type="checkbox"
							       name="displayFolderBadges" <?= $displayFolderBadges ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display System Stats:
							<input type="checkbox"
							       name="displaySystemStats" <?= $displaySystemStats ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display Apache Error Log:
							<input type="checkbox"
							       name="displayApacheErrorLog" <?= $displayApacheErrorLog ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Display PHP Error Log:
							<input type="checkbox"
							       name="displayPhpErrorLog" <?= $displayPhpErrorLog ? 'checked' : '' ?>>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Use AJAX for Stats:
							<input type="checkbox" name="useAjaxForStats" <?= $useAjaxForStats ? 'checked' : '' ?>>
							<span class="sr-only">Loads stats in the background without reloading the page</span>
						</label>
					</div>

					<div class="settings-row">
						<label>
							Use AJAX for Error Log:
							<input type="checkbox"
							       name="useAjaxForErrorLog" <?= $useAjaxForErrorLog ? 'checked' : '' ?>>
							<span class="sr-only">Loads the error log in the background without reloading the page</span>
						</label>
					</div>

				</fieldset>
			</div>
			<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>

			<?php renderAccordionSectionEnd(); ?>

			<?php renderSeparatorLine(); ?>

			<?php
			// PHP Error Handling & Logging (disabled when PHP path invalid)
			$phpErrorHeading = renderHeadingTooltip( 'php_error', $tooltips, $defaultTooltipMessage, 'h3', 'PHP Error Handling & Logging' ) . ( $phpPathValid ? '' : ' &nbsp;❕' );

			renderAccordionSectionStart(
				'php-error',
				$phpErrorHeading,
				[
					'disabled'  => ! $phpPathValid,
					'expanded'  => false,
					'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
				]
			);
			?>
			<div class="settings-container">
				<?php if ( ! $phpPathValid ): ?>
					<p><strong>Warning:</strong> PHP Error Handling & Logging will save to <code>user_config.php</code>
						but
						will
						not be reflected in <code>php.ini</code> (invalid PHP path).</p><br>
				<?php endif; ?>

				<label>Display Errors:
					<input type="checkbox" name="displayPhpErrors" <?= ini_get( 'display_errors' ) ? 'checked' : '' ?>>
				</label>

				<label>Error Reporting Level:
					<?php
					$phpErrorLevels = [
						E_ALL     => 'E_ALL',
						E_ERROR   => 'E_ERROR',
						E_WARNING => 'E_WARNING',
						E_NOTICE  => 'E_NOTICE'
					];
					?>
					<select name="phpErrorLevel">
						<?php foreach ( $phpErrorLevels as $value => $label ) : ?>
							<option value="<?= $label ?>" <?= $currentPhpErrorLevel == $value ? 'selected' : '' ?>><?= $label ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>Log Errors:
					<input type="checkbox" name="logPhpErrors" <?= ini_get( 'log_errors' ) ? 'checked' : '' ?>>
				</label>
			</div>
			<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ], [ 'top' => 'sm' ] ); ?>

			<?php renderAccordionSectionEnd(); ?>

			<?php renderSeparatorLine(); ?>

			<?php
			// Folders Configuration
			renderAccordionSectionStart(
				'folders-config',
				renderHeadingTooltip( 'folders', $tooltips, $defaultTooltipMessage, 'h3', 'Folders Configuration' ),
				[
					'expanded'  => false,
					'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
				]
			);
			?>
			<div class="settings-container">
				<div id="folders-config">
					<ul id="folders-config-list" class="draggable-list"><!-- Generated by folders.js --></ul>
					<button type="button" id="add-folder-column">➕ Add Folder Column</button>
				</div>
				<input type="hidden" id="folders_json_input" name="folders_json">
			</div>
			<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>

			<?php renderAccordionSectionEnd(); ?>

			<?php renderSeparatorLine(); ?>

			<?php
			// Folder Link Templates
			renderAccordionSectionStart(
				'link-templates-config',
				renderHeadingTooltip( 'link_templates', $tooltips, $defaultTooltipMessage, 'h3', 'Folder Link Templates' ),
				[
					'expanded'  => false,
					'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
				]
			);
			?>
			<div class="settings-container">
				<div id="link-templates-config">
					<p id="link-templates-help">
						Build your folder link templates with plain HTML. Use
						<code>{urlName}</code> wherever the site name should appear — in text
						or in attributes.<br>
						<!-- Currently supported variables: <code>{urlName}</code>.<br> -->
						Example:
						<code>&lt;li class='folder-item'&gt;&lt;a href='https://local.{urlName}.com'&gt;{urlName}&lt;/a&gt;&lt;/li&gt;</code>.<br>
						Tips: Tab to indent, Shift+Tab to outdent, Enter preserves current indent.
					</p>
					<p id="editor-instructions" class="sr-only">
						When inside the editor, press Tab to insert a tab character. Press Escape, then Tab to move
						focus
						out of the editor.
					</p>

					<?php renderSeparatorLine( 'sm' ) ?>

					<ul
							id="link-templates-list"
							class="template-list"
							aria-describedby="link-templates-help"
							aria-live="polite"
					><!-- Generated by linkTemplates.js --></ul>
					<button type="button" id="add-link-template">➕ Add Link Template</button>
				</div>
				<input type="hidden" id="link_templates_json_input" name="link_templates_json" value="">
			</div>
			<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>

			<?php renderAccordionSectionEnd(); ?>

			<?php renderSeparatorLine(); ?>

			<?php
			// Dock Configuration
			renderAccordionSectionStart(
				'dock-config',
				renderHeadingTooltip( 'dock', $tooltips, $defaultTooltipMessage, 'h3', 'Dock Configuration' ),
				[
					'expanded'  => false,
					'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
				]
			);
			?>
			<div class="settings-container">
				<div id="dock-config-editor">
					<ul id="dock-list"><!-- Generated by dock.js --></ul>
					<button type="button" id="add-dock-item">➕ Add Dock Item</button>
				</div>
				<input type="hidden" id="dock_json_input" name="dock_json">
			</div>
			<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>

			<?php renderAccordionSectionEnd(); ?>
		</form>

		<?php renderSeparatorLine(); ?>

		<!-- vHosts Manager -->
		<?php
		$vhostsHeading = renderHeadingTooltip( 'vhosts_manager', $tooltips, $defaultTooltipMessage, 'h3', 'Virtual Hosts Manager' ) . ( $apachePathValid ? '' : ' &nbsp;❕' );

		renderAccordionSectionStart(
			'vhosts-manager',
			$vhostsHeading,
			[
				'disabled'  => ! $apachePathValid,
				'expanded'  => false,
				'settings'  => true,
				'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
			]
		);
		?>
		<?php require_once __DIR__ . '/../utils/vhosts_manager.php'; ?>
		<?php renderAccordionSectionEnd(); ?>

		<?php renderSeparatorLine(); ?>

		<!-- Export Files -->
		<?php
		$exportHeading = renderHeadingTooltip( 'export', $tooltips, $defaultTooltipMessage, 'h3', 'Export Files & Database' ) . ( $phpPathValid ? '' : ' &nbsp;❕' );

		renderAccordionSectionStart(
			'export',
			$exportHeading,
			[
				'disabled'  => ! $phpPathValid,
				'expanded'  => false,
				'settings'  => true,
				'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
			]
		);
		?>
		<?php require_once __DIR__ . '/../utils/export_files.php'; ?>
		<?php renderAccordionSectionEnd(); ?>

		<?php renderSeparatorLine(); ?>

		<!-- Apache Control -->
		<?php
		$apacheControlHeading = renderHeadingTooltip( 'apache_control', $tooltips, $defaultTooltipMessage, 'h3', 'Apache Control' ) . ( $apachePathValid ? '' : ' &nbsp;❕' );

		renderAccordionSectionStart(
			'apache-control',
			$apacheControlHeading,
			[
				'disabled'  => ! $apachePathValid,
				'expanded'  => false,
				'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
			]
		);
		?>
		<?php if ( $apacheToggle && $apachePathValid ): ?>
			<?php renderButtonBlock( [
				'label' => 'Restart Apache',
				'id'    => 'restart-apache-button'
			], [ 'top' => 'sm' ] ); ?>
			<div id="apache-status-message" role="status" aria-live="polite"></div>
		<?php else: ?>
			<p><strong>Warning:</strong> Apache control unavailable.
				<?php
				if ( ! $apachePathValid ) {
					if ( ! empty( APACHE_PATH ) ) {
						echo ' The Apache path <code>' . obfuscate_value( APACHE_PATH ) . '</code> is invalid.';
					} else {
						echo ' The Apache path is not set.';
					}
				} else {
					echo ' The <code>toggle_apache.php</code> utility is missing.';
				}
				?>
			</p>
			<?php renderButtonBlock( [
				'label'    => 'Restart Apache',
				'id'       => 'restart-apache-button',
				'disabled' => true,
			], [ 'top' => 'sm' ] ); ?>

		<?php endif; ?>
		<?php renderAccordionSectionEnd(); ?>

		<?php renderSeparatorLine(); ?>

		<!-- Settings Management -->
		<?php
		renderAccordionSectionStart(
			'settings-management',
			renderHeadingTooltip( 'clear_storage', $tooltips, $defaultTooltipMessage, 'h3', 'Settings Management' ),
			[
				'expanded'  => false,
				'caretPath' => __DIR__ . '/../assets/images/caret-down.svg',
			]
		);
		?>
		<?php renderButtonBlock( [
			'label' => '🧹 Clear Local Storage',
			'id'    => 'clear-local-storage',
			'class' => 'button warning',
		], [ 'top' => 'sm' ] ); ?>

		<?php renderAccordionSectionEnd(); ?>
	</div>
</div>
