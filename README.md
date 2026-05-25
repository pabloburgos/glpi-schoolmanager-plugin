<p align="center">
  <img src="docs/assets/banner.png" alt="School Manager banner" width="100%">
</p>

<h1 align="center">School Manager for GLPI</h1>

<p align="center">
  School-focused GLPI plugin for guided tickets, IT stock, classroom maps, assets and technician workflows.
</p>

<p align="center">
  <a href="README_ES.md">Spanish</a> ·
  <a href="#overview">Overview</a> ·
  <a href="#requirements">Requirements</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#screenshots">Screenshots</a> ·
  <a href="#documentation">Documentation</a> ·
  <a href="CREDITS.md">Credits</a>
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.0.0-0f172a?style=for-the-badge">
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

School Manager adds a simplified operational layer on top of GLPI for schools and educational centres. It keeps GLPI as the core ITSM and inventory platform while providing clearer pages for teachers, technicians and school IT administrators.

## Main features

| Area | Description |
|---|---|
| Guided tickets | Simplified incident creation for teachers and staff with category, classroom, priority and affected asset. |
| IT dashboard | Technician-oriented workspace for open requests, assignment, follow-up and resolution. |
| Stock control | Consumables, cartridges, stock movements, low-stock warnings and ticket-linked usage. |
| Classrooms and maps | Buildings, floors, classrooms, map resources and direct links to GLPI locations. |
| Asset management | Simplified views for computers, monitors, printers, peripherals and network devices. |
| Configuration | Branding, logo, modules, language, buildings, rooms and maps from GLPI. |

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

## License

School Manager is distributed under the GNU General Public License v3.0 or later.

SPDX-License-Identifier: `GPL-3.0-or-later`

See [LICENSE](LICENSE).
