<?php
/**
 * Template helpers
 *
 * build_url_name(), resolve_template_html(), render_item_html(), extract_template_hosts_for_url()
 *
 * @author  Pawel Osmolski
 * @version 1.1
 */

/**
 * Build the URL/display name for a folder using column rules and special cases.
 *
 * @param string $folderName The original folder name.
 * @param array<string,mixed> $column The column definition (may contain urlRules and specialCases).
 * @param array<int,string> $errors Reference to an array that accumulates human-readable errors.
 *
 * @return string The transformed name, or "__SKIP__" sentinel if excluded.
 */
function build_url_name( $folderName, array $column, array &$errors ): string {
	$urlName = $folderName;
	if ( isset( $column['urlRules'] ) && is_array( $column['urlRules'] ) ) {
		$match       = isset( $column['urlRules']['match'] ) ? (string) $column['urlRules']['match'] : '';
		$replace     = isset( $column['urlRules']['replace'] ) ? (string) $column['urlRules']['replace'] : '';
		$matchTrim   = trim( $match );
		$replaceTrim = trim( $replace );

		if ( $matchTrim === '' && $replaceTrim === '' ) {
			// no rule
		} elseif ( ( $matchTrim === '' ) !== ( $replaceTrim === '' ) ) {
			$errors[] = 'Both urlRules.match and urlRules.replace must be set (or both empty) for column "' . htmlspecialchars( (string) ( $column['title'] ?? '' ) ) . '".';
		} else {
			set_error_handler( function () {
			}, E_WARNING );
			$ok = @preg_match( $matchTrim, '' );
			restore_error_handler();

			if ( $ok === false ) {
				$errors[] = 'Invalid regex in urlRules.match for column "' . htmlspecialchars( (string) ( $column['title'] ?? '' ) ) . '".';
			} else {
				if ( preg_match( $matchTrim, $folderName ) ) {
					$newName = @preg_replace( $replaceTrim, '', $folderName );
					if ( $newName === null ) {
						$errors[] = 'Invalid regex in urlRules.replace for column "' . htmlspecialchars( (string) ( $column['title'] ?? '' ) ) . '".';
					} else {
						$urlName = $newName;
					}
				} else {
					return '__SKIP__';
				}
			}
		}
	}

	if ( ! empty( $column['specialCases'] ) && is_array( $column['specialCases'] ) ) {
		if ( array_key_exists( $urlName, $column['specialCases'] ) ) {
			$urlName = (string) $column['specialCases'][ $urlName ];
		}
	}

	return $urlName;
}

/**
 * Resolve a template by name to HTML.
 *
 * @param string $templateName
 * @param array<string, array<string,mixed>> $templatesByName
 *
 * @return string
 */
function resolve_template_html( $templateName, array $templatesByName ): string {
	if ( isset( $templatesByName[ $templateName ]['html'] ) ) {
		return (string) $templatesByName[ $templateName ]['html'];
	}
	if ( isset( $templatesByName['basic']['html'] ) ) {
		return (string) $templatesByName['basic']['html'];
	}

	return '<li><a href="/{urlName}">{urlName}</a></li>';
}

/**
 * Render one list item from a template, substituting placeholders safely.
 *
 * @param string $templateHtml
 * @param string $urlName
 * @param bool $disableLinks
 *
 * @return string
 */
function render_item_html( $templateHtml, $urlName, $disableLinks ): string {
	$safe = htmlspecialchars( $urlName, ENT_QUOTES, 'UTF-8' );
	$html = str_replace( '{urlName}', $safe, $templateHtml );
	if ( $disableLinks ) {
		$html = strip_tags( $html, '<li><div><span>' );
	}

	return $html;
}

/**
 * Extract all hostnames used in href attributes for a given urlName
 * and link template HTML.
 *
 * This allows consumer code (e.g. folders.php) to cross-check the
 * hosts against valid vhost entries.
 *
 * @param string $templateHtml The raw link template HTML with {urlName} placeholder.
 * @param string $urlName The resolved URL name for the folder item.
 *
 * @return array<int,string> A unique, lowercased list of hostnames.
 */
function extract_template_hosts_for_url( string $templateHtml, string $urlName ): array {
	$rendered = str_replace( '{urlName}', $urlName, $templateHtml );
	$hosts    = [];

	if ( preg_match_all( '/href\s*=\s*[\'"]([^\'"]+)[\'"]/i', $rendered, $matches ) ) {
		foreach ( $matches[1] as $href ) {
			$href   = (string) $href;
			$parsed = parse_url( $href );
			if ( ! is_array( $parsed ) ) {
				continue;
			}
			if ( isset( $parsed['host'] ) && $parsed['host'] !== '' ) {
				$host = strtolower( trim( (string) $parsed['host'] ) );
				if ( $host !== '' ) {
					$hosts[] = $host;
				}
			}
		}
	}

	return array_values( array_unique( $hosts ) );
}
