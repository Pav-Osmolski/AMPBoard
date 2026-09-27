<?php

namespace AMPBoard\Ui;


/** Renders dashboard components from an explicit configuration snapshot. */
final class Renderer {
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Build page class string when viewing partials independently.
	 *
	 * When `$settingsView` is empty, this helper returns a string of CSS classes
	 * that should be added to the page container. At this time only the class
	 * "page-view" is returned, but using an array internally allows for future
	 * extensibility if additional classes are ever required.
	 *
	 * @param mixed $settingsView The view state or flag to determine if the page is
	 *                            being rendered as a standalone partial.
	 *
	 * @return string The generated space-separated CSS class string.
	 */
	public function buildPageViewClasses( mixed $settingsView ): string {
		return empty( $settingsView ) ? 'page-view' : '';
	}

	/**
	 * Render a width control button group for a resizable UI element.
	 *
	 * Outputs a `.width-controls` group wired to the JS width logic via
	 * `data-width-for`. The target element should expose the same key via
	 * `data-width-key`.
	 *
	 * Example:
	 *   // Echo inline (function echoes):
	 *   <?php $this->renderWidthControls( 'width_settings', 'Accordion', 'accordion-controls', true ); ?>
	 *
	 *   // Return then print (short echo):
	 *   <?= $this->renderWidthControls( 'width_columns', 'Column', 'column-controls' ); ?>
	 *
	 * @param string $widthKey Unique key that matches the target's data-width-key.
	 * @param string $contextLabel Human label for the target (e.g. "Accordion", "Column").
	 * @param string $extraClasses Additional wrapper classes (e.g. "accordion-controls").
	 * @param bool $echo Whether to echo the HTML (true) or return it (false). Default false.
	 *
	 * @return string The generated HTML markup.
	 */
	public function renderWidthControls( string $widthKey, string $contextLabel, string $extraClasses = '', bool $echo = false ): string {
		$widthKey     = htmlspecialchars( (string) $widthKey, ENT_QUOTES, 'UTF-8' );
		$contextLabel = htmlspecialchars( (string) $contextLabel, ENT_QUOTES, 'UTF-8' );
		$wrapperClass = trim( $extraClasses . ' width-controls' );

		$groupLabel    = htmlspecialchars( $contextLabel . ' width controls', ENT_QUOTES, 'UTF-8' );
		$resetLabel    = htmlspecialchars( 'Reset ' . $contextLabel . ' width', ENT_QUOTES, 'UTF-8' );
		$decreaseLabel = htmlspecialchars( 'Decrease ' . $contextLabel . ' width', ENT_QUOTES, 'UTF-8' );
		$increaseLabel = htmlspecialchars( 'Increase ' . $contextLabel . ' width', ENT_QUOTES, 'UTF-8' );

		// Unique IDs per group (avoid duplicates if multiple groups exist)
		$idPrefix = preg_replace( '/[^a-zA-Z0-9_-]/', '-', strtolower( $widthKey ) );
		$resetId  = $idPrefix . '-reset-width';
		$prevId   = $idPrefix . '-prev-width';
		$nextId   = $idPrefix . '-next-width';

		$html = <<<HTML
	<div class="{$wrapperClass}"
	     role="group"
	     aria-label="{$groupLabel}"
	     data-width-for="{$widthKey}">
		<button id="{$resetId}" class="width-btn width-reset" type="button" data-action="reset" aria-label="{$resetLabel}">
			<span aria-hidden="true">X</span>
		</button>
		<button id="{$prevId}" class="width-btn width-prev" type="button" data-action="decrease" aria-label="{$decreaseLabel}">
			<span aria-hidden="true">−</span>
		</button>
		<button id="{$nextId}" class="width-btn width-next" type="button" data-action="increase" aria-label="{$increaseLabel}">
			<span aria-hidden="true">+</span>
		</button>
	</div>
	HTML;

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Render the opening markup for an accordion section and configure ARIA wiring.
	 *
	 * Prints:
	 * - <div class="toggle-content-container" data-id="…">
	 * -   <div class="toggle-accordion" id="accordion-{$id}-btn" role="button"
	 * -        aria-expanded="false|true" aria-controls="panel-{$id}" tabindex="0">
	 * -       {$headingHtml}
	 * -       <span class="icon" aria-hidden="true">{caret svg}</span>
	 * -   </div>
	 * -   <div class="toggle-content" id="panel-{$id}" role="region"
	 * -        aria-labelledby="accordion-{$id}-btn">
	 *
	 * Options:
	 * - 'disabled'  => bool (adds .disabled to container)
	 * - 'expanded'  => bool (initial aria-expanded; JS may override)
	 * - 'caretPath' => string (path to caret SVG)
	 * - 'caretClass'=> string (extra class on the <span class="icon …">)
	 *
	 * @param string $id
	 * @param string $headingHtml
	 * @param array $opts
	 *
	 * @return void
	 */
	public function renderAccordionSectionStart( string $id, string $headingHtml, array $opts = [] ): void {
		$disabled    = ! empty( $opts['disabled'] );
		$expanded    = ! empty( $opts['expanded'] );
		$caretPath   = isset( $opts['caretPath'] ) ? (string) $opts['caretPath'] : $this->config['paths']['assets'] . '/images/caret-down.svg';
		$caretClass  = isset( $opts['caretClass'] ) ? (string) $opts['caretClass'] : '';
		$containerCl = 'toggle-content-container' . ( $disabled ? ' disabled' : '' );

		// Load caret SVG (decorative)
		$caretSvg = '';
		if ( is_file( $caretPath ) && is_readable( $caretPath ) ) {
			$svg = file_get_contents( $caretPath );
			// ensure decorative: strip any accidental focusability
			$svg      = preg_replace( '/(<svg\b)([^>]*)(>)/i', '$1$2 focusable="false" aria-hidden="true"$3', $svg, 1 );
			$caretSvg = '<span class="icon' . ( $caretClass ? ' ' . htmlspecialchars( $caretClass, ENT_QUOTES, 'UTF-8' ) : '' ) . '" aria-hidden="true">' . $svg . '</span>';
		}

		$idEsc = htmlspecialchars( $id, ENT_QUOTES, 'UTF-8' );

		echo '<div class="' . $containerCl . '" data-id="' . $idEsc . '">';
		echo '  <div class="toggle-accordion" id="accordion-' . $idEsc . '-btn" role="button" aria-expanded="' . ( $expanded ? 'true' : 'false' ) . '" aria-controls="panel-' . $idEsc . '" tabindex="0">';
		echo $headingHtml;
		echo $caretSvg; // decorative caret wrapped correctly
		echo '  </div>';
		echo '  <div class="toggle-content" id="panel-' . $idEsc . '" role="region" aria-labelledby="accordion-' . $idEsc . '-btn">';
	}

	/**
	 * Close the accordion panel and container.
	 *
	 * @return void
	 */
	public function renderAccordionSectionEnd(): void {
		echo '  </div>'; // .toggle-content
		echo '</div>';   // .toggle-content-container
	}

	/**
	 * Load tooltip definitions from tooltips.json.
	 *
	 * Missing keys will fall back to $this->getDefaultTooltipMessage()
	 * via $this->renderHeadingTooltip() → no need for PHP defaults.
	 *
	 * @return array<string, string>
	 */
	public function getDefaultTooltips(): array {
		return $this->config['interface']['tooltips'] ?? [];
	}

	/**
	 * Get the fallback tooltip string.
	 *
	 * @return string
	 */
	public function getDefaultTooltipMessage(): string {
		return 'No description available for this setting.';
	}

	/**
	 * Get tooltip content by key.
	 *
	 * @param string $key
	 * @param array<string, string> $tooltips
	 * @param string $default
	 *
	 * @return string
	 */
	public function getTooltip( string $key, array $tooltips, string $default ): string {
		return array_key_exists( $key, $tooltips )
			? $tooltips[ $key ]
			: $default . " (Missing tooltip key: $key)";
	}

	/**
	 * Render a labelled heading with tooltip, just the tooltip icon, or just the heading.
	 *
	 * @param string $key Unique key for tooltip content and ID.
	 * @param array<string, string> $tooltips Map of tooltip keys to messages.
	 * @param string $defaultTooltipMessage Fallback message if key is not found.
	 * @param string $headingTag HTML heading tag (ignored if $tooltipOnly is true).
	 * @param string $label Optional heading label (autogenerated from key if omitted).
	 * @param bool $tooltipOnly If true, only the tooltip icon and accessible span are rendered.
	 * @param bool $headingOnly If true, only the heading is rendered (tooltip is omitted).
	 * @param bool $below If true, tooltip is rendered with class "below"; otherwise it uses "above".
	 *
	 * @return string HTML output.
	 */
	public function renderHeadingTooltip(
		string $key,
		array $tooltips,
		string $defaultTooltipMessage,
		string $headingTag = 'h3',
		string $label = '',
		bool $tooltipOnly = false,
		bool $headingOnly = false,
		bool $below = false
	): string {
		$showTooltips = $this->config['ui']['flags']['tooltips'] ?? true;

		$desc     = $this->getTooltip( $key, $tooltips, $defaultTooltipMessage );
		$title    = $label ?: ucwords( str_replace( '_', ' ', $key ) );
		$posClass = $below ? 'below' : 'above';

		// Escape once, use everywhere.
		$escKey   = htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' );
		$escDesc  = htmlspecialchars( $desc, ENT_QUOTES, 'UTF-8' );
		$escTitle = htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' );

		$renderHeading = ! $tooltipOnly;
		$renderTooltip = ( ! $headingOnly ) && $showTooltips;

		ob_start();

		if ( $renderHeading ) {
			echo "<{$headingTag}>{$escTitle}</{$headingTag}>";
		}

		if ( $tooltipOnly || ( $renderHeading && $renderTooltip ) ) {
			?>
			<span class="tooltip-icon <?= htmlspecialchars( $posClass, ENT_QUOTES, 'UTF-8' ) ?>"
			      aria-describedby="tooltip-<?= $escKey ?>"
			      tabindex="0"
			      data-tooltip="<?= $escDesc ?>">
				<?php include $this->config['paths']['assets'] . '/images/tooltip-icon.svg'; ?>
			</span>
			<span id="tooltip-<?= $escKey ?>" class="sr-only" role="tooltip"><?= $escDesc ?></span>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Injects an SVG with unique IDs by auto-prefixing them.
	 *
	 * @param string $svgPath Path to the SVG file.
	 * @param string $prefix Unique prefix to prevent ID clashes (e.g., 'icon1').
	 *
	 * @return string SVG content with updated IDs.
	 */
	public function injectSvgWithUniqueIds( string $svgPath, string $prefix ): string {
		$svg = file_get_contents( $svgPath );
		if ( $svg === false ) {
			return '';
		}
		if ( ! preg_match_all( '/id="([^"]+)"/', $svg, $matches ) ) {
			return $svg;
		}
		foreach ( $matches[1] as $originalId ) {
			$newId = $prefix . '-' . $originalId;
			$svg   = preg_replace( '/id="' . preg_quote( $originalId, '/' ) . '"/', 'id="' . $newId . '"', $svg );
			$svg   = preg_replace( '/url\(#' . preg_quote( $originalId, '/' ) . '\)/', 'url(#' . $newId . ')', $svg );
		}

		return $svg;
	}

	/**
	 * Render a visual separator line for UI sections.
	 *
	 * @param string $extraClass Optional CSS class(es) to append. Defaults to 'md'.
	 *
	 * @return void
	 */
	public function renderSeparatorLine( string $extraClass = '' ): void {
		$class = 'separator-line' . ( $extraClass ? ' ' . $extraClass : ' md' );
		echo '<div class="' . $class . '" aria-hidden="true"></div>';
	}

	/**
	 * Outputs detected versions of Apache, PHP, and MySQL with UI badges.
	 *
	 * Uses the configured Apache path and injected database connection factory.
	 *
	 * @return void
	 */
	public function renderServerInfo( array $info ): void {
		if ( $info['apache']['status'] === 'available' ) {
			echo '<span class="apache-info">Apache: <a href="?view=apache-inspector" id="toggle-apache-inspector">' . htmlspecialchars( $info['apache']['version'], ENT_QUOTES, 'UTF-8' ) . '</a> <span class="status" aria-hidden="true">✔️</span></span>';
		} else {
			if ( $info['apache']['status'] === 'unknown' ) {
				echo '<span class="apache-unknown-info">Apache: <a href="?view=apache-inspector" id="toggle-apache-inspector">Version unknown</a> <span class="status" aria-hidden="true">⚠️</span></span>';
			} else {
				echo '<span class="apache-error-info">Apache: <a href="?view=apache-inspector" id="toggle-apache-inspector">Not detected</a> <span class="status" aria-hidden="true">❌</span></span>';
			}
		}

		$runtime = $info['php'];
		$phpVersion = htmlspecialchars( (string) $runtime['version'], ENT_QUOTES, 'UTF-8' );
		if ( ! $phpVersion ) {
			echo '<span class="php-unknown-info">PHP: Version unknown ⚠️</span>';
		} else {
			$isThreadSafe = ( $runtime['threadSafe'] ) ? 'TS' : 'NTS';
			$isFastCGI    = ( strpos( $runtime['sapi'], 'cgi-fcgi' ) !== false ) ? 'FastCGI' : 'Non-FastCGI';
			echo "<span class='php-info'>PHP: <a href='?view=phpinfo' id='toggle-phpinfo'>{$phpVersion} {$isThreadSafe} {$isFastCGI}</a> <span class='status' aria-hidden='true'>✔️</span></span>";
		}

		$label = htmlspecialchars( $info['database']['label'], ENT_QUOTES, 'UTF-8' );
		if ( $info['database']['available'] ) {
			echo "<span class='mysql-info'>MySQL: <a href='?view=mysql-inspector' id='toggle-mysql-inspector'>{$label}</a> <span class='status' aria-hidden='true'>✔️</span></span>";
		} else {
			echo "<span class='mysql-error-info'>MySQL: <a href='?view=mysql-inspector' id='toggle-mysql-inspector'>{$label}</a> <span class='status' aria-hidden='true'>❌</span></span>";
		}
	}

	/**
	 * Render versioned CSS/JS tags plus a single BASE_URL bootstrap.
	 *
	 * - BASE_URL is computed by stripping a suffix from SCRIPT_NAME's directory (default "/utils").
	 * - Each asset gets a version query from filemtime(), falling back to time().
	 * - Pass null for $cssRel or $jsRel to skip that asset.
	 * - Optional $jsAttrs lets you add attributes like ["defer" => true, "crossorigin" => "anonymous"].
	 *
	 * @param string|null $cssRel Web-relative CSS path, or null to skip. Default "dist/css/style.min.css".
	 * @param string|null $jsRel Web-relative JS path, or null to skip.  Default "dist/js/script.min.js".
	 * @param string|null $projectRoot Absolute project root; defaults to the project root.
	 * @param string $stripSuffix Suffix to strip from SCRIPT_NAME dir when computing BASE_URL.
	 * @param array $jsAttrs Key/value map of JS attributes. Boolean true renders key only.
	 *
	 * @return string HTML snippet defining window.BASE_URL once, then the tags requested.
	 */
	public function renderVersionedAssetsWithBase(
		?string $cssRel = 'dist/css/style.min.css',
		?string $jsRel = 'dist/js/script.min.js',
		?string $projectRoot = null,
		string $stripSuffix = '/utils',
		array $jsAttrs = []
	): string {
		$projectRoot = $projectRoot ?: dirname( __DIR__, 2 );

		// Compute BASE_URL once
		$scriptName = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '';
		$scriptDir  = rtrim( dirname( $scriptName ), '/\\' );

		if ( $stripSuffix !== '' && $stripSuffix[0] === '/' && preg_match( '~' . preg_quote( $stripSuffix, '~' ) . '$~', $scriptDir ) ) {
			$baseUrl = rtrim( substr( $scriptDir, 0, - strlen( $stripSuffix ) ), '/' );
		} else {
			$baseUrl = $scriptDir;
		}
		$baseUrl = ( $baseUrl === '' ? '/' : $baseUrl . '/' );

		$html = '<script>window.BASE_URL = window.BASE_URL || ' . json_encode( $baseUrl ) . ';</script>' . "\n";

		// Helper: build abs path and version
		$versionFor = static function ( string $rel ) use ( $projectRoot ): int {
			$abs = rtrim( $projectRoot, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR
			       . str_replace( [ '/', '\\' ], DIRECTORY_SEPARATOR, $rel );

			return is_file( $abs ) ? (int) filemtime( $abs ) : time();
		};

		// CSS
		if ( $cssRel !== null && $cssRel !== '' ) {
			$href = $baseUrl . ltrim( $cssRel, '/' );
			$ver  = $versionFor( $cssRel );
			$html .= '<link rel="stylesheet" href="' . htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' ) . '?v=' . $ver . '">' . "\n";
		}

		// JS
		if ( $jsRel !== null && $jsRel !== '' ) {
			$src = $baseUrl . ltrim( $jsRel, '/' );
			$ver = $versionFor( $jsRel );

			// Serialise JS attributes
			$attrStr = '';
			foreach ( $jsAttrs as $k => $v ) {
				if ( is_bool( $v ) ) {
					if ( $v ) {
						$attrStr .= ' ' . htmlspecialchars( (string) $k, ENT_QUOTES, 'UTF-8' );
					}
				} else {
					$attrStr .= ' ' . htmlspecialchars( (string) $k, ENT_QUOTES, 'UTF-8' )
					            . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"';
				}
			}

			$html .= '<script src="' . htmlspecialchars( $src, ENT_QUOTES, 'UTF-8' ) . '?v=' . $ver . '"' . $attrStr . '></script>' . "\n";
		}

		return $html;
	}

	/**
	 * Render a generic legend badge with optional Apache validity check.
	 *
	 * @param string $type CSS/identifier type (e.g. "vhost", "error", "info").
	 * @param string|null $label Visible badge text. If null, defaults to $type exactly.
	 * @param string|null $title Optional title attribute.
	 * @param string|null $aria Optional aria-label.
	 * @param bool|null $apachePathValid Used when $type === 'vhost' to show the error state.
	 *
	 * @return string HTML badge markup.
	 */
	public function renderBadge(
		string $type = '',
		?string $label = null,
		?string $title = null,
		?string $aria = null,
		?bool $apachePathValid = null
	): string {
		// Global toggle: if badge display is disabled, render nothing.
		$showBadges = $this->config['ui']['flags']['folderBadges'] ?? true;
		if ( ! $showBadges ) {
			return '';
		}

		$type = trim( $type ) !== '' ? strtolower( $type ) : 'default';

		// Special case: vHost badge error
		if ( $type === 'vhost' && $apachePathValid === false ) {
			$class = 'legend-badge legend-badge-error';
			$label = 'vHost Error';
			$title = 'Apache vHost not detected. Please confirm that your Apache path is valid.';
			$aria  = 'Apache vHost not detected';

			return '<span class="' . htmlspecialchars( $class, ENT_QUOTES, 'UTF-8' ) . '"'
			       . ' title="' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '"'
			       . ' aria-label="' . htmlspecialchars( $aria, ENT_QUOTES, 'UTF-8' ) . '"'
			       . ' role="note">'
			       . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' )
			       . '</span>';
		}

		// Standard badge
		$class = "legend-badge legend-badge-{$type}";
		$label = $label ?? $type;

		if ( $title === null ) {
			$title = $type === 'default'
				? 'Default badge'
				: "Badge type: {$label}";
		}

		if ( $aria === null ) {
			$aria = $label;
		}

		return '<span class="' . htmlspecialchars( $class, ENT_QUOTES, 'UTF-8' ) . '"'
		       . ' title="' . htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' ) . '"'
		       . ' aria-label="' . htmlspecialchars( $aria, ENT_QUOTES, 'UTF-8' ) . '"'
		       . ' role="note">'
		       . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' )
		       . '</span>';
	}

	/**
	 * Render a button with optional separator lines above and below.
	 *
	 * Button config (all optional, except "label"):
	 * - label      string  Visible text of the button.
	 * - id         string  Button id attribute.
	 * - class      string  Space separated class list.
	 * - type       string  Button type attribute (e.g. "button", "submit", "reset"). Defaults to "button".
	 * - disabled   bool    Whether the button should be rendered as disabled.
	 * - attributes array   Extra attributes as key => value, or numeric array for valueless attributes.
	 *
	 * Separators config (all optional):
	 * - top bool|string true for default separator, string for size (e.g. "sm"), false to skip.
	 * - bottom bool|string true for default separator, string for size, false to skip.
	 *
	 * Examples:
	 * - ['top' => true, 'bottom' => true] Default separators above and below.
	 * - ['top' => 'sm', 'bottom' => true] Small top separator, normal bottom.
	 * - ['top' => false, 'bottom' => false] No separators at all.
	 *
	 * @param array<string,mixed> $button Button configuration options.
	 * @param array<string,mixed> $separators Separator configuration options.
	 *
	 * @return void
	 */
	public function renderButtonBlock( array $button = [], array $separators = [] ): void {
		$buttonDefaults = [
			'label'      => '',
			'id'         => '',
			'class'      => '',
			'type'       => 'button',
			'disabled'   => false,
			'attributes' => [],
		];

		$separatorDefaults = [
			'top'    => true,
			'bottom' => true,
		];

		$button     = array_merge( $buttonDefaults, $button );
		$separators = array_merge( $separatorDefaults, $separators );

		// Nothing sensible to render.
		if ( $button['label'] === '' ) {
			return;
		}

		// Render top separator.
		if ( $separators['top'] ) {
			if ( is_string( $separators['top'] ) ) {
				$this->renderSeparatorLine( $separators['top'] );
			} else {
				$this->renderSeparatorLine();
			}
		}

		// Build attribute list.
		$attributes = [];

		if ( $button['id'] !== '' ) {
			$attributes[] = sprintf(
				'id="%s"',
				htmlspecialchars( (string) $button['id'], ENT_QUOTES, 'UTF-8' )
			);
		}

		$class = trim( (string) $button['class'] );
		if ( $class !== '' ) {
			$attributes[] = sprintf(
				'class="%s"',
				htmlspecialchars( $class, ENT_QUOTES, 'UTF-8' )
			);
		}

		$type         = $button['type'] ?: 'button';
		$attributes[] = sprintf(
			'type="%s"',
			htmlspecialchars( (string) $type, ENT_QUOTES, 'UTF-8' )
		);

		if ( ! empty( $button['disabled'] ) ) {
			$attributes[] = 'disabled';
		}

		if ( ! empty( $button['attributes'] ) && is_array( $button['attributes'] ) ) {
			foreach ( $button['attributes'] as $attrName => $attrValue ) {
				if ( is_int( $attrName ) ) {
					// Valueless / boolean attributes.
					$attributes[] = htmlspecialchars( (string) $attrValue, ENT_QUOTES, 'UTF-8' );
				} else {
					$attributes[] = sprintf(
						'%s="%s"',
						htmlspecialchars( (string) $attrName, ENT_QUOTES, 'UTF-8' ),
						htmlspecialchars( (string) $attrValue, ENT_QUOTES, 'UTF-8' )
					);
				}
			}
		}

		$attrString = $attributes ? ' ' . implode( ' ', $attributes ) : '';

		// Render button.
		echo '<button' . $attrString . '>';
		echo htmlspecialchars( (string) $button['label'], ENT_QUOTES, 'UTF-8' );
		echo '</button>';

		// Render bottom separator.
		if ( $separators['bottom'] ) {
			if ( is_string( $separators['bottom'] ) ) {
				$this->renderSeparatorLine( $separators['bottom'] );
			} else {
				$this->renderSeparatorLine();
			}
		}
	}

	/**
	 * Render a heading (and optional tooltip) by human-readable label or tooltip key.
	 *
	 * Usage:
	 *   <?= $this->renderHeading('Document Folders', 'h2', true) ?>
	 *   <?= $this->renderHeading('Export Files & Database', 'h3') ?>
	 *
	 * Resolution rules:
	 * - First, try to resolve $labelOrKey against headings.json by label.
	 * - If not found, try to match against headings.json entries where "key" === $labelOrKey.
	 * - Tooltip key comes from the "key" field if defined, otherwise is derived
	 *   from the final label (e.g. "Document Folders" → "document_folders").
	 * - "suffix" in headings.json can be:
	 *     - "PHP_VALIDITY_DEPENDENT"    (marks invalid PHP path)
	 *     - "APACHE_VALIDITY_DEPENDENT" (marks invalid Apache path)
	 *     - any other string to be appended as-is.
	 *
	 * @param string $labelOrKey Human-readable label (preferred) or tooltip key.
	 * @param string $tag Heading tag (e.g. "h2", "h3"). Defaults to "h3".
	 * @param bool $below If true, tooltip is rendered below (class "below").
	 * @param bool $tooltipOnly If true, only the tooltip icon is rendered.
	 * @param bool $headingOnly If true, only the heading is rendered (no tooltip).
	 *
	 * @return string
	 */
	public function renderHeading(
		string $labelOrKey,
		string $tag = 'h3',
		bool $below = false,
		bool $tooltipOnly = false,
		bool $headingOnly = false
	): string {
		$headingsConfig = $this->config['interface']['headings'] ?? [];
		$tooltips       = $this->getDefaultTooltips();
		$default        = $this->getDefaultTooltipMessage();

		$label = $labelOrKey;
		$cfg   = null;

		// 1. Try direct match by label (preferred usage).
		if ( isset( $headingsConfig[ $labelOrKey ] ) ) {
			$cfg   = $headingsConfig[ $labelOrKey ];
			$label = $labelOrKey;
		} else {
			// 2. Try match by "key" field (backwards-friendly).
			foreach ( $headingsConfig as $configuredLabel => $entry ) {
				if ( isset( $entry['key'] ) && (string) $entry['key'] === $labelOrKey ) {
					$cfg   = $entry;
					$label = $configuredLabel;
					break;
				}
			}
		}

		// Tooltip key: prefer explicit "key" from config, otherwise derive from label.
		if ( $cfg && isset( $cfg['key'] ) && $cfg['key'] !== '' ) {
			$key = (string) $cfg['key'];
		} else {
			$key = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', $label ) );
		}

		// Allow headings.json to provide defaults for tag / below / modes, but let
		// function arguments override them.
		if ( $cfg ) {
			if ( isset( $cfg['tag'] ) && $tag === 'h3' ) {
				$tag = (string) $cfg['tag'];
			}
			if ( isset( $cfg['below'] ) && $below === false ) {
				$below = (bool) $cfg['below'];
			}
			if ( isset( $cfg['tooltipOnly'] ) && $tooltipOnly === false ) {
				$tooltipOnly = (bool) $cfg['tooltipOnly'];
			}
			if ( isset( $cfg['headingOnly'] ) && $headingOnly === false ) {
				$headingOnly = (bool) $cfg['headingOnly'];
			}
		}

		// Compute suffix, if any.
		$suffix = '';
		if ( $cfg && isset( $cfg['suffix'] ) ) {
			$phpPathValid    = $this->config['status']['phpPathValid'] ?? true;
			$apachePathValid = $this->config['status']['apachePathValid'] ?? true;
			$type            = (string) $cfg['suffix'];

			if ( $type === 'PHP_VALIDITY_DEPENDENT' ) {
				$suffix = $phpPathValid ? '' : ' &nbsp;❕';
			} elseif ( $type === 'APACHE_VALIDITY_DEPENDENT' ) {
				$suffix = $apachePathValid ? '' : ' &nbsp;❕';
			} else {
				// Literal suffix string.
				$suffix = $type;
			}
		}

		$html = $this->renderHeadingTooltip(
			$key,
			$tooltips,
			$default,
			$tag,
			$label,
			$tooltipOnly,
			$headingOnly,
			$below
		);

		if ( $suffix !== '' ) {
			$html .= $suffix;
		}

		return $html;
	}

	/**
	 * Render a collapse toggle button for regions (header, footer, or custom).
	 *
	 * The button is positioned absolutely inside its nearest positioned ancestor
	 * and exposes data attributes that JavaScript can use to collapse the region
	 * in and out of view.
	 *
	 * Usage examples:
	 *
	 *   // Header toggle inside partials/header.php
	 *   echo $this->renderCollapseToggle( 'header' );
	 *
	 *   // Footer toggle inside partials/footer.php
	 *   echo $this->renderCollapseToggle( 'footer' );
	 *
	 *   // Custom element (JS will use the selector)
	 *   echo $this->renderCollapseToggle( 'custom', [
	 *       'targetSelector' => '#my-panel',
	 *       'id'             => 'collapse-my-panel',
	 *   ] );
	 *
	 * Options:
	 * - id             : string Custom button id. Defaults to "collapse-toggle-{region}".
	 * - labelExpanded  : string Screen reader label when region is visible.
	 * - labelCollapsed : string Screen reader label when region is hidden.
	 * - targetSelector : string Optional CSS selector for a custom target element.
	 * - extraClasses   : string Extra CSS classes for the button.
	 *
	 * JS expectations:
	 * - Buttons use the ".collapse-toggle" class.
	 * - The "data-collapse-region" attribute is one of "header", "footer", or "custom".
	 * - Optional "data-target-selector" is used for custom regions.
	 * - JS toggles:
	 *     - "is-collapsed" or "is-open" on the target element, and
	 *     - "{region}-collapsed" on <html> for header/footer.
	 *
	 * @param string $region One of "header", "footer", or "custom".
	 * @param array<string, string> $options See options list above.
	 *
	 * @return string Generated HTML button markup.
	 */
	public function renderCollapseToggle( string $region = 'header', array $options = [] ): string {
		$region = strtolower( $region );

		if ( ! in_array( $region, [ 'header', 'footer', 'custom' ], true ) ) {
			$region = 'custom';
		}

		$defaults = [
			'id'             => '',
			'labelExpanded'  => '',
			'labelCollapsed' => '',
			'targetSelector' => '',
			'extraClasses'   => '',
		];

		$options = array_merge( $defaults, $options );

		// Sensible defaults based on region.
		if ( $options['labelExpanded'] === '' ) {
			if ( $region === 'footer' ) {
				$options['labelExpanded'] = 'Collapse footer';
			} elseif ( $region === 'header' ) {
				$options['labelExpanded'] = 'Collapse header';
			} else {
				$options['labelExpanded'] = 'Collapse section';
			}
		}

		if ( $options['labelCollapsed'] === '' ) {
			if ( $region === 'footer' ) {
				$options['labelCollapsed'] = 'Expand footer';
			} elseif ( $region === 'header' ) {
				$options['labelCollapsed'] = 'Expand header';
			} else {
				$options['labelCollapsed'] = 'Expand section';
			}
		}

		if ( $options['id'] === '' ) {
			$options['id'] = 'collapse-toggle-' . $region;
		}

		$id             = htmlspecialchars( (string) $options['id'], ENT_QUOTES, 'UTF-8' );
		$regionAttr     = htmlspecialchars( (string) $region, ENT_QUOTES, 'UTF-8' );
		$labelExpanded  = htmlspecialchars( (string) $options['labelExpanded'], ENT_QUOTES, 'UTF-8' );
		$labelCollapsed = htmlspecialchars( (string) $options['labelCollapsed'], ENT_QUOTES, 'UTF-8' );
		$extraClasses   = trim( 'collapse-toggle collapse-toggle-' . $region . ' ' . $options['extraClasses'] );
		$classAttr      = htmlspecialchars( $extraClasses, ENT_QUOTES, 'UTF-8' );

		$caretPath = $this->config['paths']['assets'] . '/images/caret-down.svg';
		$caretSvg  = '<span class="icon" aria-hidden="true">^</span>';
		if ( is_file( $caretPath ) && is_readable( $caretPath ) ) {
			$svg      = file_get_contents( $caretPath );
			$svg      = preg_replace( '/(<svg\b)([^>]*)(>)/i', '$1$2 focusable="false" aria-hidden="true"$3', $svg, 1 );
			$caretSvg = '<span class="icon" aria-hidden="true">' . $svg . '</span>';
		}

		$attrs   = [];
		$attrs[] = 'id="' . $id . '"';
		$attrs[] = 'type="button"';
		$attrs[] = 'class="' . $classAttr . '"';
		$attrs[] = 'data-collapse-region="' . $regionAttr . '"';
		$attrs[] = 'data-label-expanded="' . $labelExpanded . '"';
		$attrs[] = 'data-label-collapsed="' . $labelCollapsed . '"';
		$attrs[] = 'aria-expanded="true"';
		$attrs[] = 'aria-label="' . $labelExpanded . '"';

		if ( $options['targetSelector'] !== '' ) {
			$selector = htmlspecialchars( (string) $options['targetSelector'], ENT_QUOTES, 'UTF-8' );
			$attrs[]  = 'data-target-selector="' . $selector . '"';
		}

		$attrString = implode( ' ', $attrs );

		$html = '<button ' . $attrString . '>';
		$html .= $caretSvg;
		$html .= '<span class="sr-only collapse-toggle-label">' . $labelExpanded . '</span>';
		$html .= '</button>';

		return $html;
	}

	/**
	 * Render a drag handle button with a prefixed SVG icon.
	 *
	 * This helper:
	 * - Loads the hamburger SVG from the assets/images location
	 * - Prefixes IDs to avoid collisions when multiple handles exist
	 * - Wraps the SVG in a <button> with proper ARIA attributes
	 * - Adds data-drag-allow to support dragging inside the button
	 *
	 * @param string $label Visible name of the thing being reordered (used for aria-label)
	 * @param string $iconFile Optional SVG filename (default: hamburger.svg)
	 *
	 * @return string HTML markup for the drag handle button
	 */
	public function renderDragHandle( string $label, string $iconFile = 'hamburger.svg' ): string {
		$idPrefix = 'drag-' . bin2hex( random_bytes( 3 ) );

		$iconPath = rtrim( $this->config['paths']['assets'], '/' ) . '/images/' . ltrim( $iconFile, '/' );

		$svg = '';
		if ( is_file( $iconPath ) ) {
			$svg = $this->injectSvgWithUniqueIds( $iconPath, $idPrefix );
		}

		$ariaLabel = sprintf(
			'Reorder %s',
			trim( htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) )
		);

		return sprintf(
			'<button class="drag-handle reset" data-drag-allow aria-label="%s" aria-describedby="drag-help">%s</button>',
			$ariaLabel,
			$svg
		);
	}
}
