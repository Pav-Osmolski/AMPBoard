<?php
/**
 * @var string $exportFilesHeading Export Files Heading
 * @var array $tooltips Tooltip copy map
 * @var string $defaultTooltipMessage Default tooltip fallback message
 * @var bool $phpPathValid Validation state for PHP path
 */

// Export Files
renderAccordionSectionStart(
	'export',
	$exportFilesHeading,
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
