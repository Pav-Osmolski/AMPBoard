<?php
/**
 * PHP Info Display
 *
 * Outputs the content of `phpinfo()` into a div without styling or layout junk.
 *
 * Sanitisation:
 * - Removes everything outside the `<body>` tag
 * - Strips out `<style>` blocks and inline `style` attributes
 * - Leaves only raw HTML structure and content
 *
 *
 * @author  Pawel Osmolski
 * @version 1.2
 */

require_once __DIR__ . '/../config/config.php';

$pageClasses = $ui->buildPageViewClasses( $settingsView ?? null );
?>
<div id="phpinfo-view" class="<?= $pageClasses ?>">
	<?php if ( empty( $settingsView ) ): ?>
		<?= $ui->renderVersionedAssetsWithBase(); ?>
	<?php endif; ?>
	<div class="heading">
		<?= $ui->renderHeading( 'PHP Info', 'h2', true ) ?>
	</div>
	<div class="phpinfo">
		<?php
		echo $phpInfo->render( $config['user']['isDemo'] );
		?>
	</div>
</div>
