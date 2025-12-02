<?php
/**
 * Export Files & Database
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $phpPathValid Validation state for PHP path
 * @var array<string, mixed> $config
 */

renderAccordionSectionStart(
	'export',
	renderHeading( 'Export Files & Database' ),
	[
		'disabled'  => ! $config['status']['phpPathValid'],
		'expanded'  => false,
		'settings'  => true,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<?php require_once $config['paths']['utils'] . '/export_files.php'; ?>
<?php renderAccordionSectionEnd(); ?>
