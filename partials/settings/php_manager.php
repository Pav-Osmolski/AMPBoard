<?php
/**
 * PHP Manager
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $phpPathValid Validation state for PHP path
 * @var string $currentPhpErrorReporting Current PHP error reporting level constant value
 * @var array<string, mixed> $config
 */

renderAccordionSectionStart(
	'php-manager',
	renderHeading( 'PHP Manager' ),
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
		<?php renderSeparatorLine( 'sm' ) ?>
	<?php endif; ?>

	<div class="settings-features-group">
		<div class="settings-row">
			<label>Display Errors:
				<input type="checkbox"
				       name="displayPhpErrors" <?= $config['user']['phpDisplayErrors'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
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
						<option value="<?= $label ?>" <?= $config['user']['phpErrorReporting'] == $value ? 'selected' : '' ?>><?= $label ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="settings-row">
			<label>Log Errors:
				<input type="checkbox" name="logPhpErrors" <?= $config['user']['phpLogErrors'] ? 'checked' : '' ?>>
			</label>
		</div>

		<div class="settings-row">
			<label>Memory Limit:
				<input
						type="text"
						name="phpMemoryLimit"
						value="<?= htmlspecialchars( $config['user']['phpMemoryLimit'], ENT_QUOTES, 'UTF-8' ) ?>"
						placeholder="e.g. 256M, 1G or -1"
				>
			</label>
			<?= renderHeading( 'PHP Memory Limit', 'label', false, true ); ?>
		</div>
	</div>
</div>
<?php renderButtonBlock( [ 'label' => 'Save Settings', 'type' => 'submit' ], [ 'top' => 'sm' ] ); ?>

<?php renderAccordionSectionEnd(); ?>
