# Architecture review — step 20

Reviewed on 8 October 2026 against v3.21, commit `d6b4b0c0f2df79740287a29d2ba81653c3f10356`.

Completion: step 23 implements shared request database observations; the [step 24 audit](architecture-audit.md) closes the agreed architecture work. The historical findings below retain their v3.21 baseline. Measurements and PHP discovery/switching are parked.

Follow-up: step 21 implements shortlist item 1 in `tests/composition.php`, `tests/composition-request.php`, and `tests/fixtures/composition-contracts.php`. All 17 real entries now have explicit contracts, with helper-enabled/disabled checks and repeated embedded sequences in both orders. Step 22 implements item 2's renderer/Apache request snapshots and native diagnostic reader, with independent-instance and fallback checks. `AMPBOARD_DEMO_MODE` retains its existing configuration behavior. The findings below retain the reviewed v3.21 baseline; performance changes remain future work.

The original modernization objective is substantially achieved. Application callers use namespaced policies and explicit services, the centralized configuration remains intact, and legacy profile/helper/constant support has a named compatibility boundary. The next work should strengthen those guarantees and address demonstrated costs. Another container, service locator, or wholesale rewrite is not justified by this review.

This is an architecture and coverage review. The findings below are follow-up opportunities, not a claim that the manually tested release is broken. Production PHP, endpoint policies, profiles, keys, and deployment settings were not changed during the review.

## What is working well

- `application.php`, `legacy.php`, and `config.php` distinguish modern configuration, legacy input/publication, and the complete integration composition.
- Dependency groups express their prerequisites directly. The 17 `entry-*.php` files and 19 `services-*.php` files are small, readable, and use the existing autoloader without a Composer installation requirement.
- Profile-backed utilities no longer run dashboard database credential probes. Session adapters are lazy; constructing request services does not start a session.
- Most service dependencies are supplied explicitly: paths, platform, command/process adapters, database callbacks, profile/JSON readers, session storage, and demo mode.
- Profile precedence, encrypted credential compatibility, key preservation, failed-save recovery, response shapes, and simulated platform operations have substantial fixture coverage. CI also exercises Windows/Linux PHP combinations and real MySQL workflows.
- Keeping trusted PHP profiles and their helpers available is a deliberate compatibility choice. Moving application callers away from helpers does not require immediately removing those helpers.

## Evidence collected

The released commit's current GitHub check results were all successful at review time. `php -n tests/composition.php` also passed locally on PHP 8.2. Review-only probes used supplied command adapters, a database double, and a temporary profile; they performed no native commands, real database connections, profile saves, Apache operations, or certificate generation.

| Probe | Observed result | Meaning |
| --- | --- | --- |
| Reuse one renderer, changing only `$_SERVER['SCRIPT_NAME']` between asset renders | The generated base URL changed | Rendering still reads live request data rather than a captured dependency. |
| Reuse one Apache inspector, changing only `$_SERVER['SERVER_SOFTWARE']` between version reads | The version label changed | Inspector request metadata is still ambient despite explicit path/command dependencies. |
| Invoke credential diagnostics, then the header inspector, with one database double | Two connection attempts | The dashboard path has separate credential and header probes. This is a count, not a measured latency regression. |
| Load configuration with credential checks disabled and a supplied JSON reader | Five reads: the three profile sidecars, headings, and tooltips; zero connection attempts | Narrow service composition removes database probes, but the configuration snapshot still loads UI metadata. |

Theme scanning and path/module checks were established from `Loader::load()` and `ThemeCatalog`, not from an elapsed-time benchmark. No performance percentage or response-time saving is asserted.

## Prioritized shortlist

### 1. Cover every real entry composition

**Priority: next increment. Effort: small to medium. Benefit: protects the new structure.**

`tests/composition.php` directly checks three narrow entries: config reading, PHP info, and statistics. The complete compatibility composition exercises the service groups, but it cannot prove that every individual entry selects the correct subset. Existing endpoint fixtures often replace their entry composition with a no-op and supply dependencies themselves; that is useful for handler behavior but bypasses wiring. Real request smoke checks cover several pages, although they mainly assert output markers.

Extend the temporary-profile composition fixtures to all entries. Assert the dependencies required by each entry, the absence of unrelated services, expected database connection counts, and no unintended session startup. Include an embedded-page sequence to prove shared services and the profile snapshot are reused. Preserve handler fixtures rather than replacing them with composition-only tests.

**Acceptance:** an omitted origin policy, unwanted dashboard probe, or accidental session startup fails a focused check. Assertions concern observable dependency/operation contracts rather than copying every constructor into a test.

Evidence: `tests/composition.php`, `tests/composition-request.php`, `tests/request.php`, and the endpoint-specific request fixtures.

### 2. Capture remaining request inputs explicitly

**Priority: next production refactor after coverage. Effort: small to medium. Benefit: finishes service isolation.**

`Renderer::renderVersionedAssetsWithBase()` reads `$_SERVER['SCRIPT_NAME']` at invocation. `Apache\Inspector::isApache()` and `getApacheVersion()` read `SERVER_SOFTWARE`; its environment collection also calls `getenv()`. Independently constructed services therefore do not fully describe their request context.

Capture script/server metadata at composition and supply it to the services. For Apache environment/SAPI/PHP-info reads, use a narrowly scoped native reader only where it improves isolation; those are real runtime observations, not configuration globals to abolish indiscriminately. An optional constructor argument or supplied callback is preferable to introducing a general-purpose application context or locator. Preserve existing public calls with compatible defaults where custom integrations may depend on them.

`ProfileSchema::defaults()` also reads `AMPBOARD_DEMO_MODE`. That is configuration input and should remain supported; moving its capture to the configuration boundary can be considered with this work. Do not change override precedence as an incidental cleanup.

**Acceptance:** two instances with different supplied request data render independently, later server-variable changes do not alter their snapshots, and root/subdirectory utility asset URLs and Apache report labels remain unchanged for ordinary requests.

Evidence: `src/Ui/Renderer.php:347`, `src/Apache/Inspector.php:59`, `:67`, `:223`, and `src/Config/ProfileSchema.php:21`.

### 3. Reuse dashboard database observations within one request

**Priority: targeted optimization if database latency matters. Effort: medium. Benefit: one fewer connection attempt with the header enabled.**

`entry-dashboard.php` loads credential diagnostics. Later, `partials/header.php` calls `ServerInspector::inspect()`, which opens another connection for the version/status label. The settings panel is rendered within the main page, so removing its credential check outright would change displayed indicators.

Consider one explicitly supplied, per-request read-only database observation that carries both the credential result and header version/error information. Reuse result data, not an open connection or static global cache. First characterize success, access-denied heuristics, refused connections, charset failures, unavailable drivers, and existing error-label formatting: the two current consumers do not have identical failure semantics.

**Acceptance:** the normal dashboard with header enabled makes one diagnostic connection attempt instead of two; standalone settings keeps its indicators; separate requests and operational export/inspection connections remain independent; report flags and connection cleanup remain correct.

Evidence: `config/entry-dashboard.php`, `config/services-diagnostics.php`, `partials/header.php`, `src/System/ServerInspector.php`, and `src/Database/ConnectionFactory.php`.

### 4. Measure configuration work before splitting the loader

**Priority: conditional. Effort: medium. Benefit: potentially cheaper polling utilities.**

Even config-reading, log, and statistics requests use `Loader`, which loads all profile sidecars, headings/tooltips, theme options/color scheme, path availability, and PHP runtime metadata. The service composition is narrow; the configuration construction remains dashboard-shaped. Encrypted database credentials are also resolved by the shared profile repository even for utilities that do not use a database.

Measure representative dashboard, log, statistics, and config-reader requests in the normal stack first. If metadata work is material, separate profile/runtime resolution from dashboard presentation metadata with explicit builders. Keep the complete `config.php` shape and direct `Loader::load()` behavior compatible. Avoid a growing collection of loosely related boolean switches or speculative cross-request caches. Skipping credential resolution would require a separate decision about configuration error behavior, not merely a performance patch.

**Acceptance:** before/after operation counts and timings show a useful improvement, the same saved profiles and UI metadata render identically, and narrow utilities retain required runtime settings and response contracts. If the measured benefit is small, leave the loader alone.

Evidence: `src/Config/Loader.php:25`, `:40`, `:97`, `:114`, `src/Config/ProfileRepository.php`, and `src/Ui/ThemeCatalog.php`.

### 5. Keep process deadlines as a separate behavior decision

**Priority: optional reliability work. Effort: medium to large. Benefit: predictable failure when an external tool stalls.**

Native folder launching, external archives, and shell diagnostics wait for their child processes without an application-defined deadline. This is already documented for folder opening. The adapters have different requirements: archives need a working directory, input/output and diagnostic text; desktop launchers return launch acceptance; shell diagnostics accept trusted shell commands.

Only add deadlines if stalled operations are a practical problem. Specify per-operation limits, output limits, cleanup, and response behavior first, then test with benign sleeping child fixtures. Do not merge the three adapters merely because each starts a process, and do not assume a generic process timeout safely terminates every platform's child process tree.

Evidence: `src/Apache/ShellCommandRunner.php`, `src/Export/NativeProcessRunner.php`, and `src/System/NativeProcessLauncher.php`.

## Compatibility guarantees to retain

The next increments should keep the PHP 8.0 syntax floor, utility URLs and request/response policies, profile/local/predefined-constant precedence, key location and existing ciphertext, generic settings errors, session/CSRF ordering, and demo behavior. Trusted PHP execution and process-wide INI changes remain boundaries, even when a profile returns an array.

Composition files remain once-per-request includes in the same PHP scope. `require_once` is not a re-entrant dependency container; a first include inside one function cannot make its variables available to another unrelated function. Existing documentation states that scope contract, and this review does not propose expanding it.

Default helper loading, legacy constant publication, endpoint authorization policy, and profile format removal are compatibility/product decisions. They should not be changed under the heading of architectural cleanup. Likewise, PHP version discovery/switching would be a new feature, not unfinished extraction work.

## Recommended sequence

1. Extend actual entry-composition coverage.
2. Capture renderer/Apache request inputs explicitly.
3. Assess database diagnostic reuse against the normal stack's latency.
4. Split configuration metadata only if measurements justify it.

Keep the include composition, current namespaces, legacy boundary, and service adapters in place. The review does not justify a framework migration, generic container, connection pool, or mass rewrite of views. The first two items are the clearest maintenance improvements; later work can be chosen according to observed need.
