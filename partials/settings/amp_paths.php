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
 */

renderAccordionSectionStart(
	'amp-paths',
	renderHeading( 'Database & Paths' ),
	[
		'expanded'  => false,
		'caretPath' => __DIR__ . '/../../assets/images/caret-down.svg',
	]
);
?>
<div class="background-logos">
	<?php echo injectSvgWithUniqueIds( __DIR__ . '/../../assets/images/Apache.svg', 'Apache2' ); ?>
	<?php echo injectSvgWithUniqueIds( __DIR__ . '/../../assets/images/MariaDB.svg', 'MariaDB1' ); ?>
	<?php echo injectSvgWithUniqueIds( __DIR__ . '/../../assets/images/PHP.svg', 'PHP2' ); ?>
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
