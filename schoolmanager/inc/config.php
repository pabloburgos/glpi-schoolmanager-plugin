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
    $cfg['credits']['license'] = plugin_schoolmanager_normalize_license_label((string)($cfg['credits']['license'] ?? 'GPLv3+'));
    $licenseUrl = trim((string)($cfg['credits']['license_url'] ?? ''));
    if ($licenseUrl === '' || stripos($licenseUrl, 'creativecommons.org') !== false) {
        $cfg['credits']['license_url'] = 'https://www.gnu.org/licenses/gpl-3.0.html';
    }
    $cfg['credits']['show_footer_credit'] = !empty($cfg['credits']['show_footer_credit']);
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
        'locale' => 'es_ES',
        'brand' => [
            'title' => 'School Manager',
            'menu_title' => 'School Manager',
            // Backward compatible keys kept for older saved configurations.
            'title_es' => 'School Manager',
            'title_en' => 'School Manager',
            'organization' => 'Educational center',
            'logo' => 'logo.png',
            'show_logo' => true,
        ],
        'credits' => [
            'authors' => 'Pablo Burgos y Alejandro Galán',
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
            $where = ['name' => $name, 'is_deleted' => 0];
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
    return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/instalacion.php';
}

function plugin_schoolmanager_require_initial_setup(): void {
    if (plugin_schoolmanager_is_configured()) { return; }
    if (!class_exists('Session') || !Session::getLoginUserID()) { return; }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/plugins/schoolmanager/front/instalacion.php') !== false || strpos($uri, '/plugins/schoolmanager/front/configuracion.php') !== false || strpos($uri, '/plugins/schoolmanager/front/media.php') !== false || strpos($uri, '/plugins/schoolmanager/front/i18n.php') !== false) { return; }
    if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) {
        Html::redirect(plugin_schoolmanager_setup_url());
    }
}

function plugin_schoolmanager_credit_html(): string {
    $cfg = plugin_schoolmanager_config();
    $credits = $cfg['credits'] ?? [];
    $authors = htmlspecialchars((string)($credits['authors'] ?? 'Pablo Burgos y Alejandro Galán'), ENT_QUOTES, 'UTF-8');
    $license = htmlspecialchars(plugin_schoolmanager_normalize_license_label((string)($credits['license'] ?? 'GPLv3+')), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars((string)($credits['license_url'] ?? 'https://www.gnu.org/licenses/gpl-3.0.html'), ENT_QUOTES, 'UTF-8');
    return '<div class="schoolmanager-credit">' . plugin_schoolmanager_tr('credits_by', 'Created by') . ' <strong>' . $authors . '</strong> · <a href="' . $url . '" target="_blank" rel="noopener">' . $license . '</a></div>';
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

function plugin_schoolmanager_locale(): string {
    $cfg = plugin_schoolmanager_config();
    $locale = (string)($cfg['locale'] ?? 'auto');
    if ($locale === 'auto') {
        $glpi = $_SESSION['glpilanguage'] ?? $_SESSION['glpilocale'] ?? '';
        $locale = (stripos((string)$glpi, 'en') === 0) ? 'en_GB' : 'es_ES';
    }
    return str_starts_with($locale, 'en') ? 'en_GB' : 'es_ES';
}

function plugin_schoolmanager_tr(string $key, ?string $fallback = null): string {
    static $dicts = [];
    $locale = plugin_schoolmanager_locale();
    if (!isset($dicts[$locale])) {
        $file = __DIR__ . '/../locales/' . $locale . '.php';
        $dicts[$locale] = is_file($file) ? (include $file) : [];
    }
    return (string)($dicts[$locale][$key] ?? $fallback ?? $key);
}

function plugin_schoolmanager_label(array $item, string $base, ?string $fallback = null): string {
    $suffix = plugin_schoolmanager_locale() === 'en_GB' ? '_en' : '_es';
    return (string)($item[$base . $suffix] ?? $item[$base] ?? $fallback ?? '');
}

function plugin_schoolmanager_clean_brand_name(?string $value, string $fallback = 'School Manager'): string {
    $value = trim((string)$value);
    if ($value === '') { return $fallback; }
    $bad = ['Gestión Escolar GLPI','Gestion Escolar GLPI','GLPI School Manager','Gestión School Manager','Gestion School Manager','Centro educativo Manager','Educational center Manager'];
    return in_array($value, $bad, true) ? $fallback : $value;
}

function plugin_schoolmanager_app_name(): string {
    $brand = plugin_schoolmanager_config()['brand'] ?? [];
    return plugin_schoolmanager_clean_brand_name($brand['title'] ?? ($brand['title_es'] ?? ($brand['title_en'] ?? 'School Manager')));
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
    foreach (($cfg['rooms'] ?? []) as $r) {
        $building = strtoupper((string)($r['building'] ?? ''));
        $floor = strtoupper((string)($r['floor'] ?? ''));
        $code = (string)($r['code'] ?? ($building . '-' . ($r['room'] ?? '')));
        $floorMeta = plugin_schoolmanager_floor($building, $floor) ?: [];
        $rows[] = [
            'building' => $building,
            'aula' => (string)($r['room'] ?? $r['aula'] ?? ''),
            'codigo' => $code,
            'planta' => plugin_schoolmanager_label($floorMeta, 'label', $floor),
            'floor' => $floor,
            'descripcion' => plugin_schoolmanager_label($r, 'name', (string)($r['description'] ?? '')),
            'id' => isset($r['glpi_location_id']) && $r['glpi_location_id'] !== null ? (int)$r['glpi_location_id'] : ($ids[$code] ?? null),
            'is_numbered' => true,
            'is_top_special' => false,
            'sort_floor' => $floorsByBuilding[$building][$floor] ?? 99,
        ];
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
        'teal-red' => ['name'=>'Teal red','primary'=>'#07384D','secondary'=>'#075D61','accent'=>'#B6252B','soft'=>'#F4FAFC','background'=>'#EEF6F9','card'=>'#FFFFFF','text'=>'#07384D','muted'=>'#5F7180','border'=>'#D7E6EC','dark'=>false],
        'ocean'    => ['name'=>'Ocean blue','primary'=>'#0B3A66','secondary'=>'#0E7490','accent'=>'#2563EB','soft'=>'#F3F8FF','background'=>'#EAF4FF','card'=>'#FFFFFF','text'=>'#082F49','muted'=>'#54708A','border'=>'#CFE2F3','dark'=>false],
        'forest'   => ['name'=>'Forest green','primary'=>'#12372A','secondary'=>'#15803D','accent'=>'#D97706','soft'=>'#F4FAF6','background'=>'#EDF8F0','card'=>'#FFFFFF','text'=>'#12372A','muted'=>'#617568','border'=>'#CFE7D6','dark'=>false],
        'sunset'   => ['name'=>'Sunset','primary'=>'#4A1D1F','secondary'=>'#B45309','accent'=>'#DC2626','soft'=>'#FFF7ED','background'=>'#FFF1E6','card'=>'#FFFFFF','text'=>'#3F1F1F','muted'=>'#806A58','border'=>'#F3D5BD','dark'=>false],
        'graphite' => ['name'=>'Graphite dark','primary'=>'#E5EDF4','secondary'=>'#38BDF8','accent'=>'#FB7185','soft'=>'#111827','background'=>'#0B1120','card'=>'#111827','text'=>'#E5EDF4','muted'=>'#A8B3C1','border'=>'#263244','dark'=>true],
        'midnight' => ['name'=>'Midnight dark','primary'=>'#DDEBFF','secondary'=>'#2DD4BF','accent'=>'#F59E0B','soft'=>'#081421','background'=>'#06101D','card'=>'#0E1B2A','text'=>'#EAF2FF','muted'=>'#9FB0C4','border'=>'#20354A','dark'=>true],
        'custom'   => ['name'=>'Custom','primary'=>'#07384D','secondary'=>'#075D61','accent'=>'#B6252B','soft'=>'#F4FAFC','background'=>'#EEF6F9','card'=>'#FFFFFF','text'=>'#07384D','muted'=>'#5F7180','border'=>'#D7E6EC','dark'=>false],
    ];
}

function plugin_schoolmanager_theme_values(?array $cfg = null): array {
    $cfg = $cfg ?: plugin_schoolmanager_config();
    $theme = $cfg['theme'] ?? [];
    $presets = plugin_schoolmanager_theme_presets();
    $palette = (string)($theme['palette'] ?? 'teal-red');
    if (!isset($presets[$palette])) { $palette = 'teal-red'; }
    $p = $presets[$palette];
    if ($palette === 'custom') {
        foreach (['primary','secondary','accent','soft','background','card','text','muted','border'] as $key) {
            $p[$key] = plugin_schoolmanager_css_hex((string)($theme[$key] ?? ''), (string)$p[$key]);
        }
        $p['dark'] = !empty($theme['dark']);
    }
    $radius = preg_match('/^[0-9.]+(px|rem|em|%)$/', (string)($theme['radius'] ?? '18px')) ? (string)$theme['radius'] : '18px';
    $density = (string)($theme['density'] ?? 'comfortable');
    $pad = $density === 'compact' ? '12px' : ($density === 'large' ? '24px' : '18px');
    $gap = $density === 'compact' ? '12px' : ($density === 'large' ? '26px' : '18px');
    $font = $density === 'compact' ? '14px' : ($density === 'large' ? '17px' : '15px');
    return [
        'palette'=>$palette,'name'=>(string)$p['name'],'primary'=>(string)$p['primary'],'secondary'=>(string)$p['secondary'],'accent'=>(string)$p['accent'],
        'soft'=>(string)$p['soft'],'background'=>(string)$p['background'],'card'=>(string)$p['card'],'text'=>(string)$p['text'],'muted'=>(string)$p['muted'],'border'=>(string)$p['border'],
        'dark'=>(bool)$p['dark'],'radius'=>$radius,'pad'=>$pad,'gap'=>$gap,'font'=>$font,'density'=>$density,
    ];
}

function plugin_schoolmanager_write_theme_css(?array $cfg = null): void {
    $v = plugin_schoolmanager_theme_values($cfg);
    $dark = $v['dark'] ? '1' : '0';
    $css = "/* Generated by School Manager settings. Do not edit manually. */\n";
    $css .= ":root,body{--sm-primary:{$v['primary']};--sm-secondary:{$v['secondary']};--sm-accent:{$v['accent']};--sm-soft:{$v['soft']};--sm-bg:{$v['background']};--sm-card:{$v['card']};--sm-text:{$v['text']};--sm-muted:{$v['muted']};--sm-border:{$v['border']};--sm-radius:{$v['radius']};--sm-pad:{$v['pad']};--sm-gap:{$v['gap']};--sm-font:{$v['font']};--sm-dark:{$dark};}\n";
    $css .= "html[data-sm-theme],body[data-sm-theme]{background:var(--sm-bg)!important;color:var(--sm-text)!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap){--navy:var(--sm-primary)!important;--pc-dark:var(--sm-primary)!important;--pc-teal:var(--sm-secondary)!important;--pc-red:var(--sm-accent)!important;--teal:var(--sm-secondary)!important;--red:var(--sm-accent)!important;--soft:var(--sm-soft)!important;background:var(--sm-bg)!important;color:var(--sm-text)!important;font-size:var(--sm-font)!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(.pc-head,.gsm-hero,.sm-hero,.smdoc-hero,.stp-head,.sid-hero,.pc-card,.sm-card,.gsm-card,.al-tablebox,.pcd-card,.av-card,.tic-card,.stock-card,.da-card,.ad-card,.sid-card,.stp-side-card,.pc-request-card,.pc-detail-card){background:linear-gradient(135deg,var(--sm-card),var(--sm-soft))!important;color:var(--sm-text)!important;border-color:var(--sm-border)!important;border-radius:var(--sm-radius)!important;box-shadow:0 20px 46px color-mix(in srgb,var(--sm-primary) 14%,transparent)!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(h1,h2,h3,.pc-title,.gsm-card-title,.smdoc-title,.stp-title h1,.sid-hero h1,.ticdesk h1,.smv-title){color:var(--sm-primary)!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(p,small,.sm-help,.pc-subtitle,.gsm-muted,.muted,.pc-muted){color:var(--sm-muted)!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(.sm-kicker,.pc-kicker,.pcd-kicker,.gsm-kicker,.stp-kicker,.smdoc-kicker,.al-kicker,.sid-kicker){color:var(--sm-accent)!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(.pc-btn.primary,.pcd-btn.primary,.gsm-btn.primary,.stp-btn.primary,.tic-btn.primary,.sid-btn.primary,.sm-btn,.smdoc-btn.dark,.al-open,.pcda-btn.primary,.stp-mini.in){background:linear-gradient(135deg,var(--sm-primary),var(--sm-secondary))!important;border-color:var(--sm-primary)!important;color:#fff!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(.pc-header-home,.smgr-autohome,.pc-btn-create,.smdoc-btn.red,.al-tab.active,.stp-create-submit,.stp-btn.danger,.sid-btn.red,.sid-mini.out,.sm-tab.active){background:linear-gradient(135deg,var(--sm-accent),color-mix(in srgb,var(--sm-accent) 78%,#000))!important;border-color:var(--sm-accent)!important;color:#fff!important;}\n";
    $css .= ":where(.schoolmanager-configured,.sm-home,.smset,.pc-wrap,.pc-page,.pcd-page,.pcd-wrap,.pcd,.pcda,.pcda-shell,.pc-main,.pc-stock-page,.stp-wrap,.sid-wrap,.gsm-wrap,.gsm,.ticdesk,.al-wrap,.av,.av-wrap,.smdoc,.smdoc-wrap,.smv-wrap,.pe-wrap,.ad-wrap,.da-wrap,.tr-wrap) :where(input,select,textarea){background:var(--sm-card)!important;color:var(--sm-text)!important;border-color:var(--sm-border)!important;border-radius:calc(var(--sm-radius) - 6px)!important;}\n";
    $css .= ":where(.pc-card,.sm-card,.gsm-card,.al-tablebox,.pcd-card,.av-card,.tic-card,.stock-card,.da-card,.ad-card,.sid-card,.stp-side-card){padding:var(--sm-pad)!important;margin-bottom:var(--sm-gap)!important;}\n";
    $css .= ":where(.pc-grid,.sm-grid,.gsm-grid,.stp-grid,.tic-grid,.pcd-grid,.pcda-grid,.al-actions,.sm-card-grid){gap:var(--sm-gap)!important;}\n";
    $css .= ".sm-home .sm-main{padding:var(--sm-gap)!important;}\n";
    $css .= "@media(max-width:900px){:where(.sm-home,.smset,.pc-wrap,.pc-page,.pcd-wrap,.pcda-shell,.gsm-wrap,.ticdesk,.pc-stock-page,.smdoc,.al-wrap){padding:12px!important}:where(.pc-head,.gsm-hero,.sm-hero,.smdoc-hero,.stp-head,.sid-hero){grid-template-columns:1fr!important;display:grid!important}:where(.pc-head-actions,.gsm-hero-actions,.smdoc-actions,.al-head-actions,.stp-actions,.sid-actions){justify-content:flex-start!important;width:100%!important;flex-wrap:wrap!important}}\n";
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
    $payload = [
        'locale' => plugin_schoolmanager_locale(),
        'menuTitle' => plugin_schoolmanager_clean_brand_name($brand['menu_title'] ?? ($brand['title'] ?? 'School Manager')),
        'appName' => plugin_schoolmanager_clean_brand_name($brand['title'] ?? 'School Manager'),
        'theme' => $values,
        'themeCss' => ($root ?: '') . '/plugins/schoolmanager/css/themes/' . rawurlencode((string)$values['palette']) . '.css?v=' . time(),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) { return; }
    $js = "window.SCHOOLMANAGER_CONFIG = " . $json . ";\n";
    $js .= <<<'JS'
(function(){
  var cfg = window.SCHOOLMANAGER_CONFIG || {}, t = cfg.theme || {};
  var vars = {'--sm-primary':t.primary,'--sm-secondary':t.secondary,'--sm-accent':t.accent,'--sm-soft':t.soft,'--sm-bg':t.background,'--sm-card':t.card,'--sm-text':t.text,'--sm-muted':t.muted,'--sm-border':t.border,'--sm-radius':t.radius,'--sm-pad':t.pad,'--sm-gap':t.gap,'--sm-font':t.font};
  function applyVars(){
    document.documentElement.setAttribute('data-sm-theme', t.palette || 'teal-red');
    document.documentElement.setAttribute('data-sm-dark', t.dark ? '1' : '0');
    if (document.body) { document.body.setAttribute('data-sm-theme', t.palette || 'teal-red'); document.body.setAttribute('data-sm-dark', t.dark ? '1' : '0'); }
    Object.keys(vars).forEach(function(k){ if(vars[k]) document.documentElement.style.setProperty(k, vars[k]); });
    if (cfg.themeCss && !document.getElementById('schoolmanager-active-theme')) {
      var l=document.createElement('link'); l.id='schoolmanager-active-theme'; l.rel='stylesheet'; l.href=cfg.themeCss; document.head.appendChild(l);
    }
  }
  function replaceTextNodes(el, value){
    Array.prototype.forEach.call(el.childNodes, function(n){
      if(n.nodeType === 3 && /school manager|gesti[oó]n escolar|gesti[oó]n school manager|glpi school manager|centro educativo manager/i.test(n.nodeValue || '')){ n.nodeValue = value; }
      else if(n.nodeType === 1 && !/^(svg|path|i)$/i.test(n.nodeName)){ replaceTextNodes(n, value); }
    });
  }
  function isSchoolManagerLink(a){ return /\/plugins\/schoolmanager\//.test(a.getAttribute('href') || ''); }
  function refreshMenu(){
    var title = cfg.menuTitle || cfg.appName || 'School Manager';
    Array.prototype.forEach.call(document.querySelectorAll('a,span,div'), function(el){
      var txt=(el.textContent||'').trim();
      if(/^(GLPI\s+School\s+Manager|School\s+Manager|Gestión\s+Escolar\s+GLPI|Gestion\s+Escolar\s+GLPI|Gestión\s+School\s+Manager|Gestion\s+School\s+Manager|Centro\s+educativo\s+Manager)$/.test(txt)){
        if(el.matches('a') && !isSchoolManagerLink(el)) return;
        replaceTextNodes(el, title);
      }
    });
  }
  function run(){applyVars(); refreshMenu();}
  if(document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 250); setTimeout(run, 1000); setTimeout(run, 2200);
})();
JS;
    $file = __DIR__ . '/../js/generated-config.js';
    @file_put_contents($file, $js, LOCK_EX);
    @chmod($file, 0664);
}

function plugin_schoolmanager_page_feature_map(): array {
    return [
        'nueva_incidencia.php' => 'tickets', 'mis_solicitudes.php' => 'tickets', 'avisos.php' => 'tickets', 'solicitud_detalle.php' => 'tickets',
        'aulas.php' => 'classrooms', 'detalle_aula.php' => 'classrooms', 'assets_aula.php' => 'classrooms',
        'selector.php' => 'plans', 'mapa.php' => 'plans', 'plan_frame.php' => 'plans',
        'nuevo_activo.php' => 'assets', 'gestion_activos.php' => 'assets', 'editar_activo.php' => 'assets', 'nuevo_ordenador.php' => 'assets',
        'stock_glpi.php' => 'stock', 'stock_item.php' => 'stock', 'stock_movimiento.php' => 'stock',
        'panel_tic.php' => 'tic_panel', 'tecnico_resumen.php' => 'tic_panel',
        'asignaciones_tic.php' => 'tic_assignment_rules',
        'versiones.php' => 'versions',
        'archivos.php' => 'file_editor',
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
    $locale = $locale ?: plugin_schoolmanager_locale();
    $pairs = plugin_schoolmanager_base_translation_pairs();

    // Final safety layer: avoid mixed ES/EN fragments caused by runtime partial replacements.
    // These are intentionally locale-specific, so English fixes are not inverted back into Spanish.
    $englishCleanup = [
        'GLPI SCHOOL MANAGER' => 'School Manager',
        'GLPI School Manager' => 'School Manager',
        'Gestión Escolar GLPI' => 'School Manager',
        'Datos de la ticket' => 'Ticket details',
        'Datos de la incidencia' => 'Ticket details',
        'Category de ticket' => 'Ticket category',
        'Categoría de ticket' => 'Ticket category',
        'Categoría de incidencia' => 'Ticket category',
        'CATEGORY PADRE' => 'PARENT CATEGORY',
        'Categoría padre' => 'Parent category',
        'Problema concreto' => 'Specific issue',
        'PROBLEMA CONCRETO' => 'SPECIFIC ISSUE',
        'Elige un bloque y después el problema' => 'Choose a block and then the issue',
        'Elige un bloque de la izquierda para ver sus problemas.' => 'Choose a block on the left to view its issues.',
        'El formulario se adapta automáticamente.' => 'The form adapts automatically.',
        'Selecciona una categoría' => 'Select a category',
        'Selecciona una categoría para adaptar el formulario.' => 'Select a category to adapt the form.',
        'Buscar problema...' => 'Search issue...',
        'Search problem...' => 'Search issue...',
        'Limpiar' => 'Clear',
        'Título *' => 'Title *',
        'Ubicación *' => 'Location *',
        'Sin ubicación seleccionada' => 'No location selected',
        'Selecciona el aula desde el plano o desde la lista' => 'Select the classroom from the map or the list',
        'Elegir ubicación' => 'Choose location',
        'Activo afectado del aula' => 'Affected classroom asset',
        'Asset afectado del aula' => 'Affected classroom asset',
        'No especificar activo concreto' => 'Do not specify a specific asset',
        'No especificar asset concreto' => 'Do not specify a specific asset',
        'Otro activo no listado / personalizado' => 'Other unlisted / custom asset',
        'Ej: portátil del profesor, cable HDMI, ratón, monitor sin etiqueta...' => 'Example: teacher laptop, HDMI cable, mouse, unlabelled monitor...',
        'Activos cargados desde GLPI para esta ubicación. La lista se filtrará según la categoría seleccionada. Si falta alguno, usa la opción personalizada.' => 'Assets loaded from GLPI for this location. The list will be filtered according to the selected category. If one is missing, use the custom option.',
        'Al abrir desde Detalle del aula se cargarán aquí sus activos de GLPI.' => 'When opened from classroom details, its GLPI assets will be loaded here.',
        'Describe el problema con el máximo detalle posible.' => 'Describe the problem in as much detail as possible.',
        'Detalle técnico opcional' => 'Optional technical detail',
        'Description *' => 'Description *',
        'Descripción *' => 'Description *',
        'Explica qué ocurre, desde cuándo, equipo afectado, pasos probados...' => 'Explain what happens, since when, affected device and steps already tried...',
        'Explica qué ocurre, desde cuándo, equipo afectado, pasos probados…' => 'Explain what happens, since when, affected device and steps already tried...',
        'Urgencia *' => 'Urgency *',
        'Impacto *' => 'Impact *',
        'Prioridad *' => 'Priority *',
        'Media' => 'Medium',
        'Alta' => 'High',
        'Baja' => 'Low',
        'Muy alta' => 'Very high',
        'Prioridad inteligente sugerida:' => 'Smart suggested priority:',
        'Puedes cambiarla antes de enviar.' => 'You can change it before sending.',
        'Hardware' => 'Hardware',
        'Software' => 'Software',
        'Red y comunicaciones' => 'Network and communications',
        'Network y comunicaciones' => 'Network and communications',
        'Cuentas y accesos' => 'Accounts and access',
        'Solicitudes' => 'Requests',
        'Mantenimiento preventivo' => 'Preventive maintenance',
        'Contraseña' => 'Password',
        'Cuenta bloqueada' => 'Blocked account',
        'Alta de usuario' => 'User onboarding',
        'Baja de usuario' => 'User offboarding',
        'Permisos' => 'Permissions',
        'Acceso a plataforma educativa' => 'Educational platform access',
        'Mis solicitudes' => 'My requests',
        'Consulta el estado, respuestas y resolución de tus incidencias.' => 'Check the status, replies and resolution of your tickets.',
        'solicitudes' => 'requests',
        'abiertas' => 'open',
        'respondidas' => 'answered',
        'esperando info' => 'waiting for info',
        'resueltas' => 'resolved',
        'Ver detalle' => 'View details',
        'Vista nativa' => 'Native view',
        'Vista nativa GLPI' => 'Native GLPI view',
        'Lista nativa GLPI' => 'Native GLPI list',
        'Ticket finalizado o con solución publicada' => 'Ticket finished or with a published solution',
        'GLPI status: Resuelto' => 'GLPI status: Resolved',
        'Estado GLPI: Resuelto' => 'GLPI status: Resolved',
        'Respondido' => 'Answered',
        'Resuelto' => 'Resolved',
        'Avisos' => 'Notices',
        'Alertas importantes del soporte TIC.' => 'Important IT support alerts.',
        'Actualizaciones importantes de tus solicitudes.' => 'Important updates about your requests.',
        'Request resuelta. Revisa la solución y confirma si está correcto.' => 'Request resolved. Review the solution and confirm whether it is correct.',
        'Solicitud resuelta. Revisa la solución y confirma si está correcto.' => 'Request resolved. Review the solution and confirm whether it is correct.',
        'Edificio' => 'Building',
        'Aula' => 'Classroom',
        'Planta' => 'Floor',
        'Inicio' => 'Home',
        'Crear incidencia' => 'Create ticket',
        'Crear ticket' => 'Create ticket',
        'Cancelar' => 'Cancel',
        'Volver' => 'Back',
        'Crear incidencia guiada' => 'Guided ticket creation',
        'Nueva incidencia guiada' => 'New guided ticket',
        'Incidencia general' => 'General issue',
        'Otro' => 'Other',
    ];

    $spanishCleanup = [
        'School Manager' => 'School Manager',
        'GLPI SCHOOL MANAGER' => 'School Manager',
        'GLPI School Manager' => 'School Manager',
        'Datos de la ticket' => 'Datos de la incidencia',
        'Ticket details' => 'Datos de la incidencia',
        'Category de ticket' => 'Categoría de incidencia',
        'Ticket category' => 'Categoría de incidencia',
        'PARENT CATEGORY' => 'CATEGORÍA PADRE',
        'Parent category' => 'Categoría padre',
        'SPECIFIC ISSUE' => 'PROBLEMA CONCRETO',
        'Specific issue' => 'Problema concreto',
        'Choose a block and then the issue' => 'Elige un bloque y después el problema',
        'Choose a block on the left to view its issues.' => 'Elige un bloque de la izquierda para ver sus problemas.',
        'The form adapts automatically.' => 'El formulario se adapta automáticamente.',
        'Select a category' => 'Selecciona una categoría',
        'Select a category to adapt the form.' => 'Selecciona una categoría para adaptar el formulario.',
        'Search issue...' => 'Buscar problema...',
        'Search problem...' => 'Buscar problema...',
        'Clear' => 'Limpiar',
        'Title *' => 'Título *',
        'Location *' => 'Ubicación *',
        'No location selected' => 'Sin ubicación seleccionada',
        'Select the classroom from the map or the list' => 'Selecciona el aula desde el plano o desde la lista',
        'Choose location' => 'Elegir ubicación',
        'Affected classroom asset' => 'Activo afectado del aula',
        'Asset afectado del aula' => 'Activo afectado del aula',
        'Do not specify a specific asset' => 'No especificar activo concreto',
        'No especificar asset concreto' => 'No especificar activo concreto',
        'Other unlisted / custom asset' => 'Otro activo no listado / personalizado',
        'Describe the problem in as much detail as possible.' => 'Describe el problema con el máximo detalle posible.',
        'Optional technical detail' => 'Detalle técnico opcional',
        'Description *' => 'Descripción *',
        'Explain what happens, since when, affected device and steps already tried...' => 'Explica qué ocurre, desde cuándo, equipo afectado, pasos probados...',
        'Urgency *' => 'Urgencia *',
        'Impact *' => 'Impacto *',
        'Priority *' => 'Prioridad *',
        'Medium' => 'Media',
        'High' => 'Alta',
        'Low' => 'Baja',
        'Very high' => 'Muy alta',
        'Network y comunicaciones' => 'Red y comunicaciones',
        'Network and communications' => 'Red y comunicaciones',
        'Accounts and access' => 'Cuentas y accesos',
        'Requests' => 'Solicitudes',
        'Preventive maintenance' => 'Mantenimiento preventivo',
        'Password' => 'Contraseña',
        'Blocked account' => 'Cuenta bloqueada',
        'My requests' => 'Mis solicitudes',
        'Check the status, replies and resolution of your tickets.' => 'Consulta el estado, respuestas y resolución de tus incidencias.',
        'requests' => 'solicitudes',
        'open' => 'abiertas',
        'answered' => 'respondidas',
        'waiting for info' => 'esperando info',
        'resolved' => 'resueltas',
        'View details' => 'Ver detalle',
        'Native view' => 'Vista nativa',
        'Native GLPI view' => 'Vista nativa GLPI',
        'Native GLPI list' => 'Lista nativa GLPI',
        'Ticket finished or with a published solution' => 'Ticket finalizado o con solución publicada',
        'GLPI status: Resolved' => 'Estado GLPI: Resuelto',
        'Notices' => 'Avisos',
        'Important IT support alerts.' => 'Alertas importantes del soporte TIC.',
        'Important updates about your requests.' => 'Actualizaciones importantes de tus solicitudes.',
        'Request resolved. Review the solution and confirm whether it is correct.' => 'Solicitud resuelta. Revisa la solución y confirma si está correcto.',
        'Request resuelta. Revisa la solución y confirma si está correcto.' => 'Solicitud resuelta. Revisa la solución y confirma si está correcto.',
        'Building' => 'Edificio',
        'Classroom' => 'Aula',
        'Floor' => 'Planta',
        'Home' => 'Inicio',
        'Create ticket' => 'Crear incidencia',
        'Cancel' => 'Cancelar',
        'Back' => 'Volver',
        'Guided ticket creation' => 'Crear incidencia guiada',
        'New guided ticket' => 'Nueva incidencia guiada',
        'General issue' => 'Incidencia general',
        'Other' => 'Otro',
    ];


    // v100 final translation polish: fix remaining mixed ES/EN labels in tickets, assets and stock pages.
    $englishCleanup = array_merge($englishCleanup, [
        'Detalle de solicitud' => 'Request details',
        'Vista sencilla con estado, respuestas y solución.' => 'Simple view with status, replies and resolution.',
        'Description enviada' => 'Submitted description',
        'Descripción enviada' => 'Submitted description',
        'Tipo detectado' => 'Detected type',
        'Tipo detectado:' => 'Detected type:',
        'Detalle técnico' => 'Technical detail',
        'Detalle técnico:' => 'Technical detail:',
        'Número de ordenador/equipo' => 'Computer/equipment number',
        'Número de ordenador/equipo:' => 'Computer/equipment number:',
        'Number de ordenador/equipo' => 'Computer/equipment number',
        'Number de ordenador/equipo:' => 'Computer/equipment number:',
        'Descripción del problema' => 'Problem description',
        'Problem description' => 'Problem description',
        'Gestión' => 'Management',
        'Technician asignado:' => 'Assigned technician:',
        'Técnico asignado:' => 'Assigned technician:',
        'Sin asignar' => 'Unassigned',
        'Status actual' => 'Current status',
        'Estado actual' => 'Current status',
        'El equipo TIC ha propuesto una solución.' => 'The IT team has proposed a solution.',
        'En curso / revisión' => 'In progress / review',
        'En espera si falta información' => 'Waiting if more information is needed',
        'Resolved o cerrada' => 'Resolved or closed',
        'Resuelto o cerrada' => 'Resolved or closed',
        'Chat de seguimiento' => 'Follow-up chat',
        'Profesor · solicitud inicial' => 'Teacher · initial request',
        'Teacher · request inicial' => 'Teacher · initial request',
        'Solución propuesta' => 'Proposed solution',
        'Ticket ya resuelta' => 'Ticket already resolved',
        'Incidencia ya resuelta' => 'Ticket already resolved',
        'Esta ticket está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.' => 'This ticket is archived for the IT team. No more replies, reassignment or resolution actions can be added.',
        'Esta incidencia está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.' => 'This ticket is archived for the IT team. No more replies, reassignment or resolution actions can be added.',
        'Respuesta / solución' => 'Reply / solution',
        'Material usado' => 'Used material',
        'Salida:' => 'Out:',
        'Técnico:' => 'Technician:',
        'Creada' => 'Created',
        'Última actualización' => 'Last update',
        'Origen' => 'Origin',
        'Formulario / Helpdesk' => 'Form / Helpdesk',
        'Prioridad' => 'Priority',
        'Categoría' => 'Category',
        'Categoría GLPI:' => 'GLPI category:',
        'Category GLPI:' => 'GLPI category:',
        'Ubicación' => 'Location',
        'ID ubicación GLPI' => 'GLPI location ID',
        'MODIFICAR ACTIVO' => 'EDIT ASSET',
        'Modificar activo' => 'Edit asset',
        'Back a gestión' => 'Back to management',
        'Volver a gestión' => 'Back to management',
        'Edita ubicación, datos de inventario, estado, fabricante, tipo y modelo.' => 'Edit location, inventory data, status, manufacturer, type and model.',
        'Datos editables' => 'Editable details',
        'Los cambios se guardan directamente sobre el activo de GLPI.' => 'Changes are saved directly to the GLPI asset.',
        'Nombre *' => 'Name *',
        'Name *' => 'Name *',
        'Número de serie' => 'Serial number',
        'Number de serie' => 'Serial number',
        'Número de inventario' => 'Inventory number',
        'Number de inventario' => 'Inventory number',
        'Estado' => 'Status',
        'Fabricante' => 'Manufacturer',
        'Tipo' => 'Type',
        'Modelo' => 'Model',
        'Comentarios' => 'Comments',
        'Observaciones, puesto, uso previsto, aula, periféricos asociados...' => 'Notes, position, intended use, classroom and associated peripherals...',
        'Observaciones, puesto, uso previsto, aula, periféricos asociados…' => 'Notes, position, intended use, classroom and associated peripherals...',
        'Guardar cambios' => 'Save changes',
        'Guardando...' => 'Saving...',
        'Resumen rápido' => 'Quick summary',
        'Información útil para revisar antes de guardar.' => 'Useful information to review before saving.',
        'Ubicación actual' => 'Current location',
        'Inventario' => 'Inventory',
        'Sin dato' => 'No data',
        'Abrir ficha nativa' => 'Open native record',
        'Open ficha nativa' => 'Open native record',
        'Crear otro ordenador' => 'Create another computer',
        'Crear otro monitor' => 'Create another monitor',
        'Crear otro impresora' => 'Create another printer',
        'Abrir plano de clases' => 'Open classroom map',
        'Aplicar ubicación al activo' => 'Apply location to asset',
        'Selecciona desde plano o lista' => 'Select from map or list',
        'Tipo de asset' => 'Asset type',
        'Tipo de activo' => 'Asset type',
        'Elige qué parte del inventario quieres modificar.' => 'Choose which part of the inventory you want to edit.',
        'Filtra por nombre, inventario, número de serie, comentario o aula.' => 'Filter by name, inventory number, serial number, comment or classroom.',
        'Ordenar' => 'Sort',
        'All las classrooms' => 'All classrooms',
        'Todas las classrooms' => 'All classrooms',
        'Todas las aulas' => 'All classrooms',
        'Por nombre' => 'By name',
        'Sin ubicación' => 'No location',
        'Resultados' => 'Results',
        'Modificar' => 'Edit',
        'High guiada' => 'Guided creation',
        'Alta guiada' => 'Guided creation',
        'Classroom list' => 'Classroom list',
        'Lista de aulas' => 'Classroom list',
        'Gestión de activos' => 'Asset management',
        'Inventory ordenado por tipo, aula y ficha nativa de GLPI.' => 'Inventory organized by type, classroom and native GLPI record.',
        'Inventario ordenado por tipo, aula y ficha nativa de GLPI.' => 'Inventory organized by type, classroom and native GLPI record.',
        'Dispositivo de red' => 'Network device',
        'Ordenador' => 'Computer',
        'Impresora' => 'Printer',
        'Periférico' => 'Peripheral',
        'Todas las categorías' => 'All categories',
        'All las categorias' => 'All categories',
        'All las categorías' => 'All categories',
        'Categoria' => 'Category',
        'Sin categoría' => 'No category',
        'Stock bajo' => 'Low stock',
        'Name claro para el equipo TIC.' => 'Clear name for the IT team.',
        'Nombre claro para el equipo TIC.' => 'Clear name for the IT team.',
        'Referencia / modelo' => 'Reference / model',
        'Nivel mínimo de aviso' => 'Minimum warning level',
        'Notas internas' => 'Internal notes',
        'Input suma. Output resta. Ajuste deja el numero exacto.' => 'Input adds stock. Output subtracts delivered units. Adjustment sets the exact number.',
        'Input suma material nuevo. Output descuenta unidades entregadas. View / edit abre ajustes mas finos.' => 'Input adds new material. Output subtracts delivered units. View / edit opens detailed adjustments.',
        'Aplicar ajuste' => 'Apply adjustment',
        'Motivo' => 'Reason',
        'Archivo' => 'Archive',
    ]);

    $spanishCleanup = array_merge($spanishCleanup, [
        'Submitted description' => 'Descripción enviada',
        'Detected type' => 'Tipo detectado',
        'Detected type:' => 'Tipo detectado:',
        'Technical detail' => 'Detalle técnico',
        'Technical detail:' => 'Detalle técnico:',
        'Computer/equipment number' => 'Número de ordenador/equipo',
        'Computer/equipment number:' => 'Número de ordenador/equipo:',
        'Management' => 'Gestión',
        'Assigned technician:' => 'Técnico asignado:',
        'Unassigned' => 'Sin asignar',
        'Current status' => 'Estado actual',
        'The IT team has proposed a solution.' => 'El equipo TIC ha propuesto una solución.',
        'In progress / review' => 'En curso / revisión',
        'Waiting if more information is needed' => 'En espera si falta información',
        'Resolved or closed' => 'Resuelta o cerrada',
        'Follow-up chat' => 'Chat de seguimiento',
        'Teacher · initial request' => 'Profesor · solicitud inicial',
        'Proposed solution' => 'Solución propuesta',
        'Ticket already resolved' => 'Incidencia ya resuelta',
        'This ticket is archived for the IT team. No more replies, reassignment or resolution actions can be added.' => 'Esta incidencia está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.',
        'Created' => 'Creada',
        'Last update' => 'Última actualización',
        'Origin' => 'Origen',
        'Form / Helpdesk' => 'Formulario / Helpdesk',
        'EDIT ASSET' => 'MODIFICAR ACTIVO',
        'Edit asset' => 'Modificar activo',
        'Back to management' => 'Volver a gestión',
        'Edit location, inventory data, status, manufacturer, type and model.' => 'Edita ubicación, datos de inventario, estado, fabricante, tipo y modelo.',
        'Editable details' => 'Datos editables',
        'Changes are saved directly to the GLPI asset.' => 'Los cambios se guardan directamente sobre el activo de GLPI.',
        'Serial number' => 'Número de serie',
        'Inventory number' => 'Número de inventario',
        'Status' => 'Estado',
        'Manufacturer' => 'Fabricante',
        'Type' => 'Tipo',
        'Model' => 'Modelo',
        'Comments' => 'Comentarios',
        'Notes, position, intended use, classroom and associated peripherals...' => 'Observaciones, puesto, uso previsto, aula, periféricos asociados...',
        'Quick summary' => 'Resumen rápido',
        'Useful information to review before saving.' => 'Información útil para revisar antes de guardar.',
        'Current location' => 'Ubicación actual',
        'No data' => 'Sin dato',
        'Open native record' => 'Abrir ficha nativa',
        'Create another computer' => 'Crear otro ordenador',
        'Create another monitor' => 'Crear otro monitor',
        'Open classroom map' => 'Abrir plano de clases',
        'Apply location to asset' => 'Aplicar ubicación al activo',
        'Select from map or list' => 'Selecciona desde plano o lista',
        'Asset type' => 'Tipo de activo',
        'Choose which part of the inventory you want to edit.' => 'Elige qué parte del inventario quieres modificar.',
        'Filter by name, inventory number, serial number, comment or classroom.' => 'Filtra por nombre, inventario, número de serie, comentario o aula.',
        'Sort' => 'Ordenar',
        'All classrooms' => 'Todas las aulas',
        'By name' => 'Por nombre',
        'No location' => 'Sin ubicación',
        'Results' => 'Resultados',
        'Guided creation' => 'Alta guiada',
        'Asset management' => 'Gestión de activos',
        'Inventory organized by type, classroom and native GLPI record.' => 'Inventario ordenado por tipo, aula y ficha nativa de GLPI.',
        'Network device' => 'Dispositivo de red',
        'All categories' => 'Todas las categorías',
        'No category' => 'Sin categoría',
        'Low stock' => 'Stock bajo',
        'Clear name for the IT team.' => 'Nombre claro para el equipo TIC.',
        'Reference / model' => 'Referencia / modelo',
        'Minimum warning level' => 'Nivel mínimo de aviso',
        'Internal notes' => 'Notas internas',
        'Input adds stock. Output subtracts delivered units. Adjustment sets the exact number.' => 'Entrada suma stock. Salida resta unidades entregadas. Ajuste deja el número exacto.',
        'Apply adjustment' => 'Aplicar ajuste',
        'Reason' => 'Motivo',
    ]);



    // v1.0.0 final polish: extra page-level translations collected from real UI screenshots.
    $englishCleanup = array_merge($englishCleanup, [
        'SCHOOL MANAGER' => 'SCHOOL MANAGER',
        'Gestion School Manager · Centro TIC' => 'School Manager · IT center',
        'GESTION SCHOOL MANAGER · CENTRO TIC' => 'SCHOOL MANAGER · IT CENTER',
        'Centro TIC' => 'IT center',
        'IT stock' => 'IT stock',
        'Control rapido de repuestos y consumibles sin menus raros ni pantalla cargada.' => 'Fast stock control for consumables, cartridges and spare parts without cluttered screens.',
        'Control rápido de repuestos y consumibles sin menús raros ni pantalla cargada.' => 'Fast stock control for consumables, cartridges and spare parts without cluttered screens.',
        'artículos' => 'items',
        'artículo' => 'item',
        'en vista' => 'in view',
        'disponibles' => 'available',
        'histórico' => 'historical',
        'historico' => 'historical',
        'mínimo' => 'minimum',
        'minimo' => 'minimum',
        'mínimo aviso' => 'minimum warning',
        'minimo aviso' => 'minimum warning',
        'Toner, tinta y repuestos de impresora.' => 'Toner, ink and printer supplies.',
        'Tóner, tinta y repuestos de impresora.' => 'Toner, ink and printer supplies.',
        'Cartuchos' => 'Cartridges',
        'Consumibles' => 'Consumables',
        'Cables, ratones, teclados, adaptadores...' => 'Cables, mice, keyboards, adapters...',
        'Ej: HDMI, raton, toner, aula...' => 'Example: HDMI, mouse, toner, classroom...',
        'Ej: HDMI, ratón, tóner, aula...' => 'Example: HDMI, mouse, toner, classroom...',
        'All las categorias' => 'All categories',
        'All las categorías' => 'All categories',
        'Entrada' => 'Input',
        'Salida' => 'Output',
        'In' => 'Input',
        'Out' => 'Output',
        'Input suma material nuevo. Output descuenta unidades entregadas. View / edit abre ajustes mas finos.' => 'Input adds new material. Output subtracts delivered units. View / edit opens detailed settings.',
        'Input suma material nuevo. Output descuenta unidades entregadas. View / edit abre ajustes más finos.' => 'Input adds new material. Output subtracts delivered units. View / edit opens detailed settings.',
        'In suma material nuevo. Out descuenta unidades entregadas. View / edit abre ajustes mas finos.' => 'Input adds new material. Output subtracts delivered units. View / edit opens detailed settings.',
        'In suma material nuevo. Out descuenta unidades entregadas. View / edit abre ajustes más finos.' => 'Input adds new material. Output subtracts delivered units. View / edit opens detailed settings.',
        'Como usarlo' => 'How to use it',
        'Cómo usarlo' => 'How to use it',
        'Usa nombres claros: Ratones USB, Cable HDMI 2m, Toner HP...' => 'Use clear names: USB mice, 2m HDMI cable, HP toner...',
        'Usa nombres claros: Ratones USB, Cable HDMI 2m, Tóner HP...' => 'Use clear names: USB mice, 2m HDMI cable, HP toner...',
        'El minimo sirve para que el articulo aparezca como stock bajo.' => 'The minimum value is used to show the item as low stock.',
        'El mínimo sirve para que el artículo aparezca como stock bajo.' => 'The minimum value is used to show the item as low stock.',
        'Para cambios grandes usa View / edit y ajuste exacto.' => 'For large changes, use View / edit and exact adjustment.',
        'Acciones rapidas' => 'Quick actions',
        'Acciones rápidas' => 'Quick actions',
        'Status del stock' => 'Stock status',
        'Zonas calientes' => 'Hot zones',
        'Sin tickets open por aula.' => 'No open tickets by classroom.',
        'Categorys activas' => 'Active categories',
        'Categorías activas' => 'Active categories',
        'No categorys activas.' => 'No active categories.',
        'No categorías activas.' => 'No active categories.',
        'Mesa TIC' => 'IT desk',
        'Filtra, asigna y gestiona tickets desde una vista rápida.' => 'Filter, assign and manage tickets from a quick view.',
        'Filtra, asigna y gestiona incidencias desde una vista rápida.' => 'Filter, assign and manage tickets from a quick view.',
        'Atajos útiles para trabajar como equipo TIC.' => 'Useful shortcuts for the IT team.',
        'Compacto' => 'Compact',
        'Abiertos' => 'Open',
        'Críticos' => 'Critical',
        'En espera' => 'Waiting',
        'Resolveds' => 'Resolved',
        'No hay tickets que coincidan con la búsqueda.' => 'No tickets match the search.',
        'No hay tickets in this view.' => 'No tickets in this view.',
        'No hay tickets en esta vista.' => 'No tickets in this view.',
        'Assets GLPI' => 'GLPI assets',
        'Plano classrooms' => 'Classroom map',
        'Reglas TIC' => 'IT rules',
        'Stock TIC' => 'IT stock',
        'Ficha del aula' => 'Classroom details',
        'Location registrada en GLPI.' => 'Location registered in GLPI.',
        'Ubicación registrada en GLPI.' => 'Location registered in GLPI.',
        'Inventario vinculado' => 'Linked inventory',
        'Inventory vinculado' => 'Linked inventory',
        'Acciones' => 'Actions',
        'Ver en el plano' => 'View on map',
        'Assets del aula' => 'Classroom assets',
        'Activos del aula' => 'Classroom assets',
        'Back a lista de classrooms' => 'Back to classroom list',
        'Volver a lista de aulas' => 'Back to classroom list',
        'Open GLPI nativo' => 'Open native GLPI',
        'Abrir GLPI nativo' => 'Open native GLPI',
        'Volver a Gestión School Manager' => 'Back to School Manager',
        'Activos destacados' => 'Featured assets',
        'Computer · ID 2 · abrir asset' => 'Computer · ID 2 · open asset',
        'abrir asset' => 'open asset',
        'abrir activo' => 'open asset',
        'Estos datos se leen desde GLPI usando la ubicación seleccionada. Si falta algo, revisa la ubicación asignada en el activo.' => 'These data are read from GLPI using the selected location. If something is missing, check the location assigned to the asset.',
        'Tipo de asset' => 'Asset type',
        'Tipo de activo' => 'Asset type',
        'Elige qué parte del inventario quieres modificar.' => 'Choose which part of the inventory you want to edit.',
        'Filtra por nombre, inventario, número de serie, comentario o aula.' => 'Filter by name, inventory number, serial number, comment or classroom.',
        'Ordenar' => 'Sort',
        'Todas las aulas' => 'All classrooms',
        'All las classrooms' => 'All classrooms',
        'Por nombre' => 'By name',
        'Sin ubicación' => 'No location',
        'Resultados' => 'Results',
        'Gestión de activos' => 'Asset management',
        'Inventario ordenado por tipo, aula y ficha nativa de GLPI.' => 'Inventory organized by type, classroom and native GLPI record.',
        'Alta guiada' => 'Guided creation',
        'High guiada' => 'Guided creation',
        'Dispositivo de red' => 'Network device',
        'Modificar activo' => 'Edit asset',
        'MODIFICAR ACTIVO' => 'EDIT ASSET',
        'Back a gestión' => 'Back to management',
        'Volver a gestión' => 'Back to management',
        'Open ficha nativa' => 'Open native record',
        'Abrir ficha nativa' => 'Open native record',
        'Crear otro ordenador' => 'Create another computer',
        'Crear ordenador' => 'Create computer',
        'Comentarios' => 'Comments',
        'Observaciones, puesto, uso previsto, aula, periféricos asociados...' => 'Notes, position, intended use, classroom and associated peripherals...',
        'Observaciones, puesto, uso previsto, aula, perifericos asociados...' => 'Notes, position, intended use, classroom and associated peripherals...',
        'Número de serie / etiqueta del fabricante' => 'Serial number / manufacturer label',
        'Número de inventario' => 'Inventory number',
        'Número de serie' => 'Serial number',
        'Código interno / etiqueta de inventario' => 'Internal code / inventory tag',
        'Fabricante' => 'Manufacturer',
        'Estado' => 'Status',
        'Tipo' => 'Type',
        'Modelo' => 'Model',
        'Creada' => 'Created',
        'Última actualización' => 'Last update',
        'Vista sencilla con estado, respuestas y solución.' => 'Simple view with status, replies and solution.',
        'Status actual' => 'Current status',
        'Estado actual' => 'Current status',
        'El equipo TIC ha propuesto una solución.' => 'The IT team has proposed a solution.',
        'En curso / revisión' => 'In progress / review',
        'En espera si falta información' => 'Waiting if more information is needed',
        'Resolved o cerrada' => 'Resolved or closed',
        'Resuelta o cerrada' => 'Resolved or closed',
        'Description enviada' => 'Submitted description',
        'Descripción enviada' => 'Submitted description',
        'Tipo detectado' => 'Detected type',
        'Detalle técnico' => 'Technical detail',
        'Number de ordenador/equipo' => 'Computer/equipment number',
        'Número de ordenador/equipo' => 'Computer/equipment number',
        'Problem description' => 'Problem description',
        'Gestión' => 'Management',
        'Technician asignado:' => 'Assigned technician:',
        'Técnico asignado:' => 'Assigned technician:',
        'Sin asignar' => 'Unassigned',
        'Respuesta / solución' => 'Reply / solution',
        'Material usado' => 'Used material',
        'Solución propuesta' => 'Proposed solution',
        'Chat de seguimiento' => 'Follow-up chat',
        'Teacher · request inicial' => 'Teacher · initial request',
        'Profesor · solicitud inicial' => 'Teacher · initial request',
        'Ticket ya resuelta' => 'Ticket already resolved',
        'Incidencia ya resuelta' => 'Ticket already resolved',
        'Esta ticket está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.' => 'This ticket is archived for the IT team. No more replies, reassignment or resolution actions can be added.',
        'Esta incidencia está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.' => 'This ticket is archived for the IT team. No more replies, reassignment or resolution actions can be added.',
        'Category GLPI' => 'GLPI category',
        'Categoría GLPI' => 'GLPI category',
        'Location ID' => 'Location ID',
        'Formulario / Helpdesk' => 'Form / Helpdesk',
        'Prioridad' => 'Priority',
        'Categoría' => 'Category',
        'Ubicación' => 'Location',
    ]);



    // v1.0.0 final polish: inverse fixes so Spanish pages never keep English fragments.
    $spanishCleanup = array_merge($spanishCleanup, [
        'School Manager · IT center' => 'School Manager · Centro TIC',
        'IT center' => 'Centro TIC',
        'Fast stock control for consumables, cartridges and spare parts without cluttered screens.' => 'Control rápido de repuestos y consumibles sin menús raros ni pantalla cargada.',
        'items' => 'artículos',
        'item' => 'artículo',
        'in view' => 'en vista',
        'historical' => 'histórico',
        'minimum warning' => 'mínimo aviso',
        'Toner, ink and printer supplies.' => 'Tóner, tinta y repuestos de impresora.',
        'Example: HDMI, mouse, toner, classroom...' => 'Ej: HDMI, ratón, tóner, aula...',
        'Input adds new material. Output subtracts delivered units. View / edit opens detailed settings.' => 'Entrada suma material nuevo. Salida descuenta unidades entregadas. Ver / editar abre ajustes más finos.',
        'How to use it' => 'Cómo usarlo',
        'Use clear names: USB mice, 2m HDMI cable, HP toner...' => 'Usa nombres claros: Ratones USB, Cable HDMI 2m, Tóner HP...',
        'The minimum value is used to show the item as low stock.' => 'El mínimo sirve para que el artículo aparezca como stock bajo.',
        'For large changes, use View / edit and exact adjustment.' => 'Para cambios grandes usa Ver / editar y ajuste exacto.',
        'IT desk' => 'Mesa TIC',
        'Filter, assign and manage tickets from a quick view.' => 'Filtra, asigna y gestiona incidencias desde una vista rápida.',
        'Useful shortcuts for the IT team.' => 'Atajos útiles para trabajar como equipo TIC.',
        'Compact' => 'Compacto',
        'Open' => 'Abiertos',
        'Critical' => 'Críticos',
        'Waiting' => 'En espera',
        'No tickets match the search.' => 'No hay tickets que coincidan con la búsqueda.',
        'No tickets in this view.' => 'No hay tickets en esta vista.',
        'GLPI assets' => 'Activos GLPI',
        'Classroom map' => 'Plano de aulas',
        'IT rules' => 'Reglas TIC',
        'IT stock' => 'Stock TIC',
        'Classroom details' => 'Ficha del aula',
        'Location registered in GLPI.' => 'Ubicación registrada en GLPI.',
        'Linked inventory' => 'Inventario vinculado',
        'Actions' => 'Acciones',
        'View on map' => 'Ver en el plano',
        'Classroom assets' => 'Activos del aula',
        'Back to classroom list' => 'Volver a lista de aulas',
        'Open native GLPI' => 'Abrir GLPI nativo',
        'Back to School Manager' => 'Volver a School Manager',
        'Featured assets' => 'Activos destacados',
        'open asset' => 'abrir activo',
        'These data are read from GLPI using the selected location. If something is missing, check the location assigned to the asset.' => 'Estos datos se leen desde GLPI usando la ubicación seleccionada. Si falta algo, revisa la ubicación asignada en el activo.',
        'Asset type' => 'Tipo de activo',
        'Choose which part of the inventory you want to edit.' => 'Elige qué parte del inventario quieres modificar.',
        'Filter by name, inventory number, serial number, comment or classroom.' => 'Filtra por nombre, inventario, número de serie, comentario o aula.',
        'Sort' => 'Ordenar',
        'All classrooms' => 'Todas las aulas',
        'By name' => 'Por nombre',
        'No location' => 'Sin ubicación',
        'Results' => 'Resultados',
        'Asset management' => 'Gestión de activos',
        'Inventory organized by type, classroom and native GLPI record.' => 'Inventario ordenado por tipo, aula y ficha nativa de GLPI.',
        'Guided creation' => 'Alta guiada',
        'Network device' => 'Dispositivo de red',
        'Edit asset' => 'Modificar activo',
        'EDIT ASSET' => 'MODIFICAR ACTIVO',
        'Back to management' => 'Volver a gestión',
        'Open native record' => 'Abrir ficha nativa',
        'Create another computer' => 'Crear otro ordenador',
        'Create computer' => 'Crear ordenador',
        'Serial number / manufacturer label' => 'Número de serie / etiqueta del fabricante',
        'Internal code / inventory tag' => 'Código interno / etiqueta de inventario',
        'Manufacturer' => 'Fabricante',
        'Type' => 'Tipo',
        'Model' => 'Modelo',
        'Simple view with status, replies and solution.' => 'Vista sencilla con estado, respuestas y solución.',
        'Current status' => 'Estado actual',
        'The IT team has proposed a solution.' => 'El equipo TIC ha propuesto una solución.',
        'In progress / review' => 'En curso / revisión',
        'Waiting if more information is needed' => 'En espera si falta información',
        'Resolved or closed' => 'Resuelta o cerrada',
        'Submitted description' => 'Descripción enviada',
        'Detected type' => 'Tipo detectado',
        'Technical detail' => 'Detalle técnico',
        'Computer/equipment number' => 'Número de ordenador/equipo',
        'Management' => 'Gestión',
        'Assigned technician:' => 'Técnico asignado:',
        'Unassigned' => 'Sin asignar',
        'Reply / solution' => 'Respuesta / solución',
        'Used material' => 'Material usado',
        'Proposed solution' => 'Solución propuesta',
        'Follow-up chat' => 'Chat de seguimiento',
        'Teacher · initial request' => 'Profesor · solicitud inicial',
        'Ticket already resolved' => 'Incidencia ya resuelta',
        'This ticket is archived for the IT team. No more replies, reassignment or resolution actions can be added.' => 'Esta incidencia está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.',
        'GLPI category' => 'Categoría GLPI',
        'Form / Helpdesk' => 'Formulario / Helpdesk',
    ]);

    if (str_starts_with($locale, 'en')) {
        $pairs = array_merge($pairs, $englishCleanup);
        uksort($pairs, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
        return $pairs;
    }
    $reverse = [];
    foreach ($pairs as $es => $en) { $reverse[$en] = $es; }
    $reverse = array_merge($reverse, $spanishCleanup);
    uksort($reverse, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
    return $reverse;
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
        if (strpos($out, $from) !== false) { $out = str_replace($from, $to, $out); }
    }
    return $out;
}


function plugin_schoolmanager_global_ui_guard_html(): string {
    $label = htmlspecialchars(plugin_schoolmanager_tr('menu_home', 'Home'), ENT_QUOTES, 'UTF-8');
    global $CFG_GLPI;
    $root = $CFG_GLPI['root_doc'] ?? '';
    $href = htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . (defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : time()), ENT_QUOTES, 'UTF-8');
    return '<style id="smgr-final-polish-ui">
.smgr-autohome,.pc-header-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:48px!important;border:1px solid var(--sm-accent,#b6252b)!important;background:linear-gradient(135deg,var(--sm-accent,#b6252b),color-mix(in srgb,var(--sm-accent,#b6252b) 78%,#000))!important;color:#fff!important;border-radius:18px!important;padding:0 22px!important;text-decoration:none!important;font-weight:950!important;box-shadow:0 18px 38px color-mix(in srgb,var(--sm-accent,#b6252b) 28%,transparent)!important;white-space:nowrap!important;transition:.2s!important}
.smgr-autohome:hover,.pc-header-home:hover{transform:translateY(-3px)!important;color:#fff!important}.smgr-autohome svg,.pc-header-home svg{width:18px!important;height:18px!important;stroke:currentColor!important;fill:none!important;stroke-width:2.2!important}
.pcda-card,.gsm-card,.st-card,.tic-card,.pc-request-card,.pc-detail-card{box-shadow:0 20px 44px color-mix(in srgb,var(--sm-primary,#07384d) 12%,transparent)!important}.pcda-note,.pc-empty,.tic-empty{border-radius:18px!important}.pcda-actions .pcda-btn,.gsm-btn,.st-btn,.tic-btn{transition:transform .18s ease,box-shadow .18s ease!important}.pcda-actions .pcda-btn:hover,.gsm-btn:hover,.st-btn:hover,.tic-btn:hover{transform:translateY(-2px)!important}
/* Never inject Home into the GLPI left sidebar. */
#navbar-menu .smgr-autohome,#navbar-menu .pc-header-home,.sidebar .smgr-autohome,.sidebar .pc-header-home,.layout-navbar .smgr-autohome,.layout-navbar .pc-header-home{display:none!important}
@media(max-width:760px){.smgr-autohome,.pc-header-home{width:auto!important}.pc-head-actions,.gsm-hero-actions,.ad-actions,.da-actions,.smv-actions{gap:10px!important;flex-wrap:wrap!important}}
</style><script id="smgr-final-home-guard">
(function(){
  var href="' . $href . '", label="' . $label . '";
  function ready(fn){if(document.readyState!=="loading")fn();else document.addEventListener("DOMContentLoaded",fn);}
  function isSidebar(el){return !!(el && el.closest && el.closest("#navbar-menu,.sidebar,.layout-navbar,aside.navbar"));}
  function isHome(a){var h=a.getAttribute("href")||""; return /formularios\.php/.test(h) && /home|inicio|panel|school manager|gesti/i.test((a.textContent||""));}
  function icon(){return "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M3.75 10.5 12 4.25l8.25 6.25\"/><path d=\"M6.75 9.75v9.5h10.5v-9.5\"/><path d=\"M10 19.25V14.5a2 2 0 0 1 4 0v4.75\"/></svg>";}
  function run(){
    Array.prototype.forEach.call(document.querySelectorAll("a.smgr-autohome,a.pc-header-home"),function(a){ if(isSidebar(a)) a.remove(); });
    var homes=Array.prototype.slice.call(document.querySelectorAll("a")).filter(function(a){return !isSidebar(a)&&isHome(a);});
    homes.forEach(function(a){a.classList.add("smgr-autohome");a.href=href;if(!a.querySelector("svg"))a.innerHTML=icon()+"<span>"+label+"</span>";});
    homes=Array.prototype.slice.call(document.querySelectorAll("a.smgr-autohome,a.pc-header-home")).filter(function(a){return !isSidebar(a)&&isHome(a);});
    if(homes.length>1) homes.slice(1).forEach(function(e){e.remove();});
  }
  ready(function(){run();setTimeout(run,300);setTimeout(run,1000);});
})();
</script>';
}

function plugin_schoolmanager_translate_html_output(string $html): string {
    if (!str_contains($_SERVER['REQUEST_URI'] ?? '', '/plugins/schoolmanager/')) { return $html; }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['media.php','i18n.php','assets_aula.php','plan_frame.php'], true)) { return $html; }
    $map = plugin_schoolmanager_translation_map_for_locale();
    if (!$map || $html === '') { return $html; }
    // Translate attributes first, including placeholders inside textarea elements.
    foreach (['placeholder','title','aria-label','alt','value'] as $attr) {
        $html = preg_replace_callback('/(' . preg_quote($attr, '/') . '\s*=\s*)(["\'])(.*?)\2/isu', static function($m) use ($map) {
            return $m[1] . $m[2] . plugin_schoolmanager_translate_fragment($m[3], $map) . $m[2];
        }, $html) ?? $html;
    }
    $placeholders = [];
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
    if (in_array($script, ['media.php','i18n.php','assets_aula.php','plan_frame.php'], true)) { return; }
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
