<?php
namespace AMPBoard\Folders;

/** Historical URL filtering, removal regex, and post-transform special cases. */
final class UrlRules {
	public function apply( string $folderName, array $column ): array {
		$errors = [];
		$urlName = $folderName;
		if ( isset( $column['urlRules'] ) && is_array( $column['urlRules'] ) ) {
			$match       = isset( $column['urlRules']['match'] ) ? (string) $column['urlRules']['match'] : '';
			$replace     = isset( $column['urlRules']['replace'] ) ? (string) $column['urlRules']['replace'] : '';
			$matchTrim   = trim( $match );
			$replaceTrim = trim( $replace );

			if ( $matchTrim === '' && $replaceTrim === '' ) {
				// no rule
			} elseif ( ( $matchTrim === '' ) !== ( $replaceTrim === '' ) ) {
				$errors[] = 'Both urlRules.match and urlRules.replace must be set (or both empty) for column "' . (string) ( $column['title'] ?? '' ) . '".';
			} else {
				$ok = $this->match( $matchTrim, '' );

				if ( $ok === false ) {
					$errors[] = 'Invalid regex in urlRules.match for column "' . (string) ( $column['title'] ?? '' ) . '".';
				} else {
					if ( $this->match( $matchTrim, $folderName ) ) {
						$newName = $this->remove( $replaceTrim, $folderName );
						if ( $newName === null ) {
							$errors[] = 'Invalid regex in urlRules.replace for column "' . (string) ( $column['title'] ?? '' ) . '".';
						} else {
							$urlName = $newName;
						}
					} else {
						return [ 'name' => null, 'errors' => $errors ];
					}
				}
			}
		}

		if ( ! empty( $column['specialCases'] ) && is_array( $column['specialCases'] ) ) {
			if ( array_key_exists( $urlName, $column['specialCases'] ) ) {
				$urlName = (string) $column['specialCases'][ $urlName ];
			}
		}

		return [ 'name' => $urlName === '__SKIP__' ? null : $urlName, 'errors' => $errors ];
	}

	private function match( string $pattern, string $subject ): int|false {
		set_error_handler( static function () { return true; }, E_WARNING );
		try { return preg_match( $pattern, $subject ); } finally { restore_error_handler(); }
	}
	private function remove( string $pattern, string $subject ): ?string {
		set_error_handler( static function () { return true; }, E_WARNING );
		try { return preg_replace( $pattern, '', $subject ); } finally { restore_error_handler(); }
	}
}
