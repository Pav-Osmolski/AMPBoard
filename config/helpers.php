<?php
/**
 * Helpers loader
 *
 * Central include file that loads all helper modules in a stable order.
 *
 * @author  Pawel Osmolski
 * @version 2.1
 */

$helperModules = [
    // Load polyfills and security first
    'polyfills',
    'security',

    // Core utilities
    'paths',
    'io',
    'json',
    'filesystem',
    'templates',
    'booleans',

    // System + UI helpers
    'system',
    'ui',
    'export',

    // Integrations
    'apache',
    'php',
    'mysql',
];

foreach ( $helperModules as $module ) {
    require_once __DIR__ . "/helpers/{$module}.php";
}
