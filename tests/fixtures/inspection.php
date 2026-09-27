<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }

class InspectionResult {
	private array $rows;
	public int $freed = 0;
	public bool $failFetch = false;
	public function __construct( array $rows ) { $this->rows = $rows; }
	public function fetch_assoc(): ?array {
		if ( $this->failFetch ) { throw new RuntimeException( 'Read interrupted' ); }
		return array_shift( $this->rows );
	}
	public function fetch_row(): ?array { $row = $this->fetch_assoc(); return $row === null ? null : array_values( $row ); }
	public function free(): void { ++$this->freed; }
}
class InspectionConnection {
	public string $connect_error = '';
	public string $server_info = '8.0 <server>';
	public string $host_info = 'local <host>';
	public int $closed = 0;
	public array $queries = [];
	public array $results = [];
	public array $failures = [];
	public array $data = [
		'SELECT USER()' => [ [ 'USER()' => 'alice@localhost' ] ],
		'SHOW DATABASES' => [ [ 'Database' => 'mysql' ], [ 'Database' => 'sys' ], [ 'Database' => 'information_schema' ], [ 'Database' => 'performance_schema' ], [ 'Database' => 'odd`<db>' ], [ 'Database' => 'empty' ] ],
		'SHOW TABLE STATUS FROM `odd``<db>`' => [ [ 'Data_length' => 1048576, 'Index_length' => 524288 ], [ 'Data_length' => null, 'Index_length' => null ] ],
		'SHOW TABLE STATUS FROM `empty`' => [],
		"SHOW VARIABLES LIKE 'version%'" => [ [ 'Variable_name' => 'version_comment', 'Value' => '<fixture>' ] ],
		"SHOW STATUS LIKE 'Uptime'" => [ [ 'Variable_name' => 'Uptime', 'Value' => '3661' ] ],
		'SHOW FULL PROCESSLIST' => [ [ 'Id' => 7, 'User' => 'alice', 'Host' => 'localhost', 'Info' => '<script>query</script>' ], [ 'Id' => 8, 'User' => null, 'Host' => null, 'Info' => null ] ],
	];
	public function query( string $sql ) {
		$this->queries[] = $sql;
		$failure = $this->failures[$sql] ?? '';
		if ( $failure === 'throw' ) { throw new RuntimeException( 'Permission denied <private detail>' ); }
		if ( $failure === 'warning' ) { trigger_error( 'Query warning', E_USER_WARNING ); return false; }
		if ( $failure === 'false' ) { return false; }
		if ( ! array_key_exists( $sql, $this->data ) ) { throw new LogicException( 'Unexpected SQL: ' . $sql ); }
		$result = new InspectionResult( $this->data[$sql] );
		$result->failFetch = $failure === 'fetch';
		$this->results[] = $result;
		return $result;
	}
	public function close(): void { ++$this->closed; }
}
