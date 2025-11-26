<?php
/**
 * Export Files & Database
 * Included as part of `partials/settings.php`
 *
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $phpPathValid Validation state for PHP path
 */

renderAccordionSectionStart(
	'export',
	renderHeading( 'Export Files & Database' ),
	[
		'disabled'  => ! $phpPathValid,
		'expanded'  => false,
		'settings'  => true,
		'caretPath' => __DIR__ . '/../../assets/images/caret-down.svg',
	]
);
?>
<?php require_once __DIR__ . '/../../utils/export_files.php'; ?>
<?php renderAccordionSectionEnd(); ?>
