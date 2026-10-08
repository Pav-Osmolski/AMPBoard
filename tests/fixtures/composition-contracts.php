<?php
/** Public dependencies each entry makes available; diagnostics are intentionally eager. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
return [
	'apache-control' => [ 'apacheCommands', 'apacheControl' ],
	'apache-inspector' => [ 'ui', 'demoMask', 'apacheCommands' ],
	'apache-log' => [ 'apacheLog' ],
	'certificates' => [ 'apacheCommands', 'certificates' ],
	'dashboard' => [ 'requestOrigin', 'session', 'csrfTokens', 'ui', 'demoMask', 'database' ],
	'exports' => [ 'database', 'requestOrigin', 'session', 'csrfTokens', 'ui', 'demoMask', 'exportFolders', 'exportDatabase', 'exports' ],
	'folders' => [ 'ui', 'demoMask', 'vhosts', 'directories', 'folderPresenter' ],
	'mysql' => [ 'ui', 'demoMask', 'database', 'mysqlInspector' ],
	'php-info' => [ 'ui', 'demoMask', 'phpInfo' ],
	'php-log' => [ 'phpLog' ],
	'read-config' => [ 'requestOrigin' ],
	'server' => [ 'ui', 'demoMask', 'database', 'apacheCommands', 'serverInspector' ],
	'settings' => [ 'requestOrigin', 'session', 'csrfTokens', 'ui', 'demoMask', 'phpIni', 'database' ],
	'statistics' => [ 'apacheCommands', 'systemStatistics' ],
	'submit' => [ 'requestOrigin', 'session', 'csrfTokens', 'phpIni' ],
	'ui' => [ 'ui', 'demoMask' ],
	'vhosts' => [ 'ui', 'demoMask', 'vhosts' ],
];
