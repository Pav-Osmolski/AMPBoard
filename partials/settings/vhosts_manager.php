<?php
/**
 * Virtual Hosts Manager
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $apachePathValid Validation state for Apache path
 */

renderAccordionSectionStart(
	'vhosts-manager',
	renderHeading( 'Virtual Hosts Manager' ),
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
