<?php
/**
 * Settings Manager
 * Included as part of `partials/settings.php`
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 */

$ui->renderAccordionSectionStart(
	'settings-manager',
	$ui->renderHeading( 'Settings Manager' ),
	[
		'expanded'  => false,
		'caretPath' => $config['paths']['assets'] . '/images/caret-down.svg',
	]
);
?>
<?php $ui->renderButtonBlock( [
	'label' => '🧹 Clear Local Storage',
	'id'    => 'clear-local-storage',
	'class' => 'button warning',
], [ 'top' => 'sm' ] ); ?>

<?php $ui->renderAccordionSectionEnd(); ?>
