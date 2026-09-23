<?php
/**
 * PHP Manager
 * Included as part of `partials/settings.php`
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 */

$ui->renderAccordionSectionStart(
	'php-manager',
	$ui->renderHeading( 'PHP Manager' ),
	[
		'disabled'  => ! $config['status']['phpPathValid'],
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<div class="settings-container">
	<?php if ( ! $config['status']['phpPathValid'] ): ?>
		<p><strong>Warning:</strong> PHP Error Handling &amp; Logging will save to <code>user_config.php</code>
			but will not be reflected in <code>php.ini</code> (invalid PHP path).
		</p>
		<?php $ui->renderSeparatorLine( 'sm' ) ?>
	<?php endif; ?>

	<div class="settings-features-group settings-sm-label">
		<div class="settings-row">
			<label><span>Display Errors:</span>
				<input type="checkbox"
				       name="displayPhpErrors" <?= $config['user']['phpDisplayErrors'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label><span>Error Reporting:</span>
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
						<option value="<?= $label ?>" <?= $config['user']['phpErrorReporting'] == $value ? 'selected' : '' ?>><?= $label ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="settings-row">
			<label><span>Log Errors:</span>
				<input type="checkbox" name="logPhpErrors" <?= $config['user']['phpLogErrors'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label><span>Memory Limit:</span>
				<input
						type="text"
						name="phpMemoryLimit"
						value="<?= htmlspecialchars( $config['user']['phpMemoryLimit'], ENT_QUOTES, 'UTF-8' ) ?>"
						placeholder="e.g. 256M, 1G or -1"
				>
			</label>
			<?= $ui->renderHeading( 'PHP Memory Limit', 'label', false, true ); ?>
		</div>

		<div class="settings-row">
			<label><span>Max Exec Time:</span>
				<input type="number"
				       name="phpMaxExecution"
				       value="<?= htmlspecialchars( (string) $config['user']['phpMaxExecution'], ENT_QUOTES, 'UTF-8' ) ?>"
				       min="-1"
				       step="1">
			</label>
			<?= $ui->renderHeading( 'PHP Max Execution Time', 'label', false, true ); ?>
		</div>

		<div class="settings-row">
			<label><span>Max Input Vars:</span>
				<input type="number"
				       name="phpMaxInputVars"
				       value="<?= htmlspecialchars( (string) $config['user']['phpMaxInputVars'], ENT_QUOTES, 'UTF-8' ) ?>"
				       min="1"
				       step="1">
			</label>
			<?= $ui->renderHeading( 'PHP Max Input Vars', 'label', false, true ); ?>
		</div>

		<div class="settings-row">
			<label><span>Upload Max Size:</span>
				<input type="text"
				       name="phpUploadMaxFile"
				       value="<?= htmlspecialchars( (string) $config['user']['phpUploadMaxFile'], ENT_QUOTES, 'UTF-8' ) ?>"
				       placeholder="e.g. 20M, 50M">
			</label>
			<?= $ui->renderHeading( 'PHP Upload Max File Size', 'label', false, true ); ?>
		</div>

		<div class="settings-row">
			<label><span>Post Max Size:</span>
				<input type="text"
				       name="phpPostMaxSize"
				       value="<?= htmlspecialchars( (string) $config['user']['phpPostMaxSize'], ENT_QUOTES, 'UTF-8' ) ?>"
				       placeholder="e.g. 20M, 50M">
			</label>
			<?= $ui->renderHeading( 'PHP Post Max Size', 'label', false, true ); ?>
		</div>

		<div class="settings-row">
			<label><span>PHP Timezone:</span>
				<input type="text"
				       name="phpTimezone"
				       value="<?= htmlspecialchars( (string) $config['user']['phpTimezone'], ENT_QUOTES, 'UTF-8' ) ?>"
				       placeholder="e.g. Europe/London">
			</label>
			<?= $ui->renderHeading( 'PHP Timezone', 'label', false, true ); ?>
		</div>
	</div>
</div>
<?php $ui->renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ], [ 'top' => 'sm' ] ); ?>

<?php $ui->renderAccordionSectionEnd(); ?>
