<?php
namespace AMPBoard\System;

/** Read-only host/process measurements. The statistics service handles unavailable values. */
final class NativeMetrics {
	public function read( string $diskPath ): array {
		return [
			'load' => function_exists( 'sys_getloadavg' ) ? @sys_getloadavg() : false,
			'peakMemory' => memory_get_peak_usage( true ),
			'diskFree' => function_exists( 'disk_free_space' ) ? @disk_free_space( $diskPath ) : false,
			'diskTotal' => function_exists( 'disk_total_space' ) ? @disk_total_space( $diskPath ) : false,
		];
	}
}
