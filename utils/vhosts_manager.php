<?php
/**
 * Virtual Hosts Manager
 *
 * Parses the Apache `httpd-vhosts.conf` file to list all defined virtual hosts,
 * checks for associated SSL certificate files, and validates presence of each
 * ServerName entry in the system's hosts file.
 *
 * Output:
 * - HTML table with host info, SSL status, cert validation, and open-folder actions
 * - Dynamic filter UI for SSL, host file presence, and cert state
 *
 * Assumptions:
 * - Apache path is defined via `APACHE_PATH`
 * - Tooltips are provided via the `$tooltips` array
 * - Certificate files (CRT/KEY) are stored per-host in `APACHE_PATH/crt/{servername}/`
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @version 1.2
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

/** @var string[] $tooltips */
/** @var string $defaultTooltipMessage */

require_once __DIR__ . '/../config/config.php';

$pageClasses = buildPageViewClasses( $settingsView ?? null );
?>
<div id="vhosts-manager" class="<?= $pageClasses ?>">
	<?php if ( empty( $settingsView ) ): ?>
		<?php echo render_versioned_assets_with_base(); ?>

		<div class="heading">
			<?= renderHeadingTooltip( 'vhosts_manager', $tooltips, $defaultTooltipMessage, 'h2', 'Virtual Hosts Manager', false, false, true ) ?>
		</div>
	<?php endif; ?>

	<?php
	$vhostsPath = APACHE_PATH . '/conf/extra/httpd-vhosts.conf';

	if ( ! file_exists( $vhostsPath ) ) {
		echo '<p><strong>Warning:</strong> The <code>httpd-vhosts.conf</code> file was not found at <code>' .
		     obfuscate_value( htmlspecialchars( $vhostsPath ) ) .
		     '</code>. Please ensure your Apache setup is correct and virtual hosts are enabled.</p>';
	} else {
		$serverData = getVhostServerData();
		?>

		<div class="vhost-filters">
			<label>Filter:
				<select id="vhost-filter">
					<option value="all">All</option>
					<option value="missing-cert">Missing Cert</option>
					<option value="missing-host">Missing Host</option>
					<option value="ssl-only">SSL Only</option>
					<option value="non-ssl">Non-SSL</option>
				</select>
			</label>
		</div>

		<table id="vhosts-table">
			<thead>
			<tr>
				<th>Server Name</th>
				<th>Document Root</th>
				<th>Status</th>
				<th>SSL</th>
				<th>Cert</th>
				<th>Open</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $serverData as $host => $info ) :
				$isDuplicate = $info['_duplicate'] ?? false;
				$classes = [];
				if ( ! empty( $info['ssl'] ) ) {
					$classes[] = 'vhost-ssl';
				}
				if ( ! empty( $info['certValid'] ) ) {
					$classes[] = 'cert-valid';
				}
				if ( ! empty( $info['valid'] ) ) {
					$classes[] = 'host-valid';
				}
				if ( $isDuplicate ) {
					$classes[] = 'vhost-duplicate';
				}
				$classAttr = implode( ' ', $classes );
				$protocol  = ! empty( $info['ssl'] ) ? 'https' : 'http';

				$link = ! empty( $info['valid'] )
					? '<a href="' . $protocol . '://' . $host . '" target="_blank">' . htmlspecialchars( $host ) . '</a>'
					: htmlspecialchars( $host );

				if ( $isDuplicate ) {
					$link .= ' <span class="warning" title="Duplicate ServerName">⚠️</span>';
				}
				?>
				<tr class="<?= $classAttr ?>">
					<td data-label="Server Name"><?= $link ?></td>
					<td data-label="Document Root">
						<code><?= $info['docRoot'] !== '' ? htmlspecialchars( $info['docRoot'] ) : 'N/A' ?></code>
					</td>
					<td data-label="Status" class="status">
						<?= ! empty( $info['valid'] ) ? '<span class="tick">✔️</span>' : '<span class="cross">❌</span>' ?>
					</td>
					<td data-label="SSL">
						<?= ! empty( $info['ssl'] ) ? '<span class="lock">🔒</span>' : '<span class="empty">-</span>' ?>
					</td>
					<td data-label="Cert">
						<?php if ( ! empty( $info['ssl'] ) ) : ?>
							<?= ! empty( $info['certValid'] )
								? '<span class="tick">✔️</span>'
								: (
								( defined( 'DEMO_MODE' ) && DEMO_MODE )
									? '<span class="cross">❌</span>'
									: '<span class="cross">❌</span> <button data-generate-cert="' . htmlspecialchars( $host ) . '">Generate Cert</button>'
								)
							?>
						<?php else : ?>
							<span class="empty">-</span>
						<?php endif; ?>
					</td>
					<td data-label="Open">
						<?= $info['docRoot'] !== '' ? '<button class="open-folder" data-path="' . htmlspecialchars( $info['docRoot'] ) . '">📂</button>' : '<span class="empty">-</span>' ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div id="vhost-empty-msg" style="display: none; padding: 1em;">No matching entries found.</div>

		<?php
	}
	?>
</div>
