<p align="center">
  <img src="docs/assets/banner.png" alt="School Manager banner" width="100%">
</p>

<h1 align="center">School Manager for GLPI</h1>

<p align="center">
  School-focused GLPI plugin for guided tickets, guided asset creation, IT stock, interactive classroom plans, and mobile-ready technician workflows.
</p>

<p align="center">
  <b>English</b> ·
  <a href="README_ES.md">Español</a> ·
  <a href="README_FR.md">Français</a> ·
  <a href="README_DE.md">Deutsch</a> ·
  <a href="README_IT.md">Italiano</a> ·
  <a href="README_PT.md">Português</a> ·
  <a href="#overview">Overview</a> ·
  <a href="#languages">Languages</a> ·
  <a href="#requirements">Requirements</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#screenshots">Screenshots</a> ·
  <a href="#documentation">Documentation</a> ·
  <a href="CREDITS.md">Credits</a>
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.1.0-0f172a?style=for-the-badge">
  <img alt="GLPI" src="https://img.shields.io/badge/GLPI-10%20%2F%2011-1f6feb?style=for-the-badge">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777bb4?style=for-the-badge">
  <img alt="License" src="https://img.shields.io/badge/license-GPLv3%2B-c1121f?style=for-the-badge">
</p>

<p align="center">
  <a href="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml">
    <img alt="CI" src="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml/badge.svg">
  </a>
</p>

> School Manager is an unofficial GLPI plugin. It is not maintained by the official GLPI project.

<a id="overview"></a>

## Overview

School Manager adds a simplified operational layer on top of GLPI for schools and educational centres. It keeps GLPI as the core ITSM and inventory platform while providing clearer desktop and mobile pages for teachers, technicians and school IT administrators.

## Main features

| Area | Description |
|---|---|
| Guided tickets (`new_ticket.php`) | Step-by-step incident creation with expanding mobile stepper, parent/sub-category picker, quick classroom selector, priority and linked asset. |
| Guided asset creation (`new_asset.php`) | Instant client-side asset type switching (computers, monitors, printers, network, peripherals), location picker, and mobile sticky progress bar. |
| IT dashboard (`it_panel.php`) | Technician-oriented workspace for open requests, rules-based auto-assignment, follow-up and resolution. |
| Stock control (`stock.php`) | Consumables, cartridges, stock movements, low-stock warnings and ticket-linked usage. |
| Classrooms & interactive plans (`classrooms.php`, `selector.php`) | Buildings, floors, classrooms, `diagrams.net` (`draw.io`) interactive floor plans, plus a toggleable **List-Only Mode** when floor plans are disabled. |
| Activation & settings (`install.php`, `settings.php`) | Dedicated post-install/update activation wizard and unified configuration center (branding, Signature Navy & Teal UI, modules, languages, buildings, rooms and plans). |

<a id="languages"></a>

## Languages & Internationalization (i18n)

GLPI School Manager provides native, out-of-the-box multilingual support with **6 official built-in languages** (100% coverage across 495 keys) and **English (`en_GB`)** as the primary base language:

| Language | Code | Status | Coverage |
|---|---|---|---|
| 🇬🇧 **English** | `en_GB` | Built-in (Base & Template) | 100% |
| 🇪🇸 **Español** | `es_ES` | Built-in | 100% |
| 🇫🇷 **Français** | `fr_FR` | Built-in | 100% |
| 🇩🇪 **Deutsch** | `de_DE` | Built-in | 100% |
| 🇮🇹 **Italiano** | `it_IT` | Built-in | 100% |
| 🇵🇹 **Português** | `pt_PT` | Built-in | 100% |

### In-Browser Language Center & Visual Editor

No developer tools or terminal commands required! From **GLPI > Tools > School Manager > Settings > 🌐 Languages**, administrators can:
- **Automatic Language Detection**: Detects the active GLPI session language and switches smoothly, with automatic fallback to English.
- **In-Browser String Editor**: Edit and translate any string directly from the browser with real-time search and save changes instantly.
- **Official English Template (`template.php`)**: Download the canonical 495-key template (`schoolmanager/locales/template.php`) with ready-to-use AI translation prompts.
- **Custom Language Packs**: Upload `.php` or `.json` translation files (e.g. `ca_ES`, `pt_BR`). The plugin dynamically discovers new files and registers them immediately.

### Visual Identity

School Manager uses the **Signature Navy & Teal (`#0F2951` & `#088E91`)** institutional design system across desktop and mobile, featuring crisp native form controls, zero CSS leakage into GLPI core navigation, and compact responsive headers. Additional theme presets remain clearly marked as in development inside Settings.

<a id="requirements"></a>

## Requirements

| Component | Supported / recommended |
|---|---|
| GLPI | 10.x or 11.x |
| PHP | 8.x |
| Web server | Apache recommended |
| Database | Same database stack used by GLPI |
| Operating system | Linux server recommended |
| Browser | Current Chrome, Edge, Firefox or Safari |
| Permissions | GLPI administrator access for installation and configuration |

The plugin folder must be named exactly:

```text
schoolmanager
```

Final path:

```text
/var/www/glpi/plugins/schoolmanager
```

<a id="installation"></a>

## Installation and update

Run this command on the GLPI server:

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/pabloburgos/glpi-schoolmanager-plugin/main/scripts/install-or-update.sh)"
```

Then clear cache and restart Apache:

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

Refresh the browser with `Ctrl + F5`.

## Repository structure

```text
glpi-schoolmanager-plugin/
├── schoolmanager/          # Real GLPI plugin folder
├── public_maps/            # Optional public map resources
├── docs/                   # Documentation, banner and screenshots
├── scripts/                # Installer and update scripts
├── .github/                # GitHub workflows and templates
├── README.md               # Main English README
├── README_ES.md            # Spanish README
├── README_FR.md            # French README
├── README_DE.md            # German README
├── README_IT.md            # Italian README
├── README_PT.md            # Portuguese README
├── AUTHORS.md              # Authors
├── CREDITS.md              # Credits and acknowledgements
├── CHANGELOG.md            # Change history
├── SECURITY.md             # Security policy
└── LICENSE                 # GPL-3.0-or-later
```

<a id="screenshots"></a>

## Screenshots

### Dashboard

Main landing page for the plugin.

<img src="docs/assets/screenshots/01-dashboard.png" alt="Dashboard" width="100%">

### Configuration

Plugin settings, branding and module configuration.

<img src="docs/assets/screenshots/02-configuration.png" alt="Configuration" width="100%">

### Guided tickets

Ticket creation workflow for teachers and staff.

<img src="docs/assets/screenshots/03-tickets.png" alt="Guided tickets" width="100%">

### Stock control

Consumables, stock movements and inventory actions.

<img src="docs/assets/screenshots/04-stock.png" alt="Stock control" width="100%">

### Classrooms

Classroom list, GLPI locations and quick actions.

<img src="docs/assets/screenshots/05-classrooms.png" alt="Classrooms" width="100%">

### Maps

Classroom map and location navigation.

<img src="docs/assets/screenshots/06-maps.png" alt="Maps" width="100%">

<a id="documentation"></a>

## Documentation

| Document | Description |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installation and update guide. |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Plugin configuration guide. |
| [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md) | Compatibility notes. |
| [docs/PAGES.md](docs/PAGES.md) | Main PHP pages. |
| [docs/STRUCTURE.md](docs/STRUCTURE.md) | Repository structure. |
| [docs/LEGAL.md](docs/LEGAL.md) | License and legal notes. |
| [docs/GITHUB_SETUP.md](docs/GITHUB_SETUP.md) | GitHub setup notes. |
| [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) | Release checklist. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution guide. |
| [SECURITY.md](SECURITY.md) | Security policy. |
| [SUPPORT.md](SUPPORT.md) | Support guide. |
| [CREDITS.md](CREDITS.md) | Credits and acknowledgements. |
| [CHANGELOG.md](CHANGELOG.md) | Change history. |

## Authors

- **Pablo Burgos** (<pabloburgostena@faltax.app>)
- **Alejandro Galán**

## License

School Manager is distributed under the GNU General Public License v3.0 or later.

SPDX-License-Identifier: `GPL-3.0-or-later`

See [LICENSE](LICENSE).
