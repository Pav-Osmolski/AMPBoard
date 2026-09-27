# PHP modernization: steps 1–9

This follows the central config migration beginning at `a54ac0d` and retains the behavior on `bb62f14`. The published releases, now copied into `CHANGELOG.md`, remain the source for historical changes. The frontend bundles and HTTP URLs are unchanged.

## Request composition

Pages continue to `require_once config/config.php`. That file loads the small `AMPBoard\` autoloader and remaining procedural helpers, then creates explicit dependencies:

| Variable | Responsibility |
| --- | --- |
| `$identity` | `System\Identity`, captures the request user and server label from supplied server data and a discovery callback. |
| `$directories` | `Filesystem\DirectoryCatalog`, lists dashboard folders under the supplied document root. |
| `$cipher` | `Security\CredentialCipher`, constructed with the existing key path. |
| `$profiles` | `Config\ProfileRepository`, responsible for profile data and persistence. |
| `$config` | Existing nested array, returned by `AMPBoard\Config\Loader`. |
| `$mysqlInspector` | `Database\Inspector`, collects read-only diagnostics through a supplied connection callback and client-version snapshot. |
| `$database` | `AMPBoard\Database\ConnectionFactory`, constructed from `$config['db']`. |
| `$apacheCommands` | `Apache\CommandRunner`, implemented by `ShellCommandRunner` in normal requests. |
| `$apacheControl` | `Apache\Controller`, constructed with the configured Apache path and OS. |
| `$vhosts` | `Apache\VhostCatalog`, constructed with the Apache path and hosts-file paths. |
| `$exports` | `Export\Workflow`, composed with folder, database, archive, command, and storage dependencies. |
| `$phpInfo` | `Php\InfoPage`, captures PHP-info output using the selected full/demo flags. |
| `$phpIni` | `Php\IniFile`, constructed with the loaded INI path captured during configuration. |
| `$ui` | `AMPBoard\Ui\Renderer`, constructed from the config snapshot. |
| `$serverInspector` | `System\ServerInspector`, collects header status through supplied Apache and database probes and PHP runtime metadata. |
| `$systemStatistics` | `System\Statistics`, receives platform, disk target, command runner, and measurement callback. |
| `$apacheLog`, `$phpLog` | `Logs\Viewer`, each constructed with candidate paths, a shared tail reader, and its excerpt policy. |

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

`ProfileReader` validates known setting names and types. `ProfileSchema` owns defaults and supported keys. `ProfileRepository` can resolve separate returned-array profiles in one process without publishing constants. `Loader` builds the dashboard array and asks `Php\Runtime` to apply runtime directives; this still changes process-wide PHP settings. PHP profile files remain trusted executable code, not a sandboxed configuration format.

Legacy variable/constant profiles and local overrides remain readable. Their existing PHP side effects still execute, so legacy profiles retain the one-profile-per-request limitation. Legacy local constants take precedence over profile constants; legacy local UI variables remain defaults that the profile may override. Returned-array local settings and PHP directives take precedence over the profile. Constants already defined before composition take precedence over both. Missing settings receive the same application defaults.

### Saving and migration

`partials/submit.php` retains the HTTP, origin, CSRF, and demo-mode guards. `SettingsInput` normalizes the form and validates all JSON before persistence. `ProfileRepository::save()` accepts that normalized data, encrypts nonempty supplied credentials, and writes a returned-array profile plus the three JSON sidecars. An existing legacy file migrates only when settings are successfully saved; installation and read-only requests do not rewrite it. Empty form fields retain their previous omit-and-use-default behavior.

Newly saved credentials use an explicit `['encrypted' => '...']` envelope. `CredentialCipher` keeps the existing AES-256-CBC format (base64 of IV plus ciphertext) and 64-character hexadecimal `.key` file. Existing ciphertext and keys need no conversion. Legacy unmarked strings retain the old plaintext/decryption fallback. Explicit encrypted values fail if decryption fails. Reading never creates a key; saving can create a missing key, but never replaces a malformed existing key. Keep the existing `.key` with profile backups. The procedural encryption helpers remain compatibility wrappers.

`AtomicFileWriter` stages files before replacement and serializes readers/writers with `.profile.lock`. Existing-profile saves roll back ordinary replacement failures; first saves publish a complete directory so a failed save does not shadow the default profile. This is **not** a crash-atomic transaction across four files: termination or storage failure during replacement/rollback can still require recovery from backup. Failed rollback retains available temporary recovery files. Optional php.ini editing remains best-effort after the profile save and is not part of that transaction.

All existing config-array sections remain. `ui.themes.colorScheme` is additive. `ui.tooltips.map` now receives the loaded tooltip JSON directly instead of reading an incomplete config during initialization.

Legacy constants (`APACHE_PATH`, `HTDOCS_PATH`, `PHP_PATH`, `DB_*`, `DEMO_MODE`, `EXPORT_EXCLUDE`, `CRYPTO_KEY_FILE`) remain for unconverted code. The compatibility entry point is therefore **not** a multi-profile container or a re-entrant configuration API. Remaining helpers still load through `config/helpers.php`.

The old UI free functions and the two database connection/status free functions are internal APIs and have been replaced at every repository call site. Custom PHP integrations that called them must use `$ui->renderHeading(...)`, `$database->connect(...)`, and `$database->credentialStatus(...)` after loading config. The former `normaliseDbServerInfo()` helper is now `Database\ServerVersion::normalise()`.

PHP 8.0 is the minimum dictated by existing `mixed` and union type declarations. This refactor does not introduce a higher syntax requirement. A supported PHP release is preferable for an actual installation. Composer installation is not required.

## Apache services (step 3)

`Apache\Inspector` receives the Apache path, command runner, and fast-mode flag. The inspector endpoint resolves the saved flag, query override, and demo-mode override before constructing it. No helper reads `$GLOBALS['fastMode']`; full and fast inspectors can coexist. Fast mode skips uptime, config/vhost commands, and `/proc/self/environ` reading, while retaining binary discovery as before. Environment/SAPI information still comes from the current PHP process.

`Apache\VhostCatalog` owns parsing and a per-instance snapshot used by both the folder filter and virtual-host manager. It receives hosts-file paths explicitly, so separate installations cannot reuse each other's cache. Duplicate names still use the last block and retain their duplicate marker. Certificate availability still follows the existing managed `crt/{servername}/server.crt` and `server.key` convention; this is an existence check, not certificate-chain validation. Include directives remain display-only and are not recursively expanded.

`Apache\Controller` selects the existing Windows, Linux, or macOS restart strategy. `restartCommand()` may run diagnostic commands to choose that strategy but never executes the restart itself; `restart()` executes it. The HTTP endpoint retains its action/demo guards, status codes, and JSON response fields. Config construction does not run Apache commands. Certificate generation remains a separate procedural endpoint; log reading is covered by step 7 below.

`Apache\CommandRunner` is the injection boundary for diagnostics and control. `ShellCommandRunner` captures combined output and exit status using `exec()`, or `proc_open()` with a temporary stream when `exec()` is disabled. If neither is available, execution reports failure rather than claiming success. It accepts trusted commands built by application code, **not raw request input**. Binary paths are quoted; Windows control paths containing shell-expansion characters are rejected. Platform restart permissions and tools are still installation-specific, and command success means the command exited successfully, not that a subsequent health check passed.

The old `config/helpers/apache.php` free functions have been removed after migrating every repository caller. Custom integrations must construct `Apache\Inspector` for diagnostics, use `$apacheControl->restart()`, and use `$vhosts->getVhostServerData()` or `$vhosts->isValidVhostHost(...)`. These services do not read `APACHE_PATH` or other compatibility constants. `getIniFilesInfo()` returns raw runtime information; the view applies display obfuscation.

The extraction also fixes binary discovery treating directories as executables, joining already-absolute config paths to `HTTPD_ROOT`, and treating inline hosts-file comments as host aliases.

## Export services (step 4)

`Export\FolderCatalog` receives the configured document root and folder-group snapshot. `Export\FileSelection` applies exclusions and the WordPress uploads modes once for both archive engines. Folder requests must match the filtered catalog; symbolic links are skipped, excluded directories are pruned before traversal, and excluding uploads no longer also excludes `uploads-cache`.

`Export\DatabaseExporter` receives a connection callback and closes connections on success and failure. A failed query now aborts the export rather than publishing an incomplete dump. The existing dump scope remains base tables and their rows, buffered in memory in batches of 200 inserts; views, triggers, routines, events, and transactional snapshot consistency are not added by this refactor.

`Export\ArchiveWriter` produces ZIP, with Phar TAR.GZ/TAR when ZipArchive is unavailable. `Export\ExternalArchiver` discovers 7-Zip or system zip in supplied search directories and uses the same selected files. Only physically empty directories are listed for external tools, so directories emptied by exclusions may be absent. Unsupported manifest filenames fall back to PHP. `Export\NativeProcessRunner` uses argument arrays and an explicit working directory through `proc_open()`, without changing the PHP process directory. If external execution is unavailable or fails, the workflow removes partial output before trying PHP.

`Export\Workflow` builds each archive in a private temporary directory, including intermediate SQL and manifests, then stages and renames the completed archive under `dist/exports`. Names retain their familiar prefix and timestamp with an additional random suffix to prevent same-second collisions. Ordinary failures clean up temporary files; abrupt process termination or storage failure can still leave files requiring cleanup. Published downloads retain their existing access and retention behavior.

`utils/export_files.php` retains request validation, demo/CSRF checks, and the existing response fields. The former `config/helpers/export.php` functions have been removed; custom integrations should use `$exports->scan()`, `databases()`, `files(...)`, and `dump(...)` after loading config. Configuration construction performs no export, database query, or external command.

## PHP management services (step 5)

`Php\SettingsInput` owns the existing PHP-manager field normalization, including separate profile/runtime and INI values. `Config\SettingsInput` delegates to it before persistence. The supported fields, defaults, size/integer normalization, error-level choices, and runtime-only `log_errors` behavior remain unchanged. Multiline error-reporting overrides are rejected before any settings write. The former `normaliseIniIntOption()` and `normaliseIniSizeOption()` helpers are replaced by `Php\SettingsInput::integer()` and `::size()`.

`Php\Runtime` owns process-wide directive application and inspection. The loader captures effective values after applying the profile and supplies version, SAPI, thread safety, loaded INI, and scanned INI metadata under `$config['php']['runtime']`. The renderer uses that snapshot; its fallback for integrations supplying older configuration arrays uses the runtime service. `Php\InfoPage` captures and strips the existing phpinfo layout while preserving full/demo flags. This layout processing is not a data-redaction or HTML-security boundary.

`Php\IniFile` receives a target path, updates allowlisted directives in ordinary sections, preserves `[PATH=...]`/`[HOST=...]` overrides, and inserts missing global directives before sections. Commented examples and unrelated lines are retained; changed directive lines are rewritten. It stages the complete file beside the original and renames it only after a successful write, preserving Unix permission bits. The containing directory must therefore be writable. A persistent `.ampboard.lock` sidecar serializes cooperating AMPBoard writers; external editors do not participate. Replacement can inherit directory ownership/ACLs, and storage failures or abrupt termination can leave staging files. This is not a backup system.

The submit handler retains the explicit `php_ini_path` override and otherwise uses the captured loaded INI path. `PHP_PATH` remains the existing dashboard path setting; changing it does not select another runtime or INI target. INI writes remain best-effort after profile persistence, with the same success redirect even when the optional edit fails. `Config\PhpSettings` remains a compatibility facade for `apply()` and `patch()`.

AMPBoard currently has no installed-version catalog or PHP-switching workflow. This increment extracts existing settings and diagnostics; adding version switching would be a separate feature with platform-specific requirements.

## System inspection services (step 6)

The header explicitly calls `$serverInspector->inspect()` and supplies its snapshot to `$ui->renderServerInfo($snapshot)`. Renderer construction now takes only the configuration array. Header rendering no longer discovers binaries, reads runtime globals, executes commands, or opens database connections. It retains the same labels, links, status classes, and symbols, escaping version/error labels at the HTML boundary.

`Apache\VersionProbe` owns the existing header-specific binary discovery and version parsing. It receives the Apache path, platform, server-software string, and existing `Apache\CommandRunner`. It quotes binary paths, ignores unsuccessful command output, reads multiple Windows discovery results, and treats directories as invalid binaries. Windows paths with shell-expansion characters are rejected. It only requests version information; it does not restart Apache. The fuller Apache inspector remains a separate diagnostic workflow.

`System\ServerInspector` receives a connection callback and a PHP runtime snapshot. It normalizes database version labels through `Database\ServerVersion` and closes returned connections in a `finally` block, including connection-error results. Probe exceptions become unavailable display results. Construction is lazy and rendering a supplied snapshot performs no probes. Existing credential validation during config loading remains unchanged.

`System\Statistics` receives the OS family, disk path, command runner, and measurement callback; `System\NativeMetrics` supplies native measurements in normal requests. Composition retains `/` as the disk target. Windows uses the existing typeperf command; Unix uses the existing load-average/core-count calculation and raw-load fallback when core discovery fails. Memory is PHP process peak allocation in MiB, not host RAM usage; disk is free capacity as a percentage. These historical meanings and frontend labels are retained, including percentage suffixes on the raw Unix fallback and `N/A` values. Missing, zero-capacity, negative, or nonfinite readings produce `N/A` instead of invalid JSON or division errors.

The statistics endpoint retains the enable guard, JSON fields, cache headers, HTML IDs, and AJAX/embedded switch. A disabled endpoint exits before sampling. Frontend polling and bundles are unchanged. These services reuse the existing command runner rather than adding another shell implementation. Custom integrations must supply a server snapshot to `renderServerInfo()` and use `Database\ServerVersion::normalise()` instead of the removed MySQL helper.

## Log services (step 7)

`Logs\Paths::apache()` constructs the existing platform-specific candidate list from an explicit Apache path, OS family, and home directory. PHP's configured `error_log` is captured by `Php\Runtime` and supplied to its viewer. No service reads `APACHE_PATH`, runtime INI, or server globals. Construction does not open any log. Discovery remains candidate-based: it does not parse Apache includes or access system logging services.

`Logs\Viewer` chooses the first regular file in the supplied list, preserving priority and allowing normal filesystem symlinks. Missing/non-file targets retain the existing not-configured message; a failed read gets a concise unreadable message. `Logs\TailReader` opens read-only, reads backwards in 8 KiB blocks, and closes the handle on every path. Apache retains its last five physical lines and line endings. PHP retains its historical last-25 selection: empty LF segments do not count, while whitespace-only segments count and are filtered afterward. PHP output still has no added trailing newline.

Reads are capped at 1 MiB per excerpt. If that limit prevents a complete selection, a visible truncation notice is added and a partial leading line is dropped when a subsequent complete line exists. A single oversized line is shown as a bounded suffix with the notice. Log growth after opening is outside the captured size; rotation/truncation may yield an unavailable result until the next poll. Reading neither locks nor changes the server log.

The endpoints retain their enable guards, missing-log messages, markup IDs, and cache headers. AJAX mode now follows `useAjaxForErrorLog` independently of system statistics, and both endpoints serve raw plain text. Embedded dashboard panels now read their initial content when log AJAX is disabled. Log text is escaped in embedded HTML, including invalid UTF-8 substitution, and the frontend uses `textContent` for fetched entries instead of interpreting them as HTML. Toggle behavior, the empty-log message, and three-second refresh are retained. The former endpoint-local `tail_log()` function is removed; integrations should use `TailReader::read()` or the composed viewers.

## Identity and filesystem services (step 8)

`System\Identity` receives a server-data snapshot and a `whoami` callback. Composition creates it once before loading profiles; `Config\Loader` receives that identity explicitly, and the submit handler saves with the same captured user. The header consumes the captured label under `$config['system']['serverLabel']`. Changing server variables later in the request cannot redirect profile saving. Demo mode still changes only the displayed username.

Username precedence remains `USERNAME`, then `USER`, then trimmed command output. An explicitly empty variable still becomes `Guest` without trying another source. Domain prefixes and email-style suffixes retain their existing handling. The previously written `get_current_user()` fallback was unreachable; it is deliberately not activated, because doing so could select a different profile. Server labels retain the existing loopback/private-address heuristic; they are display metadata, not an authorization check.

`Filesystem\Path::normalise()` retains existing separator and trailing-slash rules, including historical empty/root results. `Filesystem\FolderName::sanitise()` retains transliteration, punctuation trimming, and the exact reserved-name policy used by existing profiles. This includes the historical treatment of names such as `CON.txt`; the refactor does not rename profile directories or introduce a new naming scheme. Configuration defaults, settings normalization, and repository reads/writes now use these classes directly.

`Filesystem\DirectoryCatalog` receives the dashboard document root instead of reading `HTDOCS_PATH`. It preserves relative path handling, the existing rejection of any `..` substring, natural case-insensitive folder ordering, and symlink visibility. Missing/non-directory targets and directories that cannot be opened return an empty list. It does not canonicalize symlink targets and is not an export containment boundary; exports retain their separate selection policy.

For custom integrations, construct `Config\Loader($directory, $identity, $profiles)` with a `System\Identity` as the second argument; the repository remains optional. Existing entry points through `config/config.php` work as before. Procedural identity, path, and filesystem helpers remain compatibility wrappers for trusted executable profiles and integrations. The unused legacy OS-flags helper also remains available. Application consumers use explicit services; compatibility constants cannot yet be removed.

`tests/identity-filesystem.php` runs inside the isolated suite. It checks identity precedence and snapshot isolation, server labels, stable profile names, independent roots and profile loaders, natural sorting, missing targets, traversal rejection, and the compatibility wrappers. The submit fixture changes the username after creating the identity and verifies that saving still targets the original profile. All filesystem fixtures use temporary directories.

## MySQL inspection services (step 9)

`Database\Inspector` receives a callback that returns a fresh connection for each `inspect($fastMode)` call, plus the client-library version captured at composition. Construction performs no connection or query. Each inspection closes its returned connection in `finally` and frees every obtained result, including failed reads. It does not change MySQLi reporting flags: query failures returned as `false`, warnings, or exceptions become unavailable sections. Other sections continue, and an unavailable size is not reported as zero. Database identifiers from `SHOW DATABASES` have embedded backticks doubled before use in table-status queries.

The raw snapshot retains the existing diagnostic scope: server/client/host/user information, non-system databases, approximate table data/index sizes, version variables, uptime, and the process list. Fast mode skips only per-database table-status queries. These are read-only queries and a point-in-time diagnostic view, not a consistent transaction snapshot. Full mode may still be expensive on large installations; output is collected in memory.

`Ui\MysqlReport` formats supplied data without querying MySQL or reading globals. It keeps the existing labels, ordering, size/uptime formatting, and demo masking of host/user/database names, while escaping HTML and substituting invalid UTF-8. Demo masking retains its historical scope: it is not complete redaction of diagnostic values or process SQL. Connection errors retain the existing visible error message, now with a closed `<pre>` element; individual query failures receive concise section-specific messages.

`utils/mysql_inspector.php` remains the same URL. It resolves the saved fast-mode flag, the `?fast=1`/`?fast=0` override, and the demo-mode override before invoking the service. Integrations can call `$mysqlInspector->inspect($fastMode)` after loading config and pass the snapshot to `Ui\MysqlReport::render($snapshot, $demo, $elapsed)`. Configuration's existing credential validation remains separate.

`tests/mysql-inspection.php` uses supplied connection/results to check full/fast modes, false/warning/exception/read failures, result and connection cleanup, quoted database names, null fields, escaping, and actual endpoint policy. CI additionally runs `tests/mysql-inspection-mysqli.php` against its disposable MySQL service: it creates and removes a randomly named database containing a backtick and verifies full/fast inspection, unchanged report modes, and connection closure. Do not run this integration test against a production service.

## Validation

Run `php -n tests/run.php`. Each scenario gets a fresh process because legacy profiles define constants. The suite uses a deterministic MySQLi double, temporary profiles, and read-only request fixtures. It checks profile fallback and overrides, false/default values, config isolation, theme and tooltip rendering, accessibility markup, asset paths, embedded versus standalone panels, connection credentials, report-mode restoration, and rendered entry points. It does not write real profiles, restart Apache, generate certificates, or export real data.

The isolated suite includes `php -n tests/apache.php`: sample vhost/hosts configurations, independent caches and fast modes, quoted config paths, simulated Windows/Linux/macOS restart selection, and restart-handler responses. It runs only benign PHP output commands to exercise exit-code/stderr capture and disabled-function fallbacks. Request fixtures use private temporary session directories and force garbage collection, avoiding the runner system-directory permission failure. No automated check restarts Apache.

Run `php tests/profiles.php` with OpenSSL enabled for temporary-file integration tests: defaults and overrides, independent profiles, credential round trips, historical ciphertext, key preservation, legacy migration, invalid profile data, partial-write rollback, and actual submit-handler success/rejection paths. These tests use temporary profile, session, and INI paths and do not contact a database.

For real MySQLi validation, enable the extension and run `php tests/mysqli.php --failure-only` to check refused connections against loopback port 1. To also test successful connections, supply `AMPBOARD_TEST_DB_HOST`, `AMPBOARD_TEST_DB_USER`, and `AMPBOARD_TEST_DB_PASSWORD` for a **disposable test database**, then run `php tests/mysqli.php`. CI provisions its own MySQL service for this check, alongside PHP lint, isolated tests, and profile integration on PHP 8.0, 8.2, 8.3, and 8.4 on Windows and Linux.

The isolated suite also runs `tests/php-management.php` using temporary INI files, injected PHP-info output, and process-local runtime settings. It checks scoped overrides, duplicate directives, line endings, failed replacement cleanup, and independent targets. Submit fixtures check default/explicit INI targets, best-effort failure after profile save, and rejection of multiline overrides. No installed PHP configuration is edited.

The fixture checks do not replace manual testing against XAMPP/LAMP/MAMP. Before merging, exercise saved settings, encrypted credentials, PHP INI changes, vhost/certificate operations, exports, and Apache restart in your normal development stack.

Run `php -d phar.readonly=0 tests/exports.php` with ZIP and Phar enabled for fixture-only archive contents, uploads modes, SQL batching, command fallback, cleanup, and actual export-handler responses. Installed external archivers are exercised against temporary fixtures. CI also runs `tests/export-mysqli.php` against its disposable MySQL service: it creates a randomly named database, exports and restores 201 rows including NULL, Unicode, and binary values, compares them, and removes the fixture database. Never point this integration test at a production server.

## Next increments

1. Extract certificate generation, then review remaining diagnostic presentation, template, request, and serialization helpers. Remove compatibility constants only after all consumers have moved.
2. Consider PHP version discovery/switching separately if desired; it is not an existing workflow awaiting extraction.

Avoid a service locator or static global config accessor: it would preserve the hidden dependencies under a new name. Each increment should retain the existing URLs and saved profiles until a separately documented migration is ready.
