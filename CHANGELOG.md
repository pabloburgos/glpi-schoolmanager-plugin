# Changelog

## [1.1.0] - 2026-10-27

### Added

- **Official Multilingual Support**: Native support for **French (`fr_FR`)**, **German (`de_DE`)**, and **Italian (`it_IT`)**, joining English and Spanish with 296 synchronized UI keys.
- **English as Primary Base Language**: Set English (`en_GB`) as the canonical base language for templates, fallbacks, and UI references.
- **Server-Side Rendering (SSR) i18n**: Eliminated Flash of Untranslated Content (FOUC) by rendering localized strings on the server (`smcfg_tr()`) before page delivery.
- **4 Modern, Simple, Accessible Themes**:
  - `teal-red`: Modern Teal with clean contrast.
  - `ocean`: Nordic Blue with cool sky tones.
  - `forest`: Campus Emerald with amber accents.
  - `graphite`: Graphite Dark mode with WCAG AA compliant contrast (> 4.5:1 ratio).
- **In-Browser Language Center & Visual Editor**: Complete translation management interface inside GLPI settings (`front/configuracion.php`):
  - In-browser string editor with live search, English reference column, and one-click saving.
  - Direct download button for the official English translation template (`template.php`).
  - File uploader supporting `.php` and `.json` custom language packs.
  - Registered languages table showing key counts, status, and management actions.
- **Dynamic Locale Discovery**: The plugin automatically detects any `.php` or `.json` dictionary in `schoolmanager/locales/` and populates the settings selector automatically.
- **AI Translation Guide**: Ready-to-copy AI prompt in the template and settings UI to generate new language packs in seconds via LLMs (ChatGPT, Claude, Gemini).
- **GitHub Multilingual Documentation**: Added localized READMEs (`README_FR.md`, `README_DE.md`, `README_IT.md`), language request issue template, and updated contribution guides.

### Fixed

- **Infinite Duplication in Client Translation**: Fixed recursive substring replacements in `schoolmanager-i18n.js` where words like `Italian` or `General` continuously grew into `Italianooooo` and `Generalee`.
- **GLPI Native Navigation Bar Integrity**: Scoped all plugin CSS so it only affects `/plugins/schoolmanager/` pages, preventing global style leakages into native GLPI topbar and breadcrumbs.
- **Sidebar Menu Branding Cache**: Invalidate `$_SESSION['glpimenu']` upon saving settings so custom app names update immediately in the GLPI sidebar without manual logout.
- **Squished Action Buttons**: Added `white-space: nowrap !important` and `flex-shrink: 0 !important` to building and room management buttons in `configuracion.php`.
- **Consistent Emoji Navigation**: Unified all 7 configuration tabs with consistent visual emojis (`⚙️`, `🌐`, `🧩`, `🎨`, `🗺️`, `🏫`, `⚡`).
- **PHP Warnings**:
  - Handled missing `glpi_consumableitemtypes` table check via `$DB->tableExists()` in `inc/stock_helpers.php`.
  - Fixed `Undefined array key "available"` in `front/stock_glpi.php:159`.
  - Removed query strings (`?v=...`) from `$PLUGIN_HOOKS` asset registrations in `setup.php` to eliminate GLPI `Html.php` warnings.
- **Removed Redundant Inline CSS**: Eliminated heavy inline `@readfile(gestion-schoolmanager-theme.css)` from 6 front pages, improving load times and enabling reliable theme switching.

### Improved

- **Modular Runtime Translation Architecture**: Consolidated phrase mappings into dedicated JSON phrase maps (`es_en_map.json`, `es_fr_map.json`, `es_de_map.json`, `es_it_map.json`), removing ~640 lines of hardcoded arrays from `inc/config.php`.
- **Cascade Fallback Resolution**: Robust fallback hierarchy (`active locale` -> `en_GB` -> `es_ES` -> `key`) ensuring no blank or untranslated UI element occurs.
- **Multi-language Entity Labels**: Added support for language-specific suffixes (`_en`, `_es`, `_fr`, `_de`, `_it`) for buildings, floors, and rooms.

---

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
