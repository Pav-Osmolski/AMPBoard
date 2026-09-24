# Changelog

Published entries below are imported from the project's GitHub releases. Their wording and links are preserved as historical release notes; they are not a fresh compatibility or security assessment. Add future changes under Unreleased before moving them into a tagged release.

## Unreleased

- Extracts profile reading, saving, form normalization, and PHP settings into namespaced services with explicit dependencies.
- Saves profiles as versioned returned arrays; legacy profiles and local overrides remain readable and migrate when settings are saved.
- Isolates credential encryption while preserving existing `.key` files and ciphertext; malformed keys are no longer silently replaced.
- Stages profile files and rolls back ordinary write failures, preserving default fallback after a failed first save.
- Adds profile, encryption, migration, and submit-handler regression tests, plus PHP 8.3 in the Windows/Linux CI matrix.
- Updates the architecture notes with the profile format, compatibility rules, and remaining modernization work.

## [v3.5](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v3.5) — 2026-09-23

Everything in its right place... well, a few more things anyway 🗂️

The centralised config array was step 0. This is step 1: giving configuration, UI rendering, themes, and database connections a proper home under the `AMPBoard\` namespace.

**Please note: PHP 8.0+ is now the supported minimum.** The existing code already used PHP 8 type declarations that function polyfills could not cover. Existing user profiles and local overrides remain compatible; no settings migration is required.

- Moves configuration initialisation into `AMPBoard\Config\Loader`, keeping temporary profile variables out of the page scope.
- Introduces `AMPBoard\Ui\Renderer` for UI components, with configuration supplied explicitly instead of read through globals.
- Moves theme discovery, colour-scheme detection, and body classes into `AMPBoard\Ui\ThemeCatalog`.
- Introduces `AMPBoard\Database\ConnectionFactory`, using explicitly supplied credentials instead of global database variables.
- Adds a lightweight class autoloader. Composer is not required.
- Preserves existing profile files, local overrides, utility URLs, and frontend assets.
- Makes the embedded settings view explicit rather than changing global state when rendering an accordion.
- Checks MySQL credential status once during configuration loading instead of opening three separate connections.
- Correctly restores MySQLi reporting flags after successful and failed connections, in both strict and non-strict modes.
- Populates the central tooltip map directly from the loaded interface configuration.
- Updates the export view to read PHP path validity from the central config array.
- Expands PHP settings management and input validation for execution time, input variables, upload size, POST size, and timezone.
- Improves accessibility with a skip link, keyboard focus styling, and header refinements.
- Adds a deployment workflow for updates to `main`.
- Adds automated PHP regression checks on Windows and Linux, alongside real MySQL connection tests.
- Introduces `CHANGELOG.md`, preserving the previous GitHub release notes.
- Documents the new structure and the next stages of moving away from globals. A few are still making themselves comfortable for now 🤓

**Full Changelog**: https://github.com/Pav-Osmolski/AMPBoard/compare/v3.4...v3.5

## [v3.4](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v3.4) — 2025-12-10

- Updates the application to store user configurations in individual profile directories, ensuring personalized settings are maintained.
- Provides a default configuration fallback if a user-specific profile does not exist, ensuring consistent behaviour.
- Migrating over to a centralised config array. Technically “step 0” of getting away from globals and into a namespaced / more modern structure.
- Folders Config now has a display label to clearly identify each folder present.
- Removed redundant defaults for system stats and error logs.
- Removed unnecessary trailing slash with `$defaultScriptDir`.
- Added support for `local.php`. For reference, there is an example file named `local.example.php` where you can create your own overrides.
- Enhances PHP configuration management by allowing modification of `memory_limit` through the UI.
- It refactors the settings page for PHP error handling to include `memory_limit`, `display_errors`, `error_reporting`, and `log_errors`, storing these values in the user configuration and optionally patching the `php.ini` file.
- Enhanced the PHP settings UI for better layout and adds tooltips for clarification.
- Restructured `_folders.scss`, `_settings.scss` and `_media.scss` to improve SCSS targeting.
- Corrected HTML markup when `$globalErrors` are displayed for the folders view.
- Link Templates SCSS now has its own partial.
- A new UI function `renderCollapseToggle` which renders a collapse toggle button in both the header and footer.
- SCSS and JavaScript to handle the collapse/expand functionality and state.
- Updates to layout to manage collapsed header/footer.
- Improves the AMPBoard user interface and overall user experience by implementing active page highlighting in the navigation.
- Refactors SCSS structure by introducing mixins for hover states and improving code readability and maintainability across various components.
- Updates font stack to enhance cross-platform consistency.
- Implements pointer-based drag functionality for touch and pen input, allowing reordering of elements on touch-enabled devices.
- This adds a new drag handle `renderDragHandle` component and uses it to replace the old drag handle in `folders.php`.
- The previous implementation was only functional with mouse input using HTML5 drag and drop APIs.
- Removed incorrect ARIA from header and footer.
- Enhances navigation by updating links to use the `?view` parameter for routing, enabling direct access to specific sections.
- Also, refines click event handling on toggle elements to prevent unintended actions, such as opening links in new tabs or windows when modifier keys are pressed. It ensures that only plain left clicks trigger the intended view changes.
- Improves the visual appearance of the header, footer and collapse toggle components by introducing a gradient effect that blends into the collapse button.
- This change adds a subtle gradient to the header, footer, and collapse toggles, transitioning from a transparent background to a solid color at the bottom, creating a visually appealing depth effect.
- It also addresses a hover state issue for buttons, excluding collapse toggles from inheriting the default hover styles.
- Additionally, it corrects the collapsed state transformation for header and footer elements, ensuring a smoother and visually consistent transition when collapsed.
- Added synthwave theme due to personal request.

**Full Changelog**: https://github.com/Pav-Osmolski/AMPBoard/compare/v3.3...v3.4

## [v3.3](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v3.3) — 2025-11-28

- Introduces a new simplified headings configuration to streamline settings page organization.
- This change replaces the manual heading definitions with a JSON-based configuration, allowing for dynamic heading rendering using `renderHeading` and easier management.
- Adds the missing default Boolean for `displayFolderBadges` in the config.
- Refactored the settings page for improved organization and maintainability.
- Replaced the monolithic `settings.php` file with a set of modular settings panels, each responsible for a specific area of configuration (e.g., AMP paths, user interface, PHP error handling).
- Updated the tooltips configuration to reflect the new panel structure.
- Simplifies rendering of tooltips using global variables and avoids redundant escaping.
- Folders can now optionally display _only_ validated vhost entries.
- Added badges for the folder columns display to indicate specified folder behaviour.
- Improves the folder configuration interface by adding labels for input fields, improving element grouping, and enhancing accessibility with aria labels.
- Added UI toggle for folder badges.
- Improves the settings page's UI with fieldsets for logical grouping, enhancing usability.
- Adds minor styling fixes and prevent focus outlines from being clipped within accordion elements, improving accessibility.
- Adds `renderButtonBlock` helper.
- Improves dock component accessibility by adding keyboard focus support for dock items.
- Renders dock labels more robust and prevents clicks on labels.
- Enhances styling for visual consistency. It also now generates `alt` text from icon filenames for better accessibility.
- Updates UI helper functions for better readability, maintainability, and functionality.
- Improves the `buildBodyClasses` function by streamlining conditional checks for module availability and UI toggles. It also groups error log states.
- Updates styling for better readability and alignment.
- Refactors Apache helper functions to improve vhost validation.
- PHP Info now has a header and tooltip for consistency.
- Improved `package.json` and webpack config.
- Fixed overlapping/mistargeted SCSS.
- Corrected PHPDoc formatting.

**Full Changelog**: https://github.com/Pav-Osmolski/AMPBoard/compare/v3.2...v3.3

## [v3.2](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v3.2) — 2025-11-18

Slowly tidying things up and making sure there are no UI or behavioural inconsistencies. My plans for the next major release will include a PHP refactor, moving from global to namespaced functions.

- Added basic styles for the fall-back `textarea` HTML editor.
- Separated AJAX toggle for system stats and error log.
- Added tooltips to Apache/MySQL Inspector and restored missing folders tooltip.
- Dynamically update page title when switching view.
- Tooltips can now be configured to display above or below. Helps to ensure tooltips do not disappear underneath the sticky header on mobile devices.
- Improved error message readability when project folder is invalid.
- Detect if AMPBoard is running on a local or remote server.
- Expanded root SCSS variables for greater customisability.
- Tightened `searchProjects()` scope so it doesn't target the dock.
- Export functionality will now default to system archiver for optimal speed.

**Full Changelog**: https://github.com/Pav-Osmolski/AMPBoard/compare/v3.1...v3.2

## [v3.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v3.1) — 2025-11-13

- Background image swapped for optimised webp version.
- Improved clarity for Apache Control error display.
- Added Fira Code font family for `<code>` and HTML Editor.
- Moved Apache Restart and Reset Settings button into their own accordion.
- Added `getMysqliConnection()` with utf8mb4 charset, strict/non-strict mode, and optional DB selection.
- Added `normaliseDbServerInfo()` to clean up MySQL/MariaDB/Percona/Aurora version strings for display.
- Added `checkMysqlCredentialsStatus()` for host/user/pass validation, with heuristic distinction between bad user and bad password.
- Suppressed noisy mysqli warnings in non-strict mode for clean UI output.
- Replaced `.columns` width logic with a generalised `.width-resizable` system.
- Added support for independent width states per element via data-width-key and localStorage persistence.
- Introduced `.width-controls` groups with data-width-for to link control panels to specific resizable elements.
- Standardised size cycling with predictable behaviour from "auto" state.
- Updated reset behaviour to persist "auto" correctly in localStorage.
- Created PHP helper `renderWidthControls()` to dynamically generate accessible width-control markup for any target element.
- Columns now wrap properly if lots of folder listings are present.
- Footer tidy and moved export and vhosts to utils folder.
- Updated `setTheme()` to dynamically update or create the `<meta name="color-scheme">` tag in the document head, ensuring browsers correctly adapt UI chrome (scrollbars, form elements) to match the active theme.
- Prevented unnecessary DOM mutations by only toggling light-mode or dark-mode when needed.
- Improved safety by guarding against undefined serverTheme globals.
- Adjusted logic to preserve other `<html>` classes when switching themes, removing only existing `*-theme` classes.

**Full Changelog**: https://github.com/Pav-Osmolski/AMPBoard/compare/v3.0...v3.1

## [v3.0](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v3.0) — 2025-11-07

This is technically the first release with its new branding, along with a _slightly_ larger than usual changelog 🤓

**Please note that the project has transitioned from a MIT to GPL-3.0 license.**

I have preserved all previous commits made under the MIT license and moved them to a historical `legacy` branch.
https://github.com/Pav-Osmolski/AMPBoard/tree/legacy

- SCSS clean-up and `code` tag refinement
- Normalise `font-size` using `rem` for consistency
- Clear Local Storage now removes all prefixes reliably
- Tooltips can be toggled on or off
- Standardised border-radius across all SCSS partials
- Split accordion JS into its own partial
- Added `box-shadow` to header
- Robust 7-zip PATH detection for Windows
- Cleaned up Apache utils and moved helpers to partial
- Disallow full inspection whilst `DEMO_MODE` is active
- Mobile and layout fixes for Link Templates Editor
- Syntax Highlighter boolean for graceful fall-back
- Introduced a hidden `<p id="editor-instructions" class="sr-only">` element with clear usage guidance for screen readers
- Reset focus-escape state on blur for predictable keyboard flow
- Link Templates Editor now utilizes HTML Syntax Highlighting
- Harden accordion against rapid toggling and missed `transitionend`
- Added Escape → Tab behaviour allowing users to exit the syntax editor without losing normal tab focus navigation
- Retained Tab/Shift+Tab indentation for developers while maintaining full keyboard accessibility
- Export engine overhaul, cross-platform archiving & UX improvements
- Implemented cross-platform export support with safe fallbacks between external (7-Zip / system zip) and PHP’s ZipArchive/Phar
- Fixed `export_ensure_exports_dir()` so exports now correctly create and write to `HTDOCS_PATH/dist/exports/` instead of `/config/dist/exports/`
- Added error reporting and fallback notices for missing or failed external archivers
- Enhanced UI feedback in `export.js`, showing contextual messages and smoother CSRF refresh behaviour
- Clarified archive engine selection text, noting that 7-Zip or zip must be available on `PATH` when using the system archiver
- Minor SCSS tweak to target the last `<label>` within radio groups for consistent layout polish
- Fixed `safe_shell_exec()` to correctly parse and allow quoted full paths (e.g. `"C:\Program Files\7-Zip\7z.exe"`)
- Improved binary extraction logic to strip quotes and resolve only the basename before validating against the allow list
- Prevented false “Blocked command” logs when legitimate executables contain spaces in their path
- Enhanced `export_find_executable()` for cross-platform compatibility
- Added Material and One Dark themes
- Improved theme colours and display
- Restored missing alt tag usage for Dock
- Added AMPBoard Logo favicon and image file
- Added AMPBoard Logo to the header
- Replaced MIT with GPL-3.0 to ensure all derivative works of AMPBoard remain open-source and consistent with the project’s community values
- Updated README, badges and LICENSE section accordingly

**Full Changelog**: https://github.com/Pav-Osmolski/AMPBoard/compare/v2.2...v3.0

## [v2.2](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v2.2) — 2025-10-27

- Refined accessibility for folders and dock view
- Improved drag handling
- Added export accessibility semantics
- Normalise MySQL-family version strings for a tidy, consistent display
- Disallow certificate generation and Apache restart whilst `DEMO_MODE` is active

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v2.1...v2.2

## [v2.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v2.1) — 2025-10-23

- Improve accessibility semantics for server info, footer, and folders
- Introduce proper ARIA wiring and keyboard support for settings page (Space/Enter/ArrowDown)
- Use `hidden` only to remove closed panels from tab-order, never to control display
- Prevent tooltip icon clicks from toggling accordion

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v2.0...v2.1

## [v2.0](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v2.0) — 2025-10-22

## NOTE: The encryption key now uses 64-bit hex so you must re-save your DB settings when updating your files with this release.

- Always generate/store 256-bit keys as 64-char hex
- Auto-backup and regenerate key if file is missing, malformed, or legacy format
- Ensure permissions hardened (`chmod 0600` where supported)
- Update encrypt/decrypt helpers to assume new-format-only keys
- Added helper to load JS when `export.php` or `vhosts.php` page is accessed directly
- Improved export detection script for WordPress specific sites
- Update PHPDocs to reflect enforced new key policy
- Updated devDependencies to remove deprecation warning

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.9...v2.0

## [v1.9](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.9) — 2025-10-15

It's a big one. Added make-cert-prompt to scriptVariants array. Optimisation and refactoring of dock, drag and folders JavaScript. Minor drag.js refactor and added enableDragSort for columns.js. Added files and database export functionality using PHP ZipArchive with Phar fallback. Refactored helpers into their respective partials.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.8...v1.9

## [v1.8](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.8) — 2025-09-24

Fixes for the folders listing partial, and robust JSON config checks and warnings. Refactored and hardened the folders listing partial. User defined paths should be written as constants in user config. Fixed global error message showing when match and replace regex isn't set. Safely read JSON config files on remote servers.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.7.2...v1.8

## [v1.7.2](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.7.2) — 2025-09-12

CSRF token and general hardening of submit handler and settings. Added bootstrap include for tidiness.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.7.1...v1.7.2

## [v1.7.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.7.1) — 2025-09-02

Fixed cert generator inadequacies. Improved navigation by adding query string pages, and a confirmation message when settings are saved.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.7...v1.7.1

## [v1.7](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.7) — 2025-07-08

Added a demo mode Boolean for hosted showcase purposes. Minor clean-up of settings logic and helpers.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.6.1...v1.7

## [v1.6.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.6.1) — 2025-07-06

Settings page redesign tweaks and final touches.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.6...v1.6.1

## [v1.6](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.6) — 2025-07-03

The settings page was stretching endlessly like a dodgy elastic band, so I gave it a much-needed redesign. It now also remembers which sections you expand or collapse—because nobody likes doing the same clicks twice 🐁

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.5...v1.6

## [v1.5](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.5) — 2025-06-30

Good afternoon! The submit partial is semi-skimmed, renderServerInfo shouldn't throw eggs at Linux/Mac users, the Footer displays links in an unordered list and the Error log toggle is dressed in a bow tie.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.4.1...v1.5

## [v1.4.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.4.1) — 2025-06-24

Re-write of config.php and moved all helper logic into helpers.php for better organisation and clarity. Added MySQL inspector for those who like to poke their nose. Improved theme styles by refining background overlay and added the ability to change background hue. Added a PHP error log toggle.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.3...v1.4.1

## [v1.3](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.3) — 2025-06-19

Helpful PHPDocs for those who still wear a monocle. Neater CSS loader animation when loading the Apache Metal Detector, and please welcome the return of... Dracula! 🧛

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.2...v1.3

## [v1.2](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.2) — 2025-06-17

SCSS refactoring, PHP error reporting fixes and Apache error log and inspector improvements.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.1...v1.2

## [v1.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.1) — 2025-06-12

vHosts manager now correctly detects duplicates and unsets $info. Additional tooltips and minor style fixes.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.0.2...v1.1

## [v1.0.2](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.0.2) — 2025-06-11

`user_config.php` now saves `DB_USER` and `DB_PASSWORD` with 256-bit encryption. Fixes for generate SSL cert functionality and provided fall-back `make-cert-silent.bat` and `make-cert-silent.sh` scripts.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.0.1...v1.0.2

## [v1.0.1](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.0.1) — 2025-06-09

Minor refactoring, consolidation and formatting fixes. A light drizzle of caramel on your ice-cream as they say.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/compare/v1.0.0...v1.0.1

## [v1.0.0](https://github.com/Pav-Osmolski/AMPBoard/releases/tag/v1.0.0) — 2025-06-06

🎉 **First official release** of my custom XAMPP / LAMP / MAMP localhost homepage.

This release includes a modern, responsive interface with the following features:

## ✨ Features
- Live search functionality for all local project folders
- Customisable, resizable and draggable columns
- Real-time clock
- Displays the current version of Apache, PHP and MySQL
- Safely restart the currently running Apache instance based on detected OS and setup
- AJAX-powered system stats showing CPU Load, Memory Usage and Disk Space
- Configuration page for quick and easy setup
- Toggle PHP error handling and logging
- Virtual Hosts List with SSL detection and clickable hostnames
- Mac OS X style customizable dock
- Modern responsive look with dark/light themes
- Theme customiser and editable folder layout
- Peace of mind 🧘 (hopefully!)

## 📦 Installation
1. Extract the contents of the `.zip` file to your web root (e.g. `htdocs/` in XAMPP).
2. Open your browser to `http://localhost/` and follow the setup instructions.

## 🐞 Known Issues
- Not yet fully tested on all xAMP stacks (LAMP, MAMP, MAMP PRO and AMPPS)
- Some features may need permissions or admin rights (e.g. Apache restart and SSL cert creation on Windows/Linux)

## 📬 Feedback & Contributions
Feedback is welcome! Feel free to open issues or pull requests.

**Full Changelog**: https://github.com/Pav-Osmolski/Custom-XAMPP-LAMP-MAMP-localhost-Page/commits/v1.0.0
