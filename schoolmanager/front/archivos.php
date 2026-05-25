<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');

global $CFG_GLPI;
$root = $CFG_GLPI['root_doc'] ?? '';
$pluginVersion = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.0.0';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg?v=' . rawurlencode($pluginVersion));
$pluginBase = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$aulasFile = __DIR__ . '/../inc/aulas_data.php';
$idsFile = __DIR__ . '/../inc/ubicaciones_ids.php';
$cssFile = __DIR__ . '/../css/gestion-schoolmanager-theme.css';
$setupFile = __DIR__ . '/../setup.php';
$codeFiles = [
    'aulas' => ['label' => 'Aulas y códigos', 'path' => $aulasFile, 'module' => 'Planos'],
    'ids' => ['label' => 'IDs GLPI', 'path' => $idsFile, 'module' => 'Ubicaciones'],
    'css' => ['label' => 'Tema visual CSS', 'path' => $cssFile, 'module' => 'Diseño'],
    'setup' => ['label' => 'setup.php', 'path' => $setupFile, 'module' => 'Núcleo'],
    'versiones' => ['label' => 'Página Versiones', 'path' => __DIR__ . '/versiones.php', 'module' => 'Documentación'],
    'selector' => ['label' => 'Selector de planos', 'path' => __DIR__ . '/selector.php', 'module' => 'Planos'],
    'panel_tic' => ['label' => 'Panel TIC', 'path' => __DIR__ . '/panel_tic.php', 'module' => 'Soporte'],
    'gestion_activos' => ['label' => 'Gestión activos', 'path' => __DIR__ . '/gestion_activos.php', 'module' => 'Inventario'],
    'modal_script' => ['label' => 'Script selector ubicación', 'path' => __DIR__ . '/../inc/location_modal_script.php', 'module' => 'Ubicaciones'],
    'ui_helpers' => ['label' => 'Helpers UI', 'path' => __DIR__ . '/../inc/ui_helpers.php', 'module' => 'Núcleo'],
];

$canAccess = false;
if (function_exists('smgr_is_super_admin_user')) { $canAccess = smgr_is_super_admin_user(); }
if (!$canAccess && function_exists('plugin_schoolmanager_is_super_admin_v176')) { $canAccess = plugin_schoolmanager_is_super_admin_v176(); }
if (!$canAccess) { plugin_schoolmanager_access_denied_page('Editor restringido', 'Solo Super-Admin puede modificar datos internos del plugin.'); }

function pe_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function pe_token() {
    if (method_exists('Session', 'getNewCSRFToken')) {
        static $tok = null; if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . pe_h($tok) . '">';
    }
}
function pe_svg($name) {
    $icons = [
        'home' => '<svg viewBox="0 0 24 24"><path d="M3.7 10.7 12 4l8.3 6.7"/><path d="M6.5 9.8V20h11V9.8"/><path d="M10 20v-5h4v5"/></svg>',
        'versions' => '<svg viewBox="0 0 24 24"><path d="M6 4h10l3 3v13H6z"/><path d="M16 4v4h4"/><path d="M9 12h6M9 16h5"/></svg>',
        'map' => '<svg viewBox="0 0 24 24"><path d="M9 18 4 21V6l5-3 6 3 5-3v15l-5 3z"/><path d="M9 3v15M15 6v15"/></svg>',
        'room' => '<svg viewBox="0 0 24 24"><path d="M4 10.5 12 4l8 6.5"/><path d="M6.5 9.8V20h11V9.8"/><path d="M9.5 20v-6h5v6"/></svg>',
        'id' => '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 10h8M8 14h5"/><circle cx="17" cy="15" r="1"/></svg>',
        'palette' => '<svg viewBox="0 0 24 24"><path d="M12 4a8 8 0 0 0 0 16h1.5a1.7 1.7 0 0 0 1.2-2.9 1.7 1.7 0 0 1 1.2-2.9H17a5 5 0 0 0 0-10z"/><circle cx="8.5" cy="10" r=".8"/><circle cx="11" cy="8" r=".8"/><circle cx="13.8" cy="10" r=".8"/></svg>',
        'code' => '<svg viewBox="0 0 24 24"><path d="m8 9-4 3 4 3"/><path d="m16 9 4 3-4 3"/><path d="m13 5-2 14"/></svg>',
        'backup' => '<svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.3-5.7"/><path d="M4 4v6h6"/><path d="M12 8v5l3 2"/></svg>',
        'download' => '<svg viewBox="0 0 24 24"><path d="M12 4v10"/><path d="m8 10 4 4 4-4"/><path d="M5 20h14"/></svg>',
        'save' => '<svg viewBox="0 0 24 24"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4"/><path d="M8 20v-6h8v6"/></svg>',
        'copy' => '<svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/></svg>',
        'search' => '<svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6"/><path d="m16 16 4 4"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6z"/><path d="m9 12 2 2 4-5"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24"><path d="M12 3 22 20H2z"/><path d="M12 9v4M12 17h.01"/></svg>',
        'folder' => '<svg viewBox="0 0 24 24"><path d="M3.5 7.5a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/></svg>',
        'spark' => '<svg viewBox="0 0 24 24"><path d="M12 3 14.3 8.7 20 11l-5.7 2.3L12 19l-2.3-5.7L4 11l5.7-2.3z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
        'list' => '<svg viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>',
    ];
    return $icons[$name] ?? $icons['spark'];
}
function pe_backup_dir() {
    $dir = dirname(__DIR__) . '/backups';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}
function pe_backup_file($path) {
    if (!is_file($path)) { return false; }
    $dst = pe_backup_dir() . '/' . basename($path) . '.' . date('Ymd-His') . '.bak';
    return @copy($path, $dst);
}
function pe_latest_backup($path) {
    $files = glob(pe_backup_dir() . '/' . basename($path) . '.*.bak') ?: [];
    usort($files, static function($a, $b) { return filemtime($b) <=> filemtime($a); });
    return $files[0] ?? null;
}
function pe_restore_latest_backup($path) {
    $bak = pe_latest_backup($path);
    if (!$bak || !is_file($bak)) { return false; }
    pe_backup_file($path);
    return @copy($bak, $path);
}
function pe_default_css_vars() {
    return ['sm-bg'=>'#f6f8fb','sm-card'=>'#ffffff','sm-navy'=>'#07384d','sm-blue'=>'#0b5f7a','sm-red'=>'#b01f2a','sm-warning'=>'#efa300','sm-border'=>'#dbe9ef','sm-ink'=>'#102638','sm-muted'=>'#617386'];
}
function pe_read_css_vars($file) {
    $css = (string)@file_get_contents($file);
    $vars = pe_default_css_vars();
    foreach ($vars as $k => $v) {
        if (preg_match('/--' . preg_quote($k, '/') . '\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $m)) { $vars[$k] = $m[1]; }
    }
    return $vars;
}
function pe_write_css_vars($file, $vars) {
    $css = (string)@file_get_contents($file);
    foreach ($vars as $k => $v) {
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) { continue; }
        $css = preg_replace('/--' . preg_quote($k, '/') . '\s*:\s*#[0-9a-fA-F]{3,8}\s*;/', '--' . $k . ':' . $v . ';', $css, 1);
    }
    pe_backup_file($file);
    return @file_put_contents($file, $css, LOCK_EX) !== false;
}
function pe_load_ids($file) {
    $ids = require($file);
    return is_array($ids) ? $ids : [];
}
function pe_write_ids($file, $ids) {
    ksort($ids, SORT_NATURAL);
    $out = "<?php\n// IDs directas de ubicaciones GLPI para Plano de Clases.\n// Archivo generado desde el Editor de archivos School Manager.\nreturn [\n";
    foreach ($ids as $k => $v) { $out .= '    ' . var_export((string)$k, true) . ' => ' . (int)$v . ",\n"; }
    $out .= "];\n";
    pe_backup_file($file);
    return @file_put_contents($file, $out, LOCK_EX) !== false;
}
function pe_room_value($row, $key, $idx, $default = '') {
    if (is_array($row)) {
        if (array_key_exists($key, $row)) { return (string)$row[$key]; }
        if (array_key_exists($idx, $row)) { return (string)$row[$idx]; }
    }
    return $default;
}
function pe_normalize_rooms($rows) {
    $out = [];
    foreach ((array)$rows as $r) {
        $code = trim(pe_room_value($r, 'codigo', 2));
        if ($code === '') { continue; }
        $out[] = [
            'building' => pe_room_value($r, 'building', 0),
            'aula' => pe_room_value($r, 'aula', 1),
            'codigo' => $code,
            'planta' => pe_room_value($r, 'planta', 3),
            'floor' => pe_room_value($r, 'floor', 4),
            'descripcion' => pe_room_value($r, 'descripcion', 5),
        ];
    }
    return $out;
}
function pe_php_row_literal($new) {
    return '[' . var_export((string)$new['building'], true) . ',' . var_export((string)$new['aula'], true) . ',' . var_export((string)$new['codigo'], true) . ',' . var_export((string)$new['planta'], true) . ',' . var_export((string)$new['floor'], true) . ',' . var_export((string)$new['descripcion'], true) . ']';
}
function pe_replace_room_row($file, $oldCode, $new) {
    $txt = (string)@file_get_contents($file);
    $pattern = "/\[\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'" . preg_quote($oldCode, '/') . "'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*\]/u";
    $newTxt = preg_replace($pattern, pe_php_row_literal($new), $txt, 1, $count);
    if ($count < 1) { return false; }
    pe_backup_file($file);
    return @file_put_contents($file, $newTxt, LOCK_EX) !== false;
}
function pe_file_size($path) {
    if (!is_file($path)) { return '-'; }
    $bytes = filesize($path);
    if ($bytes < 1024) { return $bytes . ' B'; }
    if ($bytes < 1048576) { return round($bytes / 1024, 1) . ' KB'; }
    return round($bytes / 1048576, 1) . ' MB';
}
function pe_add_path_to_zip($zip, $path, $base, $prefix = '') {
    $path = realpath($path);
    if (!$path || !file_exists($path)) { return; }
    $base = realpath($base) ?: dirname($path);
    if (is_file($path)) {
        $rel = ltrim(str_replace('\\', '/', substr($path, strlen($base))), '/');
        if ($rel === '') { $rel = basename($path); }
        $zip->addFile($path, trim($prefix . '/' . $rel, '/'));
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $real = $file->getPathname();
        if (strpos(str_replace('\\', '/', $real), '/backups/') !== false) { continue; }
        if (!$file->isFile()) { continue; }
        $rel = ltrim(str_replace('\\', '/', substr($real, strlen($base))), '/');
        $zip->addFile($real, trim($prefix . '/' . $rel, '/'));
    }
}
function pe_create_zip_backup($target, $pluginBase, $codeFiles, $aulasFile, $idsFile, $cssFile) {
    if (!class_exists('ZipArchive')) { return [false, 'ZipArchive no está disponible en PHP.']; }
    $labels = [
        'full' => 'plugin-completo', 'rooms' => 'aulas', 'ids' => 'ids-glpi', 'theme' => 'paleta', 'code' => 'codigo-permitido'
    ];
    if (!isset($labels[$target])) { return [false, 'Tipo de backup no válido.']; }
    $name = 'schoolmanager-backup-' . $labels[$target] . '-' . date('Ymd-His') . '.zip';
    $path = pe_backup_dir() . '/' . $name;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { return [false, 'No se pudo crear el ZIP de backup.']; }
    if ($target === 'full') {
        pe_add_path_to_zip($zip, $pluginBase, dirname($pluginBase));
        $mapsRoot = realpath(__DIR__ . '/../../../maps');
        if ($mapsRoot && is_dir($mapsRoot)) { pe_add_path_to_zip($zip, $mapsRoot, dirname($mapsRoot), 'glpi-maps'); }
    } elseif ($target === 'rooms') {
        $zip->addFile($aulasFile, 'schoolmanager/inc/aulas_data.php');
        $zip->addFile($idsFile, 'schoolmanager/inc/ubicaciones_ids.php');
    } elseif ($target === 'ids') {
        $zip->addFile($idsFile, 'schoolmanager/inc/ubicaciones_ids.php');
    } elseif ($target === 'theme') {
        $zip->addFile($cssFile, 'schoolmanager/css/gestion-schoolmanager-theme.css');
    } elseif ($target === 'code') {
        foreach ($codeFiles as $info) {
            if (is_file($info['path'])) { $zip->addFile($info['path'], 'schoolmanager/' . ltrim(str_replace(str_replace('\\', '/', $pluginBase), '', str_replace('\\', '/', realpath($info['path']))), '/')); }
        }
    }
    $zip->addFromString('README-backup.txt', "Backup School Manager\nTipo: {$labels[$target]}\nFecha: " . date('Y-m-d H:i:s') . "\nVersion plugin: " . (defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.0.0') . "\n");
    $zip->close();
    return [true, $name];
}
function pe_backup_list() {
    $files = array_merge(glob(pe_backup_dir() . '/*.zip') ?: [], glob(pe_backup_dir() . '/*.bak') ?: []);
    usort($files, static function($a, $b) { return filemtime($b) <=> filemtime($a); });
    return array_slice($files, 0, 80);
}

if (isset($_GET['download_backup'])) {
    $name = basename((string)$_GET['download_backup']);
    $file = pe_backup_dir() . '/' . $name;
    if (is_file($file) && realpath(dirname($file)) === realpath(pe_backup_dir())) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

$message = '';
$messageType = 'info';
// CSRF token is emitted; avoid false positives on plugin forms.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'theme' || $action === 'theme_defaults') {
        $vars = $action === 'theme_defaults' ? pe_default_css_vars() : [];
        if ($action === 'theme') { foreach (array_keys(pe_default_css_vars()) as $k) { $vars[$k] = (string)($_POST[$k] ?? ''); } }
        $ok = pe_write_css_vars($cssFile, $vars);
        $message = $ok ? ($action === 'theme_defaults' ? 'Paleta restaurada correctamente.' : 'Paleta guardada correctamente.') : 'No se pudo guardar la paleta. Revisa permisos.';
        $messageType = $ok ? 'ok' : 'error';
    } elseif ($action === 'restore_latest') {
        $target = (string)($_POST['target'] ?? '');
        $map = ['theme'=>$cssFile, 'rooms'=>$aulasFile, 'ids'=>$idsFile];
        $names = ['theme'=>'paleta', 'rooms'=>'aulas', 'ids'=>'IDs GLPI'];
        if (isset($map[$target])) {
            $ok = pe_restore_latest_backup($map[$target]);
            $message = $ok ? 'Restaurado el último backup de ' . $names[$target] . '.' : 'No existe backup anterior para restaurar ' . $names[$target] . '.';
            $messageType = $ok ? 'ok' : 'error';
        }
    } elseif ($action === 'create_backup') {
        [$ok, $result] = pe_create_zip_backup((string)($_POST['backup_target'] ?? ''), $pluginBase, $codeFiles, $aulasFile, $idsFile, $cssFile);
        $message = $ok ? 'Backup creado: ' . $result : $result;
        $messageType = $ok ? 'ok' : 'error';
    } elseif ($action === 'code_save') {
        $key = (string)($_POST['file_key'] ?? '');
        $content = (string)($_POST['file_content'] ?? '');
        if (isset($codeFiles[$key]) && is_file($codeFiles[$key]['path'])) {
            pe_backup_file($codeFiles[$key]['path']);
            $ok = @file_put_contents($codeFiles[$key]['path'], $content, LOCK_EX) !== false;
            $message = $ok ? 'Archivo guardado con backup previo.' : 'No se pudo guardar el archivo seleccionado.';
            $messageType = $ok ? 'ok' : 'error';
        } else { $message = 'Archivo no permitido.'; $messageType = 'error'; }
    } elseif ($action === 'room') {
        $old = (string)($_POST['old_code'] ?? '');
        $new = ['building'=>(string)($_POST['building'] ?? plugin_schoolmanager_default_building_code()), 'aula'=>(string)($_POST['aula'] ?? ''), 'codigo'=>(string)($_POST['codigo'] ?? ''), 'planta'=>(string)($_POST['planta'] ?? ''), 'floor'=>(string)($_POST['floor'] ?? ''), 'descripcion'=>(string)($_POST['descripcion'] ?? '')];
        if ($old && $new['codigo'] && pe_replace_room_row($aulasFile, $old, $new)) {
            $ids = pe_load_ids($idsFile);
            if (isset($ids[$old]) && $old !== $new['codigo']) { $ids[$new['codigo']] = $ids[$old]; unset($ids[$old]); pe_write_ids($idsFile, $ids); }
            $message = 'Aula actualizada. Si cambiaste el código, también se ajustó el ID relacionado.'; $messageType = 'ok';
        } else { $message = 'No se pudo actualizar el aula. Revisa el código original.'; $messageType = 'error'; }
    } elseif ($action === 'id') {
        $old = (string)($_POST['old_code'] ?? ''); $code = (string)($_POST['codigo'] ?? ''); $id = (int)($_POST['id_glpi'] ?? 0);
        $ids = pe_load_ids($idsFile); if ($old && isset($ids[$old])) { unset($ids[$old]); } if ($code && $id > 0) { $ids[$code] = $id; }
        $ok = pe_write_ids($idsFile, $ids); $message = $ok ? 'ID de ubicación guardada.' : 'No se pudo guardar el ID.'; $messageType = $ok ? 'ok' : 'error';
    }
}

$aulasRaw = require($aulasFile);
$aulas = pe_normalize_rooms($aulasRaw);
$ids = pe_load_ids($idsFile);
$vars = pe_read_css_vars($cssFile);
$backupFiles = pe_backup_list();
$backupCount = count($backupFiles);
$firstCodeKey = array_key_first($codeFiles);

Html::header('Editor de archivos School Manager', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
?>
<style id="schoolmanager-editor-100-css">
:root{--pe-bg:#f6f8fb;--pe-card:#fff;--pe-dark:#07384d;--pe-blue:#0b5f7a;--pe-red:#b01f2a;--pe-red2:#c82935;--pe-warn:#efa300;--pe-line:#dbe9ef;--pe-ink:#102638;--pe-muted:#617386;--pe-soft:#eaf7f7;--pe-shadow:0 18px 48px rgba(7,56,77,.08)}
.pc-editor,.pc-editor *{box-sizing:border-box}.pc-editor{max-width:1540px;margin:0 auto 44px;padding:22px;color:var(--pe-dark);font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;animation:peIn .18s ease both}.pc-editor svg{width:20px;height:20px;display:block;fill:none;stroke:currentColor;stroke-width:2.25;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}.pe-hero{border:1px solid var(--pe-line);border-radius:30px;background:linear-gradient(105deg,#fff 0%,#fbfefd 68%,#fff7e8 100%);box-shadow:var(--pe-shadow);padding:24px;display:grid;grid-template-columns:150px minmax(0,1fr) auto;gap:22px;align-items:center}.pe-logo{width:150px;height:102px;display:flex;align-items:center;justify-content:center;background:transparent}.pe-logo img{max-width:150px;max-height:102px;object-fit:contain;display:block;filter:none;mix-blend-mode:multiply}.pe-kicker{margin:0 0 6px;color:var(--pe-red);font-size:14px;letter-spacing:.16em;text-transform:uppercase;font-weight:1000}.pe-title{margin:0;font-size:clamp(42px,4.7vw,64px);line-height:.93;font-weight:1000;letter-spacing:-.045em;color:var(--pe-dark)}.pe-sub{margin:10px 0 0;color:var(--pe-muted);font-weight:900;font-size:17px;line-height:1.35;max-width:850px}.pe-actions{display:flex;gap:12px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.pe-btn{min-height:48px;min-width:136px;padding:0 18px;border-radius:16px;border:1px solid #cfe2e8;background:#fff;color:var(--pe-dark)!important;display:inline-flex;align-items:center;justify-content:center;gap:10px;font-weight:1000;text-decoration:none!important;box-shadow:0 10px 22px rgba(7,56,77,.06);transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease,color .18s ease;cursor:pointer}.pe-btn:hover{transform:translateY(-2px);box-shadow:0 18px 36px rgba(7,56,77,.13);border-color:#b8d8df}.pe-btn.red{background:var(--pe-red)!important;border-color:var(--pe-red)!important;color:#fff!important;box-shadow:0 14px 34px rgba(176,31,42,.20)}.pe-btn.red:hover{background:var(--pe-red2)!important;border-color:var(--pe-red2)!important}.pe-btn.dark{background:var(--pe-dark)!important;border-color:var(--pe-dark)!important;color:#fff!important}.pe-btn.gold{background:#fff8e8;border-color:#f0c665;color:#7a5100!important}.pe-btn.danger{background:#fff4f4;border-color:#f3b8b8;color:#9e1720!important}.pe-btn.red *,.pe-btn.dark *{color:#fff!important;stroke:#fff!important}.pe-tabs{margin:16px 0;display:flex;gap:10px;flex-wrap:wrap}.pe-tab{border:1px solid var(--pe-line);background:#fff;color:var(--pe-dark);height:44px;padding:0 16px;border-radius:15px;font-weight:1000;display:inline-flex;align-items:center;gap:9px;cursor:pointer;transition:.18s ease}.pe-tab:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(7,56,77,.08);border-color:#bcdce2}.pe-tab.active{background:var(--pe-dark);border-color:var(--pe-dark);color:#fff}.pe-view{display:none}.pe-view.active{display:block;animation:peIn .18s ease both}.pe-alert{margin:14px 0;border-radius:18px;padding:14px 16px;font-weight:950;display:flex;gap:10px;align-items:center;border:1px solid}.pe-alert.ok{background:#eaf8ef;border-color:#b6e6c8;color:#0a673c}.pe-alert.error{background:#fff0f0;border-color:#ffc8c8;color:#9b1c1c}.pe-grid{display:grid;grid-template-columns:minmax(0,1.04fr) minmax(380px,.82fr);gap:16px}.pe-card{background:#fff;border:1px solid var(--pe-line);border-radius:24px;padding:20px;box-shadow:0 12px 32px rgba(7,56,77,.055);overflow:hidden}.pe-card h2{margin:0 0 12px;font-size:30px;line-height:1.05;font-weight:1000;color:var(--pe-dark);display:flex;align-items:center;gap:10px}.pe-card h3{margin:18px 0 9px;font-size:20px;color:var(--pe-dark);font-weight:1000}.pe-card p{margin:0;color:var(--pe-muted);font-weight:850;line-height:1.45}.pe-mini{width:38px;height:38px;border-radius:13px;background:var(--pe-soft);color:var(--pe-dark);display:inline-flex;align-items:center;justify-content:center}.pe-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.pe-stat{background:#fff;border:1px solid var(--pe-line);border-radius:22px;padding:16px;display:grid;grid-template-columns:42px 1fr;gap:10px;align-items:center;box-shadow:0 10px 26px rgba(7,56,77,.045)}.pe-stat .ico{width:42px;height:42px;border-radius:14px;background:var(--pe-soft);display:flex;align-items:center;justify-content:center}.pe-stat b{font-size:28px;line-height:1;color:var(--pe-dark)}.pe-stat span:last-child{color:var(--pe-muted);font-weight:900}.pe-note{border:1px dashed #c9dfe3;border-radius:18px;background:#fbfefd;padding:14px;color:#506a76;font-weight:850;line-height:1.45}.pe-list{display:grid;gap:9px;max-height:620px;overflow:auto;padding-right:4px}.pe-search{height:54px;border:1px solid var(--pe-line);border-radius:17px;background:#fff;display:grid;grid-template-columns:46px 1fr;align-items:center;overflow:hidden;margin-bottom:12px}.pe-search svg{margin-left:16px;color:var(--pe-dark)}.pe-search input{border:0!important;outline:0!important;background:transparent!important;height:100%;font-size:15px;font-weight:900;color:var(--pe-dark);padding:0 14px 0 0!important;box-shadow:none!important}.pe-row{border:1px solid #e4eef1;background:#fff;border-radius:17px;padding:12px;display:grid;grid-template-columns:56px minmax(0,1fr) auto;gap:12px;align-items:center;text-align:left;cursor:pointer;color:var(--pe-dark);transition:.16s ease;width:100%}.pe-row:hover,.pe-row.active{background:#f3fbfa;border-color:#bcdce1;transform:translateX(3px)}.pe-row .badge{width:52px;height:44px;border-radius:15px;background:var(--pe-soft);display:flex;align-items:center;justify-content:center;font-weight:1000;overflow:hidden;text-overflow:ellipsis}.pe-row b{display:block;font-size:18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pe-row small{display:block;color:var(--pe-muted);font-weight:850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pe-pill{border:1px solid #d8e9ee;background:#f8fcfd;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:900;color:var(--pe-dark);white-space:nowrap}.pe-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pe-field{display:grid;gap:6px}.pe-field.full{grid-column:1/-1}.pe-field label{font-weight:1000;color:var(--pe-dark)}.pe-field input,.pe-field textarea,.pe-field select{border:1px solid var(--pe-line);border-radius:15px;background:#fff;color:var(--pe-dark);font-weight:850;font-size:15px;padding:12px;outline:0;transition:.16s ease}.pe-field input:focus,.pe-field textarea:focus,.pe-field select:focus{border-color:#9ccbd0;box-shadow:0 0 0 4px rgba(11,141,131,.10)}.pe-actions-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}.pe-color-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.pe-color{border:1px solid var(--pe-line);border-radius:18px;padding:12px;background:#fff;display:grid;grid-template-columns:50px 1fr;gap:12px;align-items:center}.pe-color input{width:50px;height:46px;border:0;background:transparent;padding:0}.pe-color b{display:block;color:var(--pe-dark)}.pe-color small{color:var(--pe-muted);font-weight:850}.pe-preview{border:1px solid var(--pe-line);border-radius:22px;padding:18px;background:var(--prev-bg,#f6f8fb)}.pe-preview-card{background:var(--prev-card,#fff);border:1px solid var(--prev-border,#dbe9ef);border-radius:20px;padding:18px;color:var(--prev-ink,#102638)}.pe-preview-card h3{margin:0 0 8px;color:var(--prev-navy,#07384d);font-size:26px}.pe-preview-card p{color:var(--prev-muted,#617386)}.pe-preview-card .btn{margin-top:14px;display:inline-flex;border-radius:14px;padding:12px 16px;background:var(--prev-red,#b01f2a);color:#fff;font-weight:1000}.pe-code-layout{display:grid;grid-template-columns:320px minmax(0,1fr);gap:14px}.pe-code-files{display:grid;gap:8px}.pe-code-file{border:1px solid var(--pe-line);background:#fff;border-radius:16px;padding:12px;display:grid;grid-template-columns:36px 1fr;gap:10px;text-align:left;color:var(--pe-dark);cursor:pointer;transition:.16s ease}.pe-code-file:hover,.pe-code-file.active{background:#f3fbfa;border-color:#bcdce1;transform:translateX(3px)}.pe-code-file b{display:block}.pe-code-file small{color:var(--pe-muted);font-weight:850}.pe-codearea{width:100%;min-height:560px;border:1px solid var(--pe-line);border-radius:20px;background:#071e2b;color:#e9fbff;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;padding:16px;resize:vertical;outline:0}.pe-toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap}.pe-backup-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.pe-backup-card{border:1px solid var(--pe-line);background:#fff;border-radius:20px;padding:16px;display:flex;flex-direction:column;gap:10px;min-height:190px}.pe-backup-card h3{margin:0;color:var(--pe-dark);font-size:20px}.pe-backup-card p{font-size:14px}.pe-backup-list{display:grid;gap:8px;margin-top:14px}.pe-backup-row{border:1px solid var(--pe-line);border-radius:15px;background:#fff;padding:11px;display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center}.pe-backup-row b{display:block;color:var(--pe-dark)}.pe-backup-row small{color:var(--pe-muted);font-weight:850}.pe-danger{border:1px solid #f3b8b8;background:#fff7f7;border-radius:20px;padding:18px}.pe-danger h3{margin-top:0;color:#9e1720}.pe-footer{margin-top:16px;border:1px dashed #c7dfe1;background:#fff;border-radius:22px;padding:15px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;color:var(--pe-muted);font-weight:850}.pe-footer strong{color:var(--pe-dark)}@keyframes peIn{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}@media(max-width:1240px){.pe-backup-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pe-hero{grid-template-columns:auto 1fr}.pe-actions{grid-column:1/-1;justify-content:flex-start}.pe-grid,.pe-code-layout{grid-template-columns:1fr}.pe-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pe-color-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.pc-editor{padding:12px}.pe-hero{grid-template-columns:1fr;border-radius:24px;padding:18px}.pe-logo{width:120px;height:82px}.pe-logo img{max-width:120px;max-height:82px}.pe-actions{display:grid;grid-template-columns:1fr;width:100%}.pe-btn{width:100%;min-width:0}.pe-stats,.pe-color-grid,.pe-form,.pe-backup-grid{grid-template-columns:1fr}.pe-field.full{grid-column:auto}.pe-card{padding:16px}.pe-list{max-height:none}.pe-row{grid-template-columns:44px minmax(0,1fr)}.pe-row .badge{width:42px}.pe-row .pe-pill{grid-column:1/-1;justify-self:start}.pe-backup-row{grid-template-columns:1fr}}
</style>
<div class="pc-editor">
  <section class="pe-hero">
    <div class="pe-logo"><img src="<?= pe_h($logoUrl) ?>" alt="Logo del centro"></div>
    <div>
      <p class="pe-kicker">Gestión School Manager · Administración</p>
      <h1 class="pe-title">Editor de archivos</h1>
      <p class="pe-sub">Panel seguro para modificar aulas, IDs de GLPI, paleta visual y archivos internos. Ahora también permite crear backups manuales completos o por secciones cuando quieras.</p>
    </div>
    <div class="pe-actions">
      <a class="pe-btn red" href="<?= pe_h($root) ?>/plugins/schoolmanager/front/formularios.php?v=<?= urlencode($pluginVersion) ?>"><?= pe_svg('home') ?><span>Inicio</span></a>
      <a class="pe-btn" href="<?= pe_h($root) ?>/plugins/schoolmanager/front/versiones.php?v=<?= urlencode($pluginVersion) ?>"><?= pe_svg('versions') ?><span>Versiones</span></a>
      <a class="pe-btn dark" href="<?= pe_h($root) ?>/plugins/schoolmanager/front/selector.php?building=<?= urlencode(plugin_schoolmanager_default_building_code()) ?>&floor=<?= urlencode(plugin_schoolmanager_default_floor_code(plugin_schoolmanager_default_building_code())) ?>&v=<?= urlencode($pluginVersion) ?>"><?= pe_svg('map') ?><span>Plano</span></a>
    </div>
  </section>

  <?php if ($message !== ''): ?><div class="pe-alert <?= pe_h($messageType) ?>"><?= pe_svg($messageType === 'error' ? 'warning' : 'shield') ?><span><?= pe_h($message) ?></span></div><?php endif; ?>

  <nav class="pe-tabs" aria-label="Secciones del editor">
    <button class="pe-tab active" type="button" data-pe-tab="resumen"><?= pe_svg('spark') ?>Resumen</button>
    <button class="pe-tab" type="button" data-pe-tab="aulas"><?= pe_svg('room') ?>Aulas</button>
    <button class="pe-tab" type="button" data-pe-tab="ids"><?= pe_svg('id') ?>IDs GLPI</button>
    <button class="pe-tab" type="button" data-pe-tab="paleta"><?= pe_svg('palette') ?>Paleta</button>
    <button class="pe-tab" type="button" data-pe-tab="codigo"><?= pe_svg('code') ?>Código</button>
    <button class="pe-tab" type="button" data-pe-tab="backups"><?= pe_svg('backup') ?>Backups</button>
  </nav>

  <section class="pe-view active" data-pe-view="resumen">
    <section class="pe-stats">
      <div class="pe-stat"><span class="ico"><?= pe_svg('room') ?></span><b><?= count($aulas) ?></b><span>aulas registradas</span></div>
      <div class="pe-stat"><span class="ico"><?= pe_svg('id') ?></span><b><?= count($ids) ?></b><span>IDs GLPI</span></div>
      <div class="pe-stat"><span class="ico"><?= pe_svg('code') ?></span><b><?= count($codeFiles) ?></b><span>archivos editables</span></div>
      <div class="pe-stat"><span class="ico"><?= pe_svg('backup') ?></span><b><?= (int)$backupCount ?></b><span>backups disponibles</span></div>
    </section>
    <section class="pe-grid">
      <article class="pe-card">
        <h2><span class="pe-mini"><?= pe_svg('shield') ?></span>Cómo funciona</h2>
        <p>Este editor no toca la base de datos de GLPI salvo cuando la propia página enlaza a ubicaciones nativas. Modifica archivos internos del plugin: aulas, IDs, estilos y partes concretas del código. Antes de guardar se hace backup automático.</p>
        <h3>Flujo recomendado</h3>
        <div class="pe-note">1. Crea un backup manual si vas a tocar muchas cosas. 2. Edita aula, ID, paleta o archivo. 3. Guarda. 4. Prueba plano o página afectada. 5. Si algo falla, restaura el último backup.</div>
      </article>
      <aside class="pe-card">
        <h2><span class="pe-mini"><?= pe_svg('backup') ?></span>Backups manuales</h2>
        <p>Puedes crear copia del plugin completo, solo aulas, solo IDs, la paleta o los archivos permitidos desde la pestaña Backups.</p>
        <div class="pe-actions-row"><button class="pe-btn dark" type="button" data-open-tab="backups"><?= pe_svg('backup') ?>Abrir backups</button><button class="pe-btn" type="button" data-open-tab="aulas"><?= pe_svg('room') ?>Editar aulas</button></div>
      </aside>
    </section>
  </section>

  <section class="pe-view" data-pe-view="aulas">
    <section class="pe-grid">
      <aside class="pe-card">
        <h2><span class="pe-mini"><?= pe_svg('room') ?></span>Lista de aulas</h2>
        <label class="pe-search"><?= pe_svg('search') ?><input id="roomSearch" type="search" placeholder="Buscar aula, código, edificio o planta..."></label>
        <div class="pe-list" id="roomList">
          <?php foreach ($aulas as $r): $code=(string)$r['codigo']; $label = trim($r['aula'] . ' · ' . $r['descripcion'], ' ·'); ?>
            <button type="button" class="pe-row" data-room-row data-building="<?= pe_h($r['building']) ?>" data-aula="<?= pe_h($r['aula']) ?>" data-code="<?= pe_h($code) ?>" data-planta="<?= pe_h($r['planta']) ?>" data-floor="<?= pe_h($r['floor']) ?>" data-descripcion="<?= pe_h($r['descripcion']) ?>">
              <span class="badge"><?= pe_h($r['building']) ?></span><span><b><?= pe_h($label !== '' ? $label : $code) ?></b><small><?= pe_h($code . ' · ' . $r['planta'] . ' · ' . $r['floor']) ?></small></span><span class="pe-pill"><?= isset($ids[$code]) ? 'ID ' . (int)$ids[$code] : 'Sin ID' ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </aside>
      <article class="pe-card">
        <h2><span class="pe-mini"><?= pe_svg('edit') ?></span>Editar aula</h2>
        <form method="post" class="pe-form">
          <input type="hidden" name="action" value="room"><input type="hidden" name="old_code" id="roomOldCode"><?php pe_token(); ?>
          <div class="pe-field"><label>Edificio</label><input name="building" id="roomBuilding" required></div>
          <div class="pe-field"><label>Aula</label><input name="aula" id="roomAula" required></div>
          <div class="pe-field"><label>Código</label><input name="codigo" id="roomCodigo" required></div>
          <div class="pe-field"><label>Planta visible</label><input name="planta" id="roomPlanta" required></div>
          <div class="pe-field"><label>Floor interno</label><input name="floor" id="roomFloor" required></div>
          <div class="pe-field full"><label>Descripción</label><input name="descripcion" id="roomDescripcion" required></div>
          <div class="pe-field full"><div class="pe-actions-row"><button class="pe-btn dark" type="submit"><?= pe_svg('save') ?>Guardar aula</button><a class="pe-btn" href="<?= pe_h($root) ?>/plugins/schoolmanager/front/selector.php?v=<?= urlencode($pluginVersion) ?>"><?= pe_svg('map') ?>Probar plano</a><button class="pe-btn gold" type="button" data-open-tab="backups"><?= pe_svg('backup') ?>Backup aulas</button></div></div>
        </form>
      </article>
    </section>
  </section>

  <section class="pe-view" data-pe-view="ids">
    <section class="pe-grid">
      <aside class="pe-card"><h2><span class="pe-mini"><?= pe_svg('id') ?></span>IDs de ubicación</h2><label class="pe-search"><?= pe_svg('search') ?><input id="idSearch" type="search" placeholder="Buscar código o ID..."></label><div class="pe-list" id="idList"><?php foreach ($ids as $code => $id): ?><button type="button" class="pe-row" data-id-row data-code="<?= pe_h($code) ?>" data-id="<?= (int)$id ?>"><span class="badge">ID</span><span><b><?= pe_h($code) ?></b><small>Enlace directo a ubicación GLPI</small></span><span class="pe-pill">#<?= (int)$id ?></span></button><?php endforeach; ?></div></aside>
      <article class="pe-card"><h2><span class="pe-mini"><?= pe_svg('edit') ?></span>Editar ID GLPI</h2><form method="post" class="pe-form"><input type="hidden" name="action" value="id"><input type="hidden" name="old_code" id="idOldCode"><?php pe_token(); ?><div class="pe-field"><label>Código del aula</label><input name="codigo" id="idCodigo" required></div><div class="pe-field"><label>ID de ubicación GLPI</label><input name="id_glpi" id="idGlpi" type="number" min="1" required></div><div class="pe-field full"><div class="pe-note">El enlace resultante será parecido a <b>/front/location.form.php?id=ID</b>. Usa el ID real de la ubicación nativa de GLPI.</div></div><div class="pe-field full"><div class="pe-actions-row"><button class="pe-btn dark" type="submit"><?= pe_svg('save') ?>Guardar ID</button><button class="pe-btn gold" type="button" data-open-tab="backups"><?= pe_svg('backup') ?>Backup IDs</button></div></div></form></article>
    </section>
  </section>

  <section class="pe-view" data-pe-view="paleta">
    <section class="pe-grid"><article class="pe-card"><h2><span class="pe-mini"><?= pe_svg('palette') ?></span>Paleta vintage</h2><form method="post"><input type="hidden" name="action" value="theme"><?php pe_token(); ?><div class="pe-color-grid"><?php foreach ($vars as $k => $v): ?><label class="pe-color"><input type="color" name="<?= pe_h($k) ?>" value="<?= pe_h($v) ?>" data-color-var="<?= pe_h($k) ?>"><span><b><?= pe_h($k) ?></b><small><?= pe_h($v) ?></small></span></label><?php endforeach; ?></div><div class="pe-actions-row"><button class="pe-btn dark" type="submit"><?= pe_svg('save') ?>Guardar paleta</button><button class="pe-btn" type="button" id="previewDefaults"><?= pe_svg('spark') ?>Vista por defecto</button><button class="pe-btn gold" type="button" data-open-tab="backups"><?= pe_svg('backup') ?>Backup paleta</button></div></form></article><aside class="pe-card"><h2><span class="pe-mini"><?= pe_svg('eye') ?></span>Previsualización</h2><div class="pe-preview" id="themePreview"><div class="pe-preview-card"><h3>Gestión School Manager</h3><p>Ejemplo de tarjeta, texto y botón con la paleta actual.</p><span class="btn">Botón principal</span></div></div></aside></section>
  </section>

  <section class="pe-view" data-pe-view="codigo">
    <section class="pe-card"><h2><span class="pe-mini"><?= pe_svg('code') ?></span>Editor de código avanzado</h2><div class="pe-note">Usa esta parte solo para cambios concretos. Se limita a archivos permitidos y crea backup automático antes de guardar.</div><div class="pe-code-layout" style="margin-top:14px"><aside class="pe-code-files" id="codeFileList"><?php foreach ($codeFiles as $key => $info): $content = is_file($info['path']) ? file_get_contents($info['path']) : ''; ?><button type="button" class="pe-code-file <?= $key === $firstCodeKey ? 'active' : '' ?>" data-code-btn data-key="<?= pe_h($key) ?>" data-label="<?= pe_h($info['label']) ?>"><span class="pe-mini"><?= pe_svg(pathinfo($info['path'], PATHINFO_EXTENSION) === 'php' ? 'code' : 'folder') ?></span><span><b><?= pe_h($info['label']) ?></b><small><?= pe_h($info['module']) ?> · <?= pe_h(basename($info['path'])) ?> · <?= pe_h(pe_file_size($info['path'])) ?></small></span></button><script type="application/json" id="code-content-<?= pe_h($key) ?>"><?= json_encode($content, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script><?php endforeach; ?></aside><main><form method="post" id="codeForm"><input type="hidden" name="action" value="code_save"><input type="hidden" name="file_key" id="codeFileKey" value="<?= pe_h($firstCodeKey) ?>"><?php pe_token(); ?><div class="pe-toolbar"><b id="codeFileTitle"><?= pe_h($codeFiles[$firstCodeKey]['label'] ?? 'Archivo') ?></b><div class="pe-actions-row" style="margin:0"><button class="pe-btn dark" type="submit"><?= pe_svg('save') ?>Guardar código</button><button class="pe-btn" type="button" id="copyCodeBtn"><?= pe_svg('copy') ?>Copiar</button><button class="pe-btn gold" type="button" data-open-tab="backups"><?= pe_svg('backup') ?>Backup código</button></div></div><textarea class="pe-codearea" name="file_content" id="codeArea" spellcheck="false"><?= pe_h(is_file($codeFiles[$firstCodeKey]['path']) ? file_get_contents($codeFiles[$firstCodeKey]['path']) : '') ?></textarea></form></main></div></section>
  </section>

  <section class="pe-view" data-pe-view="backups">
    <section class="pe-card">
      <h2><span class="pe-mini"><?= pe_svg('backup') ?></span>Backups manuales y reversión</h2>
      <p>Crea backups cuando quieras. El backup completo incluye el plugin y, si existe, la carpeta pública de mapas de GLPI. Los backups automáticos siguen funcionando antes de cada guardado.</p>
      <div class="pe-backup-grid" style="margin-top:14px">
        <?php $manualTargets = ['full'=>['Plugin completo','Todo el plugin, mapas y archivos principales.'], 'rooms'=>['Solo aulas','aulas_data.php + ubicaciones_ids.php.'], 'ids'=>['Solo IDs GLPI','Mapa de códigos a ubicaciones GLPI.'], 'theme'=>['Solo paleta','CSS principal del tema visual.'], 'code'=>['Código editable','Archivos permitidos del editor.']]; foreach ($manualTargets as $target => $info): ?>
          <form class="pe-backup-card" method="post"><input type="hidden" name="action" value="create_backup"><input type="hidden" name="backup_target" value="<?= pe_h($target) ?>"><?php pe_token(); ?><h3><?= pe_h($info[0]) ?></h3><p><?= pe_h($info[1]) ?></p><button class="pe-btn dark" type="submit"><?= pe_svg('backup') ?>Crear backup</button></form>
        <?php endforeach; ?>
      </div>
      <h3>Restaurar último backup automático</h3>
      <div class="pe-backup-grid">
        <?php $restoreTargets = ['rooms'=>'Aulas y códigos', 'ids'=>'IDs GLPI', 'theme'=>'Paleta visual']; foreach ($restoreTargets as $target => $label): ?>
          <div class="pe-danger"><h3><?= pe_h($label) ?></h3><p>Restaura el último .bak automático de este bloque.</p><form method="post" onsubmit="return confirm('¿Restaurar el último backup de <?= pe_h($label) ?>?');"><input type="hidden" name="action" value="restore_latest"><input type="hidden" name="target" value="<?= pe_h($target) ?>"><?php pe_token(); ?><div class="pe-actions-row"><button class="pe-btn danger" type="submit"><?= pe_svg('backup') ?>Restaurar</button></div></form></div>
        <?php endforeach; ?>
      </div>
      <h3>Últimos backups creados</h3>
      <div class="pe-backup-list">
        <?php if (!$backupFiles): ?><div class="pe-note">Todavía no hay backups creados.</div><?php endif; ?>
        <?php foreach ($backupFiles as $bf): $bn = basename($bf); ?>
          <div class="pe-backup-row"><span><b><?= pe_h($bn) ?></b><small><?= pe_h(date('Y-m-d H:i:s', filemtime($bf))) ?> · <?= pe_h(pe_file_size($bf)) ?></small></span><span class="pe-pill"><?= substr($bn, -4) === '.zip' ? 'ZIP manual' : 'BAK auto' ?></span><a class="pe-btn" href="<?= pe_h($_SERVER['PHP_SELF']) ?>?download_backup=<?= urlencode($bn) ?>"><?= pe_svg('download') ?>Descargar</a></div>
        <?php endforeach; ?>
      </div>
    </section>
  </section>

  <div class="pe-footer"><span><strong>Versión:</strong> <?= pe_h($pluginVersion) ?></span><span><strong>Aulas:</strong> <?= count($aulas) ?></span><span><strong>Backups:</strong> <?= (int)$backupCount ?></span><span><strong>Ruta:</strong> /var/www/glpi/plugins/schoolmanager</span></div>
</div>
<script id="schoolmanager-editor-100-js">
(function(){
  var root=document.querySelector('.pc-editor'); if(!root) return;
  function openTab(id){var tab=root.querySelector('[data-pe-tab="'+id+'"]'); if(!tab)return; root.querySelectorAll('[data-pe-tab]').forEach(function(t){t.classList.toggle('active',t===tab)});root.querySelectorAll('[data-pe-view]').forEach(function(v){v.classList.toggle('active',v.getAttribute('data-pe-view')===id)}); window.scrollTo({top:0,behavior:'smooth'});}
  root.querySelectorAll('[data-pe-tab]').forEach(function(tab){tab.addEventListener('click',function(){openTab(tab.getAttribute('data-pe-tab'));});});
  root.querySelectorAll('[data-open-tab]').forEach(function(btn){btn.addEventListener('click',function(){openTab(btn.getAttribute('data-open-tab'));});});
  function filter(inputId,listId,itemSelector){var input=document.getElementById(inputId),list=document.getElementById(listId);if(!input||!list)return;input.addEventListener('input',function(){var q=input.value.toLowerCase();list.querySelectorAll(itemSelector).forEach(function(row){row.style.display=row.textContent.toLowerCase().indexOf(q)!==-1?'grid':'none';});});}
  filter('roomSearch','roomList','[data-room-row]'); filter('idSearch','idList','[data-id-row]');
  function clickFirst(sel){var first=root.querySelector(sel); if(first) first.click();}
  root.querySelectorAll('[data-room-row]').forEach(function(row){row.addEventListener('click',function(){root.querySelectorAll('[data-room-row]').forEach(function(x){x.classList.remove('active')});row.classList.add('active');roomOldCode.value=row.dataset.code||'';roomBuilding.value=row.dataset.building||'';roomAula.value=row.dataset.aula||'';roomCodigo.value=row.dataset.code||'';roomPlanta.value=row.dataset.planta||'';roomFloor.value=row.dataset.floor||'';roomDescripcion.value=row.dataset.descripcion||'';});}); clickFirst('[data-room-row]');
  root.querySelectorAll('[data-id-row]').forEach(function(row){row.addEventListener('click',function(){root.querySelectorAll('[data-id-row]').forEach(function(x){x.classList.remove('active')});row.classList.add('active');idOldCode.value=row.dataset.code||'';idCodigo.value=row.dataset.code||'';idGlpi.value=row.dataset.id||'';});}); clickFirst('[data-id-row]');
  var preview=document.getElementById('themePreview'); function repaint(){if(!preview)return; var map={'sm-bg':'--prev-bg','sm-card':'--prev-card','sm-navy':'--prev-navy','sm-red':'--prev-red','sm-border':'--prev-border','sm-ink':'--prev-ink','sm-muted':'--prev-muted'}; root.querySelectorAll('[data-color-var]').forEach(function(c){var sm=c.closest('.pe-color').querySelector('small'); if(sm) sm.textContent=c.value; if(map[c.dataset.colorVar]) preview.style.setProperty(map[c.dataset.colorVar],c.value);});} root.querySelectorAll('[data-color-var]').forEach(function(c){c.addEventListener('input',repaint)}); repaint();
  document.getElementById('previewDefaults')?.addEventListener('click',function(){var d={'sm-bg':'#f6f8fb','sm-card':'#ffffff','sm-navy':'#07384d','sm-blue':'#0b5f7a','sm-red':'#b01f2a','sm-warning':'#efa300','sm-border':'#dbe9ef','sm-ink':'#102638','sm-muted':'#617386'};Object.keys(d).forEach(function(k){var i=root.querySelector('[data-color-var="'+k+'"]');if(i)i.value=d[k];});repaint();});
  var area=document.getElementById('codeArea'), key=document.getElementById('codeFileKey'), title=document.getElementById('codeFileTitle'); root.querySelectorAll('[data-code-btn]').forEach(function(btn){btn.addEventListener('click',function(){root.querySelectorAll('[data-code-btn]').forEach(function(x){x.classList.remove('active')});btn.classList.add('active');var k=btn.dataset.key||'';var data=document.getElementById('code-content-'+k);if(area&&data){try{area.value=JSON.parse(data.textContent||'""')}catch(e){area.value=''}} if(key)key.value=k;if(title)title.textContent=btn.dataset.label||'Archivo';});});
  document.getElementById('copyCodeBtn')?.addEventListener('click',function(){if(navigator.clipboard&&area)navigator.clipboard.writeText(area.value||'');});
})();
</script>
<?php Html::footer(); ?>
