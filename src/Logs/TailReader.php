<?php
namespace AMPBoard\Logs;

/** Reads backwards in blocks, with a fixed maximum excerpt size. Never writes a log. */
class TailReader {
	private int $maxBytes;
	public function __construct( int $maxBytes = 1048576 ) {
		if ( $maxBytes < 1 ) { throw new \InvalidArgumentException( 'Positive excerpt limit required.' ); }
		$this->maxBytes = $maxBytes;
	}
	protected function open( string $path ) { return @fopen( $path, 'rb' ); }
	/** Null means unavailable. Compact mode preserves the historical PHP blank-line policy. */
	public function read( string $path, int $lines, bool $compact = false ): ?string {
		if ( $lines < 1 ) { throw new \InvalidArgumentException( 'Positive line count required.' ); }
		$stream = null;
		try {
			if ( ! is_file( $path ) ) { return null; }
			$stream = $this->open( $path );
			if ( ! is_resource( $stream ) ) { return null; }
			$stat = fstat( $stream );
			if ( $stat === false || ( $stat['mode'] & 0170000 ) !== 0100000 ) { return null; }
			$position = $stat['size']; $buffer = ''; $parts = [];
			while ( $position > 0 && strlen( $buffer ) < $this->maxBytes ) {
				$length = min( 8192, $position, $this->maxBytes - strlen( $buffer ) );
				$position -= $length;
				if ( fseek( $stream, $position ) !== 0 ) { return null; }
				$block = fread( $stream, $length );
				if ( $block === false || strlen( $block ) !== $length ) { return null; }
				$buffer = $block . $buffer;
				$parts = $this->parts( $buffer, $compact );
				if ( count( $parts ) > $lines ) { break; }
			}
			$truncated = $position > 0 && count( $parts ) <= $lines;
			if ( $truncated && strpos( $buffer, "\n" ) !== false ) {
				$parts = $this->parts( substr( $buffer, strpos( $buffer, "\n" ) + 1 ), $compact );
			}
			$parts = array_slice( $parts, -$lines );
			if ( $compact ) { $parts = array_filter( $parts, static function ( string $line ): bool { return trim( $line ) !== ''; } ); }
			return ( $truncated ? "[Log excerpt limited to the last {$this->maxBytes} bytes.]\n" : '' ) . implode( $compact ? "\n" : '', $parts );
		} catch ( \Throwable $error ) { return null; }
		finally { if ( is_resource( $stream ) ) { fclose( $stream ); } }
	}
	private function parts( string $content, bool $compact ): array {
		$parts = $compact ? explode( "\n", $content ) : preg_split( '/(?<=\n)/', $content );
		return array_values( array_filter( $parts, static function ( string $line ): bool { return $line !== ''; } ) );
	}
}
