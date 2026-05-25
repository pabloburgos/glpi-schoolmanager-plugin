# Plugin Pages

This document explains the main PHP pages included in the School Manager plugin.

> Some file names are in Spanish because the original prototype was created in Spanish.  
> The public project is documented in English and Spanish, and the UI can be configured from GLPI.

## Main pages

| File | Purpose |
|---|---|
| `front/index.php` | Redirects to the main School Manager dashboard. |
| `front/formularios.php` | Main dashboard for users, teachers, technicians and administrators. |
| `front/configuracion.php` | Plugin configuration page: branding, modules, buildings, rooms, maps and language. |
| `front/instalacion.php` | Initial setup wizard entrypoint. |
| `front/creditos.php` | Credits and license information inside the plugin. |
| `front/versiones.php` | Version notes and internal documentation page. |
| `front/media.php` | Safe logo/media delivery endpoint used by the plugin. |
| `front/error.php` | Friendly error page for restricted or invalid actions. |
| `front/i18n.php` | JSON endpoint for translation strings used by JavaScript. |
| `front/user_mode.php` | JSON endpoint used to identify the current simplified user mode. |

## Ticket and support pages

| File | Purpose |
|---|---|
| `front/nueva_incidencia.php` | Guided ticket creation form. |
| `front/mis_solicitudes.php` | User request list and status tracking. |
| `front/solicitud_detalle.php` | Request details page with replies, resolution and used material. |
| `front/avisos.php` | Notices and user-facing support alerts. |
| `front/panel_tic.php` | IT technician dashboard. |
| `front/tecnico_resumen.php` | Technician summary page. |
| `front/asignaciones_tic.php` | Automatic assignment rules for technicians. |

## Classroom, locations and maps

| File | Purpose |
|---|---|
| `front/selector.php` | Classroom map selector by building and floor. |
| `front/plan_frame.php` | Embedded map renderer used by the selector and modal. |
| `front/mapa.php` | Compatibility redirect to the selector. |
| `front/mapa_calor.php` | Retired heat map page with a friendly message. |
| `front/aulas.php` | Classroom list. |
| `front/detalle_aula.php` | Classroom detail page with inventory summary and actions. |
| `front/assets_aula.php` | JSON endpoint for assets linked to a classroom/location. |
| `front/locate.php` | Compatibility resolver for classroom codes and GLPI locations. |

## Asset pages

| File | Purpose |
|---|---|
| `front/gestion_activos.php` | Asset management list, filters and shortcuts. |
| `front/nuevo_activo.php` | Guided asset creation. |
| `front/nuevo_ordenador.php` | Compatibility redirect to `nuevo_activo.php?itemtype=Computer`. |
| `front/editar_activo.php` | Simplified asset edit page. |

## Stock pages

| File | Purpose |
|---|---|
| `front/stock_glpi.php` | IT stock dashboard. |
| `front/stock_item.php` | Stock item detail page, movements and unit history. |
| `front/stock_movimiento.php` | Stock movement handler. |

## Internal helper files

| Folder | Purpose |
|---|---|
| `inc/` | PHP helpers, permissions, configuration, stock, stats, assets and map classes. |
| `css/` | Plugin stylesheets and generated theme CSS. |
| `js/` | JavaScript helpers and generated runtime config. |
| `locales/` | Spanish and English translations. |
| `data/` | Local default configuration and assignment rules. |
| `maps/` | Map examples and uploaded map resources. |

## Notes for contributors

- Keep user-facing text translatable.
- Avoid hardcoding school names, classroom codes or internal locations.
- Keep native GLPI links available when the user has permission.
- If you rename a file, update internal links and this document.
