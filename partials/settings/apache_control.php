<?php
/**
 * Apache Control
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $apachePathValid Validation state for Apache path
 * @var bool $apacheToggle True if Apache restart endpoint is available
 * @var array<string, mixed> $config
 */

renderAccordionSectionStart(
	'apache-control',
	renderHeading( 'Apache Control' ),
	[
		'disabled'  => ! $config['status']['apachePathValid'],
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<?php if ( $config['status']['apacheToggleAvailable'] && $config['status']['apachePathValid'] ): ?>
	<?php renderButtonBlock( [
		'label' => 'Restart Apache',
		'id'    => 'restart-apache-button'
	], [ 'top' => 'sm' ] ); ?>
	<div id="apache-status-message" role="status" aria-live="polite"></div>
<?php else: ?>
	<p><strong>Warning:</strong> Apache control unavailable.
		<?php
		if ( ! $config['status']['apachePathValid'] ) {
			if ( ! empty( $config['paths']['apache'] ) ) {
				echo ' The Apache path <code>' . obfuscate_value( $config['paths']['apache'] ) . '</code> is invalid.';
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
