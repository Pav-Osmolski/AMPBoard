<?php
/**
 * Export Files & Database
 * Included as part of `partials/settings.php`
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 */

$ui->renderAccordionSectionStart(
	'export',
	$ui->renderHeading( 'Export Files & Database' ),
	[
		'disabled'  => ! $config['status']['phpPathValid'],
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<?php require_once $config['paths']['utils'] . '/export_files.php'; ?>
<?php $ui->renderAccordionSectionEnd(); ?>
