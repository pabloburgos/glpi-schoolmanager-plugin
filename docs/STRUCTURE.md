# Repository Structure

This document explains the main folders and why they exist.

```text
glpi-schoolmanager-plugin/
├── schoolmanager/
├── public_maps/
├── docs/
├── scripts/
├── .github/
└── root documentation files
```

## `schoolmanager/`

This is the real GLPI plugin folder.

When installed, it must be copied to:

```text
/var/www/glpi/plugins/schoolmanager
```

### Important subfolders

| Folder | Purpose |
|---|---|
| `front/` | Pages opened from GLPI. |
| `inc/` | PHP classes, helpers and configuration logic. |
| `css/` | Plugin stylesheets. |
| `js/` | JavaScript used by plugin pages. |
| `maps/` | Map resources used by the plugin. |
| `maps/uploads/` | Uploaded maps. This should be preserved during updates. |
| `pics/` | Plugin icons and logos. |
| `locales/` | Translation resources. |
| `data/` | Local configuration. This should be preserved during updates. |

## `public_maps/`

Optional public map examples or files.

The installer may copy these to the GLPI maps/public area depending on the deployment strategy.

## `docs/`

Documentation, README assets and screenshot gallery.

| Folder | Purpose |
|---|---|
| `docs/assets/` | Banner, icon, logo and supporting images. |
| `docs/assets/screenshots/` | Screenshot gallery used by the README files. |

## `scripts/`

Automation helpers.

| File | Purpose |
|---|---|
| `install-or-update.sh` | One-command installer/updater for GLPI servers. |

## `.github/`

GitHub repository metadata.

| Folder/File | Purpose |
|---|---|
| `.github/ISSUE_TEMPLATE/` | Issue templates. |
| `.github/PULL_REQUEST_TEMPLATE.md` | Pull request template. |
| `.github/workflows/` | CI and packaging workflows. |
| `.github/release.yml` | GitHub generated release notes categories. |

## Root documentation files

| File | Purpose |
|---|---|
| `README.md` | Main repository presentation in English. |
| `README_ES.md` | Main repository presentation in Spanish. |
| `LICENSE` | Canonical GPLv3+ license file. |
| `docs/LEGAL.md` | License and legal notes. |
| `docs/COMPATIBILITY.md` | Compatibility notes. |
| `docs/GITHUB_SETUP.md` | GitHub repository setup notes. |
| `RELEASE_CHECKLIST.md` | Release preparation checklist. |
| `CREDITS.md` | Authors and acknowledgements. |
| `NOTICE.md` | Extra notices. |
| `CHANGELOG.md` | Release history. |
| `CONTRIBUTING.md` | Contribution guide. |
| `SUPPORT.md` | Support guide. |
| `SECURITY.md` | Security policy. |
