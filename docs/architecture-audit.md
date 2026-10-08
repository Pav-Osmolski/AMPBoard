# Architecture completion audit — step 24

Reviewed against v3.22 and the step 23 shared-diagnostics changes, 8 October 2026.

The architecture modernization is complete for the agreed scope: central configuration, namespaced services, explicit request composition, and an intentional legacy compatibility boundary. PHP discovery/switching is parked as a separate step 25 feature. Measurements and speculative loader/process changes are also parked.

## Findings and tidy-ups

| Area | Audit result | Action |
| --- | --- | --- |
| Database diagnostics | Credential indicators and the header previously made separate connection attempts. | Step 23 supplies one lazy `Database\Observation` to both consumers. It retains result data only and closes the connection immediately. |
| Credential policy | The connection-status API and shared observation need identical historical error heuristics. | Extracted `Database\CredentialStatus`; the existing public status API still makes a fresh attempt on each call. |
| Initialization failure cleanup | Charset/database-selection failure could leave an opened connection unclosed. Failed credential checks did not close returned error objects. | Close connections on failed initialization and after all status checks; retain translated errors and report-mode restoration. |
| Missing drivers and cleanup exceptions | Native driver absence can throw `Error`; cleanup must not erase already collected diagnostics. | Diagnostic boundaries handle `Throwable`; close failures preserve collected results. |
| Entry/service lifetimes | All 17 entries have explicit contracts. Shared services use once-per-request includes. | Updated contracts for the observation; real dashboard/header/settings composition in either order proves one attempt. No extra general-purpose container is needed. |
| Application helper/global calls | Pages and utilities use supplied services/configuration; the audited application callers do not call the migrated procedural wrappers or declare global configuration. | Keep compatibility helpers for trusted profiles/custom integrations; do not remove them merely to shorten the tree. |
| Remaining ambient inputs | Renderer/Apache constructor defaults capture current request metadata; native readers inspect runtime state; configuration reads environment/legacy constants. | Retain and document these deliberate boundaries. Explicit application composition supplies request snapshots. |
| Documentation and annotations | Several notes still described the migration as ongoing, renderer construction as config-only, and old settings/header dependencies. | Corrected the descriptions, added the observation variable, and recorded architecture completion. |
| Profiles, handlers, frontend and deployment | Existing persistence, session/CSRF/origin/demo policies, URLs and frontend bundles are separate from this diagnostic change. | Preserve those contracts and their existing fixtures. No switching controls or deployment changes are included. |

## Validation and completion criteria

`tests/database-observation.php` characterizes success, refused/unknown hosts, access-denied password heuristics, other errors, charset/selection failures, missing drivers, normalization and cleanup. It verifies both consumer orders, repeated reads, separate observations, independent operational connections and legacy API behavior. The driver-absence case runs without a driver double in a fresh process.

`tests/composition.php` exercises all 17 entries with helpers enabled/disabled, repeated embedded panels, and actual dashboard/header/settings wiring with native commands replaced by a fixture boundary. Header-first and dashboard-first composition both make exactly one database attempt. Configuration-only and narrow utility composition remain unprobed and session construction stays lazy.

The existing system/header, endpoint, profile, export, and real-MySQL fixtures remain in use. Focused local checks and the full Windows/Linux CI matrix are the validation gate; installed Apache/PHP configuration is not edited by this work.

The compatibility boundary still supports one profile/composition scope per request, trusted PHP profile side effects and legacy constants/helpers. Native observations remain native observations, and operational database connections remain fresh. These are supported integration choices, not unfinished modernization tasks.

No further architecture steps are planned. Step 25 remains parked until PHP discovery/switching is explicitly resumed.
