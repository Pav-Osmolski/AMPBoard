<?php
/**
 * PHP Error Handling & Logging
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $phpPathValid Validation state for PHP path
 * @var string $currentPhpErrorLevel Current PHP error reporting level constant value
 */

renderAccordionSectionStart(
	'php-error',
	renderHeading( 'PHP Error Handling & Logging' ),
	[
		'disabled'  => ! $phpPathValid,
		'expanded'  => false,
		'caretPath' => __DIR__ . '/../../assets/images/caret-down.svg',
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
