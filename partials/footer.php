<?php
/**
 * AMPBoard Footer
 *
 * @author  Pawel Osmolski
 * @version 1.4
 */
?>
<footer>
	<?= renderCollapseToggle( 'footer' ); ?>
	<div class="footer-wrapper">
		<nav class="footer-nav" aria-label="Footer shortcuts">
			<ul class="footer-links">
				<li>
					<a href="#"
					   id="toggle-index"
					   role="button"
					   aria-expanded="false"
					   aria-controls="index-panel">
						Folders
					</a>
				</li>
				<li>
					<a href="#"
					   id="toggle-settings"
					   role="button"
					   aria-expanded="false"
					   aria-controls="settings-panel">
						Settings
					</a>
				</li>
				<li>
					<a href="/phpmyadmin/"
					   target="_blank"
					   rel="noopener"
					   aria-label="PHPMyAdmin (opens in a new tab)">
						PHPMyAdmin
					</a>
				</li>
			</ul>
		</nav>
	</div>

	<div class="footer-text">
		<figure>
			<blockquote>
				<p><span class="quote">“</span>It’s not a bug. It’s an undocumented feature!<span class="quote">”</span>
				</p>
			</blockquote>
		</figure>
	</div>
</footer>
