<?php
namespace AMPBoard\Logs;

/** Chooses the first existing file from supplied candidates; discovery does not read globals. */
final class Viewer {
	private array $paths;
	private TailReader $reader;
	private string $label;
	private int $lines;
	private bool $compact;
	public function __construct( array $paths, TailReader $reader, string $label, int $lines, bool $compact = false ) {
		$this->paths = $paths; $this->reader = $reader; $this->label = $label; $this->lines = $lines; $this->compact = $compact;
	}
	public function content(): string {
		foreach ( $this->paths as $path ) {
			if ( $path !== '' && is_file( $path ) ) {
				return $this->reader->read( $path, $this->lines, $this->compact ) ?? $this->label . ' error log could not be read.';
			}
		}
		return $this->label . ' error log not found or not configured.';
	}
}
