<?php
/**
 * User Configuration Submit Handler (Hardened)
 *
 * Responsibilities:
 * - Validate request method, content type, origin, CSRF token
 * - Respect DEMO_MODE and bail out early without touching files
 * - Normalise and validate inputs from the settings form
 * - Encrypt sensitive values using encryptValue()
 * - Persist configuration atomically:
 *     - /config/user_config.php
 *     - /config/folders.json
 *     - /config/link_templates.json
 *     - /config/dock.json
 * - Optionally patch php.ini for display_errors and error_reporting
 * - Invalidate OPcache where applicable
 * - Redirect with 303 on success
 *
 * @var string $foldersJson
 * @var string $linkTplJson
 * @var string $dockJson
 *
 * @author  Pawel Osmolski
 * @version 2.9
 */

require_once __DIR__ . '/../config/config.php';

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
	// Safe no-op if included without a POST
	return;
}

/* ------------------------------------------------------------------ */
/* Basic request hardening                                            */
/* ------------------------------------------------------------------ */

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if ( stripos( $ct, 'application/x-www-form-urlencoded' ) !== 0
	&& stripos( $ct, 'multipart/form-data' ) !== 0 ) {
	submit_fail( 'Invalid content type: ' . $ct );
}

$len = (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 );
if ( $len <= 0 ) {
	submit_fail( 'Empty POST body.' );
}
if ( $len > 2 * 1024 * 1024 ) {
	submit_fail( 'POST too large.' );
}

if ( ! function_exists( 'request_is_same_origin' ) || ! request_is_same_origin() ) {
	submit_fail( 'Failed same-origin check.' );
}

$csrf = $_POST['csrf'] ?? null;
if ( ! function_exists( 'csrf_verify' ) || ! csrf_verify( is_string( $csrf ) ? $csrf : null ) ) {
	submit_fail( 'Invalid CSRF token.' );
}

/* ------------------------------------------------------------------ */
/* DEMO MODE guard                                                    */
/* ------------------------------------------------------------------ */

if ( defined( 'DEMO_MODE' ) && DEMO_MODE ) {
	header( 'Location: ?view=settings&saved=0', true, 303 );
	exit;
}

/* ------------------------------------------------------------------ */
/* Input collection and validation                                    */
/* ------------------------------------------------------------------ */

$defs = [
	'DB_HOST'               => FILTER_DEFAULT,
	'DB_USER'               => FILTER_DEFAULT,
	'DB_PASSWORD'           => FILTER_DEFAULT,

	'APACHE_PATH'           => FILTER_DEFAULT,
	'HTDOCS_PATH'           => FILTER_DEFAULT,
	'PHP_PATH'              => FILTER_DEFAULT,

	// UI flags
	'displayHeader'         => FILTER_DEFAULT,
	'displayFooter'         => FILTER_DEFAULT,
	'displayClock'          => FILTER_DEFAULT,
	'displaySearch'         => FILTER_DEFAULT,
	'displayTooltips'       => FILTER_DEFAULT,
	'displayFolderBadges'   => FILTER_DEFAULT,
	'displaySystemStats'    => FILTER_DEFAULT,
	'displayApacheErrorLog' => FILTER_DEFAULT,
	'displayPhpErrorLog'    => FILTER_DEFAULT,
	'useAjaxForStats'       => FILTER_DEFAULT,
	'useAjaxForErrorLog'    => FILTER_DEFAULT,

	// PHP error handling
	'displayPhpErrors'      => FILTER_DEFAULT,
	'logPhpErrors'          => FILTER_DEFAULT,
	'phpErrorLevel'         => FILTER_DEFAULT,

	// JSON blobs
	'folders_json'          => FILTER_UNSAFE_RAW,
	'link_templates_json'   => FILTER_UNSAFE_RAW,
	'dock_json'             => FILTER_UNSAFE_RAW,

	// php.ini path and error_reporting value (override-able)
	'php_ini_path'          => FILTER_DEFAULT,
	'error_reporting_value' => FILTER_DEFAULT,

	// Performance flags and theme
	'apacheFastMode'        => FILTER_DEFAULT,
	'mysqlFastMode'         => FILTER_DEFAULT,
	'theme'                 => FILTER_DEFAULT,
];

$in = filter_input_array( INPUT_POST, $defs, false );
if ( ! is_array( $in ) ) {
	submit_fail( 'Failed to parse inputs.' );
}

// Normalise paths (using helper if available)
if ( function_exists( 'normalise_path' ) ) {
	foreach ( [ 'APACHE_PATH', 'HTDOCS_PATH', 'PHP_PATH', 'php_ini_path' ] as $k ) {
		if ( isset( $in[ $k ] ) && is_string( $in[ $k ] ) ) {
			$in[ $k ] = normalise_path( $in[ $k ] );
		}
	}
}

// Normalise booleans for UI flags
$displayHeader         = normalise_bool( $in['displayHeader'] ?? null );
$displayFooter         = normalise_bool( $in['displayFooter'] ?? null );
$displayClock          = normalise_bool( $in['displayClock'] ?? null );
$displaySearch         = normalise_bool( $in['displaySearch'] ?? null );
$displayTooltips       = normalise_bool( $in['displayTooltips'] ?? null );
$displayFolderBadges   = normalise_bool( $in['displayFolderBadges'] ?? null );
$displaySystemStats    = normalise_bool( $in['displaySystemStats'] ?? null );
$displayApacheErrorLog = normalise_bool( $in['displayApacheErrorLog'] ?? null );
$displayPhpErrorLog    = normalise_bool( $in['displayPhpErrorLog'] ?? null );
$useAjaxForStats       = normalise_bool( $in['useAjaxForStats'] ?? null );
$useAjaxForErrorLog    = normalise_bool( $in['useAjaxForErrorLog'] ?? null );

$displayPhpErrors      = normalise_bool( $in['displayPhpErrors'] ?? null );
$logPhpErrors          = normalise_bool( $in['logPhpErrors'] ?? null );

// Performance flags
$apacheFastMode        = normalise_bool( $in['apacheFastMode'] ?? null );
$mysqlFastMode         = normalise_bool( $in['mysqlFastMode'] ?? null );

// Theme: letters, numbers, dashes, underscores. Fallback to default.
$theme = 'default';
if ( isset( $in['theme'] ) && is_string( $in['theme'] ) ) {
	$t = trim( $in['theme'] );
	if ( $t !== '' && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $t ) ) {
		$theme = $t;
	}
}

/* ------------------------------------------------------------------ */
/* PHP error level handling (names or numeric)                        */
/* ------------------------------------------------------------------ */

// Accept names (E_ALL, E_ERROR, E_WARNING, E_NOTICE) or numeric values.
$allowedNames = [ 'E_ALL', 'E_ERROR', 'E_WARNING', 'E_NOTICE' ];
$allowedInts  = [ E_ALL, E_ERROR, E_WARNING, E_NOTICE ];

$phpErrorLevelExpr = 'E_ALL'; // default for error_reporting()
if ( isset( $in['phpErrorLevel'] ) ) {
	$val = $in['phpErrorLevel'];

	if ( is_string( $val ) ) {
		$val = trim( $val );
		if ( in_array( $val, $allowedNames, true ) ) {
			$phpErrorLevelExpr = $val;
		} elseif ( is_numeric( $val ) ) {
			$ival = (int) $val;
			if ( in_array( $ival, $allowedInts, true ) ) {
				$phpErrorLevelExpr = (string) $ival;
			}
		}
	} elseif ( is_int( $val ) && in_array( $val, $allowedInts, true ) ) {
		$phpErrorLevelExpr = (string) $val;
	}
}

/* ------------------------------------------------------------------ */
/* DB values and JSON payloads                                        */
/* ------------------------------------------------------------------ */

$DB_HOST = isset( $in['DB_HOST'] ) && is_string( $in['DB_HOST'] ) ? trim( $in['DB_HOST'] ) : '';
$DB_USER = isset( $in['DB_USER'] ) && is_string( $in['DB_USER'] ) ? trim( $in['DB_USER'] ) : '';
$DB_PASS = isset( $in['DB_PASSWORD'] ) && is_string( $in['DB_PASSWORD'] ) ? trim( $in['DB_PASSWORD'] ) : '';

if ( $DB_HOST !== '' && ! filter_var( $DB_HOST, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) && ! filter_var( $DB_HOST, FILTER_VALIDATE_IP ) ) {
	submit_fail( 'DB_HOST invalid.' );
}

$encUser = $DB_USER !== '' ? encryptValue( $DB_USER ) : null;
$encPass = $DB_PASS !== '' ? encryptValue( $DB_PASS ) : null;

// JSON
try {
	$foldersRaw   = (string) ( $in['folders_json'] ?? '' );
	$linkTplRaw   = (string) ( $in['link_templates_json'] ?? '' );
	$dockRaw      = (string) ( $in['dock_json'] ?? '' );

	$foldersJson  = validate_and_canonicalise_json( $foldersRaw );
	$linkTplJson  = validate_and_canonicalise_json( $linkTplRaw );
	$dockJson     = validate_and_canonicalise_json( $dockRaw );
} catch ( Throwable $e ) {
	submit_fail( 'Invalid JSON payload: ' . $e->getMessage() );
}

/* ------------------------------------------------------------------ */
/* Persist configuration                                              */
/* ------------------------------------------------------------------ */

$configDir = __DIR__ . '/../config';

// Build user_config.php
$user_config  = "<?php\n";
$user_config .= "/**\n * Auto-generated user configuration. Do not edit by hand.\n */\n\n";
if ( $DB_HOST !== '' ) {
	$user_config .= "if ( ! defined('DB_HOST') ) { define('DB_HOST', '" . addslashes( $DB_HOST ) . "'); }\n";
}
if ( $encUser !== null ) {
	$user_config .= "if ( ! defined('DB_USER') ) { define('DB_USER', '" . addslashes( $encUser ) . "'); }\n";
}
if ( $encPass !== null ) {
	$user_config .= "if ( ! defined('DB_PASSWORD') ) { define('DB_PASSWORD', '" . addslashes( $encPass ) . "'); }\n";
}

// Paths (only if provided)
foreach ( [ 'APACHE_PATH', 'HTDOCS_PATH', 'PHP_PATH' ] as $pkey ) {
    if ( isset( $in[ $pkey ] ) && is_string( $in[ $pkey ] ) && $in[ $pkey ] !== '' ) {
        $val = addslashes( $in[ $pkey ] );
        $user_config .= "if ( ! defined('{$pkey}') ) { define('{$pkey}', '{$val}'); }\n";
    }
}

// UI flags
$user_config .= "\$displayHeader = {$displayHeader};\n";
$user_config .= "\$displayFooter = {$displayFooter};\n";
$user_config .= "\$displayClock = {$displayClock};\n";
$user_config .= "\$displaySearch = {$displaySearch};\n";
$user_config .= "\$displayTooltips = {$displayTooltips};\n";
$user_config .= "\$displayFolderBadges = {$displayFolderBadges};\n";
$user_config .= "\$displaySystemStats = {$displaySystemStats};\n";
$user_config .= "\$displayApacheErrorLog = {$displayApacheErrorLog};\n";
$user_config .= "\$displayPhpErrorLog = {$displayPhpErrorLog};\n";
$user_config .= "\$useAjaxForStats = {$useAjaxForStats};\n";
$user_config .= "\$useAjaxForErrorLog = {$useAjaxForErrorLog};\n";

// Performance flags and theme
$user_config .= "\$apacheFastMode = {$apacheFastMode};\n";
$user_config .= "\$mysqlFastMode = {$mysqlFastMode};\n";
$user_config .= "\$theme = '" . addslashes( $theme ) . "';\n";

// PHP error handling
$user_config .= "ini_set('display_errors', {$displayPhpErrors});\n";
$user_config .= "error_reporting({$phpErrorLevelExpr});\n";
$user_config .= "ini_set('log_errors', {$logPhpErrors});\n";

atomic_write( $configDir . '/user_config.php', $user_config );

// JSON configs
atomic_write( $configDir . '/folders.json',        $foldersJson );
atomic_write( $configDir . '/link_templates.json', $linkTplJson );
atomic_write( $configDir . '/dock.json',           $dockJson );

/* ------------------------------------------------------------------ */
/* Optional php.ini patching                                          */
/* ------------------------------------------------------------------ */

// Default to the currently loaded php.ini if the form did not supply a path
$php_ini_path_input = isset( $in['php_ini_path'] ) && is_string( $in['php_ini_path'] ) ? trim( $in['php_ini_path'] ) : '';
$php_ini_path       = $php_ini_path_input !== '' ? $php_ini_path_input : ( php_ini_loaded_file() ?: '' );

// Normalise the fallback too, if helper exists
if ( function_exists( 'normalise_path' ) && $php_ini_path !== '' ) {
	$php_ini_path = normalise_path( $php_ini_path );
}

$error_reporting_value = isset( $in['error_reporting_value'] ) && is_string( $in['error_reporting_value'] ) ? trim( $in['error_reporting_value'] ) : '';
if ( $error_reporting_value === '' ) {
	$error_reporting_value = $phpErrorLevelExpr;
}

if ( $php_ini_path !== '' && is_file( $php_ini_path ) && is_readable( $php_ini_path ) && is_writable( $php_ini_path ) ) {
	$ini_content = file_get_contents( $php_ini_path );
	if ( $ini_content !== false ) {
		// Patch display_errors
		if ( preg_match( '/^\s*display_errors\s*=.*/mi', $ini_content ) ) {
			$ini_content = preg_replace( '/^\s*display_errors\s*=.*/mi', 'display_errors = ' . ( $displayPhpErrors === 'true' ? 'On' : 'Off' ), $ini_content );
		} else {
			$ini_content .= "\ndisplay_errors = " . ( $displayPhpErrors === 'true' ? 'On' : 'Off' );
		}

		// Patch error_reporting
		if ( preg_match( '/^\s*error_reporting\s*=.*/mi', $ini_content ) ) {
			$ini_content = preg_replace( '/^\s*error_reporting\s*=.*/mi', 'error_reporting = ' . $error_reporting_value, $ini_content );
		} else {
			$ini_content .= "\nerror_reporting = " . $error_reporting_value;
		}

		file_put_contents( $php_ini_path, $ini_content );
	}
}

/* ------------------------------------------------------------------ */
/* Finalisation                                                       */
/* ------------------------------------------------------------------ */

if ( function_exists( 'opcache_invalidate' ) ) {
	@opcache_invalidate( $configDir . '/user_config.php', true );
	@opcache_invalidate( $configDir . '/folders.json', true );
	@opcache_invalidate( $configDir . '/link_templates.json', true );
	@opcache_invalidate( $configDir . '/dock.json', true );
}

if ( session_status() !== PHP_SESSION_ACTIVE ) {
	session_start();
}
session_regenerate_id( true );

header( 'Location: ?view=settings&saved=1', true, 303 );
exit;
