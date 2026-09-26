<?php
namespace AMPBoard\Php;

use Closure;

/** Captures phpinfo output; removes its layout, not its diagnostic data. */
final class InfoPage {
	private Closure $output;
	public function __construct( ?callable $output = null ) {
		$this->output = Closure::fromCallable( $output ?? static function ( int $flags ): void { phpinfo( $flags ); } );
	}
	public function render( bool $demo ): string {
		ob_start();
		try {
			($this->output)( $demo ? INFO_GENERAL | INFO_CREDITS | INFO_LICENSE : INFO_ALL );
			$info = ob_get_contents();
		} finally { ob_end_clean(); }
		$info = preg_replace( '%^.*<body>(.*)</body>.*$%s', '$1', $info );
		$info = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $info );
		return preg_replace( '/style=("|\')(.*?)("|\')/i', '', $info );
	}
}
