<?php
namespace AMPBoard\Folders;

/** Profile templates are trusted HTML; only substituted names are escaped. */
final class LinkTemplates {
	private array $templates = [];
	public function __construct( array $templates ) {
		foreach ( $templates as $template ) {
			if ( is_array( $template ) && isset( $template['name'] ) ) { $this->templates[(string) $template['name']] = $template; }
		}
	}
	public function hasTemplates(): bool { return $this->templates !== []; }
	public function html( string $name ): string { return self::resolve( $name, $this->templates ); }
	public static function resolve( string $templateName, array $templatesByName ): string {
		if ( isset( $templatesByName[ $templateName ]['html'] ) ) {
			return (string) $templatesByName[ $templateName ]['html'];
		}
		if ( isset( $templatesByName['basic']['html'] ) ) {
			return (string) $templatesByName['basic']['html'];
		}

		return '<li><a href="/{urlName}">{urlName}</a></li>';
	}

	public static function render( string $templateHtml, string $urlName, bool $disableLinks ): string {
		$safe = htmlspecialchars( $urlName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$html = str_replace( '{urlName}', $safe, $templateHtml );
		if ( $disableLinks ) {
			$html = strip_tags( $html, '<li><div><span>' );
		}

		return $html;
	}

	public static function hosts( string $templateHtml, string $urlName ): array {
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

}
