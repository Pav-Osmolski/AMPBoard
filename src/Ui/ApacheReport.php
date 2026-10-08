<?php
namespace AMPBoard\Ui;

/** Formats supplied Apache diagnostics without commands, filesystem reads, or globals. */
final class ApacheReport {
	public function render( array $report, bool $demo ): string {
		$mask = new DemoMask( $demo );
		$text = '🖥️ Operating System: ' . self::escape( $report['os'] ) . ' (' . self::escape( $report['architecture'] ) . ")\n";
		$text .= '🚀 Fast Mode: ' . ( $report['fastMode'] ? 'Enabled (some checks skipped)' : 'Disabled (full inspection)' ) . "\n";
		$text .= '🧠 Apache Context Detected: ' . ( $report['isApache'] ? 'Yes' : 'No' ) . "\n";
		$text .= '📃 Apache SAPI: ' . self::escape( $report['sapi'] ?? 'Unknown or not Apache' ) . "\n";
		$text .= '📦 Apache Version: ' . self::escape( $report['version'] ) . "\n";
		$text .= '📓 Apache Binary: ' . self::escape( $report['binary'] ?: 'Not found' ) . "\n";
		if ( ! $report['fastMode'] ) {
			$text .= '🕒 Apache Uptime (estimated): ' . self::escape( $report['uptime'] === 'Unavailable' ? 'Not available on this platform or config' : $report['uptime'] ) . "\n";
		}
		if ( $report['binary'] && ! $report['fastMode'] ) {
			$text .= '📝 Config File: ' . self::escape( $report['config'] ?: 'Not detected' ) . "\n";
			if ( $report['includes'] !== null ) {
				if ( $report['includes'] ) {
					$text .= "📂 Included Config Files:\n";
					foreach ( $report['includes'] as $include ) { $text .= '  - ' . self::escape( $include ) . "\n"; }
				} else { $text .= "ℹ️ No Include directives found.\n"; }
			}
			$text .= $report['vhosts'] ? "\n🌐 Active Virtual Hosts:\n" . self::escape( $report['vhosts'] ) . "\n"
				: "❌ VirtualHost information not available (likely restricted).\n";
		} elseif ( $report['binary'] ) {
			$text .= "🔴 Fast mode: Config/VHosts skipped.\n";
		} else { $text .= "❌ Apache Binary Not Found. Config/VHosts skipped.\n"; }
		$text .= "\n🌱 Apache Environment Variables:\n";
		if ( $report['environment'] ) {
			foreach ( $report['environment'] as $key => $value ) { $text .= '  ' . self::escape( $key ) . ': ' . self::escape( $value ) . "\n"; }
		} else { $text .= "  None detected.\n"; }
		$text .= "\n⚙️ PHP Configuration:\n";
		foreach ( $report['ini'] as $key => $value ) {
			if ( $key === 'Loaded php.ini' && $demo ) { $value = $mask->value( (string) $value ); }
			$text .= '  ' . self::escape( $key ) . ': ' . self::escape( $value ) . "\n";
		}
		return '<pre>' . $text . "\n📅 Inspection complete.</pre>";
	}
	private static function escape( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
}
