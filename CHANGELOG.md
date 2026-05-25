# Changelog

## UI and licensing hotfix *(same plugin version: 1.0.0)*

### Fixed

- Normalized legacy saved configuration values that still showed **CC BY-SA** in credits or preserved installs.
- Credits page redesigned and updated to show **GPLv3+** consistently.
- Added a visible **Back to Home** action to the credits page.
- Replaced the previous home-button injection helper with a safer toolbar button to avoid appearing in the left sidebar.
- Installer/update script now deactivates and reactivates the plugin during updates to refresh metadata and cache more reliably.

### Improved

- Increased spacing and padding in the main dashboard and shared plugin UI.
- Added extra GitHub presentation polish, including a more visible credits entry and screenshot sections rendered as lists instead of tables.
- Added `AUTHORS.md` as an extra root-level authors/credits file.

---


## GitHub Pro final repository polish *(same plugin version)*

### Added

- GitHub Actions CI workflow for PHP syntax, JSON validation, shell script checks and package validation.
- Manual GitHub Actions packaging workflow to generate a downloadable ZIP and checksum.
- GitHub release notes configuration.
- `RELEASE_CHECKLIST.md` for controlled release preparation.
- `docs/COMPATIBILITY.md` with GLPI, PHP and server compatibility notes.
- `docs/GITHUB_SETUP.md` with repository setup and same-version update guidance.

### Improved

- README and Spanish README now include CI status and repository-quality sections.
- Issue templates are more complete and professional.
- Pull request template now includes affected areas and stronger testing checklist.
- GitHub issue configuration disables blank issues to keep reports structured.

### Versioning

- Plugin version remains unchanged. This is a GitHub/repository polish update, not a functional plugin release.

## Documentation and repository polish update *(no plugin version bump)*

### Improved

- GitHub presentation redesigned to look cleaner, more professional and more product-like.
- Main README rewritten with clearer sections, badges, visual hierarchy and screenshot layout.
- Spanish README upgraded to match the same level of polish.
- Legal documentation simplified to avoid duplicate GitHub license tabs.

### Fixed

- Removed duplicated top-level license documents that caused GitHub to show the license multiple times.
- Updated internal documentation links to reference the canonical [`LICENSE`](LICENSE) file.
- Cleaned repository structure descriptions and legal references.

---

## [1.0.0] - GitHub-ready public release

### Added

- First public release of School Manager.
- Guided ticket workflow for school users.
- IT dashboard for technicians and IT administrators.
- Stock control for consumables and IT materials.
- Classroom, building and floor management.
- Example classroom maps in HTML, PNG and SVG.
- Asset management shortcuts for school IT teams.
- Configuration from GLPI.
- English and Spanish interface support.
- One-command installer/updater.
- GitHub issue templates, pull request template, support guide, security policy and contribution guide.
- `CREDITS.md`, `docs/PAGES.md`, `docs/STRUCTURE.md` and `docs/LEGAL.md`.

### Changed

- Repository documentation redesigned for GitHub.
- License normalized to **GNU GPL v3.0 or later**.
- Default configuration made generic for educational centres.
- Several internal links now use the configured default building and floor instead of legacy hardcoded values.
- Installation script improved for GitHub deployments and optional public map syncing.

### Fixed

- Removed legacy non-software license references from the software package.
- Updated page documentation so it matches the actual PHP file names included in the plugin.
- Reduced school-specific fallback data in map/location helpers.
- Verified PHP syntax for plugin files.
