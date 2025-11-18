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
 * @author  Pawel Osmolski
 * @version 1.1
 */

/** @var string[] $tooltips */
/** @var string $defaultTooltipMessage */

require_once __DIR__ . '/../config/config.php';

$pageClasses = buildPageViewClasses( $settingsView ?? null );
?>
<div id="phpinfo-view" class="<?= $pageClasses ?>">
	<?php if ( empty( $settingsView ) ): ?>
		<?php echo render_versioned_assets_with_base(); ?>
	<?php endif; ?>
	<div class="heading">
		<?= renderHeadingTooltip( 'phpinfo', $tooltips, $defaultTooltipMessage, 'h2', 'PHP Info', false, false, true ) ?>
	</div>
	<div class="phpinfo">
		<?php
		if ( defined( 'DEMO_MODE' ) && DEMO_MODE ) {
			ob_start();
			// Only show general PHP info & credits — no secrets here
			phpinfo( INFO_GENERAL | INFO_CREDITS | INFO_LICENSE );
			$info = ob_get_clean();

			$info = preg_replace( '%^.*<body>(.*)</body>.*$%s', '$1', $info );
			$info = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $info );
			$info = preg_replace( '/style=("|\')(.*?)("|\')/i', '', $info );

			echo $info;
		} else {
			ob_start();
			phpinfo();
			$info = ob_get_clean();

			// Strip everything before <body> and after </body>
			$info = preg_replace( '%^.*<body>(.*)</body>.*$%s', '$1', $info );

			// Strip the style block
			$info = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $info );

			// Remove ALL styles inline or blocck
			$info = preg_replace( '/style=("|\')(.*?)("|\')/i', '', $info );

			echo $info;
		}
		?>
	</div>
</div>
