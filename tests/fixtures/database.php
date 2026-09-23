<?php
// A deterministic driver double. Tests run with php -n, without the mysqli extension.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( extension_loaded( 'mysqli' ) ) {
	throw new RuntimeException( 'Run the isolated suite with php -n tests/run.php.' );
}
define( 'MYSQLI_REPORT_OFF', 0 );
define( 'MYSQLI_REPORT_ERROR', 1 );
define( 'MYSQLI_REPORT_STRICT', 2 );
class mysqli_sql_exception extends Exception {}
class mysqli_driver {
	public static int $mode = 3;
	public function __get( string $name ) { return self::$mode; }
}
function mysqli_report( int $flags ): bool { mysqli_driver::$mode = $flags; return true; }
function mysqli_get_client_info(): string { return 'test-client'; }
class mysqli {
	public static array $connections = [];
	public static string $error = '';
	public static bool $failCharset = false;
	public int $connect_errno = 0;
	public string $connect_error = '';
	public string $server_info = '8.0.36';
	public string $charset = '';
	public ?string $database = null;
	public bool $closed = false;
	public function __construct( string $host, string $user, string $pass ) {
		self::$connections[] = [ $host, $user, $pass ];
		$this->connect_error = self::$error;
		$this->connect_errno = self::$error === '' ? 0 : 1;
		if ( $this->connect_errno && ( mysqli_driver::$mode & MYSQLI_REPORT_STRICT ) ) {
			throw new mysqli_sql_exception( self::$error );
		}
	}
	public function set_charset( string $charset ): void {
		if ( self::$failCharset ) { throw new mysqli_sql_exception( 'charset failed' ); }
		$this->charset = $charset;
	}
	public function select_db( string $database ): void { $this->database = $database; }
	public function close(): void { $this->closed = true; }
}
