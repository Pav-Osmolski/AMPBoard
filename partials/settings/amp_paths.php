<?php
/**
 * Apache, MySQL and PHP Paths
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $apachePathValid Validation state for Apache path
 * @var bool $htdocsPathValid Validation state for HTDocs path
 * @var bool $phpPathValid Validation state for PHP path
 * @var bool $mySqlHostValid Validation state for MySQL Host
 * @var bool $mySqlUserValid Validation state for MySQL Username
 * @var bool $mySqlPassValid Validation state for MySQL Password
 * @var string $dbUser Database user for display (obfuscated on output)
 * @var string $dbPass Database password for display (obfuscated on output)
 * @var array<string, mixed> $config
 */

renderAccordionSectionStart(
	'amp-paths',
	renderHeading( 'Database & Paths' ),
	[
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<div class="background-logos">
	<?php echo injectSvgWithUniqueIds( $config['paths']['assets'] . '/images/Apache.svg', 'Apache2' ); ?>
	<?php echo injectSvgWithUniqueIds( $config['paths']['assets'] . '/images/MariaDB.svg', 'MariaDB1' ); ?>
	<?php echo injectSvgWithUniqueIds( $config['paths']['assets'] . '/images/PHP.svg', 'PHP2' ); ?>
</div>
<div class="settings-container">
	<div class="settings-features-group settings-xs-label">
		<div class="settings-row">
			<label><span>DB Host:</span>
				<input type="text" name="DB_HOST" value="<?= obfuscate_value( $config['db']['host'] ) ?>">
				<?= $config['status']['mySqlHostValid'] ? '✔️' : '❌' ?>
			</label>
		</div>

		<div class="settings-row">
			<label><span>DB User:</span>
				<input type="text" name="DB_USER" value="<?= obfuscate_value( htmlspecialchars( $config['db']['user'] ) ) ?>">
				<?= $config['status']['mySqlUserValid'] ? '✔️' : '❌' ?>
			</label>
		</div>

		<div class="settings-row">
			<label><span>DB Password:</span>
				<input type="password" name="DB_PASSWORD"
				       value="<?= obfuscate_value( htmlspecialchars( $config['db']['pass'] ) ) ?>">
				<?= $config['status']['mySqlPassValid'] ? '✔️' : '❌' ?>
			</label>
		</div>

		<div class="settings-row">
			<label><span>Apache Path:</span>
				<input type="text" name="APACHE_PATH" value="<?= obfuscate_value( $config['paths']['apache'] ) ?>">
				<?= $config['status']['apachePathValid'] ? '✔️' : '❌' ?>
			</label>
		</div>

		<div class="settings-row">
			<label><span>HTDocs Path:</span>
				<input type="text" name="HTDOCS_PATH" value="<?= obfuscate_value( $config['paths']['htdocs'] ) ?>">
				<?= $config['paths']['htdocs'] ? '✔️' : '❌' ?>
			</label>
		</div>

		<div class="settings-row">
			<label><span>PHP Path:</span>
				<input type="text" name="PHP_PATH" value="<?= obfuscate_value( $config['paths']['php'] ) ?>">
				<?= $config['status']['phpPathValid'] ? '✔️' : '❌' ?>
			</label>
		</div>
	</div>
	<?php renderSeparatorLine( 'xs' ); ?>
	<fieldset>
		<legend>Inspector settings</legend>

		<label>
			<input type="checkbox"
			       name="apacheFastMode" <?= isset( $config['ui']['flags']['apacheFastMode'] ) && $config['ui']['flags']['apacheFastMode'] ? 'checked' : '' ?>>
			Fast Mode for Apache Inspector
		</label>

		<label>
			<input type="checkbox"
			       name="mysqlFastMode" <?= isset( $config['ui']['flags']['mysqlFastMode'] ) && $config['ui']['flags']['mysqlFastMode'] ? 'checked' : '' ?>>
			Fast Mode for MySQL Inspector
		</label>
	</fieldset>
	<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ] ); ?>
</div>
<?php renderAccordionSectionEnd(); ?>
