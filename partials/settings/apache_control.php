<?php
/**
 * @var string $apacheControlHeading Apache Control Heading
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $apachePathValid Validation state for Apache path
 * @var bool $apacheToggle True if Apache restart endpoint is available
 */

// Apache Control
renderAccordionSectionStart(
	'apache-control',
	$apacheControlHeading,
	[
		'disabled'  => ! $apachePathValid,
		'expanded'  => false,
		'caretPath' => __DIR__ . '/../../assets/images/caret-down.svg',
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
