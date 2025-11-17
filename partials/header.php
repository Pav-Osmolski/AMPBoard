<?php
/** @var string $user */
/** @var string $dbUser */
/** @var string $dbPass */
/** @var bool $displayClock */
/** @var bool $displaySearch */

require_once __DIR__ . '/../config/config.php';
?>
<header role="banner">
	<h1><span>server is ready, <?php echo htmlspecialchars( $user ); ?>! <img src="./assets/favicon/AMPBoard.png" alt="AMPBoard Logo" aria-hidden="true"></span></h1>
	<?= $displaySearch ? '<input type="text" class="search-bar" placeholder="Search projects..." aria-label="Search projects">' : '' ?>
	<?= $displayClock ? '<div class="clock" aria-live="polite"></div>' : '' ?>
	<div class="server-info" role="status" aria-label="Server environment information">
		<?php renderServerInfo( $dbUser, $dbPass ); ?>
	</div>
</header>
