# PHP modernization: step 1

This follows the central config migration beginning at `a54ac0d` and retains the behavior on `bb62f14`. The published releases, now copied into `CHANGELOG.md`, remain the source for historical changes. The frontend bundles and HTTP URLs are unchanged.

## Request composition

Pages continue to `require_once config/config.php`. That file loads the small `AMPBoard\` autoloader and remaining procedural helpers, then creates three explicit dependencies:

| Variable | Responsibility |
| --- | --- |
| `$config` | Existing nested array, returned by `AMPBoard\Config\Loader`. |
| `$database` | `AMPBoard\Database\ConnectionFactory`, constructed from `$config['db']`. |
| `$ui` | `AMPBoard\Ui\Renderer`, constructed from the config and database factory. |

The renderer owns a config snapshot. Methods no longer read `global $config`, and separate renderers can use separate settings. Theme metadata and body classes live in `Ui\ThemeCatalog`, which takes an asset directory. Database connections use supplied credentials rather than `$dbUser`, `$dbPass`, or `DB_HOST`. Credential validation runs once when config loads. Both connection modes restore the caller's actual MySQLi report flags, including on failure.

`bootstrap.php` still starts the session before rendering and runs the submit handler. Utility URLs retain their existing entry points. Settings sets `$settingsView` explicitly for included panels; rendering an accordion no longer changes a global flag or defines `SETTINGS_VIEW`.

## Compatibility boundary

`Loader::load()` runs once per request. It resolves the same current user, selects the user profile directory or the default directory, loads `local.php` before the profile, and preserves existing defaults and decryption helpers. Local and profile PHP files execute in the loader's scope, so their temporary variables do not spill into views. Their constants and `ini_set()` effects still work. Generated profiles and the settings writer keep their existing format; no profile or encryption-key migration is required.

All existing config-array sections remain. `ui.themes.colorScheme` is additive. `ui.tooltips.map` now receives the loaded tooltip JSON directly instead of reading an incomplete config during initialization.

Legacy constants (`APACHE_PATH`, `HTDOCS_PATH`, `PHP_PATH`, `DB_*`, `DEMO_MODE`, `EXPORT_EXCLUDE`, `CRYPTO_KEY_FILE`) remain for unconverted code. The loader is therefore **not** a multi-profile container or a re-entrant configuration API. Remaining helpers still load through `config/helpers.php`.

The old UI free functions and the two database connection/status free functions are internal APIs and have been replaced at every repository call site. Custom PHP integrations that called them must use `$ui->renderHeading(...)`, `$database->connect(...)`, and `$database->credentialStatus(...)` after loading config. The pure `normaliseDbServerInfo()` helper remains procedural.

PHP 8.0 is the minimum dictated by existing `mixed` and union type declarations. This refactor does not introduce a higher syntax requirement. A supported PHP release is preferable for an actual installation. Composer installation is not required.

## Validation

Run `php -n tests/run.php`. Each scenario gets a fresh process because legacy profiles define constants. The suite uses a deterministic MySQLi double, temporary profiles, and read-only request fixtures. It checks profile fallback and overrides, false/default values, config isolation, theme and tooltip rendering, accessibility markup, asset paths, embedded versus standalone panels, connection credentials, report-mode restoration, and rendered entry points. It does not write real profiles, restart Apache, generate certificates, or export real data.

For real MySQLi validation, enable the extension and run `php tests/mysqli.php --failure-only` to check refused connections against loopback port 1. To also test successful connections, supply `AMPBOARD_TEST_DB_HOST`, `AMPBOARD_TEST_DB_USER`, and `AMPBOARD_TEST_DB_PASSWORD` for a **disposable test database**, then run `php tests/mysqli.php`. CI provisions its own MySQL service for this check, alongside PHP lint and isolated tests on Windows and Linux.

The fixture checks do not replace manual testing against XAMPP/LAMP/MAMP. Before merging, exercise saved settings, encrypted credentials, PHP INI changes, vhost/certificate operations, exports, and Apache restart in your normal development stack.

## Next increments

1. Extract profile reading/writing and credential encryption behind explicit dependencies. Introduce a returned-array profile format with a legacy reader before removing constants.
2. Extract Apache inspection and control from procedural helpers. Pass paths and fast-mode options explicitly, replacing the remaining `$GLOBALS['fastMode']` dependency. Keep platform commands behind a testable boundary.
3. Move export and PHP-management workflows into services with explicit filesystem, database, and command dependencies. Keep thin HTTP handlers for validation and responses.
4. Separate page rendering from system probes, then migrate the remaining pure helper functions into focused namespaces. Remove compatibility constants only after all consumers have moved.

Avoid a service locator or static global config accessor: it would preserve the hidden dependencies under a new name. Each increment should retain the existing URLs and saved profiles until a separately documented migration is ready.
