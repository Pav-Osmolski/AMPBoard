<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( ! defined( 'MYSQLI_NUM' ) ) { define( 'MYSQLI_NUM', 2 ); }
if ( ! defined( 'MYSQLI_USE_RESULT' ) ) { define( 'MYSQLI_USE_RESULT', 1 ); }
final class ExportRows {
	private array $rows;
	public function __construct( array $rows ) { $this->rows = $rows; }
	public function fetch_row() { return array_shift( $this->rows ); }
	public function fetch_array( $mode ) { return $this->fetch_row(); }
	public function fetch_assoc() { return $this->fetch_row(); }
	public function free(): void {}
}
final class ExportConnection {
	public bool $closed = false;
	public string $error = 'fixture query failure';
	public string $fail = '';
	public function query( string $sql, $mode = null ) {
		if ( $this->fail !== '' && str_contains( $sql, $this->fail ) ) { return false; }
		if ( $sql === 'SHOW DATABASES' ) { return new ExportRows( [ [ 'mysql' ], [ 'site10' ], [ 'site2' ], [ 'sys' ] ] ); }
		if ( str_starts_with( $sql, 'SHOW FULL TABLES' ) ) { return new ExportRows( [ [ 'odd`table' ] ] ); }
		if ( str_starts_with( $sql, 'SHOW CREATE' ) ) { return new ExportRows( [ [ 'odd`table', 'CREATE TABLE `odd``table` (`value` text, `empty` text)' ] ] ); }
		if ( str_starts_with( $sql, 'SHOW COLUMNS' ) ) { return new ExportRows( [ [ 'Field' => 'value' ], [ 'Field' => 'empty' ] ] ); }
		if ( str_starts_with( $sql, 'SELECT' ) ) { return new ExportRows( array_fill( 0, 201, [ 'value' => "quote'\\value", 'empty' => null ] ) ); }
		throw new RuntimeException( 'Unexpected query: ' . $sql );
	}
	public function real_escape_string( string $value ): string { return addslashes( $value ); }
	public function close(): void { $this->closed = true; }
}
