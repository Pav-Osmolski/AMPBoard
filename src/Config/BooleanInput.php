<?php
namespace AMPBoard\Config;

/** Preserve the strict, case-sensitive HTML form truth values. */
final class BooleanInput {
	public static function value( mixed $value ): bool {
		return in_array( $value, [ '1', 1, true, 'true', 'on', 'yes' ], true );
	}
}
