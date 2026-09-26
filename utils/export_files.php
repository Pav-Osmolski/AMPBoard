<?php
/**
 * Export Files & Database
 *
 * Behaviour:
 * - When included by index.php (already bootstrapped), just render the UI.
 * - When hit directly via AJAX, lightly self-bootstrap (security/config/helpers only).
 *
 * JSON endpoints:
 *   ?action=scan    → list exportable groups/subfolders
 *   ?action=dbs     → list databases
 *   POST action=zip → create archive for chosen subfolder (ZIP preferred, tar.gz fallback)
 *   POST action=dumpdb → dump a database to SQL and compress (ZIP preferred, tar.gz fallback)
 *   ?action=token   → return a fresh CSRF token (for rotating-tokens setups)
 *
 * @var \AMPBoard\Ui\Renderer $ui
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.5
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/../config/config.php';

$pageClasses = $ui->buildPageViewClasses( $settingsView ?? null );

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ( $action ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	try {
		if ( in_array( $action, [ 'zip', 'dumpdb' ], true ) ) {
			if ( $config['user']['isDemo'] ) { throw new RuntimeException( 'Demo mode: export disabled.' ); }
			$token = $_POST['csrf'] ?? '';
			if ( ! is_string( $token ) || ! csrf_verify( $token ) ) { throw new RuntimeException( 'Invalid CSRF token.' ); }
		}
		if ( $action === 'token' ) { $response = [ 'ok' => true, 'token' => csrf_get_token() ]; }
		elseif ( $action === 'scan' ) { $response = [ 'ok' => true, 'groups' => $exports->scan() ]; }
		elseif ( $action === 'dbs' ) {
			$dbs = $exports->databases();
			if ( $config['user']['isDemo'] ) { $dbs = array_map( 'obfuscate_value', $dbs ); }
			$response = [ 'ok' => true, 'databases' => $dbs ];
		} elseif ( $method === 'POST' && in_array( $action, [ 'zip', 'dumpdb' ], true ) ) {
			foreach ( [ 'group', 'folder', 'uploadsMode', 'engine', 'db' ] as $field ) {
				if ( isset( $_POST[$field] ) && ! is_scalar( $_POST[$field] ) ) { throw new InvalidArgumentException( 'Invalid export input.' ); }
			}
			@set_time_limit( 0 );
			$engine = ( $_POST['engine'] ?? '' ) === 'external' ? 'external' : 'php';
			$response = $action === 'zip'
				? $exports->files( (int) ( $_POST['group'] ?? -1 ), (string) ( $_POST['folder'] ?? '' ), (string) ( $_POST['uploadsMode'] ?? 'exclude' ), $engine )
				: $exports->dump( (string) ( $_POST['db'] ?? '' ), $engine );
		} else { throw new InvalidArgumentException( 'Unknown action.' ); }
	} catch ( Throwable $error ) { $response = [ 'ok' => false, 'error' => $error->getMessage() ]; }
	echo json_encode( $response );
	exit;
}
?>

<div id="export" class="<?= $pageClasses ?>">
	<?php if ( empty( $settingsView ) ): ?>
		<?= $ui->renderVersionedAssetsWithBase(); ?>

		<div class="heading">
			<?= $ui->renderHeading( 'Export Files & Database', 'h2', true ) ?>
		</div>
	<?php endif; ?>

	<?php if ( $config['status']['phpPathValid'] ) { ?>
		<div class="export-engine" role="group" aria-labelledby="export-engine-title">
			<h3 id="export-engine-title">Archive engine</h3>
			<p id="export-engine-help" class="description small">
				Choose your preferred archiver.<br>
				If using <strong>External 7-Zip / system archiver</strong>, ensure <code>7z</code>, <code>7za</code>,
				<code>7zz</code>, or <code>zip</code> is available on your system <code>PATH</code>.
			</p>
			<?php $ui->renderSeparatorLine( 'sm' ) ?>
			<fieldset class="radio-group" aria-describedby="export-engine-help">
				<legend>Archive engine</legend>
				<label>
					<input type="radio" name="archiveEngine" value="external" checked>
					External 7-Zip / system archiver
				</label>
				<label>
					<input type="radio" name="archiveEngine" value="php">
					PHP ZipArchive / Phar
				</label>
			</fieldset>
			<?php $ui->renderSeparatorLine( 'sm' ) ?>
		</div>
		<div class="export-grid">
			<!-- Files export -->
			<div class="export-card" role="region" aria-labelledby="export-files-title">
				<h3 id="export-files-title">Files</h3>
				<p id="export-files-help" class="description small">Select a group and subfolder to export as a
					compressed archive. WordPress
					uploads are excluded by
					default.</p>
				<?php $ui->renderSeparatorLine( 'sm' ) ?>
				<form id="export-files-form" method="post" aria-describedby="export-files-help export-files-status"
				      novalidate>
					<input type="hidden" name="csrf" value="<?= htmlspecialchars( csrf_get_token(), ENT_QUOTES ) ?>">
					<div class="row">
						<label for="export-group">Group:</label>
						<select
								id="export-group"
								name="group"
								required
								aria-required="true"
								aria-controls="export-folder"
						></select>
					</div>
					<div class="row" id="export-folder-row">
						<label for="export-folder">Subfolder:</label>
						<select
								id="export-folder"
								name="folder"
								required
								disabled
								aria-required="true"
						>
							<option value="">Select a subfolder…</option>
						</select>
					</div>
					<div class="row" id="uploads-mode-row" style="display:none">
						<fieldset class="radio-group" aria-describedby="uploads-mode-desc">
							<legend>Media:</legend>
							<p id="uploads-mode-desc" class="sr-only">Choose how WordPress uploads should be
								handled.</p>
							<label>
								<input type="radio" name="uploadsMode" value="exclude" checked>
								Exclude uploads
							</label>
							<label>
								<input type="radio" name="uploadsMode" value="include">
								Include uploads
							</label>
							<label>
								<input type="radio" name="uploadsMode" value="only">
								Export uploads only
							</label>
						</fieldset>
					</div>
					<?php $ui->renderSeparatorLine( 'sm' ) ?>
					<div>
						<?php $ui->renderButtonBlock( [
							'label'      => 'Create Archive',
							'class'      => 'button',
							'type'       => 'submit',
							'attributes' => [ 'aria-describedby' => 'export-files-submit-desc' ]
						], [ 'top' => false, 'bottom' => false, ] ); ?>
						<span id="export-files-submit-desc" class="sr-only">This will create a compressed archive based on your selections.</span>
						<small
								id="export-files-status"
								class="muted"
								role="status"
								aria-live="polite"
								aria-atomic="true"
						></small>
					</div>
					<br>
				</form>
			</div>

			<!-- Database export -->
			<div class="export-card" role="region" aria-labelledby="export-db-title">
				<h3 id="export-db-title">Database</h3>
				<p id="export-db-help" class="description small">Choose a database to dump into a compressed
					archive.</p>
				<?php $ui->renderSeparatorLine( 'sm' ) ?>
				<form id="export-db-form" method="post" aria-describedby="export-db-help export-db-status" novalidate>
					<input type="hidden" name="csrf" value="<?= htmlspecialchars( csrf_get_token(), ENT_QUOTES ) ?>">
					<div class="row">
						<label for="export-db">Database:</label>
						<select
								id="export-db"
								name="db"
								required
								aria-required="true"
						>
							<option value="">Loading…</option>
						</select>
					</div>
					<?php $ui->renderSeparatorLine( 'sm' ) ?>
					<div>
						<?php $ui->renderButtonBlock( [
							'label'      => 'Export Database',
							'class'      => 'button',
							'type'       => 'submit',
							'attributes' => [ 'aria-describedby' => 'export-db-submit-desc' ]
						], [ 'top' => false, 'bottom' => false, ] ); ?>
						<span id="export-db-submit-desc" class="sr-only">This will create a compressed dump of the selected database.</span>

						<small
								id="export-db-status"
								class="muted"
								role="status"
								aria-live="polite"
								aria-atomic="true"
						></small>
					</div>
					<?php $ui->renderSeparatorLine( 'sm' ) ?>
				</form>
			</div>
		</div>
	<?php } else { ?>
		<p role="alert"><strong>Warning:</strong> The <code>PHP Path</code> is not valid. Please ensure your PHP setup
			is
			correct.</p>
	<?php } ?>
</div>
