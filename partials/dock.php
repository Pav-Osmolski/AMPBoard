<?php
/**
 * Dock Items Renderer
 *
 * Loads a list of dock shortcut items from `dock.json` and renders them
 * as clickable icons with optional labels. Each item supports:
 * - A URL (`url`)
 * - An icon image path (`icon`)
 * - Alternate text (`alt`)
 * - An optional label (`label`)
 *
 * Configuration:
 * - Reads from `/config/dock.json`
 *
 * Output:
 * - A horizontal dock bar with anchor elements linking to external tools or resources
 *
 * @var array $dockConfig
 * @var array<string, mixed> $config
 *
 * @author  Pawel Osmolski
 * @version 1.5
 */

require_once __DIR__ . '/../config/config.php';
?>
<nav class="dock" aria-label="Quick launch">
	<ul class="dock-list">
		<?php foreach ( $config['profile']['dock'] as $item ):

			$label = isset( $item['label'] ) ? trim( $item['label'] ) : '';
			$alt = isset( $item['alt'] ) ? trim( $item['alt'] ) : '';
			$url = isset( $item['url'] ) ? $item['url'] : '#';
			$icon = isset( $item['icon'] ) ? $item['icon'] : '';

			$opens = '(opens in a new tab)';

			// If alt is empty and there is no visible label, derive alt from the icon filename.
			if ( $alt === '' && $label === '' && $icon !== '' ) {
				$filename = pathinfo( $icon, PATHINFO_FILENAME ); // e.g. "GitHub"
				$filename = str_replace( [ '-', '_' ], ' ', $filename ); // e.g. "git hub"
				$alt      = trim( $filename );
			}

			// Accessible name: label wins, else alt (possibly filename-derived).
			$name = $label !== '' ? $label : $alt;

			// If we still don't have a usable name, skip for accessibility.
			if ( $name === '' ) {
				continue;
			}
			?>
			<li class="dock-item">
				<a
						href="<?= htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) ?>"
						target="_blank"
						rel="noopener noreferrer"
					<?php if ( $label === '' ): ?>
						aria-label="<?= htmlspecialchars( $name . ' ' . $opens, ENT_QUOTES, 'UTF-8' ) ?>"
					<?php endif; ?>
				>
					<img
							src="<?= htmlspecialchars( $icon, ENT_QUOTES, 'UTF-8' ) ?>"
							alt="<?= $label === '' ? htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' ) : '' ?>"
							loading="lazy"
					>
					<?php if ( $label !== '' ): ?>
						<span class="dock-label"><?= htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) ?></span>
						<span class="sr-only"><?= htmlspecialchars( trim( $alt . ' ' . $label . ' ' . $opens ), ENT_QUOTES, 'UTF-8' ) ?></span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
