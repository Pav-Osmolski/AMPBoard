<?php

namespace AMPBoard\Ui;

/** Theme metadata and body classes, with an explicit asset directory. */
final class ThemeCatalog {
	private string $assetsDirectory;

	public function __construct( string $assetsDirectory ) {
		$this->assetsDirectory = $assetsDirectory;
	}

	/**
	 * Determine the theme color scheme (light or dark) based on the SCSS theme file.
	 *
	 * @param string $theme The name of the theme (used to locate its SCSS file).
	 *
	 * @return string Returns 'light' or 'dark' depending on the $theme-type in the SCSS file.
	 */
	public function getThemeColorScheme( string $theme ): string {
		$themeFile     = $this->assetsDirectory . '/scss/themes/_' . $theme . '.scss';
		$defaultScheme = 'dark';

		if ( $theme === 'default' || ! file_exists( $themeFile ) ) {
			return $defaultScheme;
		}

		$scssContent = file_get_contents( $themeFile );

		if ( preg_match( '#\$theme-type\s*:\s*[\'"]Light[\'"]#i', $scssContent ) ) {
			return 'light';
		}

		return 'dark';
	}

	/**
	 * Generate dynamic <body> class string based on UI settings and theme.
	 *
	 * This assembles a space-delimited class list for the <body> element using:
	 * - UI toggle states (header, footer, clock, search, tooltips).
	 * - Optional module availability checks (system stats, Apache log, PHP log).
	 * - The resolved theme colour scheme (light-mode / dark-mode).
	 *
	 * Optional modules are only marked active when their file exists and the
	 * corresponding display flag is enabled.
	 *
	 * @param string $theme Theme identifier used by $this->getThemeColorScheme().
	 * @param bool $displayHeader Whether the header UI should be visible.
	 * @param bool $displayFooter Whether the footer UI should be visible.
	 * @param bool $displayClock Whether the clock widget should be visible.
	 * @param bool $displaySearch Whether the search widget should be visible.
	 * @param bool $displayTooltips Whether tooltip icons should be visible.
	 * @param bool $displaySystemStats Whether the system monitor panel should be visible.
	 * @param bool $displayApacheErrorLog Whether the Apache error log panel should be visible.
	 * @param bool $displayPhpErrorLog Whether the PHP error log panel should be visible.
	 * @param bool $systemStatsAvailable Whether the system stats utility file exists.
	 * @param bool $apacheErrorLogAvailable Whether the Apache error log utility file exists.
	 * @param bool $phpErrorLogAvailable Whether the PHP error log utility file exists.
	 *
	 * @return string Space-delimited body class list.
	 */
	public function buildBodyClasses(
		string $theme,
		bool $displayHeader,
		bool $displayFooter,
		bool $displayClock,
		bool $displaySearch,
		bool $displayTooltips,
		bool $displaySystemStats,
		bool $displayApacheErrorLog,
		bool $displayPhpErrorLog,
		bool $systemStatsAvailable,
		bool $apacheErrorLogAvailable,
		bool $phpErrorLogAvailable
	): string {
		$classes = [
			'background-image',
			$displayHeader ? 'header-active' : 'header-inactive',
			$displayFooter ? 'footer-active' : 'footer-inactive',
			$displayClock ? 'clock-active' : 'clock-inactive',
			$displaySearch ? 'search-active' : 'search-inactive',
			$displayTooltips ? 'tooltips-active' : 'tooltips-inactive',
			( $systemStatsAvailable && $displaySystemStats )
				? 'system-monitor-active'
				: 'system-monitor-inactive',
			( $apacheErrorLogAvailable && $displayApacheErrorLog )
				? 'apache-error-log-active'
				: 'apache-error-log-inactive',
			( $phpErrorLogAvailable && $displayPhpErrorLog )
				? 'php-error-log-active'
				: 'php-error-log-inactive',
		];

		// Grouped error log state.
		if (
			( $apacheErrorLogAvailable && $displayApacheErrorLog ) ||
			( $phpErrorLogAvailable && $displayPhpErrorLog )
		) {
			$classes[] = 'error-log-active';
		}

		$themeColorScheme = $this->getThemeColorScheme( $theme );
		$classes[]        = ( $themeColorScheme === 'light' ) ? 'light-mode' : 'dark-mode';

		return implode( ' ', $classes );
	}

	/**
	 * Load theme names and types from SCSS metadata.
	 *
	 * @param string $themeDir
	 *
	 * @return array{0: array<string, string>, 1: array<string, string>}
	 */
	public function loadThemes( string $themeDir ): array {
		$themeOptions = [ 'default' => 'Default' ];
		$themeTypes   = [];

		foreach ( glob( $themeDir . '_*.scss' ) as $file ) {
			$themeId = str_replace( '_', '', basename( $file, '.scss' ) );
			$content = preg_replace( '#//.*#', '', file_get_contents( $file ) );

			$nameMatch = preg_match( '/\$theme-name\s*:\s*[\'"](.+?)[\'"]/', $content, $name ) ? $name[1] : ucfirst( $themeId );
			$typeMatch = preg_match( '/\$theme-type\s*:\s*[\'"](light|dark)[\'"]/i', $content, $type ) ? strtolower( $type[1] ) : null;

			$themeOptions[ $themeId ] = $nameMatch;
			if ( $typeMatch ) {
				$themeTypes[ $themeId ] = $typeMatch;
			}
		}

		return [ $themeOptions, $themeTypes ];
	}
}
