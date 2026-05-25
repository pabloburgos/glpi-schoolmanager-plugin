# Compatibility

This page summarizes the intended compatibility targets for School Manager.

## GLPI

| GLPI version | Status | Notes |
|---|---|---|
| GLPI 10.x | Supported target | Main compatibility target. |
| GLPI 11.x | Supported target | Intended compatibility target, test before production updates. |

## PHP

| PHP version | Status | Notes |
|---|---|---|
| PHP 8.x | Supported target | Recommended for current GLPI deployments. |

## Server stack

| Component | Recommended option |
|---|---|
| Operating system | Ubuntu Server LTS or another supported Linux server distribution. |
| Web server | Apache with PHP support. |
| Database | MariaDB or MySQL supported by your GLPI version. |

## Browser support

School Manager is designed for modern browsers:

- Chrome / Chromium
- Edge
- Firefox
- Safari

## Notes

- Always test updates in a staging GLPI installation before applying them in production.
- Keep GLPI, PHP and the operating system updated.
- The plugin should be installed in `/var/www/glpi/plugins/schoolmanager`.
