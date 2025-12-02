<?php
/**
 * User Interface
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var array $themeOptions Theme options for the select box
 * @var string $currentTheme Active theme key
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
 * @var bool $apacheFastMode Fast mode flag for Apache inspector
 * @var bool $mysqlFastMode Fast mode flag for MySQL inspector
 * @var array<string, mixed> $config
 */

renderAccordionSectionStart(
	'user-interface',
	renderHeading( 'User Interface' ),
	[
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<div class="settings-container settings-features">
	<div class="settings-features-group">
		<div class="settings-row">
			<label class="select" for="theme-selector">Theme:</label>
			<select id="theme-selector" name="theme">
				<?php foreach ( $config['ui']['themes']['options'] as $id => $label ) : ?>
					<option value="<?= htmlspecialchars( $id, ENT_QUOTES, 'UTF-8' ) ?>"
						<?= $config['ui']['themes']['currentTheme'] === $id ? 'selected="selected"' : '' ?>>
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
				<input type="checkbox" name="displayHeader" <?= $config['ui']['flags']['header'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display Footer:
				<input type="checkbox" name="displayFooter" <?= $config['ui']['flags']['footer'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display Clock:
				<input type="checkbox" name="displayClock" <?= $config['ui']['flags']['clock'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display Search:
				<input type="checkbox" name="displaySearch" <?= $config['ui']['flags']['search'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display Tooltips:
				<input type="checkbox"
				       name="displayTooltips" <?= $config['ui']['flags']['tooltips'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display Folder Badges:
				<input type="checkbox"
				       name="displayFolderBadges" <?= $config['ui']['flags']['folderBadges'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display System Stats:
				<input type="checkbox"
				       name="displaySystemStats" <?= $config['ui']['flags']['systemStats'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display Apache Error Log:
				<input type="checkbox"
				       name="displayApacheErrorLog" <?= $config['ui']['flags']['apacheErrorLog'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Display PHP Error Log:
				<input type="checkbox"
				       name="displayPhpErrorLog" <?= $config['ui']['flags']['phpErrorLog'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Use AJAX for Stats:
				<input type="checkbox"
				       name="useAjaxForStats" <?= $config['ui']['flags']['useAjaxForStats'] ? 'checked' : '' ?>>
				<span class="sr-only">Loads stats in the background without reloading the page</span>
			</label>
		</div>

		<div class="settings-row">
			<label>
				Use AJAX for Error Log:
				<input type="checkbox"
				       name="useAjaxForErrorLog" <?= $config['ui']['flags']['useAjaxForErrorLog'] ? 'checked' : '' ?>>
				<span class="sr-only">Loads the error log in the background without reloading the page</span>
			</label>
		</div>

	</fieldset>
</div>
<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>

<?php renderAccordionSectionEnd(); ?>
