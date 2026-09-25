# PHP modernization: steps 1–3

This follows the central config migration beginning at `a54ac0d` and retains the behavior on `bb62f14`. The published releases, now copied into `CHANGELOG.md`, remain the source for historical changes. The frontend bundles and HTTP URLs are unchanged.

## Request composition

Pages continue to `require_once config/config.php`. That file loads the small `AMPBoard\` autoloader and remaining procedural helpers, then creates explicit dependencies:

| Variable | Responsibility |
| --- | --- |
| `$cipher` | `Security\CredentialCipher`, constructed with the existing key path. |
| `$profiles` | `Config\ProfileRepository`, responsible for profile data and persistence. |
| `$config` | Existing nested array, returned by `AMPBoard\Config\Loader`. |
| `$database` | `AMPBoard\Database\ConnectionFactory`, constructed from `$config['db']`. |
| `$apacheCommands` | `Apache\CommandRunner`, implemented by `ShellCommandRunner` in normal requests. |
| `$apacheControl` | `Apache\Controller`, constructed with the configured Apache path and OS. |
| `$vhosts` | `Apache\VhostCatalog`, constructed with the Apache path and hosts-file paths. |
| `$ui` | `AMPBoard\Ui\Renderer`, constructed from the config and database factory. |

The renderer owns a config snapshot. Methods no longer read `global $config`, and separate renderers can use separate settings. Theme metadata and body classes live in `Ui\ThemeCatalog`, which takes an asset directory. Database connections use supplied credentials rather than `$dbUser`, `$dbPass`, or `DB_HOST`. Credential validation runs once when config loads. Both connection modes restore the caller's actual MySQLi report flags, including on failure.

`bootstrap.php` still starts the session before rendering and runs the submit handler. Utility URLs retain their existing entry points. Settings sets `$settingsView` explicitly for included panels; rendering an accordion no longer changes a global flag or defines `SETTINGS_VIEW`.

## Compatibility boundary

`config/config.php` remains a once-per-request compatibility entry point. It captures predefined constants as overrides, constructs the profile repository and cipher, and calls `Loader::load(true)` to publish constants for remaining procedural consumers. The repository reads `local.php`, selects the current user's profile directory or the default directory, and reads the PHP profile and JSON sidecars under a shared profile lock. The settings reader and export workflow use that loaded JSON snapshot.

New profiles return a versioned array rather than defining constants or applying PHP settings directly:

```php
<?php
return [
    'version' => 1,
    'settings' => [
        'theme' => 'default',
        'displayHeader' => false,
        'DB_HOST' => 'localhost',
        'DB_USER' => 'root',
    ],
    'php' => [ 'display_errors' => '0' ],
];
```

`ProfileReader` validates known setting names and types. `ProfileSchema` owns defaults and supported keys. `ProfileRepository` can resolve separate returned-array profiles in one process without publishing constants. `Loader` builds the dashboard array and asks `PhpSettings` to apply runtime directives; this still changes process-wide PHP settings. PHP profile files remain trusted executable code, not a sandboxed configuration format.

Legacy variable/constant profiles and local overrides remain readable. Their existing PHP side effects still execute, so legacy profiles retain the one-profile-per-request limitation. Legacy local constants take precedence over profile constants; legacy local UI variables remain defaults that the profile may override. Returned-array local settings and PHP directives take precedence over the profile. Constants already defined before composition take precedence over both. Missing settings receive the same application defaults.

### Saving and migration

`partials/submit.php` retains the HTTP, origin, CSRF, and demo-mode guards. `SettingsInput` normalizes the form and validates all JSON before persistence. `ProfileRepository::save()` accepts that normalized data, encrypts nonempty supplied credentials, and writes a returned-array profile plus the three JSON sidecars. An existing legacy file migrates only when settings are successfully saved; installation and read-only requests do not rewrite it. Empty form fields retain their previous omit-and-use-default behavior.

Newly saved credentials use an explicit `['encrypted' => '...']` envelope. `CredentialCipher` keeps the existing AES-256-CBC format (base64 of IV plus ciphertext) and 64-character hexadecimal `.key` file. Existing ciphertext and keys need no conversion. Legacy unmarked strings retain the old plaintext/decryption fallback. Explicit encrypted values fail if decryption fails. Reading never creates a key; saving can create a missing key, but never replaces a malformed existing key. Keep the existing `.key` with profile backups. The procedural encryption helpers remain compatibility wrappers.

`AtomicFileWriter` stages files before replacement and serializes readers/writers with `.profile.lock`. Existing-profile saves roll back ordinary replacement failures; first saves publish a complete directory so a failed save does not shadow the default profile. This is **not** a crash-atomic transaction across four files: termination or storage failure during replacement/rollback can still require recovery from backup. Failed rollback retains available temporary recovery files. Optional php.ini editing remains best-effort after the profile save and is not part of that transaction.

All existing config-array sections remain. `ui.themes.colorScheme` is additive. `ui.tooltips.map` now receives the loaded tooltip JSON directly instead of reading an incomplete config during initialization.

Legacy constants (`APACHE_PATH`, `HTDOCS_PATH`, `PHP_PATH`, `DB_*`, `DEMO_MODE`, `EXPORT_EXCLUDE`, `CRYPTO_KEY_FILE`) remain for unconverted code. The compatibility entry point is therefore **not** a multi-profile container or a re-entrant configuration API. Remaining helpers still load through `config/helpers.php`.

The old UI free functions and the two database connection/status free functions are internal APIs and have been replaced at every repository call site. Custom PHP integrations that called them must use `$ui->renderHeading(...)`, `$database->connect(...)`, and `$database->credentialStatus(...)` after loading config. The pure `normaliseDbServerInfo()` helper remains procedural.

PHP 8.0 is the minimum dictated by existing `mixed` and union type declarations. This refactor does not introduce a higher syntax requirement. A supported PHP release is preferable for an actual installation. Composer installation is not required.

## Apache services (step 3)

`Apache\Inspector` receives the Apache path, command runner, and fast-mode flag. The inspector endpoint resolves the saved flag, query override, and demo-mode override before constructing it. No helper reads `$GLOBALS['fastMode']`; full and fast inspectors can coexist. Fast mode skips uptime, config/vhost commands, and `/proc/self/environ` reading, while retaining binary discovery as before. Environment/SAPI information still comes from the current PHP process.

`Apache\VhostCatalog` owns parsing and a per-instance snapshot used by both the folder filter and virtual-host manager. It receives hosts-file paths explicitly, so separate installations cannot reuse each other's cache. Duplicate names still use the last block and retain their duplicate marker. Certificate availability still follows the existing managed `crt/{servername}/server.crt` and `server.key` convention; this is an existence check, not certificate-chain validation. Include directives remain display-only and are not recursively expanded.

`Apache\Controller` selects the existing Windows, Linux, or macOS restart strategy. `restartCommand()` may run diagnostic commands to choose that strategy but never executes the restart itself; `restart()` executes it. The HTTP endpoint retains its action/demo guards, status codes, and JSON response fields. Config construction does not run Apache commands. Certificate generation and log workflows remain separate procedural endpoints for a later increment.

`Apache\CommandRunner` is the injection boundary for diagnostics and control. `ShellCommandRunner` captures combined output and exit status using `exec()`, or `proc_open()` with a temporary stream when `exec()` is disabled. If neither is available, execution reports failure rather than claiming success. It accepts trusted commands built by application code, **not raw request input**. Binary paths are quoted; Windows control paths containing shell-expansion characters are rejected. Platform restart permissions and tools are still installation-specific, and command success means the command exited successfully, not that a subsequent health check passed.

The old `config/helpers/apache.php` free functions have been removed after migrating every repository caller. Custom integrations must construct `Apache\Inspector` for diagnostics, use `$apacheControl->restart()`, and use `$vhosts->getVhostServerData()` or `$vhosts->isValidVhostHost(...)`. These services do not read `APACHE_PATH` or other compatibility constants. `getIniFilesInfo()` returns raw runtime information; the view applies display obfuscation.

The extraction also fixes binary discovery treating directories as executables, joining already-absolute config paths to `HTTPD_ROOT`, and treating inline hosts-file comments as host aliases.

## Validation

Run `php -n tests/run.php`. Each scenario gets a fresh process because legacy profiles define constants. The suite uses a deterministic MySQLi double, temporary profiles, and read-only request fixtures. It checks profile fallback and overrides, false/default values, config isolation, theme and tooltip rendering, accessibility markup, asset paths, embedded versus standalone panels, connection credentials, report-mode restoration, and rendered entry points. It does not write real profiles, restart Apache, generate certificates, or export real data.

The isolated suite includes `php -n tests/apache.php`: sample vhost/hosts configurations, independent caches and fast modes, quoted config paths, simulated Windows/Linux/macOS restart selection, and restart-handler responses. It runs only benign PHP output commands to exercise exit-code/stderr capture and disabled-function fallbacks. Request fixtures use private temporary session directories and force garbage collection, avoiding the runner system-directory permission failure. No automated check restarts Apache.

Run `php tests/profiles.php` with OpenSSL enabled for temporary-file integration tests: defaults and overrides, independent profiles, credential round trips, historical ciphertext, key preservation, legacy migration, invalid profile data, partial-write rollback, and actual submit-handler success/rejection paths. These tests use temporary profile, session, and INI paths and do not contact a database.

For real MySQLi validation, enable the extension and run `php tests/mysqli.php --failure-only` to check refused connections against loopback port 1. To also test successful connections, supply `AMPBOARD_TEST_DB_HOST`, `AMPBOARD_TEST_DB_USER`, and `AMPBOARD_TEST_DB_PASSWORD` for a **disposable test database**, then run `php tests/mysqli.php`. CI provisions its own MySQL service for this check, alongside PHP lint, isolated tests, and profile integration on PHP 8.0, 8.2, 8.3, and 8.4 on Windows and Linux.

The fixture checks do not replace manual testing against XAMPP/LAMP/MAMP. Before merging, exercise saved settings, encrypted credentials, PHP INI changes, vhost/certificate operations, exports, and Apache restart in your normal development stack.

## Next increments

1. Move export and PHP-management workflows into services with explicit filesystem, database, and command dependencies. Keep thin HTTP handlers for validation and responses.
2. Separate page rendering from system probes, then migrate the remaining pure helper functions into focused namespaces. Remove compatibility constants only after all consumers have moved.

Avoid a service locator or static global config accessor: it would preserve the hidden dependencies under a new name. Each increment should retain the existing URLs and saved profiles until a separately documented migration is ready.
