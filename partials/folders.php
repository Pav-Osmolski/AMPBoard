<?php
/**
 * Document Folders Viewer
 *
 * Dynamically generates a folder listing UI based on a JSON configuration file.
 * Each column in the layout corresponds to a configured directory and can:
 * - Apply exclusion lists
 * - Transform URLs via regex
 * - Use a named link template from `link_templates.json`
 * - Support custom folder name replacements (`specialCases`)
 * - Disable links entirely if required
 *
 * Configuration is read from:
 * - `/config/folders.json`
 * - `/config/link_templates.json`
 *
 * Output:
 * - HTML markup with columns and folder links
 * - Error or warning messages for invalid or empty directories
 *
 * @var string[] $tooltips
 * @var string $defaultTooltipMessage
 * @var bool $apachePathValid
 * @var array $linkTemplatesConfig
 * @var array<string, mixed> $config
 *
 * @author  Pawel Osmolski
 * @version 1.9
 */

require_once __DIR__ . '/../config/config.php';

// Index templates by name for fast lookup
$templatesByName = [];
foreach ( $config['profile']['linkTemplates'] as $tpl ) {
	if ( is_array( $tpl ) && isset( $tpl['name'] ) ) {
		$templatesByName[ (string) $tpl['name'] ] = $tpl;
	}
}

// Load hamburger icon, prefixing IDs to avoid collisions
$hamburgerSvgPath = $config['paths']['assets'] . '/images/hamburger.svg';
$hamburgerSvg     = is_file( $hamburgerSvgPath )
	? injectSvgWithUniqueIds( $hamburgerSvgPath, 'drag-' . bin2hex( random_bytes( 3 ) ) )
	: '';

$columnCounter           = 0;
$globalErrors            = [];
$hasVhostFilteredColumns = false;
?>

<?php if ( empty( $config['profile']['folders'] ) || empty( $templatesByName ) ) : ?>
	<div id="folders-view" class="visible" aria-labelledby="folders-view-heading">
		<div class="heading">
			<?= renderHeading( 'Document Folders', 'h2', true ) ?>
		</div>
		<div class="columns width-resizable max-md">
			<div class="column">
				<?php if ( empty( $config['profile']['folders'] ) && empty( $templatesByName ) ) : ?>
					<p>No folders or link templates configured yet. Pop over to <a href="?view=settings">Settings</a> to
						add your first folder column and link template.</p>
				<?php elseif ( empty( $config['profile']['folders'] ) ) : ?>
					<p>No folders configured yet. Pop over to <a href="?view=settings">Settings</a> to add your first
						folder column.</p>
				<?php elseif ( empty( $templatesByName ) ) : ?>
					<p>No link templates configured yet. Pop over to <a href="?view=settings">Settings</a> to add your
						first link template.</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php else : ?>
	<div id="folders-view" class="visible">
		<?= renderWidthControls( 'width_columns', 'Column', 'column-controls' ); ?>
		<div class="heading">
			<?= renderHeading( 'Document Folders', 'h2', true ) ?>
		</div>
		<div class="columns width-resizable" role="list" data-width-key="width_columns">
			<?php foreach ( $config['profile']['folders'] as $column ): ?>
				<?php
				if ( ! is_array( $column ) ) {
					$globalErrors[] = 'Column configuration must be an object.';
					continue;
				}

				$title        = isset( $column['title'] ) ? (string) $column['title'] : 'Untitled';
				$href         = isset( $column['href'] ) ? (string) $column['href'] : '';
				$template     = isset( $column['linkTemplate'] ) ? (string) $column['linkTemplate'] : 'basic';
				$excludeList  = isset( $column['excludeList'] ) && is_array( $column['excludeList'] ) ? $column['excludeList'] : [];
				$disable      = ! empty( $column['disableLinks'] );
				$requireVhost = ! empty( $column['requireVhost'] );

				if ( $requireVhost ) {
					$hasVhostFilteredColumns = true;
				}

				$norm = normalise_subdir( $column['dir'] ?? '' );
				$dir  = $norm['dir'];
				if ( $norm['error'] ) {
					$globalErrors[] = $norm['error'] . ' (Column: ' . htmlspecialchars( $title ) . ')';
				}

				$folders = $dir ? list_subdirs( $dir ) : [];
				?>
				<div class="column" id="<?php echo 'column_' . ( ++ $columnCounter ); ?>" role="listitem">
					<button class="drag-handle reset" aria-label="Reorder column <?= htmlspecialchars( $title ) ?>"
					        aria-describedby="drag-help" data-drag-allow><?php echo $hamburgerSvg; ?></button>
					<h3 class="<?= $requireVhost ? 'with-badges' : '' ?><?= $config['status']['apachePathValid'] ? ' valid-apache-path' : ' invalid-apache-path' ?>">
						<?php if ( $href !== '' ): ?>
							<a href="<?= htmlspecialchars( $href ) ?>"><?= htmlspecialchars( $title ) ?></a>
						<?php else: ?>
							<?= htmlspecialchars( $title ) ?>
						<?php endif; ?>

						<?php if ( $disable ): ?>
							<?= renderBadge(
								'default',
								'No Links',
								'This column only lists folders that contain no link entries.',
								'Column filtered to folders without links'
							); ?>
						<?php endif; ?>
						<?php if ( $requireVhost ): ?>
							<?= renderBadge(
								'vhost',
								'vHost',
								'This column only lists folders with valid Apache vHosts.',
								'Column filtered by valid Apache vHost configuration',
								$config['status']['apachePathValid']
							); ?>
						<?php endif; ?>
					</h3>
					<ul>
						<?php
						if ( ! $dir || ! is_dir( $dir ) ) {
							echo "<li class='invalid'><strong>Error:</strong> The directory <code>'" . htmlspecialchars( $dir ?: '(unset)' ) . "'</code> does not exist.</li>";
						} elseif ( empty( $folders ) ) {
							echo "<li class='empty'><strong>Warning:</strong> No projects found in <code>'" . htmlspecialchars( $dir ) . "'</code>.</li>";
						} else {
							$templateHtml = resolve_template_html( $template, $templatesByName );

							foreach ( $folders as $folderName ) {
								if ( in_array( $folderName, $excludeList, true ) ) {
									continue;
								}

								$errors  = [];
								$urlName = build_url_name( $folderName, $column, $errors );

								if ( $urlName === '__SKIP__' ) {
									continue;
								}

								foreach ( $errors as $e ) {
									$globalErrors[] = $e;
								}

								// If this column is configured to only show entries with a valid vhost,
								// check the hosts used in the template for this urlName against the
								// parsed vhost + hosts data.
								if ( $requireVhost ) {
									$hostsForItem = extract_template_hosts_for_url( $templateHtml, $urlName );
									$hasValidHost = false;

									foreach ( $hostsForItem as $host ) {
										if ( isValidVhostHost( $host ) ) {
											$hasValidHost = true;
											break;
										}
									}

									// No matching vhost-backed host? Skip this item.
									if ( ! $hasValidHost ) {
										continue;
									}
								}

								echo render_item_html( $templateHtml, $urlName, $disable );
							}
						}
						?>
					</ul>
				</div>
			<?php endforeach; ?>
			<p id="drag-help" class="sr-only">Drag the handle to reorder columns.</p>
		</div>

		<?php if ( ! empty( $globalErrors ) ): ?>
			<div class="columns width-resizable max-fc">
				<div class="column warnings max-md">
					<h4>Warnings</h4>
					<ul>
						<?php foreach ( array_unique( $globalErrors ) as $msg ): ?>
							<li><?= $msg ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
