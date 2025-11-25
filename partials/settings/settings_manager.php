<?php
/**
 * @var string $settingsManagerHeading Settings Manager Heading
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 */

// Settings Manager
renderAccordionSectionStart(
	'settings-manager',
	$settingsManagerHeading,
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
