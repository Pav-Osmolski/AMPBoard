<?php
/**
 * Document Folders Viewer
 *
 * Renders prepared folder columns from the loaded profile snapshot.
 * Each column in the layout corresponds to a configured directory and can:
 * - Apply exclusion lists
 * - Transform URLs via regex
 * - Use a named link template from `link_templates.json`
 * - Support custom folder name replacements (`specialCases`)
 * - Disable links entirely if required
 *
 * Configuration is read from:
 * - The loaded folders and link-template snapshots via FolderPresenter.
 *
 * Output:
 * - HTML markup with columns and folder links
 * - Error or warning messages for invalid or empty directories
 *
 * @var \AMPBoard\Ui\FolderPresenter $folderPresenter
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 *
 * @author  Pawel Osmolski
 * @version 2.0
 */

require_once __DIR__ . '/../config/config.php';

$folderView = $folderPresenter->prepare();
$columnCounter = 0;
$globalErrors = $folderView['errors'];
$hasVhostFilteredColumns = $folderView['hasVhostFilteredColumns'];
?>

<?php if ( $folderView['empty'] !== null ) : ?>
	<div id="folders-view" class="visible" aria-labelledby="folders-view-heading">
		<div class="heading">
			<?= $ui->renderHeading( 'Document Folders', 'h2', true ) ?>
		</div>
		<div class="columns width-resizable max-md">
			<div class="column">
				<?php if ( $folderView['empty'] === 'both' ) : ?>
					<p>No folders or link templates configured yet. Pop over to <a href="?view=settings">Settings</a> to
						add your first folder column and link template.</p>
				<?php elseif ( $folderView['empty'] === 'folders' ) : ?>
					<p>No folders configured yet. Pop over to <a href="?view=settings">Settings</a> to add your first
						folder column.</p>
				<?php elseif ( $folderView['empty'] === 'templates' ) : ?>
					<p>No link templates configured yet. Pop over to <a href="?view=settings">Settings</a> to add your
						first link template.</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php else : ?>
	<div id="folders-view" class="visible">
		<?= $ui->renderWidthControls( 'width_columns', 'Column', 'column-controls' ); ?>
		<div class="heading">
			<?= $ui->renderHeading( 'Document Folders', 'h2', true ) ?>
		</div>
		<div class="columns width-resizable" role="list" data-width-key="width_columns">
			<?php foreach ( $folderView['columns'] as $column ): ?>
				<?php
				$title = $column['title'];
				$href = $column['href'];
				$disable = $column['disable'];
				$requireVhost = $column['requireVhost'];
				$dir = $column['dir'];
				?>
				<div class="column" id="<?php echo 'column_' . ( ++ $columnCounter ); ?>" role="listitem">
					<?= $ui->renderDragHandle( $title ); ?>
					<h3 class="<?= $requireVhost ? 'with-badges' : '' ?><?= $config['status']['apachePathValid'] ? ' valid-apache-path' : ' invalid-apache-path' ?>">
						<?php if ( $href !== '' ): ?>
							<a href="<?= htmlspecialchars( $href ) ?>"><?= htmlspecialchars( $title ) ?></a>
						<?php else: ?>
							<?= htmlspecialchars( $title ) ?>
						<?php endif; ?>

						<?php if ( $disable ): ?>
							<?= $ui->renderBadge(
								'default',
								'No Links',
								'This column only lists folders that contain no link entries.',
								'Column filtered to folders without links'
							); ?>
						<?php endif; ?>
						<?php if ( $requireVhost ): ?>
							<?= $ui->renderBadge(
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
						if ( $column['state'] === 'missing' ) {
							echo "<li class='invalid'><strong>Error:</strong> The directory <code>'" . htmlspecialchars( $dir ?: '(unset)' ) . "'</code> does not exist.</li>";
						} elseif ( $column['state'] === 'empty' ) {
							echo "<li class='empty'><strong>Warning:</strong> No projects found in <code>'" . htmlspecialchars( $dir ) . "'</code>.</li>";
						} else {
							foreach ( $column['items'] as $item ) { echo $item['html']; }
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
							<li><?= htmlspecialchars( $msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
