<?php
/**
 * Settings Manager
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 */

renderAccordionSectionStart(
	'settings-manager',
	renderHeading( 'Settings Manager' ),
	[
		'expanded'  => false,
		'caretPath' => __DIR__ . '/../../assets/images/caret-down.svg',
	]
);
?>
<?php renderButtonBlock( [
	'label' => '🧹 Clear Local Storage',
	'id'    => 'clear-local-storage',
	'class' => 'button warning',
], [ 'top' => 'sm' ] ); ?>

<?php renderAccordionSectionEnd(); ?>
