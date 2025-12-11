<?php
/**
 * PHP helpers
 *
 * normaliseIniIntOption(), normaliseIniSizeOption()
 *
 * @author  Pawel Osmolski
 * @version 1.0
 */

/**
 * Normalise an integer-based php.ini option (e.g. max_execution_time, max_input_vars).
 *
 * Returns:
 *  - int value if valid
 *  - -1 if allowed and provided
 *  - null if invalid or empty
 *
 * @param int|string|null $raw
 * @param bool $allowMinusOne Accept -1 as unlimited
 * @return int|null
 */
function normaliseIniIntOption( $raw, bool $allowMinusOne = false ): ?int {
    if ( $raw === null || $raw === '' ) {
        return null;
    }

    if ( is_string( $raw ) ) {
        $raw = trim( $raw );
    }

    if ( ! is_numeric( $raw ) ) {
        return null;
    }

    $ival = (int) $raw;

    if ( $ival > 0 ) {
        return $ival;
    }

    if ( $allowMinusOne && $ival === -1 ) {
        return -1;
    }

    return null;
}

/**
 * Normalise a size-based php.ini option such as "256M", "1G", or "50K".
 *
 * @param string|null $raw Raw user input
 * @param bool $allowMinusOne Whether "-1" is accepted (for memory_limit)
 * @param bool $requireUnit Whether a K/M/G suffix must be present
 * @return string|null Normalised uppercase value or null
 */
function normaliseIniSizeOption( ?string $raw, bool $allowMinusOne = false, bool $requireUnit = false ): ?string {
    if ( $raw === null ) {
        return null;
    }

    $val = trim( $raw );

    if ( $val === '' ) {
        return null;
    }

    if ( $allowMinusOne && $val === '-1' ) {
        return '-1';
    }

    $unitPattern = $requireUnit ? '(K|M|G)' : '(K|M|G)?';

    if ( preg_match( '/^[1-9]\d*' . $unitPattern . '$/i', $val ) ) {
        return strtoupper( $val );
    }

    return null;
}
