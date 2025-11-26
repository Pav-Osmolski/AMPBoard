<?php
/**
 * Renders the Apache/PHP error log panels and optional system statistics section.
 *
 * Overview:
 * - Builds a feature map describing each section (title, IDs, associated PHP partials).
 * - Apache and PHP log panels:
 *   - Render a toggleable section with aria attributes.
 *   - Include a <pre><code> container initialised with a "Loading..." placeholder.
 * - System stats panel:
 *   - If AJAX is enabled, prints placeholder stats fields (CPU, RAM, Disk) for JS to update.
 *   - Otherwise includes the system_stats.php partial.
 *
 * Accessibility:
 * - Regions use role="region" and aria-labelledby.
 * - Live content uses aria-live="polite" for nonintrusive updates.
 *
 * @var bool $displayApacheErrorLog
 * @var bool $displayPhpErrorLog
 * @var bool $displaySystemStats
 * @var bool $useAjaxForStats
 *
 * @author  Pawel Osmolski
 * @version 1.2
 */

require_once __DIR__ . '/../config/config.php';

$features = [
	'apache' => [
		'enabled'     => $displayApacheErrorLog,
		'title'       => 'Apache Error Log',
		'toggle_id'   => 'toggle-apache-error-log',
		'log_id'      => 'apache-error-log',
		'php_include' => '/../utils/apache_error_log.php',
	],
	'php'    => [
		'enabled'     => $displayPhpErrorLog,
		'title'       => 'PHP Error Log',
		'toggle_id'   => 'toggle-php-error-log',
		'log_id'      => 'php-error-log',
		'php_include' => '/../utils/php_error_log.php',
	],
	'stats'  => [
		'enabled'     => $displaySystemStats,
		'title'       => 'System Stats',
		'php_include' => '/../utils/system_stats.php',
	],
];

$icons = [
	'apache' => __DIR__ . '/../assets/images/Apache.svg',
	'php'    => __DIR__ . '/../assets/images/PHP.svg',
];

if ( array_filter( array_column( $features, 'enabled' ) ) ): ?>

	<?php foreach ( $features as $key => $feature ):
		if ( ! $feature['enabled'] ) {
			continue;
		}

		$id       = $key === 'stats' ? 'system-monitor' : htmlspecialchars( $feature['log_id'] . '-section' );
		$class    = $key === 'stats' ? 'system-monitor' : 'error-log-section';
		$titleId  = $key === 'stats' ? 'system-monitor-title' : htmlspecialchars( $feature['log_id'] . '-title' );
		$regionId = $key === 'stats' ? 'system-monitor' : $feature['log_id'];
		?>

		<?php if ( $key === 'stats' ): ?>
			<?php if ( $useAjaxForStats ): ?>
				<div id="<?= $id ?>" class="<?= $class ?>" role="region" aria-labelledby="<?= $titleId ?>">
					<h3 id="<?= $titleId ?>"><?= htmlspecialchars( $feature['title'] ) ?></h3>
					<p>CPU Load: <span id="cpu-load" aria-live="polite">N/A</span></p>
					<p>RAM Usage: <span id="memory-usage" aria-live="polite">N/A</span></p>
					<p>Disk Space: <span id="disk-space" aria-live="polite">N/A</span></p>
				</div>
			<?php else: ?>
				<section id="<?= $id ?>" class="<?= $class ?>" role="region" aria-labelledby="<?= $titleId ?>">
					<?php include __DIR__ . $feature['php_include']; ?>
				</section>
			<?php endif; ?>
		<?php else: ?>
			<section id="<?= $id ?>" class="<?= $class ?>" aria-labelledby="<?= $titleId ?>">
				<h3 id="<?= $titleId ?>">
					<button id="<?= htmlspecialchars( $feature['toggle_id'] ) ?>" aria-expanded="false"
					        aria-controls="<?= htmlspecialchars( $regionId ) ?>">
						<?php if ( isset( $icons[ $key ] ) ) {
							include $icons[ $key ];
						} ?>
						Toggle <?= htmlspecialchars( $feature['title'] ) ?>
					</button>
				</h3>
				<pre id="<?= htmlspecialchars( $regionId ) ?>" aria-live="polite"
				     tabindex="0"><code>Loading...</code></pre>
			</section>
		<?php endif; ?>

	<?php endforeach; ?>

<?php endif; ?>
