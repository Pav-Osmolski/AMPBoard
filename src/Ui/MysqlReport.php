<?php
namespace AMPBoard\Ui;

/** Formats an inspection snapshot without querying MySQL or reading runtime globals. */
final class MysqlReport {
	public function render( array $report, bool $demo, float $elapsed ): string {
		if ( $report['error'] !== null ) { return '<pre>❌ ' . self::escape( $report['error'] ) . "\n</pre>"; }
		$text = "✅ Connected to MySQL server\n";
		$text .= '🚀 Fast Mode: ' . ( $report['fastMode'] ? 'Enabled (some checks skipped)' : 'Disabled (full inspection)' ) . "\n";
		$text .= '🔢 Server Version: ' . self::escape( $report['serverVersion'] ) . "\n";
		$text .= '📚 Client Version: ' . self::escape( $report['clientVersion'] ) . "\n";
		$text .= '🔌 Host Info: ' . self::escape( self::mask( $report['hostInfo'], $demo ) ) . "\n";
		$text .= '🔐 Current User: ' . self::escape( self::mask( $report['user'], $demo ) ) . "\n";
		if ( $report['databases'] === null ) {
			$text .= "⚠️ Failed to list databases.\n";
		} else {
			$text .= $report['fastMode'] ? "\n📁 Databases:" : "\n📦 Databases with Approximate Sizes:";
			foreach ( $report['databases'] as $database ) {
				// Preserve the historical database-name masking length after escaping.
				$text .= "\n- " . self::mask( self::escape( $database['name'] ), $demo );
				if ( ! $report['fastMode'] ) { $text .= $database['sizeMb'] === null ? ': N/A (size unavailable)' : ': ' . self::escape( $database['sizeMb'] ) . ' MB'; }
			}
		}
		$text .= "\n\n⚙️ Configuration Variables (Partial):\n";
		if ( $report['variables'] === null ) { $text .= "⚠️ Configuration variables unavailable.\n"; }
		foreach ( $report['variables'] ?? [] as $row ) { $text .= self::escape( $row['Variable_name'] ) . ': ' . self::escape( $row['Value'] ) . "\n"; }
		$text .= "\n📊 Status Snapshot:\n";
		if ( $report['status'] === null ) { $text .= "⚠️ Status snapshot unavailable.\n"; }
		foreach ( $report['status'] ?? [] as $row ) {
			$seconds = (int) $row['Value'];
			$hours = floor( $seconds / 3600 );
			$minutes = floor( ( $seconds % 3600 ) / 60 );
			$remaining = $seconds % 60;
			$text .= self::escape( $row['Variable_name'] ) . ": {$seconds} seconds ({$hours}h {$minutes}m {$remaining}s)\n";
		}
		$text .= "\n📋 Current Processes:\n";
		if ( $report['processes'] === null ) { $text .= "⚠️ Process list unavailable.\n"; }
		foreach ( $report['processes'] ?? [] as $row ) {
			$text .= '- [' . self::escape( $row['Id'] ?? '' ) . '] ' . self::escape( self::mask( (string) ( $row['User'] ?? '' ), $demo ) )
				. '@' . self::escape( self::mask( (string) ( $row['Host'] ?? '' ), $demo ) ) . ': ' . self::escape( $row['Info'] ?? '' ) . "\n";
		}
		$text .= "\n⏱️ Completed in " . round( $elapsed, 2 ) . " seconds\n";
		return '<pre>' . $text . '</pre>';
	}

	private static function escape( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	private static function mask( string $value, bool $demo ): string {
		if ( ! $demo ) { return $value; }
		return strlen( $value ) <= 4 ? '****' : ( strlen( $value ) <= 12 ? '************' : '****************' );
	}
}
