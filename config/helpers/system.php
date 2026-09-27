<?php
require_once __DIR__ . '/../autoload.php';
/** Compatibility wrappers; application requests use the composed identity snapshot. */
function getServerLabel(): string { return \AMPBoard\System\Identity::label( $_SERVER ); }
function resolveCurrentUser(): string {
	return \AMPBoard\System\Identity::resolveUser( $_SERVER, static function () {
		return function_exists( 'safe_shell_exec' ) ? safe_shell_exec( 'whoami' ) : '';
	} );
}
function getLegacyOSFlags(): array {
	return [
		'isWindows' => strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN',
		'isLinux'   => strtoupper( substr( PHP_OS, 0, 5 ) ) === 'LINUX',
		'isMac'     => strtoupper( substr( PHP_OS, 0, 6 ) ) === 'DARWIN' || strtoupper( substr( PHP_OS, 0, 3 ) ) === 'MAC',
	];
}
