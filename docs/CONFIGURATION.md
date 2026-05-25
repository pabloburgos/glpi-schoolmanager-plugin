# Configuration

School Manager can be configured from GLPI.

## Recommended first setup

1. Set the plugin name.
2. Set the left menu label.
3. Upload the logo.
4. Enable or disable modules.
5. Create buildings.
6. Create floors.
7. Create classrooms.
8. Upload or configure maps.
9. Review language.

## Left menu label

If you change the left menu label, deactivate and activate the plugin again from GLPI.

GLPI caches plugin menu labels, so the change may not appear immediately.

## Logo

The logo is used in plugin headers and configuration pages.

If the logo does not refresh:

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

Then refresh the browser with `Ctrl + F5`.

## Maps

Example maps are included in:

```text
schoolmanager/maps/planos
public_maps/planos
```

Supported examples:

- HTML
- PNG
- SVG
