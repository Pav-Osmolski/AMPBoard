<?php
/**
 *  AMPBoard Local Configuration Overrides
 *
 *  This file is automatically loaded by config/config.php (if present)
 *  and is intended *solely* for per-machine overrides that should never
 *  be committed to version control.
 *
 *  Typical uses:
 *  - Turning DEMO_MODE on/off for local testing
 *  - Overriding DB credentials on one developer machine
 *  - Modifying stack paths (Apache, PHP, htdocs) privately
 *
 *  This file is optional. If absent, AMPBoard falls back to the normal
 *  defaults defined in user_config.php and config.php.
 *
 *  IMPORTANT:
 *  - Before modifying, please rename `local.example.php` to `local.php`.
 *  - Do not place business logic here.
 *  - Do not include project-wide settings that belong in user_config.php.
 *  - The modified file must stay out of Git.
 *
 *  Safe overrides:
 *
 *      define( 'DEMO_MODE', true );
 *
 *      define( 'DB_HOST', '127.0.0.1' );
 *      define( 'DB_USER', 'root' );
 *      define( 'DB_PASSWORD', 'local-pass' );
 *
 *      define( 'APACHE_PATH', 'C:/xampp/apache' );
 *      define( 'HTDOCS_PATH', 'C:/htdocs' );
 *      define( 'PHP_PATH', 'C:/xampp/php' );
 *
 *  Place only the overrides you actually need.
 *  Leave the rest to AMPBoard’s defaults.
 */

// Example: Enable demo mode locally
// define( 'DEMO_MODE', true );

// Example: Override DB creds
// define( 'DB_HOST', 'localhost' );
// define( 'DB_USER', 'root' );
// define( 'DB_PASSWORD', 'mypass' );

// Example: Override stack paths
// define( 'APACHE_PATH', 'C:/xampp/apache' );
// define( 'HTDOCS_PATH', 'D:/Web/htdocs' );
// define( 'PHP_PATH', 'C:/xampp/php' );
