<?php
/**
 * Virtual Hosts Manager
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $apachePathValid Validation state for Apache path
 * @var array<string, mixed> $config
 */

renderAccordionSectionStart(
	'vhosts-manager',
	renderHeading( 'Virtual Hosts Manager' ),
	[
		'disabled'  => ! $config['status']['apachePathValid'],
		'expanded'  => false,
		'settings'  => true,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<?php require_once $config['paths']['utils'] . '/vhosts_manager.php'; ?>
<?php renderAccordionSectionEnd(); ?>
