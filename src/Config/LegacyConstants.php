<?php

namespace AMPBoard\Config;

/** Transitional boundary for procedural platform helpers, outside the data services. */
final class LegacyConstants {
	public static function read(): array {
		$values = [];
		foreach ( ProfileSchema::CONSTANTS as $name ) {
			if ( defined( $name ) ) { $values[ $name ] = constant( $name ); }
		}
		return $values;
	}

	public static function publish( array $settings ): void {
		foreach ( ProfileSchema::CONSTANTS as $name ) {
			if ( ! defined( $name ) ) {
				$value = $settings[ $name ];
				define( $name, is_array( $value ) && isset( $value['encrypted'] ) ? $value['encrypted'] : $value );
			}
		}
	}
}
