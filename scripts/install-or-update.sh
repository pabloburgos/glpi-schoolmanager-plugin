#!/usr/bin/env bash
set -euo pipefail

REPO_URL="${SCHOOLMANAGER_REPO_URL:-https://github.com/pabloburgos/glpi-schoolmanager-plugin.git}"
REPO_BRANCH="${SCHOOLMANAGER_BRANCH:-main}"
GLPI_DIR="${GLPI_DIR:-/var/www/glpi}"
PLUGIN_DIR="$GLPI_DIR/plugins/schoolmanager"
PUBLIC_MAPS_DIR="${PUBLIC_MAPS_DIR:-$GLPI_DIR/maps}"
TMP_DIR="/tmp/glpi-schoolmanager-plugin-install"
BACKUP_DIR="/tmp/schoolmanager-preserve-$(date +%Y%m%d-%H%M%S)"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-$WEB_USER}"

say() { printf '%s\n' "$*"; }

say "== School Manager installer / updater =="

if [ "$(id -u)" -ne 0 ]; then
  say "Please run this script as root, for example with sudo."
  exit 1
fi

if [ ! -d "$GLPI_DIR" ]; then
  say "GLPI_DIR not found: $GLPI_DIR"
  exit 1
fi

command -v git >/dev/null 2>&1 || { say "git is required"; exit 1; }
command -v rsync >/dev/null 2>&1 || { say "rsync is required"; exit 1; }
command -v php >/dev/null 2>&1 || { say "php is required"; exit 1; }

rm -rf "$TMP_DIR"
git clone --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$TMP_DIR"

if [ ! -d "$TMP_DIR/schoolmanager" ]; then
  say "Repository does not contain the schoolmanager plugin folder."
  exit 1
fi

mkdir -p "$BACKUP_DIR"
if [ -d "$PLUGIN_DIR/data" ]; then
  mkdir -p "$BACKUP_DIR/data"
  rsync -a "$PLUGIN_DIR/data/" "$BACKUP_DIR/data/"
fi
if [ -d "$PLUGIN_DIR/maps/uploads" ]; then
  mkdir -p "$BACKUP_DIR/maps/uploads"
  rsync -a "$PLUGIN_DIR/maps/uploads/" "$BACKUP_DIR/maps/uploads/"
fi
if [ -f "$PLUGIN_DIR/css/generated-theme.css" ]; then
  mkdir -p "$BACKUP_DIR/css"
  cp -a "$PLUGIN_DIR/css/generated-theme.css" "$BACKUP_DIR/css/generated-theme.css"
fi
if [ -f "$PLUGIN_DIR/js/generated-config.js" ]; then
  mkdir -p "$BACKUP_DIR/js"
  cp -a "$PLUGIN_DIR/js/generated-config.js" "$BACKUP_DIR/js/generated-config.js"
fi

mkdir -p "$PLUGIN_DIR"
rsync -a --delete \
  --exclude 'data/config.php' \
  --exclude 'data/config.json' \
  --exclude 'data/tic_assignment_rules.php' \
  --exclude 'maps/uploads/' \
  "$TMP_DIR/schoolmanager/" "$PLUGIN_DIR/"

mkdir -p "$PLUGIN_DIR/data" "$PLUGIN_DIR/maps/uploads" "$PLUGIN_DIR/css" "$PLUGIN_DIR/js"
if [ -d "$BACKUP_DIR/data" ]; then rsync -a "$BACKUP_DIR/data/" "$PLUGIN_DIR/data/"; fi
if [ -d "$BACKUP_DIR/maps/uploads" ]; then rsync -a "$BACKUP_DIR/maps/uploads/" "$PLUGIN_DIR/maps/uploads/"; fi
if [ -f "$BACKUP_DIR/css/generated-theme.css" ]; then cp -a "$BACKUP_DIR/css/generated-theme.css" "$PLUGIN_DIR/css/generated-theme.css"; fi
if [ -f "$BACKUP_DIR/js/generated-config.js" ]; then cp -a "$BACKUP_DIR/js/generated-config.js" "$PLUGIN_DIR/js/generated-config.js"; fi

# Optional public maps/resources. The plugin itself works without this folder,
# but copying examples here keeps compatibility with deployments that serve maps
# from /var/www/glpi/maps.
if [ -d "$TMP_DIR/public_maps" ]; then
  mkdir -p "$PUBLIC_MAPS_DIR"
  rsync -a "$TMP_DIR/public_maps/" "$PUBLIC_MAPS_DIR/"
fi

chown -R "$WEB_USER:$WEB_GROUP" "$PLUGIN_DIR"
chmod -R a+rX "$PLUGIN_DIR"
chmod -R u+rwX,g+rwX "$PLUGIN_DIR/data" "$PLUGIN_DIR/maps" "$PLUGIN_DIR/css" "$PLUGIN_DIR/js"

if [ -d "$PUBLIC_MAPS_DIR" ]; then
  chown -R "$WEB_USER:$WEB_GROUP" "$PUBLIC_MAPS_DIR" || true
  chmod -R a+rX "$PUBLIC_MAPS_DIR" || true
fi

cd "$GLPI_DIR"
rm -rf files/_cache/* files/_tmp/* files/_plugins_cache/* 2>/dev/null || true

if [ -f bin/console ]; then
  sudo -u "$WEB_USER" php bin/console glpi:plugin:deactivate schoolmanager -n || true
  sudo -u "$WEB_USER" php bin/console glpi:plugin:install schoolmanager -n --force || true
  sudo -u "$WEB_USER" php bin/console glpi:plugin:activate schoolmanager -n || true
  sudo -u "$WEB_USER" php bin/console cache:clear || true
fi

systemctl restart apache2 2>/dev/null || systemctl restart httpd 2>/dev/null || true
rm -rf "$TMP_DIR"

say "Done. Local settings were preserved in: $BACKUP_DIR"
say "Plugin path: $PLUGIN_DIR"
say "Open: /plugins/schoolmanager/front/index.php"
