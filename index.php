<?php
/**
 * AMPBoard — Modern Localhost and Remote Dashboard for Apache, MySQL & PHP
 *
 * @var string $user
 * @var string $theme
 * @var bool $displayHeader
 * @var bool $displayFooter
 * @var bool $useAjaxForStats
 * @var bool $useAjaxForErrorLog
 * @var string $bodyClasses
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/config/bootstrap.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="<?php echo htmlspecialchars( $user ); ?>'s AMPBoard.">
	<meta name="robots" content="noindex, nofollow">
	<meta name="color-scheme" content="<?php echo htmlspecialchars( getThemeColorScheme( $theme ) ); ?>">
	<title><?php echo htmlspecialchars( $user ); ?>'s AMPBoard — Modern Localhost and Remote Dashboard for Apache, MySQL & PHP</title>
	<meta id="page-title-prefix" data-prefix="<?php echo htmlspecialchars( $user ); ?>'s AMPBoard — ">
	<link rel="icon" type="image/x-icon" href="assets/favicon/AMPBoard.ico">
	<link rel="icon" type="image/png" sizes="512x512" href="assets/favicon/AMPBoard.png">
	<link rel="apple-touch-icon" sizes="512x512" href="assets/favicon/AMPBoard.png">
	<link rel="stylesheet" type="text/css"
	      href="dist/css/style.min.css?v=<?= filemtime( 'dist/css/style.min.css' ); ?>">
	<script>
		window.BASE_URL = "<?= rtrim( dirname( $_SERVER['SCRIPT_NAME'] ), '/' ) ?>/";
	</script>
	<script src="dist/js/script.min.js?v=<?= filemtime( 'dist/js/script.min.js' ); ?>"></script>
</head>
<body<?= $useAjaxForStats ? ' data-ajax-stats-enabled="true"' : '' ?><?= $useAjaxForErrorLog ? ' data-ajax-error-log-enabled="true"' : '' ?> class="<?= $bodyClasses ?>">
<div class="container">
	<?php $displayHeader && require_once __DIR__ . '/partials/header.php'; ?>
	<main role="main">
		<section class="folders">
			<?php require_once __DIR__ . '/partials/folders.php'; ?>
			<?php require_once __DIR__ . '/partials/settings.php'; ?>
			<?php require_once __DIR__ . '/utils/phpinfo.php'; ?>
			<div id="apache-view"><?php /* Dynamically loads /utils/apache_inspector.php */ ?></div>
			<div id="mysql-view"><?php /* Dynamically loads /utils/mysql_inspector.php */ ?></div>
			<?php require_once __DIR__ . '/partials/dock.php'; ?>
		</section>
		<?php require_once __DIR__ . '/partials/info.php'; ?>
	</main>
	<?php $displayFooter && require_once __DIR__ . '/partials/footer.php'; ?>
</div>
</body>
</html>
