<?php
namespace AMPBoard\Logs;

/** Existing platform candidate order, constructed without touching the filesystem. */
final class Paths {
	public static function apache( string $apachePath, string $os, string $home = '' ): array {
		$apachePath = rtrim( $apachePath, '/\\' );
		switch ( $os ) {
			case 'Windows':
				$possibleLogs = [
					$apachePath . '/logs/error.log',
					'C:\\Program Files\\Ampps\\apache/logs/error.log',
					'C:\\Program Files (x86)\\Ampps\\apache/logs/error.log'
				];

				break;
			case 'Darwin':
				$possibleLogs = [
					$apachePath . '/logs/error.log',
					'/Applications/MAMP/logs/apache_error.log',
					'/Applications/AMPPS/logs/apache_error.log',
					'/Library/Application Support/appsolute/MAMP PRO/logs/apache_error.log',
					'/opt/homebrew/var/log/httpd/error_log',
					'/usr/local/var/log/httpd/error_log',
					'/usr/local/var/log/apache2/error_log',
					'/home/linuxbrew/.linuxbrew/var/log/httpd/error_log',
					'/opt/lampp/logs/error_log'
				];

				break;
			default:
				$possibleLogs = [
					$apachePath . '/logs/error.log',
					'/var/log/apache2/error.log',
					'/var/log/httpd/error_log',
					'/opt/lampp/logs/error_log'
				];

				if ( $home !== '' ) {
					$possibleLogs[] = $home . '/snap/httpd/common/error.log';
				}


				break;
		}

		return $possibleLogs;
	}
}
