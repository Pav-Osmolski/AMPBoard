<?php
namespace AMPBoard\System;

use AMPBoard\Apache\CommandRunner;
use Closure;
use Throwable;

/** Preserves the existing cpu/memory/disk response with explicit platform and probe dependencies. */
final class Statistics {
	private string $os;
	private string $diskPath;
	private CommandRunner $commands;
	private Closure $metrics;
	public function __construct( string $os, string $diskPath, CommandRunner $commands, callable $metrics ) {
		$this->os = $os; $this->diskPath = $diskPath; $this->commands = $commands; $this->metrics = Closure::fromCallable( $metrics );
	}
	private static function number( $value ): bool {
		return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) && $value >= 0;
	}
	private function output( string $command ): string {
		try { $result = $this->commands->run( $command ); return $result['success'] ? $result['output'] : ''; }
		catch ( Throwable $error ) { return ''; }
	}
	public function snapshot(): array {
		try { $metrics = ($this->metrics)( $this->diskPath ); } catch ( Throwable $error ) { $metrics = []; }
		$cpu = 'N/A';
		if ( $this->os === 'Windows' ) {
			$output = $this->output( 'typeperf "\Processor(_Total)\% Processor Time" -sc 1' );
			if ( preg_match( '/"[^"]+","([\d.]+)"/', $output, $match ) && is_numeric( $match[1] ) ) { $cpu = round( (float) $match[1] ); }
		} else {
			$load = $metrics['load'][0] ?? null;
			$cores = (int) $this->output( 'nproc 2>/dev/null || sysctl -n hw.ncpu' );
			if ( self::number( $load ) ) { $cpu = round( $cores > 0 ? $load * 100 / $cores : $load, 1 ); }
		}
		$peak = $metrics['peakMemory'] ?? null;
		$free = $metrics['diskFree'] ?? null;
		$total = $metrics['diskTotal'] ?? null;
		$disk = self::number( $free ) && self::number( $total ) && $total > 0 ? round( $free / $total * 100, 1 ) : 'N/A';
		return [ 'cpu' => self::number( $cpu ) ? $cpu : 'N/A',
			'memory' => self::number( $peak ) ? round( $peak / 1024 / 1024, 1 ) : 'N/A',
			'disk' => self::number( $disk ) ? $disk : 'N/A' ];
	}
}
