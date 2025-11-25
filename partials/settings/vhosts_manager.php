<?php
/**
 * @var string $vhostsManagerHeading Vhosts Manager Heading
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $apachePathValid Validation state for Apache path
 */

// vHosts Manager
renderAccordionSectionStart(
	'vhosts-manager',
	$vhostsManagerHeading,
	[
		'disabled'  => ! $apachePathValid,
		'expanded'  => false,
		'settings'  => true,
		'caretPath' => __DIR__ . '/../../assets/images/caret-down.svg',
	]
);
?>
<?php require_once __DIR__ . '/../../utils/vhosts_manager.php'; ?>
<?php renderAccordionSectionEnd(); ?>
