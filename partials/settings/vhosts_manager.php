<?php
/**
 * Virtual Hosts Manager
 * Included as part of `partials/settings.php`
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 */

$ui->renderAccordionSectionStart(
	'vhosts-manager',
	$ui->renderHeading( 'Virtual Hosts Manager' ),
	[
		'disabled'  => ! $config['status']['apachePathValid'],
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<?php require_once $config['paths']['utils'] . '/vhosts_manager.php'; ?>
<?php $ui->renderAccordionSectionEnd(); ?>
