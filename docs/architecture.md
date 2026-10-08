# PHP modernization: steps 1–24 (complete)

This follows the central config migration beginning at `a54ac0d` and retains the behavior on `bb62f14`. The published releases, now copied into `CHANGELOG.md`, remain the source for historical changes. The frontend bundles and HTTP URLs are unchanged.

## Request composition

Pages and utilities use `config/entry-*.php` to compose their required dependencies. `config/config.php` remains the complete compatibility composition for existing PHP integrations. Both paths use the small `AMPBoard\` autoloader:

| Variable | Responsibility |
| --- | --- |
| `$requestOrigin` | `Http\RequestOrigin`, compares supplied request headers with host, scheme, and port. |
| `$session` | `Http\SessionStore`, implemented by the lazy `NativeSession` adapter. |
| `$csrfTokens` | `Security\CsrfToken`, issues and rotates form tokens through the supplied session. |
| `$identity` | `System\Identity`, captures the request user and server label from supplied server data and a discovery callback. |
| `$jsonReader` | `Filesystem\JsonReader`, shared tolerant JSON reads for profiles and interface metadata. |
| `$folderOpener` | `Filesystem\FolderOpener`, validates directories and invokes a supplied platform launcher. |
| `$directories` | `Filesystem\DirectoryCatalog`, lists dashboard folders under the supplied document root. |
| `$cipher` | `Security\CredentialCipher`, constructed with the existing key path. |
| `$profiles` | `Config\ProfileRepository`, responsible for profile data and persistence. |
| `$config` | Existing nested array, returned by `AMPBoard\Config\Loader`. |
| `$mysqlInspector` | `Database\Inspector`, collects read-only diagnostics through a supplied connection callback and client-version snapshot. |
| `$database` | `AMPBoard\Database\ConnectionFactory`, constructed from `$config['db']`. |
| `$databaseObservation` | `Database\Observation`, shares one closed-connection diagnostic result between credential indicators and header status. |
| `$apacheCommands` | `Apache\CommandRunner`, implemented by `ShellCommandRunner` in normal requests. |
| `$certificates` | `Certificates\Generator`, uses explicit Apache/script paths, platform, installer, and command runner. |
| `$apacheControl` | `Apache\Controller`, constructed with the configured Apache path and OS. |
| `$vhosts` | `Apache\VhostCatalog`, constructed with the Apache path and hosts-file paths. |
| `$exports` | `Export\Workflow`, composed with folder, database, archive, command, and storage dependencies. |
| `$phpInfo` | `Php\InfoPage`, captures PHP-info output using the selected full/demo flags. |
| `$phpIni` | `Php\IniFile`, constructed with the loaded INI path captured during configuration. |
| `$folderPresenter` | `Ui\FolderPresenter`, prepares folder columns through explicit directory, template, rule, and vhost dependencies. |
| `$demoMask` | `Ui\DemoMask`, applies historical display masks using the captured demo flag. |
| `$ui` | `AMPBoard\Ui\Renderer`, constructed from the config snapshot. |
| `$serverInspector` | `System\ServerInspector`, collects header status through supplied Apache and database probes and PHP runtime metadata. |
| `$systemStatistics` | `System\Statistics`, receives platform, disk target, command runner, and measurement callback. |
| `$apacheLog`, `$phpLog` | `Logs\Viewer`, each constructed with candidate paths, a shared tail reader, and its excerpt policy. |

The renderer owns config and script-name snapshots. Methods no longer read `global $config`, and separate renderers can use separate settings. Theme metadata and body classes live in `Ui\ThemeCatalog`, which takes an asset directory. Database connections use supplied credentials rather than `$dbUser`, `$dbPass`, or `DB_HOST`. Dashboard/settings credential diagnostics and the header share one request observation; configuration-only/narrow utility composition does not probe credentials. Both connection modes restore the caller's actual MySQLi report flags, including on failure.

`bootstrap.php` still starts the session before rendering and runs the submit handler. Utility URLs retain their existing entry points. Settings sets `$settingsView` explicitly for included panels; rendering an accordion no longer changes a global flag or defines `SETTINGS_VIEW`.

## Compatibility boundary

`config/config.php` remains a once-per-request compatibility entry point. `config/legacy.php` explicitly loads helpers before trusted profiles execute (unless `AMPBOARD_NO_HELPERS` is defined), initializes configuration through `config/application.php`, and publishes the resolved legacy settings. The repository reads `local.php`, selects the current user's profile directory or the default directory, and reads the PHP profile and JSON sidecars under a shared profile lock. The settings reader and export workflow use that loaded JSON snapshot.

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

`Apache\Controller` selects the existing Windows, Linux, or macOS restart strategy. `restartCommand()` may run diagnostic commands to choose that strategy but never executes the restart itself; `restart()` executes it. The HTTP endpoint retains its action/demo guards, status codes, and JSON response fields. Config construction does not run Apache commands. Certificate generation is covered by step 10; log reading is covered by step 7 below.

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

The header explicitly calls `$serverInspector->inspect()` and supplies its snapshot to `$ui->renderServerInfo($snapshot)`. Renderer construction accepts configuration and an optional script-name snapshot. Rendering a supplied header snapshot no longer discovers binaries, reads runtime globals, executes commands, or opens database connections. It retains the same labels, links, status classes, and symbols, escaping version/error labels at the HTML boundary.

`Apache\VersionProbe` owns the existing header-specific binary discovery and version parsing. It receives the Apache path, platform, server-software string, and existing `Apache\CommandRunner`. It quotes binary paths, ignores unsuccessful command output, reads multiple Windows discovery results, and treats directories as invalid binaries. Windows paths with shell-expansion characters are rejected. It only requests version information; it does not restart Apache. The fuller Apache inspector remains a separate diagnostic workflow.

`System\ServerInspector` receives a connection callback, a PHP runtime snapshot, and an optional shared `Database\Observation`. Application composition supplies the observation, so credential indicators and database version/status share one attempt and its closed-connection result. Existing three-argument integrations retain their independent, fresh header probes. Database labels use `Database\ServerVersion`; failed probes become unavailable display results. Construction is lazy and rendering a supplied snapshot performs no probes.

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

## Certificate services (step 10)

`Certificates\Generator` receives the Apache installation path, bundled script directory, platform, and existing `Apache\CommandRunner`. Construction performs no writes or commands. `generate($name)` validates an ASCII hostname (including localhost and punycode labels) before touching the filesystem. Empty labels, traversal, whitespace, wildcards, trailing dots, leading/trailing label hyphens, overlong names, and Windows device-name directories are rejected rather than silently stripped or renamed. Valid names retain their spelling and case.

`Certificates\ScriptInstaller` preserves the existing missing/outdated script update policy, including prompt variants. It stages each copy beside its target before replacement and stops on copy/replace failure; this is not a transaction across all scripts. Newer installed scripts remain unchanged. A persistent `.ampboard-cert.lock` in Apache's `crt` directory serializes cooperating requests because the existing scripts share `cert.conf` and `cert.log`. Busy requests fail rather than waiting. The lock is released on exceptions; external tools do not participate.

Windows retains PowerShell preference and BAT fallback; Linux and macOS use Bash. Commands quote paths and domains and use the existing exit-status-aware runner. Windows paths containing shell/script expansion characters are rejected before writes. Native tests run only benign temporary scripts with spaces in their paths, never installed certificate scripts. Script exit status determines success; a successful exit is not independent certificate or trust-store verification.

The existing scripts, OpenSSL/template requirements, certificate locations, validity period, and certificate contents remain unchanged. In particular, `cert-template.conf` must already be supplied by the installation; this increment does not create it or install trust roots. Script failures can still leave partial certificate output, and this extraction does not introduce certificate backup or rollback.

`utils/generate_cert.php` retains its URL, GET parameter, demo guard, and plain-text response. It now uses composed configuration/services instead of `APACHE_PATH` and `DEMO_MODE`, returns 400 for invalid names, and 500 for installation or command failure. The frontend retains its confirmation and success-text restart rule, but now requires a successful HTTP response before requesting restart. The JavaScript bundle was rebuilt. Custom integrations can call `$certificates->generate($name)` and inspect `success` and `output`; validation/setup errors throw exceptions.

The isolated suite includes `tests/certificates.php`: platform selection, domain rejection before writes, script update policy, failed copies/replacements, missing directories/scripts, lock contention/release, exit status, and actual endpoint responses. `node tests/cert-ui.cjs` covers cancelled requests, network failures, output display, and restart gating. All certificate fixtures live in temporary folders.

## Folder presentation services (step 11)

`Ui\FolderPresenter` receives the folder-column snapshot, `Folders\LinkTemplates`, `Filesystem\DirectoryCatalog`, and a vhost-validation callback. Construction does no directory reads or vhost discovery. `prepare()` resolves directories, applies exclusions and URL rules, checks template hosts only for eligible vhost-filtered entries, and returns prepared columns, rendered items, states, and plain-text diagnostics. Separate instances keep separate profiles and roots. The panel renders that view without discovering folders or applying filters.

`Folders\UrlRules` preserves the existing rule order: strict basename exclusions occur first in the presenter; `urlRules.match` filters candidates; `urlRules.replace` is a second regex whose matches are removed with an empty replacement; then `specialCases` maps the transformed name. It does not introduce conventional regex replacement strings. Nonmatches and the historical `__SKIP__` sentinel remain excluded; empty special-case values remain valid. Incomplete or invalid patterns retain the original folder and produce warnings. Regex operations temporarily suppress their own warnings and restore the caller's error handler, including with a throwing handler.

`Folders\LinkTemplates` indexes the loaded template snapshot with last-duplicate-wins semantics. Resolution keeps named-template, `basic`, and built-in fallbacks. `{urlName}` is HTML-escaped with invalid UTF-8 substitution. Disabled links retain the existing `strip_tags` allowlist. Templates remain trusted profile HTML: this is not a general HTML sanitizer, URL scheme validator, or URL encoder. Host extraction preserves the existing quoted-`href` regex and raw placeholder substitution, returning unique lowercase parsed hosts. It is a display-filter heuristic, not a complete HTML parser or security boundary.

Column order, natural folder order, strict exclusions, vhost-any-match behaviour, badges, width controls, IDs, warning wording, and all three configuration-empty states remain. Missing and physically empty directories still display their existing messages. A directory with entries that are all excluded or filtered still renders an empty list rather than a new no-projects warning. Diagnostics are escaped once at the view boundary and deduplicated as before.

Custom integrations can call `$folderPresenter->prepare()` after composition. The procedural template helpers remain compatibility wrappers: `build_url_name()` retains its sentinel and accumulated HTML-safe error strings, while the namespaced rule service returns nullable names and plain-text errors. Saved profiles and frontend bundles need no migration.

`tests/folder-presentation.php` covers rule order, malformed patterns, handler restoration, template fallbacks, escaping, host extraction, combined exclusion/rule/vhost filters, independent views, disabled links, directory and configuration-empty states, and the actual panel markup. All directory and panel fixtures are temporary; no installed vhosts or profiles are changed.

## Apache report presentation (step 12)

`Apache\Inspector::inspect($os, $architecture)` collects a raw snapshot for one report. It discovers the binary once, includes full-only config/include/vhost and uptime probes when requested, and captures environment and INI metadata. Existing individual probe methods remain available. Construction remains lazy; inspection reads the current PHP process through the existing probe methods. Separate inspector instances retain independent fast modes and paths.

`Ui\ApacheReport::render($snapshot, $demo)` formats only supplied data without commands, filesystem reads, runtime inspection, or globals. It retains labels, section order, unavailable messages, and fast-mode omissions. All diagnostic strings and keys are HTML-escaped with invalid UTF-8 substitution. Demo mode retains its historical masking of only the loaded php.ini value; it does not redact the rest of the report.

The endpoint keeps the saved fast flag, query override, and demo-mode override at the HTTP boundary, using the loaded config snapshot instead of `DEMO_MODE`. Its URL and heading remain unchanged. Failed command output is now discarded instead of being parsed as a successful result, and exceptions from independent probes become their existing unavailable values so other sections can still render. Include directives remain display-only, without recursive expansion; inspection does not restart or reconfigure Apache.

`tests/apache-report.php` checks raw snapshots, independent fast/full modes, failed and throwing commands, escaping, invalid UTF-8, masking, unavailable sections, and rendering after source changes. Existing endpoint fixtures now check saved mode, both query overrides, demo forcing, and escaped output. All fixtures use supplied commands and temporary files, never installed Apache executables.

## JSON services (step 13)

`Config\Json` owns strict form JSON normalization and profile serialization. It retains associative decoding, array/object root acceptance, empty-input defaults, pretty printing, unescaped slashes, escaped Unicode, and existing numeric formatting. Empty JSON objects and sequential numeric object keys still follow PHP's associative-array encoding semantics; this increment does not introduce a schema or preserve object identity. Invalid syntax, UTF-8, depth, or encoding fails before profile persistence.

`Filesystem\JsonReader` owns tolerant sidecar and interface reads. Its per-instance read and diagnostic callbacks are optional; the native reader checks for a readable regular file and treats failed reads as empty data. Missing, unreadable, empty, malformed, and scalar-root files retain the existing empty-array fallback. Decode diagnostics retain their basename-only wording, including the historical scalar-root `No error` suffix. File contents and full paths are not added to diagnostics. Native read warnings are suppressed so a read failure can use the documented fallback. Construction performs no reads.

Composition shares a reader between the profile repository and loader. `ProfileRepository` accepts it as an optional fifth constructor argument; `Loader` accepts it as an optional fourth argument and passes it to a default repository. Existing constructor calls remain valid. Repository sidecars still read under the existing profile lock; interface files retain their independent reads. No shared cache is introduced.

`SettingsInput` and `ProfileRepository::save()` use the strict formatter directly. The settings input retains its canonical encode/decode round trip, including integer conversion of `1.0`. Profile files, JSON sidecar formats, locking, migration, and settings URLs remain unchanged. The procedural JSON helpers delegate to the services for trusted legacy profiles and custom integrations.

`tests/json-services.php` checks strict formatting and rejection, tolerant reads and diagnostics, supplied read failures, independent loader/repository snapshots without JSON helpers, settings normalization, serialization failure before writes, and compatibility wrappers. All files are temporary. Existing profile integration continues to cover saved sidecar round trips and rejection of invalid form JSON.

## Request, session, and CSRF services (step 14)

`Http\RequestOrigin` captures an explicit server snapshot. It compares parsed HTTP(S) scheme, host, and effective port for every supplied Origin/Referer header. Matching explicit ports (including IPv6 and default ports) now work; mismatched ports, malformed URLs, user information, and invalid host authorities are rejected. The existing policy permitting absent Origin and Referer is retained. Forwarded headers are not used. This check does not provide authentication.

`Http\SessionStore` separates CSRF state from PHP session operations. `NativeSession` starts lazily, retains the HTTPS/HttpOnly/SameSite=Lax cookie policy and existing lifetime/path/domain, and reports failed starts or headers already sent. Bootstrap starts the composed adapter before profiles and rendering; loading `config/config.php` directly only constructs it. Active sessions are reused without changing their cookie parameters. Successful settings saves still regenerate the session ID after persistence and retain their existing redirect behavior if regeneration fails.

`Security\CsrfToken` receives a session store and optional random-byte generator. Tokens remain 32 random bytes encoded as 64 hex characters, stored under `csrf_token`, reused during rendering, and rotated after successful verification. Null, empty, malformed stored tokens, unavailable sessions, failed storage, and entropy failures cannot authorize a request. Failed verification does not rotate; failed generation leaves an existing token unchanged. Native PHP session-file durability remains the session handler's responsibility; the adapter cannot independently guarantee writes at shutdown.

Settings forms and saves, export forms/actions/token responses, and the config-reader origin guard use the composed services. Existing URLs, fields, response shapes, demo guards, and export method policy remain. The export token action still returns the current token (an empty string if unavailable). Legacy `csrf_get_token()`, `csrf_verify()`, and `request_is_same_origin()` delegate to the same policies and native session for custom integrations. No service locator or global config lookup is introduced.

`tests/request-services.php` checks independent snapshots/stores, default/custom/IPv6 ports, malformed headers, replay rejection, token repair, unavailable storage, rotation/entropy failures, real temporary session persistence, cookie policy, ID regeneration, and compatibility wrappers. The actual config reader runs without security helpers. Submit/export integration includes native session-start failures, and submit integration covers a matching custom port. No installed profiles or session paths are changed.

## Demo presentation and constant audit (step 15)

`Ui\DemoMask` receives an explicit boolean and returns either the supplied string or the historical asterisk mask: 4 characters for byte lengths up to 4, 12 for lengths up to 12, and 16 otherwise. Empty strings remain masked in demo mode. It performs no HTML escaping and retains byte-length semantics for Unicode. Composition captures `config['user']['isDemo']` in `$demoMask`; report renderers construct a mask from their existing explicit render argument, so their public signatures remain unchanged.

Settings credentials/paths, Apache-control warnings, missing-vhost warnings, export database lists, and Apache/MySQL report fields use the same service. Existing escaping order is preserved, including usernames and database names masked after escaping. Masking scope is unchanged: vhost names/document roots, other diagnostic fields, and process SQL are not newly redacted. This is display masking, not comprehensive anonymisation.

Settings saving, the settings notice, Apache restart, and vhost certificate buttons now read the supplied configuration flag. Existing endpoints already using that flag retain their guards. CSRF rotation, redirects, HTTP statuses, action ordering, and response fields remain. `obfuscate_value()` delegates to `DemoMask` while retaining its constant-based mode for custom integrations; application views and handlers no longer call it or read `DEMO_MODE` directly.

The remaining constant boundary was audited, not removed:

| Constant/input | Why it remains |
| --- | --- |
| `DEMO_MODE` | Trusted legacy profile/override input, published compatibility value, and the legacy masking wrapper. Application consumers use the loaded config flag. |
| `HTDOCS_PATH` | Legacy profile/override input and `normalise_subdir()` compatibility wrapper. Namespaced directory services receive an explicit root. |
| `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `APACHE_PATH`, `PHP_PATH`, `EXPORT_EXCLUDE` | Existing profile field names and published/accepted compatibility inputs. Credential wrappers still resolve allowed legacy database constants (including `DB_NAME` if supplied by an integration). Application services use composed credentials, paths, and export exclusions. |
| `CRYPTO_KEY_FILE` | Existing key-file override used at composition and by credential wrappers; retiring it could select a different key for an existing installation. |
| `AMPBOARD_NO_HELPERS` | Existing include-policy switch for integrations and fixtures. |

`Config\LegacyConstants::read()`/`publish()` therefore remain at the compatibility boundary. Field-name strings in profile schemas and forms are not runtime constant reads, and PHP's built-in error-level/session/MySQL constants are not candidates for this migration. Removing default constant publication would be a separate compatibility decision, rather than an incidental cleanup.

`tests/demo-services.php` checks independent normal/demo masks, byte boundaries, Unicode, escaping order, actual settings and vhost panels, and legacy wrapper modes. Submit and restart fixtures deliberately supply config opposite to `DEMO_MODE` and verify normal actions proceed through their existing service path while demo actions perform no save/restart. Export fixtures cover masked database lists and unchanged demo export rejection. Existing report fixtures cover normal/demo rendering. No installed profiles, commands, or certificates are used.

## Folder-opening services (step 16)

`Filesystem\FolderOpener` receives a platform, `System\ProcessLauncher`, and optional directory-check callback. Construction performs no reads or launches. `open($path)` rejects empty, relative, control-character, missing, and regular-file paths before launching. Windows accepts drive-absolute and UNC paths, normalizes separators, and rejects double quotes and device namespaces. Unix absolute paths retain their spelling and are passed as a single argument. Existing-directory symlinks remain eligible; this is not a document-root containment policy.

macOS uses `open`; Linux uses `xdg-open`. Windows uses a fixed PowerShell wrapper to start Explorer and return without waiting for the Explorer window to close. Path data is base64-encoded independently of the ASCII script, decoded as UTF-8, and passed as one quoted Explorer argument; trailing backslashes are doubled before the closing quote. The script is encoded as UTF-16LE without requiring an optional PHP extension. No user path is evaluated as PowerShell source or passed through `cmd.exe`. Windows now requires `powershell.exe` to be available; unavailable launch tools return an error.

`System\NativeProcessLauncher` invokes a separate argument array through `proc_open`, captures output away from the response, closes stdin, and reports the launch utility's exit status. Disabled process functions, missing tools, and nonzero exits cannot report success. Success means the launch utility accepted the request; it cannot confirm that a visible desktop window opened. macOS/Linux utilities may wait for their desktop handler, and this increment adds no launch timeout.

`Http\FolderOpenAction` accepts explicit method/body data and maps results to the existing JSON fields. `utils/open_folder.php` keeps its POST JSON URL, successful `Opened: {supplied path}` message, 405 method error, 400 invalid-path error, and 500 launch error. It loads only the autoloader and these services, without profile/database/session initialization. Malformed JSON and nonscalar paths are rejected safely. File and relative-path rejection enforce the endpoint's documented directory/absolute-path contract. Configuration also composes `$folderOpener` for custom integrations.

The frontend and bundle are unchanged. This extraction does not add authentication, CSRF, origin, demo, or allowed-root policy to the folder endpoint; those would be separate behavior decisions. The operation opens the folder on the server machine, as before, rather than downloading it or opening a folder on the remote browser's computer.

`tests/folder-opener.php` covers platform commands, directory validation, spaces/Unicode/punctuation, drive roots/UNC paths, launch failures, exceptions, explicit request policy, and actual endpoint rejection responses. Native checks execute only benign PHP fixtures. On Windows a supplied PowerShell `Start-Process` function shadows the real cmdlet to validate the wrapper without opening Explorer. No automated check invokes a desktop folder launcher.

## Remaining helper policies (step 17)

`System\UserDiscovery` provides the fixed `whoami` fallback used by identity composition, with an optional supplied executor for fixtures. No request input becomes a command. Identity still prefers `USERNAME`, then `USER`, preserves empty-versus-null precedence, strips domain/email notation, and falls back to `Guest`. Disabled or failing native discovery now returns unavailable instead of causing a fatal error. The general-purpose legacy `safe_shell_exec()` remains available to integrations but has no application callers.

`Config\BooleanInput` owns the strict form truth values (`'1'`, `1`, `true`, `'true'`, `'on'`, `'yes'`). Settings normalization no longer needs `normalise_bool()`; the legacy wrapper retains its string return values. `Http\BadRequest::send()` preserves settings errors: status 400, plain UTF-8 `Bad request.`, detailed server logging, and termination. `submit_fail()` remains a compatibility delegate for custom integrations and the old `atomic_write()` wrapper. Application callers no longer use these procedural policies.

## Explicit compatibility composition (step 18)

`config/application.php` loads the profile/configuration snapshot without loading procedural helpers, publishing default constants, creating sessions, or probing database credentials. It still accepts predefined legacy overrides, and trusted PHP profile/local files may themselves define constants, invoke helpers already loaded by the caller, or change runtime settings. This is not a sandbox or re-entrant multi-profile API. `Loader::load()` retains its existing default credential-check behavior for direct integrations; the modern composition explicitly skips that check. Unprobed `config['status']['mySql*Valid']` values are `null`, not a failed credential result.

`config/legacy.php` deliberately retains default helper availability for existing trusted PHP profiles and then publishes the resolved settings. This compatibility choice avoids breaking helper-dependent local/profile files. Returned-array installations can opt out using the existing `AMPBOARD_NO_HELPERS` switch. The complete `config/config.php` wrapper supplies all historical service variables and performs the dashboard credential check once. Loading it after modern composition retains the same profile snapshot and any already-composed services; configuration files must be included in the same request scope.

Modern integrations can explicitly select dependencies without a service locator:

```php
require_once __DIR__ . '/config/application.php';
require_once __DIR__ . '/config/services-folders.php';
$columns = $folderPresenter->prepare();
```

For rendering, include `config/services-ui.php` and use `$ui` as before. For credential indicators, explicitly include `config/services-diagnostics.php`. Existing integrations can continue requiring `config/config.php` unchanged. No saved profile or key is rewritten by initialization.

## Entry-point composition (step 19)

Small `services-*.php` files express dependencies through explicit includes and constructors. `entry-*.php` files select those groups behind the retained legacy profile boundary. `require_once` shares the already-composed services when panels are embedded, without constructing another profile snapshot or running duplicate credential diagnostics. These files do not perform operations merely by constructing their services.

| Entry point | Required composition |
| --- | --- |
| Main bootstrap | Request/session policy, UI, and credential diagnostics; session starts before output as before. |
| Settings | Request policy, UI, INI target, and credential diagnostics; embedded utilities compose their own services. |
| Config reader | Profile/config snapshot and origin policy; no session or database factory. |
| Statistics | Platform measurements and command runner; no database or session. |
| Error logs | Their respective log viewer; no database or session. |
| PHP info | UI and PHP-info renderer; no database or session. |
| Apache inspection/control, certificates, vhosts | Their respective Apache services; no database credential probe. |
| MySQL inspection | UI and explicit database inspector; only the requested inspection connects. |
| Exports | Explicit export, database, request, and UI services; no dashboard credential probe. |
| Settings submission | Request policy, profile repository, identity, and INI target. |
| Folder opening | Existing autoloader-only composition; no profile, session, or database. |

Utility URLs, response shapes, redirects, demo guards, CSRF rotation, and profile precedence remain unchanged. Profile-backed utilities still initialize configuration and apply profile PHP directives because their paths/flags come from that snapshot. Narrow composition removes unrelated services and dashboard probes; it does not introduce authentication or change endpoint authorization policy.

`tests/composition.php` uses real composition files with temporary returned-array and helper-dependent legacy profiles. It checks opt-in constant publication, helper-free settings and full composition, upgrade/reuse, unknown-versus-probed credential status, no unintended database connections or session starts, and narrow utility dependencies on PHP 8.0+. Existing request smoke fixtures now disable helpers; submit fixtures also run without them.

## Validation

Run `php -n tests/run.php`. Each scenario gets a fresh process because legacy profiles define constants. The suite uses a deterministic MySQLi double, temporary profiles, and read-only request fixtures. It checks profile fallback and overrides, false/default values, config isolation, theme and tooltip rendering, accessibility markup, asset paths, embedded versus standalone panels, connection credentials, report-mode restoration, and rendered entry points. It does not write real profiles, restart Apache, generate certificates, or export real data.

The isolated suite includes `php -n tests/apache.php`: sample vhost/hosts configurations, independent caches and fast modes, quoted config paths, simulated Windows/Linux/macOS restart selection, and restart-handler responses. It runs only benign PHP output commands to exercise exit-code/stderr capture and disabled-function fallbacks. Request fixtures use private temporary session directories and force garbage collection, avoiding the runner system-directory permission failure. No automated check restarts Apache.

Run `php tests/profiles.php` with OpenSSL enabled for temporary-file integration tests: defaults and overrides, independent profiles, credential round trips, historical ciphertext, key preservation, legacy migration, invalid profile data, partial-write rollback, and actual submit-handler success/rejection paths. These tests use temporary profile, session, and INI paths and do not contact a database.

For real MySQLi validation, enable the extension and run `php tests/mysqli.php --failure-only` to check refused connections against loopback port 1. To also test successful connections, supply `AMPBOARD_TEST_DB_HOST`, `AMPBOARD_TEST_DB_USER`, and `AMPBOARD_TEST_DB_PASSWORD` for a **disposable test database**, then run `php tests/mysqli.php`. CI provisions its own MySQL service for this check, alongside PHP lint, isolated tests, and profile integration on PHP 8.0, 8.2, 8.3, and 8.4 on Windows and Linux.

The isolated suite also runs `tests/php-management.php` using temporary INI files, injected PHP-info output, and process-local runtime settings. It checks scoped overrides, duplicate directives, line endings, failed replacement cleanup, and independent targets. Submit fixtures check default/explicit INI targets, best-effort failure after profile save, and rejection of multiline overrides. No installed PHP configuration is edited.

The fixture checks do not replace manual testing against XAMPP/LAMP/MAMP. Before merging, exercise saved settings, encrypted credentials, PHP INI changes, vhost/certificate operations, exports, and Apache restart in your normal development stack.

Run `php -d phar.readonly=0 tests/exports.php` with ZIP and Phar enabled for fixture-only archive contents, uploads modes, SQL batching, command fallback, cleanup, and actual export-handler responses. Installed external archivers are exercised against temporary fixtures. CI also runs `tests/export-mysqli.php` against its disposable MySQL service: it creates a randomly named database, exports and restores 201 rows including NULL, Unicode, and binary values, compares them, and removes the fixture database. Never point this integration test at a production server.

## Final architecture increments

Step 23 adds `Database\Observation` and `services-database-observation.php`. Dashboard/settings diagnostics eagerly request its credential result; the header requests its database label. Either order makes one attempt, including failures, and closes the connection immediately. Separate requests/observations and export/MySQL-inspector connections remain independent. `Database\CredentialStatus` preserves the existing access-denied/host-error heuristics. `ConnectionFactory::credentialStatus()` retains its signature, key lookup and fresh-per-call behavior; unavailable drivers now return invalid indicators, and cleanup errors do not erase collected results. Connection initialization failures close any opened connection before propagating the original/translated error.

Step 24's [completion audit](architecture-audit.md) records reviewed boundaries, justified tidy-ups, and the retained compatibility contracts. The architecture modernization is complete. No PHP discovery/switching implementation or measurement-driven optimization is included.

Step 22 gives `Ui\Renderer` an optional script-name snapshot and `Apache\Inspector` optional server data and an `Apache\RuntimeReader`. Application composition supplies the script name and the Apache utility supplies its server data explicitly. Existing constructor calls capture the current request once; later changes to `SCRIPT_NAME` or `SERVER_SOFTWARE` no longer alter those instances. Custom integrations that intentionally render another request should construct another instance. An explicit empty script name or empty server array suppresses ambient request fallback.

`Apache\NativeRuntimeReader` keeps native Apache-version availability, SAPI/module output, the four selected environment keys, and INI observations behind a lazy boundary. Runtime observations, proc environment files, binary/config discovery, and commands are still collected when requested; they are not cached as request metadata. Native version → server header → PHP-info version precedence, full/fast behavior, report fallbacks, masking, and escaping remain unchanged. `tests/request-snapshots.php` checks supplied request data and runtime readers without running commands; real composition tests also prove the renderer retains its supplied script. `AMPBOARD_DEMO_MODE` remains an existing configuration input with unchanged override precedence.

Step 21 extends `tests/composition.php` to all 17 real entry compositions, both with and without legacy helpers. Each fresh process checks its public service contract, excludes unrelated operation services, and permits a credential probe only for dashboard/settings. Composing entries does not start a session; request handlers remain responsible for deliberately starting one. Repeated embedded sequences in forward and reverse order retain service instances and one profile snapshot, with only one credential diagnostic probe. Adding an entry without a contract fails the coverage check. Existing endpoint behavior fixtures remain separate.

The historical step 20 [architecture review](architecture-review.md) records the original shortlist. Entry-composition coverage, explicit request snapshots, shared database observations, and the final audit are complete. Measurements and further optimization are parked.

Step 25, PHP version discovery/switching, is parked as a separate feature. Legacy helper loading remains an explicit compatibility choice, rather than an application requirement.

Avoid a service locator or static global config accessor: it would preserve the hidden dependencies under a new name. Each increment should retain the existing URLs and saved profiles until a separately documented migration is ready.
