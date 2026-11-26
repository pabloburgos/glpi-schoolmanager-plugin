# Plugin Pages

This document explains the main PHP pages included in the School Manager plugin.

> All user-facing entrypoints have been standardized to clear English filenames under `schoolmanager/front/`.  
> Legacy Spanish URLs remain supported automatically via `schoolmanager/front/.htaccess` rewrite rules.

## Main pages

| File | Purpose |
|---|---|
| `front/index.php` | Redirects to the main School Manager dashboard. |
| `front/dashboard.php` | Main dashboard for users, teachers, technicians and administrators. |
| `front/settings.php` | Unified plugin configuration center: branding, modules, `diagrams.net` floor-plan toggle, buildings, rooms, maps and languages. |
| `front/install.php` | Dedicated post-install / post-update activation screen. |
| `front/credits.php` | Credits and license information inside the plugin. |
| `front/versions.php` | Version notes and internal documentation page. |
| `front/file_editor.php` | Internal template/file editor with automatic `.bak` backups (Super-Admin only, optional module). |
| `front/media.php` | Safe logo/media delivery endpoint used by the plugin. |
| `front/error.php` | Friendly error page for restricted or invalid actions. |
| `front/i18n.php` | JSON endpoint for translation strings used by JavaScript. |
| `front/user_mode.php` | JSON endpoint used to identify the current simplified user mode. |

## Ticket and support pages

| File | Purpose |
|---|---|
| `front/new_ticket.php` | Step-by-step guided ticket creation form (desktop & mobile optimized). |
| `front/my_requests.php` | User request list and status tracking. |
| `front/ticket_detail.php` | Request details page with replies, resolution and used material. |
| `front/notifications.php` | Notices and user-facing support alerts. |
| `front/it_panel.php` | IT technician dashboard. |
| `front/technician_summary.php` | Technician summary page. |
| `front/it_assignments.php` | Automatic assignment rules for technicians. |

## Classroom, locations and maps

| File | Purpose |
|---|---|
| `front/selector.php` | Classroom selector by building and floor (with `diagrams.net` plans or list-only fallback). |
| `front/plan_frame.php` | Embedded map renderer used by the selector and modal. |
| `front/map.php` | Compatibility redirect to `selector.php`. |
| `front/heatmap.php` | Retired heat map page with a friendly redirect/notice. |
| `front/classrooms.php` | Classroom directory list with GLPI IDs and quick links. |
| `front/classroom_detail.php` | Classroom detail page with inventory summary and actions. |
| `front/classroom_assets.php` | JSON endpoint for assets linked to a classroom/location. |
| `front/locate.php` | Compatibility resolver for classroom codes and GLPI locations. |

## Asset pages

| File | Purpose |
|---|---|
| `front/asset_management.php` | Asset management list, filters and shortcuts. |
| `front/new_asset.php` | Step-by-step guided asset creation (desktop & mobile optimized). |
| `front/new_computer.php` | Compatibility redirect to `new_asset.php?itemtype=Computer`. |
| `front/edit_asset.php` | Simplified asset edit page. |

## Stock pages

| File | Purpose |
|---|---|
| `front/stock.php` | IT stock dashboard. |
| `front/stock_item.php` | Stock item detail page, movements and unit history. |
| `front/stock_movement.php` | Stock movement handler. |

## Internal helper files

| Folder | Purpose |
|---|---|
| `inc/` | Shared layout (`layout.php`), mobile form UX (`form_mobile_ux.php`), permissions, configuration, stock, stats, assets and map helpers. |
| `css/` | Plugin stylesheets, themes and generated theme CSS. |
| `js/` | JavaScript helpers, native combobox restorer and generated runtime config. |
| `locales/` | 6 official dictionaries (`en_GB`, `es_ES`, `fr_FR`, `de_DE`, `it_IT`, `pt_PT`), `template.php`, and runtime JSON phrase maps. |
| `data/` | Local default configuration and assignment rules. |
| `maps/` | Map examples and uploaded map resources. |

## Notes for contributors

- Keep user-facing text translatable.
- Avoid hardcoding school names, classroom codes or internal locations.
- Keep native GLPI links available when the user has permission.
- If you rename a file, update internal links, `front/.htaccess` (if needed) and this document.
