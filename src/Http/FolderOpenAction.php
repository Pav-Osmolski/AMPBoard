<?php
namespace AMPBoard\Http;

use AMPBoard\Filesystem\FolderOpener;

/** Existing JSON request/response policy, with explicit input and no runtime globals. */
final class FolderOpenAction {
	private FolderOpener $opener;
	public function __construct( FolderOpener $opener ) { $this->opener = $opener; }
	/** @return array{status:int,body:array} */
	public function handle( string $method, string $body ): array {
		if ( $method !== 'POST' ) { return $this->error( 405, 'Invalid request method.' ); }
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['path'] ) || ! is_string( $data['path'] ) ) {
			return $this->error( 400, 'Invalid or missing folder path.' );
		}
		try { $this->opener->open( $data['path'] ); }
		catch ( \InvalidArgumentException $error ) { return $this->error( 400, 'Invalid or missing folder path.' ); }
		catch ( \RuntimeException $error ) { return $this->error( 500, $error->getMessage() ); }
		catch ( \Throwable $error ) { return $this->error( 500, 'Failed to open folder.' ); }
		return [ 'status' => 200, 'body' => [ 'success' => true, 'message' => 'Opened: ' . $data['path'] ] ];
	}
	private function error( int $status, string $message ): array { return [ 'status' => $status, 'body' => [ 'error' => $message ] ]; }
}
