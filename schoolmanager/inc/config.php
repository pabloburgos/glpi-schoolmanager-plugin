<?php
if (!defined('GLPI_ROOT')) { die('No direct access'); }

function plugin_schoolmanager_deep_merge(array $base, array $override): array {
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && array_keys($value) !== range(0, count($value) - 1)) {
            $base[$key] = plugin_schoolmanager_deep_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}


function plugin_schoolmanager_normalize_license_label(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return 'GPLv3+';
    }
    $plain = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    if (strpos($plain, 'cc by') !== false || strpos($plain, 'creative commons') !== false || strpos($plain, 'cc-by') !== false) {
        return 'GPLv3+';
    }
    if (strpos($plain, 'gpl') !== false) {
        return 'GPLv3+';
    }
    return $value;
}

function plugin_schoolmanager_normalize_config(array $cfg): array {
    if (!isset($cfg['schema']) || (int)$cfg['schema'] < 6) {
        $cfg['schema'] = 6;
    }
    if (!isset($cfg['credits']) || !is_array($cfg['credits'])) {
        $cfg['credits'] = [];
    }
    $cfg['credits']['authors'] = trim((string)($cfg['credits']['authors'] ?? 'Pablo Burgos y Alejandro Galán')) ?: 'Pablo Burgos y Alejandro Galán';
    $cfg['credits']['email'] = trim((string)($cfg['credits']['email'] ?? 'pabloburgostena@faltax.app')) ?: 'pabloburgostena@faltax.app';
    $cfg['credits']['license'] = plugin_schoolmanager_normalize_license_label((string)($cfg['credits']['license'] ?? 'GPLv3+'));
    $licenseUrl = trim((string)($cfg['credits']['license_url'] ?? ''));
    if ($licenseUrl === '' || stripos($licenseUrl, 'creativecommons.org') !== false) {
        $cfg['credits']['license_url'] = 'https://www.gnu.org/licenses/gpl-3.0.html';
    }
    $cfg['credits']['show_footer_credit'] = !empty($cfg['credits']['show_footer_credit']);
    if (isset($cfg['brand']['organization']) && trim((string)$cfg['brand']['organization']) === 'Educational center') {
        $cfg['brand']['organization'] = 'Centro educativo';
    }
    return $cfg;
}

function plugin_schoolmanager_force_normalize_persist(): void {
    if (!function_exists('plugin_schoolmanager_save_config')) {
        return;
    }
    $raw = plugin_schoolmanager_default_config();
    $jsonFile = plugin_schoolmanager_config_json_file();
    $phpFile = plugin_schoolmanager_config_file();
    $user = null;
    if (is_file($jsonFile)) {
        $json = @file_get_contents($jsonFile);
        $tmp = is_string($json) ? json_decode($json, true) : null;
        if (is_array($tmp)) {
            $user = $tmp;
        }
    }
    if ($user === null && is_file($phpFile)) {
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($phpFile, true); }
        $tmp = include($phpFile);
        if (is_array($tmp)) {
            $user = $tmp;
        }
    }
    if (is_array($user)) {
        $raw = plugin_schoolmanager_deep_merge($raw, $user);
    }
    $normalized = plugin_schoolmanager_normalize_config($raw);
    if ($normalized != $raw) {
        plugin_schoolmanager_save_config($normalized);
    }
}

function plugin_schoolmanager_default_config(): array {
    return [
        'schema' => 6,
        'setup_complete' => false,
        'configured_at' => null,
        'locale' => 'auto',
        'brand' => [
            'title' => 'School Manager',
            'menu_title' => 'School Manager',
            // Backward compatible keys kept for older saved configurations.
            'title_es' => 'School Manager',
            'title_en' => 'School Manager',
            'organization' => 'Centro educativo',
            'logo' => 'logo.png',
            'show_logo' => true,
        ],
        'credits' => [
            'authors' => 'Pablo Burgos y Alejandro Galán',
            'email' => 'pabloburgostena@faltax.app',
            'license' => 'GPLv3+',
            'license_url' => 'https://www.gnu.org/licenses/gpl-3.0.html',
            'show_footer_credit' => false,
        ],
        'theme' => [
            'palette' => 'teal-red',
            'density' => 'comfortable',
            'radius' => '18px',
            'primary' => '#07384D',
            'secondary' => '#075D61',
            'accent' => '#B6252B',
            'soft' => '#F4FAFC',
            'background' => '#EEF6F9',
            'card' => '#FFFFFF',
            'text' => '#07384D',
            'muted' => '#5F7180',
            'border' => '#D7E6EC',
            'dark' => false,
        ],
        'features' => [
            'tickets' => true,
            'classrooms' => true,
            'plans' => true,
            'assets' => true,
            'stock' => true,
            'tic_panel' => true,
            'tic_assignment_rules' => true,
            'versions' => false,
            'file_editor' => false,
        ],
        'roles' => [
            'professor_profiles' => ['profesor', 'teacher', 'self-service', 'post-only'],
            'technician_profiles' => ['tecnico tic', 'técnico tic', 'technician', 'it technician'],
            'it_admin_profiles' => ['admin tic', 'it admin', 'ict admin'],
        ],
        'buildings' => [
            [
                'code' => 'MAIN', 'enabled' => true, 'name_es' => 'Edificio principal', 'name_en' => 'Main building',
                'floors' => [
                    ['code' => 'G', 'enabled' => true, 'label_es' => 'Planta baja', 'label_en' => 'Ground floor', 'number' => '0', 'plan' => '', 'select_plan' => ''],
                    ['code' => 'F1', 'enabled' => true, 'label_es' => 'Primera planta', 'label_en' => 'First floor', 'number' => '1', 'plan' => '', 'select_plan' => ''],
                ],
            ],
        ],
        'rooms' => [
            ['building'=>'MAIN','room'=>'101','code'=>'MAIN-101','floor'=>'G','name_es'=>'Aula de ejemplo','name_en'=>'Sample classroom','glpi_location_id'=>null],
            ['building'=>'MAIN','room'=>'IT','code'=>'MAIN-IT','floor'=>'G','name_es'=>'Aula TIC / informática','name_en'=>'IT / computer room','glpi_location_id'=>null],
        ],
    ];
}

function plugin_schoolmanager_config_file(): string { return __DIR__ . '/../data/config.php'; }
function plugin_schoolmanager_config_json_file(): string { return __DIR__ . '/../data/config.json'; }
function plugin_schoolmanager_user_upload_dir(): string { return __DIR__ . '/../maps/uploads'; }
function plugin_schoolmanager_plan_root(): string { return __DIR__ . '/../maps/planos'; }

function plugin_schoolmanager_public_url(string $relative): string {
    global $CFG_GLPI;
    $root = $CFG_GLPI['root_doc'] ?? '';
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    return $root . '/plugins/schoolmanager/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
}

function plugin_schoolmanager_create_glpi_location(string $name, int $parent_id = 0): int {
    $name = trim($name);
    if ($name === '' || !class_exists('Location')) { return 0; }
    global $DB;
    try {
        if (isset($DB) && method_exists($DB, 'request')) {
            $where = ['name' => $name];
            if (method_exists($DB, 'fieldExists') && $DB->fieldExists('glpi_locations', 'is_deleted')) {
                $where['is_deleted'] = 0;
            }
            if ($parent_id > 0) { $where['locations_id'] = $parent_id; }
            $it = $DB->request(['FROM' => 'glpi_locations', 'WHERE' => $where, 'LIMIT' => 1]);
            foreach ($it as $row) { return (int)$row['id']; }
        }
    } catch (Throwable $e) {}
    try {
        $loc = new Location();
        $input = [
            'name' => $name,
            'entities_id' => (int)($_SESSION['glpiactive_entity'] ?? 0),
            'is_recursive' => 1,
        ];
        if ($parent_id > 0) { $input['locations_id'] = $parent_id; }
        $id = (int)$loc->add($input);
        return $id > 0 ? $id : 0;
    } catch (Throwable $e) { return 0; }
}

function plugin_schoolmanager_config(bool $force_reload = false): array {
    if (!$force_reload && isset($GLOBALS['PLUGIN_SCHOOLMANAGER_CONFIG_CACHE']) && is_array($GLOBALS['PLUGIN_SCHOOLMANAGER_CONFIG_CACHE'])) { return $GLOBALS['PLUGIN_SCHOOLMANAGER_CONFIG_CACHE']; }
    $cfg = plugin_schoolmanager_default_config();
    $jsonFile = plugin_schoolmanager_config_json_file();
    $phpFile = plugin_schoolmanager_config_file();
    $user = null;
    if (is_file($jsonFile)) {
        $raw = @file_get_contents($jsonFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) { $user = $decoded; }
    }
    if ($user === null && is_file($phpFile)) {
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($phpFile, true); }
        $tmp = include($phpFile);
        if (is_array($tmp)) { $user = $tmp; }
    }
    if (is_array($user)) { $cfg = plugin_schoolmanager_deep_merge($cfg, $user); }
    $cfg = plugin_schoolmanager_normalize_config($cfg);
    $GLOBALS['PLUGIN_SCHOOLMANAGER_CONFIG_CACHE'] = $cfg;
    return $cfg;
}

function plugin_schoolmanager_reload_config(): array { return plugin_schoolmanager_config(true); }

function plugin_schoolmanager_is_configured(): bool {
    $cfg = plugin_schoolmanager_config();
    return !empty($cfg['setup_complete']);
}

function plugin_schoolmanager_setup_url(): string {
    global $CFG_GLPI;
    return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/install.php';
}

function plugin_schoolmanager_require_initial_setup(): void {
    if (plugin_schoolmanager_is_configured()) { return; }
    if (!class_exists('Session') || !Session::getLoginUserID()) { return; }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/plugins/schoolmanager/front/install.php') !== false || strpos($uri, '/plugins/schoolmanager/front/settings.php') !== false || strpos($uri, '/plugins/schoolmanager/front/media.php') !== false || strpos($uri, '/plugins/schoolmanager/front/i18n.php') !== false) { return; }
    if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) {
        Html::redirect(plugin_schoolmanager_setup_url());
    }
}

function plugin_schoolmanager_credit_html(): string {
    $cfg = plugin_schoolmanager_config();
    $credits = $cfg['credits'] ?? [];
    $authors = htmlspecialchars((string)($credits['authors'] ?? 'Pablo Burgos y Alejandro Galán'), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string)($credits['email'] ?? 'pabloburgostena@faltax.app'), ENT_QUOTES, 'UTF-8');
    $license = htmlspecialchars(plugin_schoolmanager_normalize_license_label((string)($credits['license'] ?? 'GPLv3+')), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars((string)($credits['license_url'] ?? 'https://www.gnu.org/licenses/gpl-3.0.html'), ENT_QUOTES, 'UTF-8');
    return '<div class="schoolmanager-credit">' . plugin_schoolmanager_tr('credits_by', 'Created by') . ' <strong>' . $authors . '</strong> (<a href="mailto:' . $email . '">' . $email . '</a>) · <a href="' . $url . '" target="_blank" rel="noopener">' . $license . '</a></div>';
}

function plugin_schoolmanager_save_config(array $cfg): bool {
    $dir = dirname(plugin_schoolmanager_config_file());
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (!is_writable($dir)) { @chmod($dir, 0775); }

    $jsonFile = plugin_schoolmanager_config_json_file();
    $phpFile = plugin_schoolmanager_config_file();
    $jsonPayload = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($jsonPayload)) { return false; }

    $tmpJson = $jsonFile . '.tmp';
    $ok = (bool)@file_put_contents($tmpJson, $jsonPayload . "
", LOCK_EX);
    if ($ok) { $ok = @rename($tmpJson, $jsonFile); }
    if (!$ok) { @unlink($tmpJson); return false; }
    @chmod($jsonFile, 0664);

    $phpPayload = "<?php
return " . var_export($cfg, true) . ";
";
    $tmpPhp = $phpFile . '.tmp';
    if (@file_put_contents($tmpPhp, $phpPayload, LOCK_EX) !== false) {
        @rename($tmpPhp, $phpFile);
        @chmod($phpFile, 0664);
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($phpFile, true); }
    } else { @unlink($tmpPhp); }

    clearstatcache(true);
    $GLOBALS['PLUGIN_SCHOOLMANAGER_CONFIG_CACHE'] = $cfg;
    plugin_schoolmanager_write_theme_css($cfg);
    if (function_exists('plugin_schoolmanager_write_runtime_config_js')) { plugin_schoolmanager_write_runtime_config_js($cfg); }
    return true;
}

function plugin_schoolmanager_builtin_locales(): array {
    return ['en_GB', 'es_ES', 'fr_FR', 'de_DE', 'it_IT', 'pt_PT'];
}

function plugin_schoolmanager_available_locales(bool $includeDetails = false): array {
    $localesDir = __DIR__ . '/../locales';
    $builtins = plugin_schoolmanager_builtin_locales();
    $knownNames = [
        'en_GB' => 'English (UK)',
        'es_ES' => 'Español',
        'fr_FR' => 'Français',
        'de_DE' => 'Deutsch',
        'it_IT' => 'Italiano',
        'pt_PT' => 'Português',
        'en_US' => 'English (US)',
        'pt_BR' => 'Português (Brasil)',
        'ca_ES' => 'Català',
    ];

    $found = [];
    if (is_dir($localesDir)) {
        $files = scandir($localesDir) ?: [];
        foreach ($files as $f) {
            if ($f === 'template.php' || preg_match('/_map\.json$/', $f) || !preg_match('/^([a-z]{2}(?:_[A-Z]{2})?)\.(php|json)$/', $f, $m)) {
                continue;
            }
            $code = $m[1];
            $ext = $m[2];
            $filePath = $localesDir . '/' . $f;

            if (isset($found[$code]) && $ext === 'json') {
                continue;
            }

            $name = $knownNames[$code] ?? $code;
            $keysCount = 0;

            if ($ext === 'php') {
                $content = @include $filePath;
                if (is_array($content)) {
                    if (!empty($content['_language_name']) && is_string($content['_language_name'])) {
                        $name = trim($content['_language_name']);
                    }
                    $keysCount = count(array_filter(array_keys($content), static fn($k) => $k !== '_language_name' && !str_starts_with((string)$k, '_')));
                }
            } elseif ($ext === 'json') {
                $raw = @file_get_contents($filePath);
                $content = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($content)) {
                    if (!empty($content['_language_name']) && is_string($content['_language_name'])) {
                        $name = trim($content['_language_name']);
                    }
                    $keysCount = count(array_filter(array_keys($content), static fn($k) => $k !== '_language_name' && !str_starts_with((string)$k, '_')));
                }
            }

            $isBuiltin = in_array($code, $builtins, true);
            $found[$code] = [
                'code' => $code,
                'name' => $name,
                'is_builtin' => $isBuiltin,
                'is_custom' => !$isBuiltin,
                'file' => $f,
                'format' => $ext,
                'keys_count' => $keysCount,
                'mtime' => filemtime($filePath),
            ];
        }
    }

    $ordered = [];
    foreach ($builtins as $bCode) {
        if (isset($found[$bCode])) {
            $ordered[$bCode] = $found[$bCode];
            unset($found[$bCode]);
        }
    }
    uasort($found, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    foreach ($found as $k => $v) {
        $ordered[$k] = $v;
    }

    if ($includeDetails) {
        return $ordered;
    }

    $simple = [];
    foreach ($ordered as $code => $info) {
        $simple[$code] = $info['name'];
    }
    return $simple;
}

function plugin_schoolmanager_locale(): string {
    $cfg = plugin_schoolmanager_config();
    $locale = (string)($cfg['locale'] ?? 'auto');
    if ($locale === 'auto' || $locale === '') {
        $glpi = (string)($_SESSION['glpilanguage'] ?? $_SESSION['glpilocale'] ?? '');
        $glpi = trim($glpi);
        $available = plugin_schoolmanager_available_locales(true);
        $availableCodes = array_keys($available);
        if (in_array($glpi, $availableCodes, true)) {
            return $glpi;
        }
        $prefix2 = substr($glpi, 0, 2);
        if ($prefix2 !== '') {
            foreach ($availableCodes as $code) {
                if (str_starts_with($code, $prefix2)) {
                    return $code;
                }
            }
        }
        return 'en_GB';
    }

    $filePhp = __DIR__ . '/../locales/' . $locale . '.php';
    $fileJson = __DIR__ . '/../locales/' . $locale . '.json';
    if (is_file($filePhp) || is_file($fileJson)) {
        return $locale;
    }
    return 'en_GB';
}

function plugin_schoolmanager_tr(string $key, ?string $fallback = null): string {
    static $dicts = [];
    $locale = plugin_schoolmanager_locale();

    $load = static function(string $loc) use (&$dicts): array {
        if (!isset($dicts[$loc])) {
            $file = __DIR__ . '/../locales/' . $loc . '.php';
            if (is_file($file)) {
                $tmp = include $file;
                $dicts[$loc] = is_array($tmp) ? $tmp : [];
            } else {
                $json = __DIR__ . '/../locales/' . $loc . '.json';
                if (is_file($json)) {
                    $raw = @file_get_contents($json);
                    $tmp = is_string($raw) ? json_decode($raw, true) : null;
                    $dicts[$loc] = is_array($tmp) ? $tmp : [];
                } else {
                    $dicts[$loc] = [];
                }
            }
        }
        return $dicts[$loc];
    };

    $current = $load($locale);
    if (isset($current[$key]) && is_string($current[$key]) && $current[$key] !== '') {
        return $current[$key];
    }

    if (str_starts_with($locale, 'es') && $fallback !== null && $fallback !== '') {
        return $fallback;
    }

    if ($locale !== 'en_GB' && !str_starts_with($locale, 'es')) {
        $en = $load('en_GB');
        if (isset($en[$key]) && is_string($en[$key]) && $en[$key] !== '') {
            return $en[$key];
        }
    }

    if ($locale !== 'es_ES') {
        $es = $load('es_ES');
        if (isset($es[$key]) && is_string($es[$key]) && $es[$key] !== '') {
            return $es[$key];
        }
    }

    return (string)($fallback ?? $key);
}

function plugin_schoolmanager_label(array $item, string $base, ?string $fallback = null): string {
    $locale = plugin_schoolmanager_locale();
    $lang = strtolower(substr($locale, 0, 2));

    if (isset($item[$base . '_' . $locale]) && trim((string)$item[$base . '_' . $locale]) !== '') {
        return (string)$item[$base . '_' . $locale];
    }
    if (isset($item[$base . '_' . $lang]) && trim((string)$item[$base . '_' . $lang]) !== '') {
        return (string)$item[$base . '_' . $lang];
    }
    if (isset($item[$base . '_en']) && trim((string)$item[$base . '_en']) !== '') {
        return (string)$item[$base . '_en'];
    }
    if (isset($item[$base . '_es']) && trim((string)$item[$base . '_es']) !== '') {
        return (string)$item[$base . '_es'];
    }
    return (string)($item[$base] ?? $fallback ?? '');
}

function plugin_schoolmanager_save_locale_file(string $code, array $keys, ?string $name = null, ?string &$error = null): bool {
    $code = trim($code);
    if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
        $error = 'Invalid locale code format. Expected e.g. pt_PT, ca_ES, or nl.';
        return false;
    }
    $localesDir = __DIR__ . '/../locales';
    if (!is_dir($localesDir)) {
        @mkdir($localesDir, 0775, true);
    }
    if (!is_writable($localesDir)) {
        $error = 'Locales directory is not writable.';
        return false;
    }

    $cleanKeys = [];
    if (!empty($name)) {
        $cleanKeys['_language_name'] = trim($name);
    } elseif (isset($keys['_language_name']) && trim((string)$keys['_language_name']) !== '') {
        $cleanKeys['_language_name'] = trim((string)$keys['_language_name']);
    }

    foreach ($keys as $k => $v) {
        $k = trim((string)$k);
        if ($k === '' || $k === '_language_name') { continue; }
        $cleanKeys[$k] = (string)$v;
    }

    $export = var_export($cleanKeys, true);
    $content = "<?php\n/**\n * GLPI School Manager - Locale Dictionary: {$code}\n */\nreturn {$export};\n";

    $targetFile = $localesDir . '/' . $code . '.php';
    $tmpFile = $targetFile . '.tmp';
    if (@file_put_contents($tmpFile, $content, LOCK_EX) === false) {
        $error = 'Failed to write temporary locale file.';
        return false;
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($tmpFile, true);
    }

    if (!@rename($tmpFile, $targetFile)) {
        @unlink($tmpFile);
        $error = 'Failed to save locale file.';
        return false;
    }
    @chmod($targetFile, 0664);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($targetFile, true);
    }
    return true;
}

function plugin_schoolmanager_delete_custom_locale(string $code, ?string &$error = null): bool {
    $code = trim($code);
    if (in_array($code, plugin_schoolmanager_builtin_locales(), true)) {
        $error = 'Cannot delete built-in official locales.';
        return false;
    }
    $targetFile = __DIR__ . '/../locales/' . $code . '.php';
    $targetJson = __DIR__ . '/../locales/' . $code . '.json';
    $deleted = false;
    if (is_file($targetFile)) {
        if (@unlink($targetFile)) {
            $deleted = true;
            if (function_exists('opcache_invalidate')) { @opcache_invalidate($targetFile, true); }
        }
    }
    if (is_file($targetJson)) {
        if (@unlink($targetJson)) {
            $deleted = true;
        }
    }
    if (!$deleted) {
        $error = 'Locale file does not exist or could not be removed.';
        return false;
    }
    return true;
}

function plugin_schoolmanager_clean_brand_name(?string $value, string $fallback = 'School Manager'): string {
    $value = trim((string)$value);
    if ($value === '') { return $fallback; }
    $bad = ['Gestión Escolar GLPI','Gestion Escolar GLPI','GLPI School Manager','Gestión School Manager','Gestion School Manager','Centro educativo Manager','Educational center Manager'];
    return in_array($value, $bad, true) ? $fallback : $value;
}

function plugin_schoolmanager_app_name(): string {
    return 'School Manager';
}

function plugin_schoolmanager_menu_name(): string {
    $brand = plugin_schoolmanager_config()['brand'] ?? [];
    return plugin_schoolmanager_clean_brand_name($brand['menu_title'] ?? ($brand['menu_title_es'] ?? ($brand['menu_title_en'] ?? ($brand['title'] ?? 'School Manager'))));
}

function plugin_schoolmanager_feature_enabled(string $feature): bool {
    $features = plugin_schoolmanager_config()['features'] ?? [];
    return array_key_exists($feature, $features) ? (bool)$features[$feature] : true;
}

function plugin_schoolmanager_buildings(bool $enabledOnly = true): array {
    $out = [];
    foreach ((plugin_schoolmanager_config()['buildings'] ?? []) as $b) {
        if ($enabledOnly && empty($b['enabled'])) { continue; }
        if (empty($b['code'])) { continue; }
        $out[] = $b;
    }
    return $out;
}
function plugin_schoolmanager_building_codes(): array { return array_map(static fn($b) => strtoupper((string)$b['code']), plugin_schoolmanager_buildings()); }
function plugin_schoolmanager_building(string $code): ?array {
    $code = strtoupper($code);
    foreach (plugin_schoolmanager_buildings(false) as $b) { if (strtoupper((string)($b['code'] ?? '')) === $code) { return $b; } }
    return null;
}
function plugin_schoolmanager_floors(string $buildingCode, bool $enabledOnly = true): array {
    $b = plugin_schoolmanager_building($buildingCode);
    if (!$b) { return []; }
    $out = [];
    foreach (($b['floors'] ?? []) as $f) {
        if ($enabledOnly && empty($f['enabled'])) { continue; }
        if (empty($f['code'])) { continue; }
        $out[] = $f;
    }
    return $out;
}
function plugin_schoolmanager_floor(string $buildingCode, string $floorCode): ?array {
    $floorCode = strtoupper($floorCode);
    foreach (plugin_schoolmanager_floors($buildingCode, false) as $f) { if (strtoupper((string)($f['code'] ?? '')) === $floorCode) { return $f; } }
    return null;
}
function plugin_schoolmanager_default_building_code(): string { $b = plugin_schoolmanager_buildings(); return strtoupper((string)($b[0]['code'] ?? 'MAIN')); }
function plugin_schoolmanager_default_floor_code(string $buildingCode): string { $f = plugin_schoolmanager_floors($buildingCode); return strtoupper((string)($f[0]['code'] ?? 'G')); }

function plugin_schoolmanager_building_labels(): array {
    $out = [];
    foreach (plugin_schoolmanager_buildings() as $b) {
        $bc = strtoupper((string)($b['code'] ?? ''));
        if ($bc === '') { continue; }
        $out[$bc] = plugin_schoolmanager_label($b, 'name', $bc);
    }
    return $out;
}

function plugin_schoolmanager_floors_by_building(): array {
    $out = [];
    foreach (plugin_schoolmanager_buildings() as $b) {
        $bc = strtoupper((string)($b['code'] ?? ''));
        if ($bc === '') { continue; }
        $out[$bc] = [];
        foreach (plugin_schoolmanager_floors($bc) as $f) {
            $fc = strtoupper((string)($f['code'] ?? ''));
            if ($fc === '') { continue; }
            $out[$bc][$fc] = [(string)($f['number'] ?? $fc), plugin_schoolmanager_label($f, 'label', $fc)];
        }
    }
    return $out;
}

function plugin_schoolmanager_room_rows(): array {
    $cfg = plugin_schoolmanager_config();
    $ids = [];
    $idsFile = __DIR__ . '/ubicaciones_ids.php';
    if (is_file($idsFile)) { $tmp = include($idsFile); if (is_array($tmp)) { $ids = $tmp; } }
    $floorsByBuilding = [];
    foreach (plugin_schoolmanager_buildings(false) as $b) {
        $rank = 0;
        foreach (($b['floors'] ?? []) as $f) { $floorsByBuilding[strtoupper($b['code'])][strtoupper($f['code'])] = $rank++; }
    }
    $rows = [];
    $updatedCfg = false;
    foreach (($cfg['rooms'] ?? []) as $idx => $r) {
        $building = strtoupper((string)($r['building'] ?? ''));
        $floor = strtoupper((string)($r['floor'] ?? ''));
        $aula = (string)($r['room'] ?? $r['aula'] ?? '');
        $code = (string)($r['code'] ?? ($building . '-' . $aula));
        $floorMeta = plugin_schoolmanager_floor($building, $floor) ?: [];
        $desc = plugin_schoolmanager_label($r, 'name', (string)($r['description'] ?? ''));
        $locId = isset($r['glpi_location_id']) && (int)$r['glpi_location_id'] > 0
            ? (int)$r['glpi_location_id']
            : (isset($ids[$code]) && (int)$ids[$code] > 0 ? (int)$ids[$code] : 0);
        if ($locId <= 0 && function_exists('plugin_schoolmanager_create_glpi_location')) {
            $locName = $aula !== '' ? ($code . ' - ' . ($desc !== '' ? $desc : ('Aula ' . $aula))) : $code;
            $createdId = plugin_schoolmanager_create_glpi_location($locName, 0);
            if ($createdId > 0) {
                $locId = $createdId;
                $cfg['rooms'][$idx]['glpi_location_id'] = $locId;
                $updatedCfg = true;
            }
        }
        $rows[] = [
            'building' => $building,
            'aula' => $aula,
            'codigo' => $code,
            'planta' => plugin_schoolmanager_label($floorMeta, 'label', $floor),
            'floor' => $floor,
            'descripcion' => $desc,
            'id' => $locId > 0 ? $locId : ($idx + 1),
            'is_numbered' => true,
            'is_top_special' => false,
            'sort_floor' => $floorsByBuilding[$building][$floor] ?? 99,
        ];
    }
    if ($updatedCfg) {
        @plugin_schoolmanager_save_config($cfg);
    }
    usort($rows, static function($a, $b) {
        if ($a['building'] !== $b['building']) { return strcmp($a['building'], $b['building']); }
        if ($a['sort_floor'] !== $b['sort_floor']) { return $a['sort_floor'] <=> $b['sort_floor']; }
        return strnatcasecmp($a['aula'], $b['aula']);
    });
    return $rows;
}

function plugin_schoolmanager_logo_mtime(): int {
    $brand = plugin_schoolmanager_config()['brand'] ?? [];
    $logo = (string)($brand['logo'] ?? 'logo.png');
    $baseName = basename(str_replace('\\', '/', $logo));
    $candidates = [];
    if ($baseName !== 'logo.png') { $candidates[] = __DIR__ . '/../maps/uploads/' . $baseName; }
    $candidates[] = __DIR__ . '/../logo.png';
    $candidates[] = __DIR__ . '/../logo.svg';
    $candidates[] = plugin_schoolmanager_config_json_file();
    foreach ($candidates as $path) { if (is_file($path)) { return (int)@filemtime($path); } }
    return time();
}

function plugin_schoolmanager_logo_url(): string {
    $data = function_exists('plugin_schoolmanager_logo_data_uri') ? plugin_schoolmanager_logo_data_uri() : '';
    if ($data !== '') { return $data; }
    return plugin_schoolmanager_public_url('front/media.php') . '?asset=logo&v=' . rawurlencode((string)plugin_schoolmanager_logo_mtime());
}

function plugin_schoolmanager_safe_plan_path(string $rel): ?string {
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');
    if ($rel === '' || str_contains($rel, '..')) { return null; }
    $base = realpath(plugin_schoolmanager_plan_root());
    $path = plugin_schoolmanager_plan_root() . '/' . $rel;
    $realDir = realpath(dirname($path));
    if ($base && $realDir && str_starts_with($realDir, $base)) { return $path; }
    return null;
}
function plugin_schoolmanager_plan_path(string $building, string $floor, string $mode = 'normal'): ?string {
    $f = plugin_schoolmanager_floor($building, $floor);
    if (!$f) { return null; }
    $rel = (string)(($mode === 'select' && !empty($f['select_plan'])) ? $f['select_plan'] : ($f['plan'] ?? ''));
    return plugin_schoolmanager_safe_plan_path($rel);
}
function plugin_schoolmanager_plan_type_from_path(string $path): string { return strtolower(pathinfo($path, PATHINFO_EXTENSION)); }
function plugin_schoolmanager_plan_is_supported(string $path): bool { return in_array(plugin_schoolmanager_plan_type_from_path($path), ['html','htm','svg','png','jpg','jpeg','webp'], true); }

function plugin_schoolmanager_css_hex(string $value, string $fallback): string {
    $value = trim($value);
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : $fallback;
}

function plugin_schoolmanager_theme_presets(): array {
    return [
        'teal-red' => ['name'=>'Signature Logo (Navy & Teal)','primary'=>'#0F2951','secondary'=>'#088E91','accent'=>'#F0B010','soft'=>'#F4FAFC','background'=>'#F8FAFC','card'=>'#FFFFFF','text'=>'#0F2951','muted'=>'#64748B','border'=>'#D7E6EC','dark'=>false],
        'ocean'    => ['name'=>'Nordic Blue (Ocean Ice Glass)','primary'=>'#0B3A66','secondary'=>'#0284C7','accent'=>'#38BDF8','soft'=>'#F0F9FF','background'=>'#E0F2FE','card'=>'#FFFFFF','text'=>'#0C4A6E','muted'=>'#0369A1','border'=>'#BAE6FD','dark'=>false],
        'forest'   => ['name'=>'Campus Emerald (Botanical Glass)','primary'=>'#064E3B','secondary'=>'#059669','accent'=>'#10B981','soft'=>'#F0FDF4','background'=>'#DCFCE7','card'=>'#FFFFFF','text'=>'#064E3B','muted'=>'#047857','border'=>'#A7F3D0','dark'=>false],
        'sunset'   => ['name'=>'Warm Sunset (Amber & Coral)','primary'=>'#7C2D12','secondary'=>'#EA580C','accent'=>'#F59E0B','soft'=>'#FFF7ED','background'=>'#FFEDD5','card'=>'#FFFFFF','text'=>'#431407','muted'=>'#9A3412','border'=>'#FED7AA','dark'=>false],
        'graphite' => ['name'=>'Graphite Dark (Cyber Glass)','primary'=>'#38BDF8','secondary'=>'#0284C7','accent'=>'#818CF8','soft'=>'#1E293B','background'=>'#0B1120','card'=>'#111827','text'=>'#F1F5F9','muted'=>'#94A3B8','border'=>'#334155','dark'=>true],
        'midnight' => ['name'=>'Deep Cyber Midnight','primary'=>'#8B5CF6','secondary'=>'#6366F1','accent'=>'#EC4899','soft'=>'#0F172A','background'=>'#030712','card'=>'#090D16','text'=>'#F8FAFC','muted'=>'#A1A1AA','border'=>'#1F2937','dark'=>true],
        'custom'   => ['name'=>'Personalizado','primary'=>'#0F2951','secondary'=>'#088E91','accent'=>'#F0B010','soft'=>'#F4FAFC','background'=>'#F8FAFC','card'=>'#FFFFFF','text'=>'#0F2951','muted'=>'#64748B','border'=>'#D7E6EC','dark'=>false],
    ];
}

function plugin_schoolmanager_theme_values(?array $cfg = null): array {
    $cfg = $cfg ?: plugin_schoolmanager_config();
    $theme = $cfg['theme'] ?? [];
    $presets = plugin_schoolmanager_theme_presets();
    // Themes are currently in development; lock to official Signature Logo palette (teal-red)
    $palette = 'teal-red';
    $p = $presets[$palette];
    $radius = '16px';
    $density = (string)($theme['density'] ?? 'comfortable');
    $pad = $density === 'compact' ? '14px' : ($density === 'large' ? '24px' : '20px');
    $gap = $density === 'compact' ? '12px' : ($density === 'large' ? '24px' : '18px');
    $font = $density === 'compact' ? '14px' : ($density === 'large' ? '16px' : '15px');
    return [
        'palette'=>$palette,'name'=>(string)$p['name'],'primary'=>(string)$p['primary'],'secondary'=>(string)$p['secondary'],'accent'=>(string)$p['accent'],
        'soft'=>(string)$p['soft'],'background'=>(string)$p['background'],'card'=>(string)$p['card'],'text'=>(string)$p['text'],'muted'=>(string)$p['muted'],'border'=>(string)$p['border'],
        'dark'=>(bool)$p['dark'],'radius'=>$radius,'pad'=>$pad,'gap'=>$gap,'font'=>$font,'density'=>$density,
    ];
}

function plugin_schoolmanager_write_theme_css(?array $cfg = null): void {
    $v = plugin_schoolmanager_theme_values($cfg);
    $dark = $v['dark'] ? '1' : '0';
    $c = '.schoolmanager-configured,.sm-page-shell,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.stp,.sid-wrap,.sid,.gsm-wrap,.gsm,.gsm-assets,.gsm-edit,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.pc-editor,.ad-wrap,.da-wrap,.tr-wrap,.tr,.pcas,.pcas-wrap,.pc-form,.pc-requests,.pc-selector,.schoolmanager-credits,.pc-disabled';
    $cards = '.pc-head,.gsm-hero,.sm-hero,.smdoc-hero,.stp-head,.sid-hero,.pcd-hero,.pcas-hero,.tr-hero,.pc-card,.sm-card,.gsm-card,.al-tablebox,.pcd-card,.av-card,.av-item,.tic-card,.stock-card,.da-card,.ad-card,.sid-card,.stp-side-card,.pc-request-card,.pc-detail-card,.pe-card,.gsm-panel,.tr-card,.tr-metric,.pcas-card,.pcas-box,.box,.al-card,.pc-section,.pc-category-block,.pc-child-box,.pc-empty-subcat,.pc-ticket,.tr-item';
    $btnPrimary = '.sm-btn-primary,.pc-btn-primary,.pc-btn.primary,.gsm-btn.primary,.sm-btn.primary,.tic-btn.primary,.sid-btn.primary,.stp-btn.primary,.av-btn.primary,.al-btn.primary,.pcd-btn.primary,.pe-btn.dark,.pcda-btn.primary,.pc-btn-create,.al-open,.tr-btn.primary';
    $btnSecondary = '.sm-btn-secondary,.pc-btn-secondary,.sm-btn.light,.pc-btn.secondary,.gsm-btn.soft,.stp-btn.ghost,.av-btn:not(.primary):not(.home):not(.red),.al-btn:not(.primary):not(.al-home),.pe-btn:not(.dark):not(.red):not(.gold),.pcd-btn:not(.primary),.sid-btn:not(.primary):not(.red),.stp-btn:not(.primary):not(.danger):not(.red),.tr-btn:not(.primary),.pcda-btn:not(.primary),.pc-btn-cancel,.pc-btn:not(.primary):not(.secondary):not(.home):not(.pc-btn-home):not(.pc-btn-cancel):not(.pc-btn-create):not(.gold)';
    $btnHome = '.pc-header-home,.smgr-autohome,.pc-btn-home,.av-btn.home,.al-home,.smn-action-home,.smc-btn.home,.sm-btn-home,.pc-btn-home-red';
    $btnDanger = '.sm-btn-danger,.sm-btn.red,.sid-btn.red,.stp-btn.danger,.stp-btn.red,.pe-btn.red,.sid-mini.out,.pc-btn.red';

    $cardBg = '#FFFFFF';
    $heroBg = '#FFFFFF';
    $cardBorder = '#E2E8F0';
    $cardShadow = '0 4px 16px rgba(15, 41, 81, 0.05)';
    $heroTitleColor = 'var(--sm-primary)';
    $heroSubColor = 'var(--sm-muted)';

    $css = "/* Generated by School Manager settings. Do not edit manually. */\n";
    $css .= ":is({$c}){--sm-primary:{$v['primary']};--sm-secondary:{$v['secondary']};--sm-accent:{$v['accent']};--sm-soft:{$v['soft']};--sm-bg:{$v['background']};--sm-card:{$v['card']};--sm-text:{$v['text']};--sm-muted:{$v['muted']};--sm-border:{$v['border']};--sm-radius:{$v['radius']};--sm-pad:{$v['pad']};--sm-gap:{$v['gap']};--sm-font:{$v['font']};--sm-dark:{$dark};}\n";
    $css .= ":is({$c}){--navy:{$v['primary']}!important;--teal:{$v['secondary']}!important;--red:{$v['accent']}!important;--sm-navy:{$v['primary']}!important;--sm-navy-2:{$v['primary']}!important;--sm-blue:{$v['secondary']}!important;--sm-blue-2:{$v['secondary']}!important;--sm-red:{$v['accent']}!important;--sm-red-2:{$v['accent']}!important;--sm-red-soft:#FEF9EE!important;--sm-ink:{$v['text']}!important;--pc-dark:{$v['primary']}!important;--pc-teal:{$v['secondary']}!important;--pc-red:{$v['accent']}!important;}\n";
    $css .= ":is({$c}){background:var(--sm-bg)!important;color:var(--sm-text)!important;font-size:var(--sm-font)!important;}\n";
    $css .= ":is({$c}) :is({$cards}){background:{$cardBg}!important;color:var(--sm-text)!important;border:1px solid {$cardBorder}!important;border-radius:var(--sm-radius)!important;box-shadow:{$cardShadow}!important;}\n";
    $css .= ":is({$c}) :is(h1,h2,h3,.pc-title,.gsm-card-title,.smdoc-title,.stp-title h1,.sid-hero h1,.ticdesk h1,.smv-title):not(.active *){color:var(--sm-primary)!important;}\n";
    $css .= ":is({$c}) :is(p,small,.sm-help,.pc-subtitle,.gsm-muted,.muted,.pc-muted,.pcd-sub,.av-sub,.tr-sub):not(.active *){color:var(--sm-muted)!important;}\n";
    $css .= ":is({$c}) :is(.sm-kicker,.pc-kicker,.pcd-kicker,.gsm-kicker,.stp-kicker,.smdoc-kicker,.al-kicker,.sid-kicker){color:var(--sm-secondary)!important;}\n";
    $css .= ":is({$c}) .sm-universal-hero{background:{$heroBg}!important;border:1px solid {$cardBorder}!important;box-shadow:{$cardShadow}!important;border-radius:18px!important;}\n";
    $css .= ":is({$c}) .sm-universal-hero::after{display:none!important;}\n";
    $css .= ":is({$c}) :is(.sm-hero-logo,.pc-hero-logo,.sm-install-logo,.pc-logo,.al-logo,.pcd-logo,.gsm-logo,.sid-logo,.logo){background:transparent!important;border:none!important;box-shadow:none!important;padding:0!important;filter:none!important;-webkit-filter:none!important;outline:none!important;}\n";
    $css .= ":is({$c}) .sm-universal-hero :is(.sm-hero-title, h1){color:{$heroTitleColor}!important;}\n";
    $css .= ":is({$c}) .sm-universal-hero :is(.sm-hero-subtitle, p){color:{$heroSubColor}!important;}\n";
    $css .= ":is({$c}) .sm-universal-hero .sm-hero-kicker{color:var(--sm-secondary)!important;}\n";
    $css .= ":is({$c}) :is({$btnPrimary},{$btnSecondary},{$btnHome},{$btnDanger},.sm-btn,.pc-btn,.gsm-btn,.tic-btn,.stp-btn,.sid-btn,.av-btn,.al-btn,.pcd-btn,.pcda-btn){display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;min-height:42px!important;padding:10px 18px!important;border-radius:12px!important;font-weight:650!important;font-size:13.5px!important;line-height:1.2!important;text-decoration:none!important;cursor:pointer!important;transition:background-color 0.16s ease,border-color 0.16s ease,color 0.16s ease,transform 0.16s ease,box-shadow 0.16s ease!important;stroke:none!important;-webkit-text-stroke:0!important;}\n";
    $css .= ":is({$c}) :is({$btnPrimary},{$btnSecondary},{$btnHome},{$btnDanger},.sm-btn,.pc-btn,.gsm-btn,.tic-btn,.stp-btn,.sid-btn,.av-btn,.al-btn,.pcd-btn,.pcda-btn) :is(span,b,strong,i){stroke:none!important;-webkit-text-stroke:0!important;}\n";
    $css .= ":is({$c}) :is({$btnPrimary},{$btnSecondary},{$btnHome},{$btnDanger},.sm-btn,.pc-btn,.gsm-btn,.tic-btn,.stp-btn,.sid-btn,.av-btn,.al-btn,.pcd-btn,.pcda-btn) svg{width:17px!important;height:17px!important;flex-shrink:0!important;fill:none!important;stroke:currentColor!important;stroke-width:2.1!important;}\n";
    $css .= ":is({$c}) :is({$btnPrimary}){background:#0F2951!important;border:1px solid #0F2951!important;color:#ffffff!important;box-shadow:0 2px 6px rgba(15,41,81,0.12)!important;}\n";
    $css .= ":is({$c}) :is({$btnPrimary}):hover{background:#088E91!important;border-color:#088E91!important;transform:translateY(-1px)!important;box-shadow:0 4px 12px rgba(8,142,145,0.20)!important;color:#ffffff!important;}\n";
    $css .= ":is({$c}) :is({$btnPrimary}) :is(span,b,strong,i,svg){color:#ffffff!important;}\n";
    $css .= ":is({$c}) :is({$btnSecondary}){background:#ffffff!important;color:#0F2951!important;border:1px solid #CBD5E1!important;box-shadow:0 1px 2px rgba(15,41,81,0.04)!important;}\n";
    $css .= ":is({$c}) :is({$btnSecondary}):hover{background:#F1F5F9!important;border-color:#94A3B8!important;color:#0F2951!important;transform:translateY(-1px)!important;box-shadow:0 3px 8px rgba(15,41,81,0.07)!important;}\n";
    $css .= ":is({$c}) :is({$btnSecondary}) :is(span,b,strong,i,svg){color:#0F2951!important;}\n";
    $css .= ":is({$c}) :is({$btnHome}){background:#FFFFFF!important;border:1px solid #CBD5E1!important;color:#0F2951!important;border-radius:12px!important;box-shadow:0 1px 2px rgba(15,41,81,0.04)!important;}\n";
    $css .= ":is({$c}) :is({$btnHome}):hover{background:#F1F5F9!important;border-color:#94A3B8!important;color:#0F2951!important;transform:translateY(-1px)!important;box-shadow:0 3px 8px rgba(15,41,81,0.07)!important;}\n";
    $css .= ":is({$c}) :is({$btnHome}) :is(span,b,strong,i,svg){color:#0F2951!important;}\n";
    $css .= ":is({$c}) :is({$btnDanger}){background:#DC2626!important;border:1px solid #DC2626!important;color:#ffffff!important;border-radius:12px!important;box-shadow:0 2px 6px rgba(220,38,38,0.16)!important;}\n";
    $css .= ":is({$c}) :is({$btnDanger}):hover{background:#B91C1C!important;border-color:#B91C1C!important;color:#ffffff!important;transform:translateY(-1px)!important;}\n";
    $css .= ":is({$c}) :is({$btnDanger}) :is(span,b,strong,i,svg){color:#ffffff!important;}\n";
    $css .= ":is({$c}) :is(input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"submit\"]):not([type=\"button\"]),textarea){background:#ffffff!important;color:var(--sm-text)!important;border:1.5px solid #CBD5E1!important;border-radius:12px!important;}\n";
    $css .= ":is({$c}) select{appearance:none!important;-webkit-appearance:none!important;height:44px!important;padding:0 38px 0 14px!important;background-color:#ffffff!important;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230F2951' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E\")!important;background-repeat:no-repeat!important;background-position:right 12px center!important;background-size:16px!important;color:#0F2951!important;border:1.5px solid #CBD5E1!important;border-radius:12px!important;font-size:14px!important;font-weight:600!important;cursor:pointer!important;box-shadow:0 1px 2px rgba(15,41,81,0.03)!important;}\n";
    $css .= ":is({$c}) select:focus{border-color:#088E91!important;box-shadow:0 0 0 3px rgba(8,142,145,0.14)!important;outline:none!important;}\n";
    $css .= ":is({$c}) :is(.sm-card:not(:has(> .sm-table)),.gsm-card,.al-tablebox,.pcd-card,.av-card,.tic-card,.stock-card,.da-card,.ad-card,.sid-card,.stp-side-card){padding:var(--sm-pad);margin-bottom:var(--sm-gap)!important;border-radius:18px!important;}\n";
    $css .= ":is({$c}) .sm-card:has(> .sm-table){padding:0!important;margin-bottom:var(--sm-gap)!important;border-radius:18px!important;overflow:hidden!important;}\n";
    $css .= ":is({$c}) .sm-btn-sm{min-height:36px!important;padding:7px 14px!important;font-size:12.5px!important;border-radius:10px!important;gap:6px!important;}\n";
    $css .= ":is({$c}) :is(.pc-grid,.sm-grid,.gsm-grid,.stp-grid,.tic-grid,.pcd-grid,.pcda-grid,.al-actions,.sm-card-grid){gap:var(--sm-gap)!important;}\n";
    $css .= ".sm-home .sm-main{padding:var(--sm-gap)!important;}\n";
    $css .= "@media(max-width:900px){:is(.sm-home,.smset,.pc-wrap,.pc-page,.pcd-wrap,.pcda-shell,.gsm-wrap,.ticdesk,.pc-stock-page,.smdoc,.al-wrap){padding:12px!important}:is(.pc-head,.gsm-hero,.sm-hero,.smdoc-hero,.stp-head,.sid-hero){grid-template-columns:1fr!important;display:grid!important}:is(.pc-head-actions,.gsm-hero-actions,.smdoc-actions,.al-head-actions,.stp-actions,.sid-actions){justify-content:flex-start!important;width:100%!important;flex-wrap:wrap!important}:is({$btnPrimary},{$btnSecondary},{$btnHome},{$btnDanger},.sm-btn,.pc-btn,.gsm-btn,.tic-btn,.stp-btn,.sid-btn,.av-btn,.al-btn,.pcd-btn,.pcda-btn){min-height:44px!important;padding:10px 18px!important;}}\n";
    $file = __DIR__ . '/../css/generated-theme.css';
    @file_put_contents($file, $css, LOCK_EX);
    @chmod($file, 0664);
}

function plugin_schoolmanager_write_runtime_config_js(?array $cfg = null): void {
    $cfg = $cfg ?: plugin_schoolmanager_config();
    $brand = $cfg['brand'] ?? [];
    $values = plugin_schoolmanager_theme_values($cfg);
    global $CFG_GLPI;
    $root = $CFG_GLPI['root_doc'] ?? '';
    $paletteFile = __DIR__ . '/../css/themes/' . $values['palette'] . '.css';
    $paletteVer = rawurlencode((string)(@filemtime($paletteFile) ?: '1.1.0'));
    $themeCssUrl = $root !== ''
        ? ($root . '/plugins/schoolmanager/css/themes/' . rawurlencode((string)$values['palette']) . '.css?v=' . $paletteVer)
        : '';
    $payload = [
        'locale' => plugin_schoolmanager_locale(),
        'menuTitle' => plugin_schoolmanager_clean_brand_name($brand['menu_title'] ?? ($brand['title'] ?? 'School Manager')),
        'appName' => plugin_schoolmanager_clean_brand_name($brand['title'] ?? 'School Manager'),
        'features' => $cfg['features'] ?? [],
        'theme' => $values,
        'themeCss' => $themeCssUrl,
        'themePalette' => (string)$values['palette'],
        'themeVer' => $paletteVer,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) { return; }
    $js = "window.SCHOOLMANAGER_CONFIG = " . $json . ";\n";
    $js .= <<<'JS'
(function(){
  var isSM = window.location.pathname.indexOf('/plugins/schoolmanager/') !== -1;
  var cfg = window.SCHOOLMANAGER_CONFIG || {}, t = cfg.theme || {};
  var vars = {
    '--sm-primary':t.primary,'--sm-secondary':t.secondary,'--sm-accent':t.accent,'--sm-soft':t.soft,'--sm-bg':t.background,
    '--sm-card':t.card,'--sm-text':t.text,'--sm-muted':t.muted,'--sm-border':t.border,'--sm-radius':t.radius,'--sm-pad':t.pad,
    '--sm-gap':t.gap,'--sm-font':t.font,'--sm-navy':t.primary,'--sm-blue':t.secondary,'--sm-red':t.accent,'--sm-ink':t.text,
    '--navy':t.primary,'--teal':t.secondary,'--red':t.accent,'--pc-dark':t.primary,'--pc-teal':t.secondary,'--pc-red':t.accent
  };
  function resolveThemeCss(){
    if (cfg.themeCss) return cfg.themeCss;
    if (!isSM) return '';
    var base = window.location.pathname.split('/plugins/schoolmanager/')[0] || '';
    var pal = encodeURIComponent(cfg.themePalette || t.palette || 'teal-red');
    var ver = encodeURIComponent(cfg.themeVer || '1.1.0');
    return base + '/plugins/schoolmanager/css/themes/' + pal + '.css?v=' + ver;
  }
  function applyVars(){
    if (!isSM) return;
    document.documentElement.setAttribute('data-sm-theme', t.palette || 'teal-red');
    document.documentElement.setAttribute('data-sm-dark', t.dark ? '1' : '0');
    document.documentElement.setAttribute('data-sm-density', t.density || 'comfortable');
    if (document.body) {
      document.body.setAttribute('data-sm-theme', t.palette || 'teal-red');
      document.body.setAttribute('data-sm-dark', t.dark ? '1' : '0');
      document.body.setAttribute('data-sm-density', t.density || 'comfortable');
    }
    Object.keys(vars).forEach(function(k){
      if(vars[k]) {
        document.documentElement.style.setProperty(k, vars[k], 'important');
        if (document.body) document.body.style.setProperty(k, vars[k], 'important');
      }
    });
    var themeHref = resolveThemeCss();
    if (themeHref) {
      var l = document.getElementById('schoolmanager-active-theme');
      if (l) {
        if (l.getAttribute('href') !== themeHref) l.setAttribute('href', themeHref);
      } else {
        l = document.createElement('link');
        l.id = 'schoolmanager-active-theme';
        l.rel = 'stylesheet';
        l.href = themeHref;
        document.head.appendChild(l);
      }
    }
  }
  function refreshMenu(){
    var title = cfg.menuTitle || cfg.appName || 'School Manager';
    var navLinks = document.querySelectorAll(
      'aside.navbar a[href*="/plugins/schoolmanager/"], ' +
      'header.navbar a[href*="/plugins/schoolmanager/"], ' +
      '#navigation-menu a[href*="/plugins/schoolmanager/"], ' +
      '.navbar-vertical a[href*="/plugins/schoolmanager/"], ' +
      '.glpi-sidebar a[href*="/plugins/schoolmanager/"], ' +
      '.sidebar a[href*="/plugins/schoolmanager/"]'
    );
    navLinks.forEach(function(a){
      if (a.closest('.schoolmanager-configured, .sm-home, .pc-wrap, .pc-requests, .pc-form, .gsm-wrap, .stp, .av, .al-wrap, .ticdesk, .pcd, .smset, .smdoc, .pe-wrap')) {
        return;
      }
      var span = a.querySelector('.text-wrap, .nav-link-title');
      if (span) { span.textContent = title; }
      if (a.hasAttribute('title')) { a.setAttribute('title', title); }
    });
  }
  function run(){applyVars(); refreshMenu();}
  if(document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 150); setTimeout(run, 600);
})();
JS;
    $file = __DIR__ . '/../js/generated-config.js';
    @file_put_contents($file, $js, LOCK_EX);
    @chmod($file, 0664);
}

function plugin_schoolmanager_page_feature_map(): array {
    return [
        'new_ticket.php' => 'tickets', 'my_requests.php' => 'tickets', 'notifications.php' => 'tickets', 'ticket_detail.php' => 'tickets',
        'classrooms.php' => 'classrooms', 'classroom_detail.php' => 'classrooms', 'classroom_assets.php' => 'classrooms',
        'selector.php' => 'plans', 'map.php' => 'plans', 'plan_frame.php' => 'plans',
        'new_asset.php' => 'assets', 'asset_management.php' => 'assets', 'edit_asset.php' => 'assets', 'new_computer.php' => 'assets',
        'stock.php' => 'stock', 'stock_item.php' => 'stock', 'stock_movement.php' => 'stock',
        'it_panel.php' => 'tic_panel', 'technician_summary.php' => 'tic_panel',
        'it_assignments.php' => 'tic_assignment_rules',
        'versions.php' => 'versions',
        'file_editor.php' => 'file_editor',
    ];
}
function plugin_schoolmanager_guard_current_feature(): void {
    if (PHP_SAPI === 'cli') { return; }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $map = plugin_schoolmanager_page_feature_map();
    if (!isset($map[$script])) { return; }
    if (plugin_schoolmanager_feature_enabled($map[$script])) { return; }
    if (class_exists('Html')) { Html::header(plugin_schoolmanager_tr('module_disabled_title', 'Module disabled'), $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa'); }
    echo '<div style="max-width:900px;margin:30px auto;padding:24px;border:1px solid #d7e6ec;border-radius:22px;background:#fff;font-family:system-ui"><h1 style="margin-top:0;color:#07384d">' . htmlspecialchars(plugin_schoolmanager_tr('module_disabled_title', 'Module disabled'), ENT_QUOTES, 'UTF-8') . '</h1><p style="font-weight:800;color:#607582">' . htmlspecialchars(plugin_schoolmanager_tr('module_disabled_body', 'This module is disabled in the plugin settings.'), ENT_QUOTES, 'UTF-8') . '</p><a href="' . htmlspecialchars(plugin_schoolmanager_setup_url(), ENT_QUOTES, 'UTF-8') . '" style="display:inline-flex;padding:12px 16px;border-radius:14px;background:#07384d;color:white;text-decoration:none;font-weight:900">' . htmlspecialchars(plugin_schoolmanager_tr('menu_config', 'Settings'), ENT_QUOTES, 'UTF-8') . '</a></div>';
    if (class_exists('Html')) { Html::footer(); }
    exit;
}


// ---- Runtime translation and reliable embedded logo helpers ----
function plugin_schoolmanager_base_translation_pairs(): array {
    static $pairs = null;
    if ($pairs !== null) { return $pairs; }
    $pairs = [];
    $esFile = __DIR__ . '/../locales/es_ES.php';
    $enFile = __DIR__ . '/../locales/en_GB.php';
    $es = is_file($esFile) ? (include $esFile) : [];
    $en = is_file($enFile) ? (include $enFile) : [];
    foreach ($es as $k => $v) {
        if (isset($en[$k]) && is_string($v) && is_string($en[$k]) && trim($v) !== '' && trim($en[$k]) !== '') {
            $pairs[trim($v)] = trim($en[$k]);
        }
    }
    $mapFile = __DIR__ . '/../locales/es_en_map.json';
    if (is_file($mapFile)) {
        $raw = @file_get_contents($mapFile);
        $extra = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($extra)) {
            foreach ($extra as $from => $to) {
                if (is_string($from) && is_string($to) && trim($from) !== '' && trim($to) !== '') {
                    $pairs[trim($from)] = trim($to);
                }
            }
        }
    }
    uksort($pairs, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
    return $pairs;
}

function plugin_schoolmanager_translation_map_for_locale(?string $locale = null): array {
    static $cache = [];
    $locale = $locale ?: plugin_schoolmanager_locale();
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $pairs = [];
    $localesDir = __DIR__ . '/../locales';

    $esFile = $localesDir . '/es_ES.php';
    $enFile = $localesDir . '/en_GB.php';
    $targetFile = $localesDir . '/' . $locale . '.php';

    $esDict = is_file($esFile) ? (include $esFile) : [];
    $enDict = is_file($enFile) ? (include $enFile) : [];
    $targetDict = is_file($targetFile) ? (include $targetFile) : [];

    // Check runtime JSON phrase map (e.g. es_en_map.json, es_fr_map.json, es_de_map.json, es_it_map.json)
    $prefix = substr($locale, 0, 2);
    $mapFile = $localesDir . '/es_' . $prefix . '_map.json';
    if (!is_file($mapFile) && is_file($localesDir . '/es_' . $locale . '_map.json')) {
        $mapFile = $localesDir . '/es_' . $locale . '_map.json';
    }

    $jsonMap = [];
    if (is_file($mapFile)) {
        $raw = @file_get_contents($mapFile);
        $tmp = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($tmp)) { $jsonMap = $tmp; }
    }

    if (str_starts_with($locale, 'es')) {
        // Target is Spanish: only map default English placeholders -> Spanish.
        // Never allow any Spanish string to be mapped to English.
        $pairs['Educational center'] = 'Centro educativo';
        foreach ($enDict as $k => $enVal) {
            if (isset($esDict[$k]) && is_string($enVal) && is_string($esDict[$k])) {
                $enTrim = trim($enVal);
                $esTrim = trim($esDict[$k]);
                if ($enTrim !== '' && $esTrim !== '' && $enTrim !== $esTrim) {
                    $pairs[$enTrim] = $esTrim;
                }
            }
        }
        foreach ($esDict as $esVal) {
            if (is_string($esVal)) {
                unset($pairs[trim($esVal)]);
            }
        }
    } elseif (str_starts_with($locale, 'en')) {
        // Target is English: map Spanish phrases -> English
        foreach ($esDict as $k => $esVal) {
            if (isset($enDict[$k]) && is_string($esVal) && is_string($enDict[$k]) && trim($esVal) !== '' && trim($enDict[$k]) !== '') {
                $pairs[trim($esVal)] = trim($enDict[$k]);
            }
        }
        foreach ($jsonMap as $from => $to) {
            if (is_string($from) && is_string($to) && trim($from) !== '' && trim($to) !== '') {
                $pairs[trim($from)] = trim($to);
            }
        }
    } else {
        // Target is French, German, Italian, or custom:
        // Map Spanish phrases -> Target
        foreach ($esDict as $k => $esVal) {
            if (isset($targetDict[$k]) && is_string($esVal) && is_string($targetDict[$k]) && trim($esVal) !== '' && trim($targetDict[$k]) !== '') {
                $pairs[trim($esVal)] = trim($targetDict[$k]);
            }
        }
        foreach ($jsonMap as $from => $to) {
            if (is_string($from) && is_string($to) && trim($from) !== '' && trim($to) !== '') {
                $pairs[trim($from)] = trim($to);
            }
        }
        // Also map English phrases -> Target
        foreach ($enDict as $k => $enVal) {
            if (isset($targetDict[$k]) && is_string($enVal) && is_string($targetDict[$k]) && trim($enVal) !== '' && trim($targetDict[$k]) !== '') {
                $pairs[trim($enVal)] = trim($targetDict[$k]);
            }
        }
        $enMapFile = $localesDir . '/es_en_map.json';
        if (is_file($enMapFile)) {
            $raw = @file_get_contents($enMapFile);
            $enMap = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($enMap)) {
                foreach ($enMap as $esPhrase => $enPhrase) {
                    if (isset($jsonMap[$esPhrase]) && is_string($enPhrase) && is_string($jsonMap[$esPhrase])) {
                        $enP = trim($enPhrase);
                        $targetP = trim($jsonMap[$esPhrase]);
                        if ($enP !== '' && $targetP !== '') {
                            $pairs[$enP] = $targetP;
                        }
                    }
                }
            }
        }
    }

    // Protect official plugin name from being altered in ANY language
    unset($pairs['School Manager'], $pairs['GLPI School Manager'], $pairs['School'], $pairs['Manager']);
    $pairs['School Manager'] = 'School Manager';
    $pairs['GLPI School Manager'] = 'GLPI School Manager';

    uksort($pairs, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
    $cache[$locale] = $pairs;
    return $pairs;
}

function plugin_schoolmanager_translate_fragment(string $text, ?array $map = null): string {
    if ($text === '') { return $text; }
    $map = $map ?? plugin_schoolmanager_translation_map_for_locale();
    $trim = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($trim === '' || $trim === '0') { return $text; }
    if (isset($map[$trim])) {
        $prefix = preg_replace('/^(\s*).*$/su', '$1', $text) ?? '';
        $suffix = preg_replace('/^.*?(\s*)$/su', '$1', $text) ?? '';
        return $prefix . $map[$trim] . $suffix;
    }
    $out = $text;
    foreach ($map as $from => $to) {
        if (strlen($from) < 3) { continue; }
        if (preg_match('/^\w+$/u', $from)) {
            $pattern = '/\b' . preg_quote($from, '/') . '\b/u';
            $out = preg_replace($pattern, $to, $out);
        } else {
            if (strpos($out, $from) !== false) {
                $out = str_replace($from, $to, $out);
            }
        }
    }
    return $out;
}


function plugin_schoolmanager_global_ui_guard_html(): string {
    return '<script id="smgr-final-home-guard">
(function(){
  function ready(fn){if(document.readyState!=="loading")fn();else document.addEventListener("DOMContentLoaded",fn);}
  function isSidebar(el){return !!(el && el.closest && el.closest("#navbar-menu,.sidebar,.layout-navbar,aside.navbar"));}
  function run(){
    Array.prototype.forEach.call(document.querySelectorAll("a.smgr-autohome,a.pc-header-home"),function(a){ if(isSidebar(a)) a.remove(); });
    var heroHome = document.querySelector(".sm-universal-hero .sm-btn-home");
    if (heroHome) {
      Array.prototype.forEach.call(document.querySelectorAll("a.pc-btn-home-red, a.pc-header-home, a.smgr-autohome, .pc-selector a.pc-btn-home"), function(a){
        if (a !== heroHome && !a.closest(".sm-universal-hero")) {
          var card = a.closest(".pc-card");
          if (card && card.children.length === 1 && card.querySelector(".pc-head-actions")) {
            card.remove();
          } else {
            a.remove();
          }
        }
      });
    }
  }
  ready(function(){run();setTimeout(run,200);});
})();
</script>';
}

function plugin_schoolmanager_translate_html_output(string $html): string {
    if (!str_contains($_SERVER['REQUEST_URI'] ?? '', '/plugins/schoolmanager/')) { return $html; }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['media.php','i18n.php','classroom_assets.php','plan_frame.php'], true)) { return $html; }
    $map = plugin_schoolmanager_translation_map_for_locale();
    if (!$map || $html === '') { return $html; }
    // Translate attributes first, including placeholders inside textarea elements.
    foreach (['placeholder','title','aria-label','alt','value'] as $attr) {
        $html = preg_replace_callback('/(' . preg_quote($attr, '/') . '\s*=\s*)(["\'])(.*?)\2/isu', static function($m) use ($map) {
            return $m[1] . $m[2] . plugin_schoolmanager_translate_fragment($m[3], $map) . $m[2];
        }, $html) ?? $html;
    }
    $placeholders = [];
    $html = preg_replace_callback('#<([a-z0-9_-]+)\b[^>]*(?:translate=["\']no["\']|class=["\'][^"\']*\b(?:sm-no-translate|sm-editor-table)\b)[^>]*>.*?</\1>#isu', static function($m) use (&$placeholders) {
        $key = '%%SMGR_SKIP_' . count($placeholders) . '%%';
        $placeholders[$key] = $m[0];
        return $key;
    }, $html) ?? $html;
    $html = preg_replace_callback('#<(script|style|textarea|pre|code)\b[^>]*>.*?</\1>#is', static function($m) use (&$placeholders) {
        $key = '%%SMGR_SKIP_' . count($placeholders) . '%%';
        $placeholders[$key] = $m[0];
        return $key;
    }, $html) ?? $html;
    $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (is_array($parts)) {
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<' || str_starts_with($part, '%%SMGR_SKIP_')) { continue; }
            $parts[$i] = plugin_schoolmanager_translate_fragment($part, $map);
        }
        $html = implode('', $parts);
    }
    foreach ($placeholders as $key => $value) { $html = str_replace($key, $value, $html); }
    return $html;
}

function plugin_schoolmanager_start_translation_buffer(): void {
    if (PHP_SAPI === 'cli') { return; }
    if (!str_contains($_SERVER['REQUEST_URI'] ?? '', '/plugins/schoolmanager/')) { return; }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['media.php','i18n.php','classroom_assets.php','plan_frame.php','settings.php','install.php'], true)) { return; }
    if (!empty($GLOBALS['PLUGIN_SCHOOLMANAGER_TRANSLATION_BUFFER_STARTED'])) { return; }
    $GLOBALS['PLUGIN_SCHOOLMANAGER_TRANSLATION_BUFFER_STARTED'] = true;
    ob_start('plugin_schoolmanager_translate_html_output');
}

function plugin_schoolmanager_logo_path(): string {
    $brand = plugin_schoolmanager_config()['brand'] ?? [];
    $logo = (string)($brand['logo'] ?? 'logo.png');
    $baseName = basename(str_replace('\\', '/', $logo));
    $candidates = [];
    if ($baseName !== '' && $baseName !== 'logo.png') { $candidates[] = __DIR__ . '/../maps/uploads/' . $baseName; }
    $candidates[] = __DIR__ . '/../logo.png';
    $candidates[] = __DIR__ . '/../icon.png';
    $candidates[] = __DIR__ . '/../logo.svg';
    foreach ($candidates as $path) { if (is_file($path) && filesize($path) > 0) { return $path; } }
    return __DIR__ . '/../icon.png';
}

function plugin_schoolmanager_logo_data_uri(): string {
    $path = plugin_schoolmanager_logo_path();
    if (!is_file($path) || filesize($path) <= 0) { return ''; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml'][$ext] ?? 'image/png';
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') { return ''; }
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

plugin_schoolmanager_start_translation_buffer();

?>
