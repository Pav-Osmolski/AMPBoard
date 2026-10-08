<?php
namespace AMPBoard\Ui;

/** Historical display masking, with an explicit mode rather than a runtime constant. */
final class DemoMask {
	private bool $enabled;
	public function __construct( bool $enabled ) { $this->enabled = $enabled; }
	public function value( string $value ): string {
		if ( ! $this->enabled ) { return $value; }
		$length = strlen( $value );
		return $length <= 4 ? '****' : ( $length <= 12 ? '************' : '****************' );
	}
}
