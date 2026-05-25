# Installation and Update

## One-command install/update

Run this on your GLPI server:

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/pabloburgos/glpi-schoolmanager-plugin/main/scripts/install-or-update.sh)"
```

The installer is designed to preserve:

- Plugin configuration
- Uploaded logo
- Uploaded maps
- Local plugin data

## Correct path

The final plugin path must be:

```text
/var/www/glpi/plugins/schoolmanager
```

## After installation

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

Refresh the browser:

```text
Ctrl + F5
```

## Manual backup before updating

```bash
sudo cp -a /var/www/glpi/plugins/schoolmanager/data /tmp/schoolmanager-data-backup
sudo cp -a /var/www/glpi/plugins/schoolmanager/maps/uploads /tmp/schoolmanager-map-uploads-backup
```
