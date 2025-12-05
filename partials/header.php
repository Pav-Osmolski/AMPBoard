<?php
/**
 * AMPBoard Header
 *
 * @var string $user
 * @var string $dbUser
 * @var string $dbPass
 * @var bool $displayClock
 * @var bool $displaySearch
 * @var array<string, mixed> $config
 *
 * @author  Pawel Osmolski
 * @version 1.4
 */

require_once __DIR__ . '/../config/config.php';
?>
<header role="banner">
	<?= renderCollapseToggle( 'header' ); ?>
	<h1>
		<span><?php echo getServerLabel(); ?> is ready, <?php echo htmlspecialchars( $config['user']['name'], ENT_QUOTES, 'UTF-8' ) ?>! <img
					src="./assets/favicon/AMPBoard.png" alt="AMPBoard Logo" aria-hidden="true"></span></h1>
	<?= $config['ui']['flags']['search'] ? '<input type="text" class="search-bar" placeholder="Search projects..." aria-label="Search projects">' : '' ?>
	<?= $config['ui']['flags']['clock'] ? '<div class="clock" aria-live="polite"></div>' : '' ?>
	<div class="server-info" role="status" aria-label="Server environment information">
		<?php renderServerInfo( $config['db']['user'], $config['db']['pass'] ); ?>
	</div>
</header>
