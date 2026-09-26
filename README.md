# AMPBoard — Modern Localhost and Remote Dashboard for Apache, MySQL & PHP

![Version](https://img.shields.io/github/v/release/Pav-Osmolski/AMPBoard)
![PHP Compatibility](https://img.shields.io/badge/PHP-8.0%2B-blue)
![Platform](https://img.shields.io/badge/platform-Windows%20%7C%20macOS%20%7C%20Linux-lightgrey)
![License](https://img.shields.io/badge/license-GPL--3.0-blue)
![Webpack](https://img.shields.io/badge/Bundler-Webpack-orange)
![SCSS](https://img.shields.io/badge/Styles-SCSS%20%26%20Babel-purple)
![Modular JS](https://img.shields.io/badge/JavaScript-ES6%20Modules-yellow)
![Last Commit](https://img.shields.io/github/last-commit/Pav-Osmolski/AMPBoard)

<p align="center">
    <img src="/assets/favicon/AMPBoard.ico" alt="AMPBoard Logo" />
</p>

**AMPBoard** is a modern, modular dashboard for developers using Apache, MySQL, and PHP — whether on localhost or remote servers.
It replaces the plain Apache index page with a responsive, feature-rich control panel that displays your projects, monitors system stats, and manages Apache & MySQL — all from one sleek interface.

> [!NOTE]
> Advanced Apache specific tools like Vhosts Management, Restart Apache, Inspector and Log Viewer, require server-level access when deployed remotely.

✅ Requires **PHP 8.0+**, with mysqli and OpenSSL enabled<br>
✅ Works on **Windows, macOS, and Linux**  
✅ Built with **Webpack, Babel, Sass, and module-based JS**

It is intended to be used with AMP stacks such as:

- [XAMPP](https://www.apachefriends.org/)
- [AMPPS](https://ampps.com/)
- [WAMP](https://www.wampserver.com/en/)
- [LAMP](https://www.digitalocean.com/community/tutorials/how-to-install-lamp-stack-on-ubuntu)
- [MAMP & MAMP PRO](https://www.mamp.info/)

## 📚 Table of Contents

- [✨ Features](#features)
- [🛠️ How to Install](#how-to-install)
- [📸 Screenshots](#screenshots)
- [📁 Project Structure](#project-structure)
- [⚖️ License](#license)

## Features

- **Instant Project Search** – Live filter through all your local folders with ease
- **Flexible Column Layout** – Draggable, resizable, and fully customisable folder views
- **Inclusive UX** – Designed so sighted, low-vision, and non-mouse users all get the same power
- **Environment Snapshot** – Instantly see which versions of Apache, PHP, and MySQL you're running
- **Smart Apache Control** – Safely restart the active Apache instance based on your OS and setup
- **Live System Monitoring** – AJAX-powered CPU, memory, and disk usage at a glance
- **Apache and MySQL Inspector** – Inspect configuration and uptime for Apache and MySQL servers
- **Reusable Link Templates** – Define and reuse HTML templates across folder listings
- **Quick Config Panel** – Update paths, ports, and settings without breaking a sweat
- **PHP Management** – Adjust the PHP memory limit and toggle error display/logging on the fly
- **Virtual Hosts Overview** – View and validate active VHosts, with SSL certificate management
- **Apache and PHP Error Log Toggle** – One-click access to the latest server logs
- **Export Files & Database** – Export folders as ZIP or 7-Zip; include/only WP uploads; exclude junk
- **Security** – CSRF on POST, path checks, safe fallbacks; no secrets persisted
- **Open Folder from UI** – Instantly launch projects in your file explorer from the browser
- **Custom Dock** – macOS-style dock with editable shortcuts to your key tools and sites
- **Real-Time Clock** – Because knowing the time is still a thing
- **Responsive Interface** – Sleek, modern design that adapts to all screen sizes
- **Theme Switcher** – Many themes, one destiny. Pick your favourite
- **Demo Mode** – Disables exports and obfuscates credentials for demonstrative purposes
- **Low-Stress Local Dev** – Designed to stay out of your way 🧘 so you can focus on building

## How to Install

1. Clone this repo into your Apache document root, e.g. `C:/xampp/htdocs/`
2. Run `npm install` in the repo's location to install dev dependencies
3. Access `http://localhost/` on your web browser to view your new dashboard
4. Set your custom user config by navigating to the Settings page in the footer
5. Customise to your delight
6. Run `npm run build` to compile any changed SCSS or JavaScript

## Screenshots

![search functionality](screenshots/index-dark.png)

![search functionality](screenshots/settings.png)

![search functionality](screenshots/index-light.png)

## PHP development

The modernization is incremental. See [architecture and migration notes](docs/architecture.md) for the new namespaced services, compatibility boundaries, and next steps. Published release history is recorded in [CHANGELOG.md](CHANGELOG.md).

The isolated suite includes PHP-manager normalization, runtime inspection, PHP-info output, and temporary INI edits through `tests/php-management.php`. Profile integration also checks optional INI failures without changing installed PHP configuration.

Run `php -d phar.readonly=0 tests/exports.php` with ZIP and Phar enabled for temporary file/database export fixtures, archive fallback, cleanup, and request checks. Installed external archivers are tested against those fixtures; no live project data is exported.

Run `php -n tests/run.php` for the isolated regression suite. It uses a database double and temporary profiles, so no running Apache or MySQL server is needed. Run `php tests/profiles.php` with OpenSSL enabled for profile saving, encryption, migration, and submit-handler checks using temporary files. The isolated suite also covers Apache services and simulated restart requests without controlling a real server. See the architecture notes for the separate real-MySQL check.

## Project Structure

A quick overview of the core files and folders in this project, so you’re never left wondering what does what.

---

### 📄 Root Files

| File                     | Description |
|--------------------------|-------------|
| `index.php`              | Main entry point. Displays the homepage with all widgets and layout. |
| `package.json`           | Lists build dependencies and Webpack/Babel/Sass configuration. |
| `webpack.config.js`      | Webpack build pipeline for JS and SCSS. |

---

### Namespaced PHP (`src/`)

| File | Responsibility |
| --- | --- |
| `Config/Loader.php` | Assemble the central config array from resolved profile data. |
| `Config/ProfileRepository.php` | Load profiles and save versioned settings with JSON sidecars. |
| `Config/ProfileReader.php`, `ProfileSchema.php` | Read legacy/new profiles and define supported settings. |
| `Config/SettingsInput.php`, `PhpSettings.php` | Normalize settings forms and apply PHP directives. |
| `Config/AtomicFileWriter.php`, `LegacyConstants.php` | Coordinate profile writes and bridge remaining constant consumers. |
| `Security/CredentialCipher.php` | Encrypt credentials using an explicitly supplied existing key file. |
| `Ui/ThemeCatalog.php` | Read theme metadata and assemble body classes. |
| `Ui/Renderer.php` | Render components using explicitly supplied configuration. |
| `Apache/Inspector.php` | Inspect Apache using explicit paths and fast-mode options. |
| `Apache/VhostCatalog.php` | Parse virtual hosts and cache validity per configured installation. |
| `Apache/Controller.php` | Select platform restart commands and return their execution results. |
| `Apache/CommandRunner.php`, `ShellCommandRunner.php` | Injectable command execution with output and exit status. |
| `Database/ConnectionFactory.php` | Open connections and validate injected credentials. |

Classes are loaded by `config/autoload.php`; Composer is not required.

### ⚙️ Config (`config/`)

| File                     | Description |
|--------------------------|-------------|
| `helpers/`               | Common helpers for the whole app, grouped logically and easy to extend. |
| `interface/`             | Heading configuration and tooltip descriptions for settings and panels. |
| `profiles/`              | Profile folder for auto generated user-defined overrides saved from the settings UI. |
| `bootstrap.php`          | Init headers, session, security, and config; starts session early for CSRF rendering. |
| `config.php`             | Composition entry point exposing `$config`, `$database`, and `$ui`. |
| `autoload.php`           | Loads `AMPBoard\` classes from `src/` without Composer. |
| `helpers.php`            | Loads remaining procedural helpers during the incremental migration. |
| `debug.php`              | Logs raw shell commands (with optional context) to `logs/localhost-page.log`. |

---

### 📜 Certificate Generator Scripts (`crt/`)

These scripts are automatically used by `utils/generate_cert.php` to generate self-signed certificates for local development environments.

| Script                    | Purpose |
|---------------------------|---------|
| `make-cert-prompt.bat`    | Windows Batch that prompts for CN/SANs, generates `.key` and `.crt` with OpenSSL. |
| `make-cert-prompt.sh`     | Bash script that prompts for CN/SANs, generates `.key` and `.crt` with OpenSSL. |
| `make-cert-silent.bat`    | Generates a `.crt` and `.key` using OpenSSL silently via Windows Batch script. |
| `make-cert-silent.sh`     | Bash script to generate a cert/key pair non-interactively using OpenSSL. |

> 💡 These scripts are auto-copied from `crt/` if missing from `apache/crt/` or outdated.

---

### 🧩 Partials (`partials/`)

| File                     | Description |
|--------------------------|-------------|
| `settings/`              | Contains all modular settings panels used to build the full configuration interface. |
| `dock.php`               | Renders the customizable macOS-style dock. |
| `folders.php`            | Dynamically scans and lists local project folders. |
| `footer.php`             | Displays the page footer with navigation links and a humorous quote. |
| `header.php`             | Shared header banner with greeting, search bar, clock, and server info. |
| `info.php`               | Displays system information like PHP, Apache, and MySQL versions. |
| `settings.php`           | The settings interface for configuring paths, theme, folders, dock, and logs. |
| `submit.php`             | Handles the saving of user-configured settings. |

---

### 🧰 Utils (`utils/`)

| File                     | Description |
|--------------------------|-------------|
| `apache_error_log.php`   | Fetches and returns Apache error log entries via AJAX. |
| `apache_inspector.php`   | Detects Apache installation details like version, modules, and config paths. |
| `export_files.php`       | UI + JSON endpoints to export files or DB with CSRF and WP uploads options. |
| `generate_cert.php`      | Generates SSL certificates. |
| `mysql_inspector.php`    | Fetches MySQL version, uptime, configuration, and connection status. |
| `open_folder.php`        | Opens a specified folder path in the system file explorer (cross-platform). |
| `php_error_log.php`      | Fetches and returns PHP error log entries via AJAX. |
| `phpinfo.php`            | Outputs PHP environment details via `phpinfo()` — handy for debugging. |
| `read_config.php`        | Read-only endpoint for whitelisted UI JSON over GET. |
| `system_stats.php`       | Provides live server stats (CPU, memory, disk) using AJAX. |
| `toggle_apache.php`      | Safely restarts the currently running Apache instance. |
| `vhosts_manager.php`     | Lists and validates Apache virtual hosts, including SSL and hosts file checks. |

---

### 🛠️ JavaScript (`assets/js/`)

| File/Folder              | Description |
|--------------------------|-------------|
| `main.js`                | Webpack entry point — initialises all modules. |
| `modules/`               | Modular ES6 scripts (e.g. `clock.js`, `dock.js`, `columns.js`) |

---

### 🎨 SCSS (`assets/scss/`)

| Folder           | Description |
|------------------|-------------|
| `base/`          | Base-level styles including fonts, resets, and root CSS variables. |
| `components/`    | Reusable UI components such as dock, folders, forms, tooltips, and system modules. |
| `layout/`        | Page layout structure including header, footer, and main content styles. |
| `pages/`         | Styles specific to individual pages like settings and PHP info. |
| `themes/`        | Configurations for all available themes, including colour schemes and metadata. |
| `utils/`         | SCSS utilities including keyframes, media queries, mixins, and variables. |
| `style.scss`     | The main SCSS entry point that imports all partials. |

---

### 🔤 Fonts (`fonts/`)

| File                     | Description |
|--------------------------|-------------|
| `fira-code/`             | Fira Code font family. Used for `<code>` elements and the HTML Editor. |
| `ubuntu/`                | Ubuntu font family. Main `<body>` font. |

---

## License

This project is licensed under the [GNU General Public License v3.0 (GPL-3.0)](https://www.gnu.org/licenses/gpl-3.0.html).
You are free to use, modify, and distribute this software, provided that any derivative works are also released under the same open-source license.
