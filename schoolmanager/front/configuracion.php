<?php
include('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');

if (!class_exists('Session') || !Session::getLoginUserID()) { Html::redirect($CFG_GLPI['root_doc'] . '/index.php'); }
if (function_exists('Session::checkRight')) {}
if (method_exists('Session', 'checkRight')) { Session::checkRight('config', UPDATE); }

// Process downloads before headers or output
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'download_template') {
        $tpl = __DIR__ . '/../locales/template.php';
        if (is_file($tpl)) {
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Description: File Transfer');
            header('Content-Type: text/x-php; charset=utf-8');
            header('Content-Disposition: attachment; filename="schoolmanager_template_en_GB.php"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tpl));
            readfile($tpl);
            exit;
        }
    }
    if ($_GET['action'] === 'download_locale') {
        $code = trim((string)($_GET['code'] ?? ''));
        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
            $file = __DIR__ . '/../locales/' . $code . '.php';
            if (is_file($file)) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Description: File Transfer');
                header('Content-Type: text/x-php; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $code . '.php"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            }
        }
    }
}

function smcfg_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function smcfg_tr(string $key, string $fallback): string { return plugin_schoolmanager_tr($key, $fallback); }
function smcfg_slug($v): string { $v = strtoupper(trim((string)$v)); $v = preg_replace('/[^A-Z0-9_-]+/', '-', $v); return trim($v, '-'); }
function smcfg_bool(string $key): bool { return !empty($_POST[$key]); }
function smcfg_token(): void { static $tok = null; if (method_exists('Session','getNewCSRFToken')) { if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . smcfg_h($tok) . '">'; } }
function smcfg_redirect(string $msg='saved'): void {
    global $CFG_GLPI;
    $tab = 'general';
    if (str_contains($msg, 'modules')) { $tab = 'modules'; }
    elseif (str_contains($msg, 'theme') || str_contains($msg, 'logo')) { $tab = 'visual'; }
    elseif (str_contains($msg, 'plan')) { $tab = 'plans'; }
    elseif (str_contains($msg, 'building') || str_contains($msg, 'floor') || str_contains($msg, 'room')) { $tab = 'structure'; }
    elseif (str_contains($msg, 'json')) { $tab = 'advanced'; }
    elseif (str_contains($msg, 'locale') || str_contains($msg, 'i18n') || str_contains($msg, 'lang')) { $tab = 'i18n'; }
    $extra = '';
    if (!empty($_GET['edit_locale']) && !str_contains($msg, 'edit_locale=')) {
        $extra .= '&edit_locale=' . rawurlencode((string)$_GET['edit_locale']);
    }
    Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/configuracion.php?' . $msg . '&tab=' . rawurlencode($tab) . $extra);
}
function smcfg_save(array $cfg, array &$errors): bool {
    $cfg['schema'] = 6;
    $cfg['setup_complete'] = true;
    $cfg['configured_at'] = date('Y-m-d H:i:s');
    if (!plugin_schoolmanager_save_config($cfg)) { $errors[] = smcfg_tr('save_error', 'No se pudo guardar. Revisa permisos de data/ y css/.'); return false; }
    return true;
}
function smcfg_find_building_index(array $cfg, string $code): int { foreach (($cfg['buildings'] ?? []) as $i=>$b) if (strtoupper((string)($b['code']??'')) === $code) return (int)$i; return -1; }
function smcfg_find_floor_index(array $b, string $code): int { foreach (($b['floors'] ?? []) as $i=>$f) if (strtoupper((string)($f['code']??'')) === $code) return (int)$i; return -1; }
function smcfg_location_link($id): string { global $CFG_GLPI; $id=(int)$id; return $id>0 ? (($CFG_GLPI['root_doc'] ?? '') . '/front/location.form.php?id=' . $id) : ''; }

function smcfg_parse_uploaded_locale(string $tmpPath, string $ext, ?string &$error = null): ?array {
    if (!is_file($tmpPath)) {
        $error = 'El archivo no existe.';
        return null;
    }
    $raw = @file_get_contents($tmpPath);
    if (!is_string($raw) || trim($raw) === '') {
        $error = 'El archivo está vacío.';
        return null;
    }

    if ($ext === 'json') {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $error = 'El archivo JSON no tiene un formato válido.';
            return null;
        }
        return $data;
    }

    if ($ext === 'php') {
        if (preg_match('/(system|exec|passthru|shell_exec|eval|popen|proc_open|base64_decode|assert)\s*\(/i', $raw)) {
            $error = 'Se detectaron funciones no permitidas en el archivo PHP.';
            return null;
        }
        $data = @include $tmpPath;
        if (!is_array($data)) {
            $error = 'El archivo PHP debe retornar un array asociativo con las claves de traducción.';
            return null;
        }
        return $data;
    }

    $error = 'Formato no soportado.';
    return null;
}

$cfg = plugin_schoolmanager_config(true);
$errors = [];
$info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_general') {
        $name = trim((string)($_POST['title'] ?? 'School Manager'));
        if ($name === '') { $name = 'School Manager'; }
        $menuTitle = trim((string)($_POST['menu_title'] ?? $name));
        if ($menuTitle === '') { $menuTitle = $name; }
        $cfg['brand']['title'] = $name;
        $cfg['brand']['menu_title'] = $menuTitle;
        $cfg['brand']['title_es'] = $name;
        $cfg['brand']['title_en'] = $name;
        $cfg['brand']['menu_title_es'] = $menuTitle;
        $cfg['brand']['menu_title_en'] = $menuTitle;
        $cfg['brand']['organization'] = trim((string)($_POST['organization'] ?? 'Educational center')) ?: 'Educational center';
        $cfg['brand']['show_logo'] = smcfg_bool('show_logo');
        $locale = (string)($_POST['locale'] ?? 'auto');
        $availableLocales = array_keys(plugin_schoolmanager_available_locales(true));
        $cfg['locale'] = (in_array($locale, $availableLocales, true) || $locale === 'auto') ? $locale : 'auto';
        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=general');
    }

    if ($action === 'save_theme') {
        $errors[] = smcfg_tr('theme_locked_message', 'El editor de temas estará disponible próximamente. Está en fase de pruebas y se mantiene bloqueado para la versión estable.');
    }

    if ($action === 'save_modules') {
        $keys = ['tickets','classrooms','plans','assets','stock','tic_panel','tic_assignment_rules','versions','file_editor'];
        $enabled = $_POST['features'] ?? [];
        if (!is_array($enabled)) { $enabled = []; }
        $enabled = array_map('strval', $enabled);
        foreach ($keys as $k) { $cfg['features'][$k] = in_array($k, $enabled, true); }
        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=modules');
    }

    if ($action === 'upload_logo') {
        if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)($_FILES['logo_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) { $errors[] = smcfg_tr('invalid_file','Archivo no válido.'); }
            else {
                $dir = plugin_schoolmanager_user_upload_dir(); if (!is_dir($dir)) @mkdir($dir, 0775, true); @chmod($dir, 0775);
                $name = 'logo-' . date('Ymd-His') . '-' . substr(sha1((string)microtime(true)), 0, 6) . '.' . $ext;
                if (@move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . '/' . $name)) {
                    @chmod($dir . '/' . $name, 0664);
                    $cfg['brand']['logo'] = $name;
                    $cfg['brand']['show_logo'] = true;
                    if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=logo');
                } else { $errors[] = smcfg_tr('upload_error','No se pudo subir el archivo. Revisa permisos de maps/uploads.'); }
            }
        } else { $errors[] = smcfg_tr('invalid_file','Selecciona un archivo válido.'); }
    }

    if ($action === 'reset_logo') {
        $cfg['brand']['logo'] = 'logo.png';
        $cfg['brand']['show_logo'] = true;
        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=logo_reset');
    }

    if ($action === 'upload_plan') {
        if (!empty($_FILES['plan_file']['tmp_name']) && is_uploaded_file($_FILES['plan_file']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)($_FILES['plan_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['html','htm','svg','png','jpg','jpeg','webp'], true)) { $errors[] = smcfg_tr('invalid_file','Archivo no válido.'); }
            else {
                $b = smcfg_slug($_POST['plan_building'] ?? plugin_schoolmanager_default_building_code());
                $f = smcfg_slug($_POST['plan_floor'] ?? plugin_schoolmanager_default_floor_code($b));
                $mode = ($_POST['plan_mode'] ?? 'normal') === 'select' ? 'select' : 'normal';
                $bi = smcfg_find_building_index($cfg, $b);
                if ($bi < 0) { $errors[] = smcfg_tr('building_not_found','Edificio no encontrado.'); }
                else {
                    $fi = smcfg_find_floor_index($cfg['buildings'][$bi], $f);
                    if ($fi < 0) { $errors[] = smcfg_tr('floor_not_found','Planta no encontrada. Créala primero.'); }
                    else {
                        $dir = plugin_schoolmanager_plan_root() . '/' . $b; if (!is_dir($dir)) @mkdir($dir, 0775, true); @chmod($dir, 0775);
                        $name = $b . '-' . $f . ($mode === 'select' ? '-select' : '') . '.' . $ext;
                        if (@move_uploaded_file($_FILES['plan_file']['tmp_name'], $dir . '/' . $name)) {
                            @chmod($dir . '/' . $name, 0664);
                            $cfg['buildings'][$bi]['floors'][$fi][$mode === 'select' ? 'select_plan' : 'plan'] = $b . '/' . $name;
                            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=plan');
                        } else { $errors[] = smcfg_tr('upload_error','No se pudo subir el archivo. Revisa permisos de maps/planos.'); }
                    }
                }
            }
        } else { $errors[] = smcfg_tr('invalid_file','Selecciona un archivo válido.'); }
    }

    if ($action === 'add_building') {
        $code = smcfg_slug($_POST['building_code'] ?? '');
        $nameEs = trim((string)($_POST['building_name_es'] ?? $code));
        $nameEn = trim((string)($_POST['building_name_en'] ?? $nameEs));
        if (!$code || !$nameEs) { $errors[] = smcfg_tr('code_required','Código y nombre obligatorios.'); }
        elseif (smcfg_find_building_index($cfg, $code) >= 0) { $errors[] = smcfg_tr('duplicate_code','Ese código ya existe.'); }
        else {
            $locId = smcfg_bool('create_glpi_location') ? plugin_schoolmanager_create_glpi_location($nameEs, 0) : 0;
            $cfg['buildings'][] = ['code'=>$code,'enabled'=>true,'name_es'=>$nameEs,'name_en'=>$nameEn,'glpi_location_id'=>$locId ?: null,'floors'=>[]];
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=building');
        }
    }

    if ($action === 'add_floor') {
        $bCode = smcfg_slug($_POST['floor_building'] ?? '');
        $fCode = smcfg_slug($_POST['floor_code'] ?? '');
        $labelEs = trim((string)($_POST['floor_label_es'] ?? $fCode));
        $labelEn = trim((string)($_POST['floor_label_en'] ?? $labelEs));
        $num = trim((string)($_POST['floor_number'] ?? ''));
        $bi = smcfg_find_building_index($cfg, $bCode);
        if ($bi < 0 || !$fCode || !$labelEs) { $errors[] = smcfg_tr('code_required','Selecciona edificio y escribe código/nombre.'); }
        elseif (smcfg_find_floor_index($cfg['buildings'][$bi], $fCode) >= 0) { $errors[] = smcfg_tr('duplicate_code','Ese código ya existe.'); }
        else {
            $parent = (int)($cfg['buildings'][$bi]['glpi_location_id'] ?? 0);
            $locId = smcfg_bool('create_glpi_location') ? plugin_schoolmanager_create_glpi_location($labelEs, $parent) : 0;
            $cfg['buildings'][$bi]['floors'][] = ['code'=>$fCode,'enabled'=>true,'label_es'=>$labelEs,'label_en'=>$labelEn,'number'=>$num,'glpi_location_id'=>$locId ?: null,'plan'=>'','select_plan'=>''];
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=floor');
        }
    }

    if ($action === 'add_room') {
        $bCode = smcfg_slug($_POST['room_building'] ?? '');
        $fCode = smcfg_slug($_POST['room_floor'] ?? '');
        $room = trim((string)($_POST['room'] ?? ''));
        $nameEs = trim((string)($_POST['room_name_es'] ?? $room));
        $nameEn = trim((string)($_POST['room_name_en'] ?? $nameEs));
        $bi = smcfg_find_building_index($cfg, $bCode);
        if ($bi < 0 || !$fCode || !$room || !$nameEs) { $errors[] = smcfg_tr('room_required','Selecciona edificio, planta y escribe aula.'); }
        else {
            $fi = smcfg_find_floor_index($cfg['buildings'][$bi], $fCode);
            $parent = $fi >= 0 ? (int)($cfg['buildings'][$bi]['floors'][$fi]['glpi_location_id'] ?? 0) : 0;
            if (!$parent) { $parent = (int)($cfg['buildings'][$bi]['glpi_location_id'] ?? 0); }
            $locId = smcfg_bool('create_glpi_location') ? plugin_schoolmanager_create_glpi_location($nameEs, $parent) : 0;
            $cfg['rooms'][] = ['building'=>$bCode,'room'=>$room,'code'=>trim((string)($_POST['room_code'] ?? ($bCode.'-'.$room))) ?: ($bCode.'-'.$room),'floor'=>$fCode,'name_es'=>$nameEs,'name_en'=>$nameEn,'glpi_location_id'=>$locId ?: ((int)($_POST['room_glpi_id'] ?? 0) ?: null)];
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=room');
        }
    }

    if ($action === 'toggle_building') {
        $code = smcfg_slug($_POST['code'] ?? ''); $bi = smcfg_find_building_index($cfg, $code);
        if ($bi >= 0) { $cfg['buildings'][$bi]['enabled'] = empty($cfg['buildings'][$bi]['enabled']); if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=building_toggle'); }
    }
    if ($action === 'toggle_floor') {
        $bCode = smcfg_slug($_POST['building'] ?? ''); $fCode = smcfg_slug($_POST['floor'] ?? ''); $bi = smcfg_find_building_index($cfg,$bCode);
        if ($bi >= 0) { $fi = smcfg_find_floor_index($cfg['buildings'][$bi],$fCode); if ($fi >= 0) { $cfg['buildings'][$bi]['floors'][$fi]['enabled'] = empty($cfg['buildings'][$bi]['floors'][$fi]['enabled']); if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=floor_toggle'); } }
    }
    if ($action === 'delete_room') {
        $idx = (int)($_POST['idx'] ?? -1);
        if (isset($cfg['rooms'][$idx])) { array_splice($cfg['rooms'], $idx, 1); if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=room_deleted'); }
    }

    if ($action === 'save_json') {
        $decoded = json_decode((string)($_POST['structure_json'] ?? ''), true);
        if (!is_array($decoded)) { $errors[] = smcfg_tr('invalid_json','JSON no válido.'); }
        else {
            if (isset($decoded['buildings']) && is_array($decoded['buildings'])) $cfg['buildings'] = $decoded['buildings'];
            if (isset($decoded['rooms']) && is_array($decoded['rooms'])) $cfg['rooms'] = $decoded['rooms'];
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=json');
        }
    }

    // Language Center Actions
    if ($action === 'upload_locale') {
        if (!empty($_FILES['locale_file']['tmp_name']) && is_uploaded_file($_FILES['locale_file']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)($_FILES['locale_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['php', 'json'], true)) {
                $errors[] = smcfg_tr('invalid_file', 'Archivo no válido. Solo se admiten archivos .php o .json.');
            } else {
                $code = trim((string)($_POST['locale_code'] ?? ''));
                if ($code === '') {
                    $base = pathinfo((string)($_FILES['locale_file']['name'] ?? ''), PATHINFO_FILENAME);
                    if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $base)) {
                        $code = $base;
                    }
                }
                if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
                    $errors[] = smcfg_tr('invalid_locale_code', 'Código de idioma no válido. Debe tener el formato xx_XX (ej: pt_PT, ca_ES) o xx (ej: pt, ca).');
                } else {
                    $name = trim((string)($_POST['locale_name'] ?? ''));
                    $err = null;
                    $parsed = smcfg_parse_uploaded_locale($_FILES['locale_file']['tmp_name'], $ext, $err);
                    if ($parsed === null) {
                        $errors[] = smcfg_tr('upload_error', 'Error al procesar el archivo: ') . smcfg_h($err);
                    } else {
                        if (empty($name) && !empty($parsed['_language_name'])) {
                            $name = (string)$parsed['_language_name'];
                        }
                        if (plugin_schoolmanager_save_locale_file($code, $parsed, $name, $err)) {
                            smcfg_redirect('saved=locale_uploaded&edit_locale=' . rawurlencode($code));
                        } else {
                            $errors[] = smcfg_tr('save_error', 'No se pudo guardar el archivo de idioma: ') . smcfg_h($err);
                        }
                    }
                }
            }
        } else {
            $errors[] = smcfg_tr('invalid_file', 'Selecciona un archivo válido.');
        }
    }

    if ($action === 'save_locale_editor') {
        $code = trim((string)($_POST['locale_code'] ?? ''));
        $name = trim((string)($_POST['locale_name'] ?? ''));
        $trans = $_POST['trans'] ?? [];
        if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
            $errors[] = smcfg_tr('invalid_locale_code', 'Código de idioma no válido. Debe tener el formato xx_XX o xx.');
        } elseif (!is_array($trans)) {
            $errors[] = smcfg_tr('invalid_data', 'Datos de traducción no válidos.');
        } else {
            $err = null;
            if (plugin_schoolmanager_save_locale_file($code, $trans, $name, $err)) {
                smcfg_redirect('saved=locale_saved&edit_locale=' . rawurlencode($code));
            } else {
                $errors[] = smcfg_tr('save_error', 'No se pudo guardar el idioma: ') . smcfg_h($err);
            }
        }
    }

    if ($action === 'delete_custom_locale') {
        $code = trim((string)($_POST['code'] ?? ''));
        $err = null;
        if (plugin_schoolmanager_delete_custom_locale($code, $err)) {
            smcfg_redirect('saved=locale_deleted');
        } else {
            $errors[] = smcfg_tr('delete_error', 'No se pudo eliminar el idioma: ') . smcfg_h($err);
        }
    }

    $cfg = plugin_schoolmanager_config(true);
}

$features = $cfg['features'] ?? [];
$theme = $cfg['theme'] ?? [];
$brand = $cfg['brand'] ?? [];
$currentLocale = plugin_schoolmanager_locale();
$isEnglish = $currentLocale === 'en_GB';
$credits = $cfg['credits'] ?? [];
$logoUrl = plugin_schoolmanager_logo_url();
$structure = json_encode(['buildings'=>$cfg['buildings']??[],'rooms'=>$cfg['rooms']??[]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
$dataFile = plugin_schoolmanager_config_file();
$dataDir = dirname($dataFile);
$uploadDir = plugin_schoolmanager_user_upload_dir();
$planDir = plugin_schoolmanager_plan_root();
$canWrite = is_writable($dataDir) && (!is_file($dataFile) || is_writable($dataFile));
$canUpload = is_writable($uploadDir) || (!is_dir($uploadDir) && is_writable(dirname($uploadDir)));
$canPlans = is_writable($planDir) || (!is_dir($planDir) && is_writable(dirname($planDir)));
$buildings = $cfg['buildings'] ?? [];
$rooms = $cfg['rooms'] ?? [];
$planRows = [];
foreach (plugin_schoolmanager_buildings(false) as $b) {
    foreach (($b['floors'] ?? []) as $f) {
        $planRows[] = ['building'=>$b['code']??'', 'floor'=>$f['code']??'', 'label'=>plugin_schoolmanager_label($f,'label',$f['code']??''), 'plan'=>$f['plan']??'', 'select_plan'=>$f['select_plan']??''];
    }
}
$moduleLabels = [
    'tickets' => smcfg_tr('feature_tickets','Incidencias guiadas'),
    'classrooms' => smcfg_tr('feature_classrooms','Aulas y ubicaciones'),
    'plans' => smcfg_tr('feature_plans','Planos interactivos'),
    'assets' => smcfg_tr('feature_assets','Activos'),
    'stock' => smcfg_tr('feature_stock','Stock'),
    'tic_panel' => smcfg_tr('feature_tic_panel','Panel TIC'),
    'tic_assignment_rules' => smcfg_tr('feature_tic_rules','Reglas TIC'),
    'versions' => smcfg_tr('feature_versions','Versiones'),
    'file_editor' => smcfg_tr('feature_file_editor','Editor de archivos'),
];

// Locales data for UI
$availableLocalesList = plugin_schoolmanager_available_locales(true);
$builtinLocalesList = plugin_schoolmanager_builtin_locales();

// Canonical base dictionary for editor (English en_GB)
$canonicalEn = (include __DIR__ . '/../locales/en_GB.php') ?: [];
$canonicalKeys = array_keys(array_filter($canonicalEn, static fn($k) => $k !== '_language_name' && !str_starts_with((string)$k, '_'), ARRAY_FILTER_USE_KEY));

// Selected locale for editor
$editLocale = trim((string)($_GET['edit_locale'] ?? 'en_GB'));
if ($editLocale !== 'new' && !isset($availableLocalesList[$editLocale])) {
    $editLocale = 'en_GB';
}

$editDict = [];
$editLangName = '';
if ($editLocale !== 'new') {
    $ePhp = __DIR__ . '/../locales/' . $editLocale . '.php';
    if (is_file($ePhp)) {
        $tmp = include $ePhp;
        $editDict = is_array($tmp) ? $tmp : [];
    } else {
        $eJson = __DIR__ . '/../locales/' . $editLocale . '.json';
        if (is_file($eJson)) {
            $tmp = json_decode(@file_get_contents($eJson), true);
            $editDict = is_array($tmp) ? $tmp : [];
        }
    }
    $editLangName = $editDict['_language_name'] ?? ($availableLocalesList[$editLocale]['name'] ?? $editLocale);
}

Html::header(smcfg_tr('settings_title','Configuración de School Manager'), $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
?>
<style>
.smset{--navy:var(--sm-primary,#07384d);--teal:var(--sm-secondary,#075d61);--red:var(--sm-accent,#b6252b);--soft:var(--sm-soft,#f4fafc);--line:#d7e6ec;--muted:#5f7180;max-width:1500px;margin:0 auto;padding:18px;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#07384d}.smset *{box-sizing:border-box}.sm-hero{background:linear-gradient(135deg,#fff,var(--soft));border:1px solid var(--line);border-radius:28px;padding:26px;display:flex;gap:22px;align-items:center;justify-content:space-between;box-shadow:0 20px 50px rgba(7,56,77,.08);margin-bottom:18px}.sm-hero h1{font-size:clamp(32px,4vw,64px);line-height:.98;margin:0;color:#07384d;font-weight:950}.sm-hero p{font-weight:850;color:var(--muted);font-size:16px}.sm-logo-preview{width:120px;height:120px;object-fit:contain;border-radius:24px;background:#fff;border:1px solid var(--line);padding:10px}.sm-kicker{text-transform:uppercase;letter-spacing:.16em;color:#b6252b;font-weight:950}.sm-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}.sm-tab{border:1px solid var(--line);background:#fff;color:#07384d;padding:12px 16px;border-radius:16px;font-weight:950;cursor:pointer;transition:.18s}.sm-tab:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(7,56,77,.12)}.sm-tab.active{background:#07384d;color:#fff;border-color:#07384d}.sm-panel{display:none}.sm-panel.active{display:block}.sm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.sm-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.sm-card{background:#fff;border:1px solid var(--line);border-radius:24px;padding:20px;box-shadow:0 14px 36px rgba(7,56,77,.06);margin-bottom:16px}.sm-card h2{margin:0 0 14px;font-size:24px;color:#07384d}.sm-card h3{margin:16px 0 10px;font-size:18px}.sm-field{display:block;margin:0 0 12px}.sm-field span,.sm-label{display:block;font-weight:950;margin:0 0 6px;color:#07384d}.sm-input,.sm-select,.sm-textarea{width:100%;border:1px solid #d9e5ea;border-radius:14px;padding:12px 14px;font-weight:800;background:#fff;color:#243244}.sm-textarea{min-height:320px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px}.sm-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sm-row.three{grid-template-columns:repeat(3,minmax(0,1fr))}.sm-help{font-weight:800;color:#687887;margin-top:6px}.sm-btn{border:0;background:#07384d;color:#fff;border-radius:15px;padding:12px 16px;font-weight:950;cursor:pointer;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:.18s}.sm-btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(7,56,77,.2);color:#fff}.sm-btn.red{background:#b6252b}.sm-btn.light{background:#fff;color:#07384d;border:1px solid var(--line)}.sm-btn.small{font-size:13px;padding:8px 10px;border-radius:12px}.sm-alert{padding:14px 16px;border-radius:16px;margin:12px 0;font-weight:950}.sm-alert.ok{background:#e9fbf1;color:#14783b;border:1px solid #b5eac8}.sm-alert.error{background:#fff0f0;color:#b6252b;border:1px solid #ffb4b4}.sm-switches{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sm-switch{display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:18px;background:#fbfdff;font-weight:950}.sm-switch input{width:20px;height:20px}.sm-switch small{color:#687887}.sm-table{width:100%;border-collapse:separate;border-spacing:0 8px}.sm-table th{text-align:left;color:#607080;font-size:12px;text-transform:uppercase;letter-spacing:.05em}.sm-table td{background:#fbfdff;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:12px;font-weight:850}.sm-table td:first-child{border-left:1px solid var(--line);border-radius:14px 0 0 14px}.sm-table td:last-child{border-right:1px solid var(--line);border-radius:0 14px 14px 0}.sm-pill{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:#f8fbfd;border-radius:999px;padding:5px 9px;font-weight:950}.sm-ok{color:#168346}.sm-bad{color:#b6252b}.sm-current-logo{display:flex;gap:18px;align-items:center}.sm-structure{display:grid;grid-template-columns:1fr 1fr;gap:16px}.sm-building-card{border:1px solid var(--line);border-radius:18px;padding:14px;margin-bottom:12px;background:#fbfdff}.sm-building-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.sm-actions{display:flex;gap:8px;flex-wrap:wrap}.sm-floor-list{display:grid;gap:8px;margin-top:10px}.sm-floor{border:1px dashed #cfe0e7;border-radius:14px;padding:10px;background:#fff}.sm-muted{color:#687887;font-weight:800}@media(max-width:1050px){.sm-grid,.sm-grid.three,.sm-structure{grid-template-columns:1fr}.sm-switches{grid-template-columns:1fr 1fr}.sm-hero{align-items:flex-start;flex-direction:column}}@media(max-width:650px){.sm-row,.sm-row.three,.sm-switches{grid-template-columns:1fr}.smset{padding:10px}.sm-card{padding:14px}.sm-hero{padding:18px}.sm-logo-preview{width:86px;height:86px}}
.smset{background:linear-gradient(180deg,#f7fbfd 0,#eef6f9 100%);border-radius:32px}.sm-panel{animation:smFade .18s ease}.sm-card{overflow:hidden}.sm-card h2{display:flex;align-items:center;gap:10px}.sm-grid,.sm-grid.three,.sm-structure{align-items:start}.sm-field{min-width:0}.sm-input:focus,.sm-select:focus,.sm-textarea:focus{outline:3px solid color-mix(in srgb,var(--teal) 18%,transparent);border-color:var(--teal);box-shadow:0 0 0 4px rgba(7,93,97,.08)}.sm-switch{transition:.16s ease;min-height:76px}.sm-switch:hover{transform:translateY(-2px);box-shadow:0 14px 26px rgba(7,56,77,.08);border-color:var(--teal)}.sm-switch input:checked+span{color:var(--navy)}.sm-current-logo{padding:16px;border:1px dashed var(--line);border-radius:22px;background:#fbfdff}.sm-logo-preview{box-shadow:0 12px 28px rgba(7,56,77,.08)}.sm-hero{position:relative;overflow:hidden}.sm-hero:after{content:"";position:absolute;right:-80px;top:-80px;width:220px;height:220px;border-radius:50%;background:color-mix(in srgb,var(--teal) 12%,transparent);pointer-events:none}.sm-tab.active{background:linear-gradient(135deg,var(--navy),var(--teal));box-shadow:0 14px 30px rgba(7,56,77,.16)}.sm-building-card,.sm-floor,.sm-table td{transition:.14s ease}.sm-building-card:hover,.sm-floor:hover,.sm-table tr:hover td{background:#fff;box-shadow:0 12px 24px rgba(7,56,77,.07)}.sm-actions-sticky{position:sticky;bottom:12px;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);border:1px solid var(--line);border-radius:20px;padding:12px 18px;z-index:4;box-shadow:0 14px 36px rgba(7,56,77,.15);display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px}@keyframes smFade{from{opacity:.5;transform:translateY(4px)}to{opacity:1;transform:none}}@media(max-width:850px){.sm-table,.sm-table thead,.sm-table tbody,.sm-table tr,.sm-table td{display:block}.sm-table thead{display:none}.sm-table tr{border:1px solid var(--line);border-radius:18px;margin-bottom:12px;background:#fbfdff}.sm-table td{border:0!important;border-radius:0!important}.sm-table td:before{content:attr(data-label);display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:950;margin-bottom:3px}}
.sm-info-callout{display:flex;gap:12px;align-items:flex-start;border:1px solid color-mix(in srgb,var(--teal) 24%,#d7e6ec);background:linear-gradient(135deg,#f7fcff,#eef8f8);border-radius:18px;padding:13px 15px;margin:8px 0 14px;font-weight:850;color:var(--navy)}
.sm-info-callout i{font-style:normal;display:inline-grid;place-items:center;min-width:28px;height:28px;border-radius:999px;background:color-mix(in srgb,var(--teal) 14%,#fff);color:var(--teal);font-weight:950}
.sm-lock-card{position:relative;border:1px solid #ffd48a!important;background:linear-gradient(135deg,#fffaf0,#f7fbfd)!important;overflow:hidden}.sm-lock-card:before{content:"";position:absolute;right:-70px;top:-90px;width:230px;height:230px;border-radius:50%;background:rgba(245,158,11,.13);pointer-events:none}.sm-lock-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;position:relative;z-index:1}.sm-lock-badge{display:inline-flex;align-items:center;gap:8px;border:1px solid #f2c36b;background:#fff6df;color:#8a5800;border-radius:999px;padding:9px 13px;font-weight:950;white-space:nowrap}.sm-lock-button{border:0;background:#8a5800;color:#fff;border-radius:15px;padding:12px 16px;font-weight:950;display:inline-flex;align-items:center;gap:8px;opacity:.96;cursor:not-allowed}.sm-lock-muted{opacity:.48;filter:saturate(.4);pointer-events:none}.sm-coming-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}.sm-coming-item{border:1px solid var(--line);background:#fff;border-radius:16px;padding:12px;font-weight:900;color:var(--navy)}
.sm-editor-table{width:100%;border-collapse:collapse;margin-top:14px}
.sm-editor-table th{background:var(--soft);color:var(--navy);font-weight:950;padding:12px;text-align:left;border-bottom:2px solid var(--line);font-size:13px;text-transform:uppercase}
.sm-editor-table td{padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:middle;font-weight:800}
.sm-editor-table tr:hover td{background:#f8fcfe}
.sm-badge-builtin{background:#e8f4fd;color:#0b5cab;padding:4px 8px;border-radius:8px;font-size:12px;font-weight:950;border:1px solid #c2e0f8}
.sm-badge-custom{background:#fef3e7;color:#b45309;padding:4px 8px;border-radius:8px;font-size:12px;font-weight:950;border:1px solid #fed7aa}
.sm-code-snippet{background:#1e293b;color:#f8fafc;padding:14px;border-radius:14px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;line-height:1.5;overflow-x:auto;margin:10px 0}
</style>

<div class="smset schoolmanager-configured">
  <section class="sm-hero">
    <div>
      <div class="sm-kicker">GLPI School Manager</div>
      <h1><?= smcfg_h(plugin_schoolmanager_app_name() . ' settings') ?></h1>
      <p><?= smcfg_h(smcfg_tr('settings_subtitle','Configure branding, modules, classrooms, plans, language and visual theme from GLPI.')) ?></p>
    </div>
    <div class="sm-hero-side"><img class="sm-logo-preview" src="<?= smcfg_h($logoUrl) ?>" alt="Logo" onerror="this.onerror=null;this.src='<?= smcfg_h(plugin_schoolmanager_logo_data_uri()) ?>';"></div>
  </section>

  <?php if(isset($_GET['saved'])): ?>
    <div class="sm-alert ok">
      <?php if($_GET['saved'] === 'locale_uploaded'): ?>
        <?= $isEnglish ? 'Language file uploaded and registered successfully.' : 'Archivo de idioma subido y registrado correctamente.' ?>
      <?php elseif($_GET['saved'] === 'locale_saved'): ?>
        <?= $isEnglish ? 'Language translations saved successfully.' : 'Traducciones guardadas correctamente en el archivo de idioma.' ?>
      <?php elseif($_GET['saved'] === 'locale_deleted'): ?>
        <?= $isEnglish ? 'Custom language removed successfully.' : 'Idioma personalizado eliminado correctamente.' ?>
      <?php else: ?>
        <?= smcfg_h(smcfg_tr('configuration_saved','Configuración guardada correctamente.')) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if(!$canWrite): ?><div class="sm-alert error"><?= smcfg_h(smcfg_tr('write_warning','El plugin no puede escribir la configuración. Revisa permisos de:')) ?> <code><?= smcfg_h($dataDir) ?></code></div><?php endif; ?>
  <?php if(!$canUpload): ?><div class="sm-alert error">No se puede escribir en <code><?= smcfg_h($uploadDir) ?></code>. Los logos no se podrán guardar.</div><?php endif; ?>
  <?php if(!$canPlans): ?><div class="sm-alert error">No se puede escribir en <code><?= smcfg_h($planDir) ?></code>. Los planos no se podrán guardar.</div><?php endif; ?>
  <?php foreach($errors as $e): ?><div class="sm-alert error"><?= smcfg_h($e) ?></div><?php endforeach; ?>

  <div class="sm-tabs">
    <button class="sm-tab active" data-tab="general" type="button">General</button>
    <button class="sm-tab" data-tab="i18n" type="button">🌐 <?= $isEnglish ? 'Languages' : 'Idiomas' ?></button>
    <button class="sm-tab" data-tab="modules" type="button">Módulos</button>
    <button class="sm-tab" data-tab="visual" type="button">Logo y estilo</button>
    <button class="sm-tab" data-tab="plans" type="button">Planos</button>
    <button class="sm-tab" data-tab="structure" type="button">Edificios y aulas</button>
    <button class="sm-tab" data-tab="advanced" type="button">Avanzado</button>
  </div>

  <!-- TAB: GENERAL -->
  <section class="sm-panel active" id="tab-general">
    <form method="post" class="sm-card"><?php smcfg_token(); ?><input type="hidden" name="action" value="save_general">
      <h2>Configuración principal</h2>
      <div class="sm-row">
        <label class="sm-field"><span><?= $isEnglish ? 'Plugin name' : 'Nombre del plugin' ?></span><input class="sm-input" name="title" value="<?= smcfg_h($brand['title'] ?? ($brand['title_es'] ?? 'School Manager')) ?>"><small class="sm-help"><?= $isEnglish ? 'Default and recommended: School Manager. This name is used in every language.' : 'Valor recomendado: School Manager. Es el nombre oficial del plugin y se usa igual en todos los idiomas.' ?></small></label>
        <label class="sm-field"><span><?= $isEnglish ? 'Left menu label' : 'Nombre en el menú lateral' ?></span><input class="sm-input" name="menu_title" value="<?= smcfg_h($brand['menu_title'] ?? ($brand['menu_title_es'] ?? ($brand['title'] ?? 'School Manager'))) ?>"><small class="sm-help"><?= $isEnglish ? 'Name shown in the GLPI left sidebar.' : 'Nombre que aparecerá en el menú lateral izquierdo de GLPI.' ?></small></label>
      </div>
      <div class="sm-info-callout"><i>i</i><div><?= $isEnglish ? 'To apply a left menu label change in GLPI, save the settings and then deactivate and activate the plugin again from Setup > Plugins. This is a GLPI menu cache behaviour.' : 'Para aplicar un cambio en el nombre del menú lateral de GLPI, guarda los ajustes y después desactiva y vuelve a activar el plugin desde Configuración > Plugins. Es por la caché interna del menú de GLPI.' ?></div></div>
      <div class="sm-row">
        <label class="sm-field"><span><?= $isEnglish ? 'Organization' : 'Organización' ?></span><input class="sm-input" name="organization" value="<?= smcfg_h($brand['organization'] ?? 'Educational center') ?>"></label>
        <label class="sm-field">
          <span><?= $isEnglish ? 'Plugin language' : 'Idioma del plugin' ?></span>
          <select class="sm-select" name="locale">
            <option value="auto" <?= ($cfg['locale']??'auto')==='auto'?'selected':'' ?>><?= $isEnglish ? 'Automatic (detect from GLPI session)' : 'Automático (detectar según sesión GLPI)' ?></option>
            <?php foreach($availableLocalesList as $lcode => $linfo): ?>
              <option value="<?= smcfg_h($lcode) ?>" <?= ($cfg['locale']??'')===$lcode?'selected':'' ?>><?= smcfg_h($linfo['name']) ?> (<?= smcfg_h($lcode) ?>)<?= $linfo['is_custom'] ? ' [' . ($isEnglish ? 'Custom' : 'Personalizado') . ']' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;gap:8px">
            <small class="sm-help"><?= $isEnglish ? 'Built-in: English, Spanish, French, German, Italian.' : 'Nativos: Inglés, Español, Francés, Alemán e Italiano.' ?></small>
            <button type="button" class="sm-btn small light" onclick="window.smActivateTab('i18n')">🌐 <?= $isEnglish ? 'Add or edit languages' : '¿No sale tu idioma? Añadir o editar' ?></button>
          </div>
        </label>
      </div>
      <label class="sm-switch"><input type="checkbox" name="show_logo" value="1" <?= !empty($brand['show_logo'])?'checked':'' ?>> <span><?= $isEnglish ? 'Show logo in headers' : 'Mostrar logo en cabeceras' ?><br><small><?= $isEnglish ? 'Disable it to hide the plugin logo on main pages.' : 'Si lo desactivas se oculta el logo del plugin en las páginas principales.' ?></small></span></label>
      <button class="sm-btn" type="submit"><?= $isEnglish ? 'Save general settings' : 'Guardar ajustes generales' ?></button>
    </form>
  </section>

  <!-- TAB: LANGUAGES & VISUAL I18N EDITOR -->
  <section class="sm-panel" id="tab-i18n">
    <div class="sm-card">
      <h2>🌐 <?= $isEnglish ? 'Language Center & Visual Translation Editor' : 'Centro de Idiomas y Editor Visual de Traducciones' ?></h2>
      <p class="sm-help"><?= $isEnglish ? 'GLPI School Manager now natively supports English (primary base), Spanish, French, German, and Italian. You can download the English translation template, translate it manually or using an AI model (ChatGPT, Claude, Gemini), upload it here, or edit any language directly from this screen.' : 'GLPI School Manager cuenta con soporte oficial para Inglés (base principal), Español, Francés, Alemán e Italiano. Puedes descargar la plantilla oficial en inglés, traducirla tú mismo o con una Inteligencia Artificial (ChatGPT, Claude, Gemini), subirla aquí, o editar cualquier idioma en directo desde esta misma pantalla.' ?></p>
    </div>

    <div class="sm-grid">
      <!-- Card: Download Template -->
      <div class="sm-card">
        <h2>📥 <?= $isEnglish ? 'Download English Template' : 'Descargar plantilla en inglés' ?></h2>
        <p class="sm-help"><?= $isEnglish ? 'Official base template in English (en_GB.php) with all 183 plugin translation keys and structured prompt instructions for AI.' : 'Plantilla base oficial en inglés (en_GB.php) con las 183 claves del plugin e instrucciones optimizadas para traducir con IA.' ?></p>
        <a class="sm-btn" href="<?= smcfg_h($_SERVER['PHP_SELF']) ?>?action=download_template" style="margin-top:10px">⬇ <?= $isEnglish ? 'Download template.php' : 'Descargar template.php' ?></a>
        
        <h3 style="margin-top:20px"><?= $isEnglish ? '🤖 Tip: Translate in seconds with AI' : '🤖 Consejo: Traduce en segundos con IA' ?></h3>
        <p class="sm-help"><?= $isEnglish ? 'Open ChatGPT, Claude or Gemini and send:' : 'Abre ChatGPT, Claude o Gemini y pega este prompt junto con la plantilla:' ?></p>
        <div class="sm-code-snippet">Translate this PHP associative array of GLPI School Manager to [YOUR LANGUAGE] (e.g. Portuguese pt_PT). Keep all keys intact, only translate values, and set '_language_name' => '[Language Name]'. Return valid PHP.</div>
      </div>

      <!-- Card: Upload Language File -->
      <div class="sm-card">
        <h2>📤 <?= $isEnglish ? 'Upload Language File' : 'Subir archivo de idioma' ?></h2>
        <form method="post" enctype="multipart/form-data">
          <?php smcfg_token(); ?>
          <input type="hidden" name="action" value="upload_locale">
          <div class="sm-row">
            <label class="sm-field">
              <span><?= $isEnglish ? 'Locale code (e.g. pt_PT, ca_ES)' : 'Código de idioma (ej: pt_PT, ca_ES)' ?></span>
              <input class="sm-input" name="locale_code" placeholder="pt_PT" required pattern="[a-z]{2}(_[A-Z]{2})?">
            </label>
            <label class="sm-field">
              <span><?= $isEnglish ? 'Language name' : 'Nombre del idioma' ?></span>
              <input class="sm-input" name="locale_name" placeholder="Português">
            </label>
          </div>
          <label class="sm-field">
            <span><?= $isEnglish ? 'Translation file (.php or .json)' : 'Archivo de traducción (.php o .json)' ?></span>
            <input class="sm-input" type="file" name="locale_file" accept=".php,.json" required>
          </label>
          <p class="sm-help"><?= $isEnglish ? 'Uploaded files are automatically validated and registered into the plugin locales directory.' : 'El archivo se valida y se registra automáticamente en la carpeta de locales del plugin.' ?></p>
          <button class="sm-btn" type="submit">📤 <?= $isEnglish ? 'Upload & Register Language' : 'Subir y registrar idioma' ?></button>
        </form>
      </div>
    </div>

    <!-- Card: In-Browser Visual Editor -->
    <div class="sm-card" id="sm-language-editor-box">
      <h2>✏️ <?= $isEnglish ? 'Visual Language Editor' : 'Editor visual de idioma' ?></h2>
      <p class="sm-help"><?= $isEnglish ? 'Select a language to edit or create a brand new one online. Changes are saved directly to the locale file.' : 'Selecciona el idioma a editar o crea uno nuevo directamente desde el navegador. Los cambios se guardan al instante en el archivo de idioma.' ?></p>

      <div class="sm-row" style="margin-top:14px;align-items:flex-end">
        <label class="sm-field" style="margin-bottom:0">
          <span><?= $isEnglish ? 'Language to edit' : 'Idioma para editar' ?></span>
          <select class="sm-select" id="smEditLocaleSelect" onchange="window.smSwitchEditLocale(this.value)">
            <?php foreach($availableLocalesList as $lcode => $linfo): ?>
              <option value="<?= smcfg_h($lcode) ?>" <?= $editLocale===$lcode?'selected':'' ?>><?= smcfg_h($linfo['name']) ?> (<?= smcfg_h($lcode) ?>) <?= $linfo['is_builtin'] ? '· Built-in' : '· Custom' ?></option>
            <?php endforeach; ?>
            <option value="new" <?= $editLocale==='new'?'selected':'' ?>>➕ <?= $isEnglish ? 'Create new language...' : 'Crear nuevo idioma...' ?></option>
          </select>
        </label>
        <label class="sm-field" style="margin-bottom:0">
          <span>🔍 <?= $isEnglish ? 'Filter strings' : 'Buscar frases' ?></span>
          <input class="sm-input" id="smLocaleSearch" placeholder="<?= $isEnglish ? 'Search key, English text or translation...' : 'Buscar clave, texto en inglés o traducción...' ?>" oninput="window.smFilterLocaleRows(this.value)">
        </label>
      </div>

      <form method="post" style="margin-top:18px">
        <?php smcfg_token(); ?>
        <input type="hidden" name="action" value="save_locale_editor">

        <div class="sm-row" style="margin-bottom:14px;background:var(--soft);padding:14px;border-radius:16px;border:1px solid var(--line)">
          <label class="sm-field" style="margin-bottom:0">
            <span><?= $isEnglish ? 'Locale Code (e.g. pt_PT, ca_ES, it_IT)' : 'Código de idioma (ej: pt_PT, ca_ES, it_IT)' ?></span>
            <input class="sm-input" name="locale_code" value="<?= smcfg_h($editLocale==='new' ? '' : $editLocale) ?>" required pattern="[a-z]{2}(_[A-Z]{2})?" placeholder="pt_PT" <?= ($editLocale!=='new' && in_array($editLocale, $builtinLocalesList, true)) ? 'readonly' : '' ?>>
          </label>
          <label class="sm-field" style="margin-bottom:0">
            <span><?= $isEnglish ? 'Language display name (e.g. Português, Català)' : 'Nombre visible del idioma (ej: Português, Català)' ?></span>
            <input class="sm-input" name="locale_name" value="<?= smcfg_h($editLangName) ?>" required placeholder="Português">
          </label>
        </div>

        <div style="overflow-x:auto;max-height:550px;border:1px solid var(--line);border-radius:16px">
          <table class="sm-editor-table">
            <thead>
              <tr>
                <th style="width:240px"><?= $isEnglish ? 'Key identifier' : 'Identificador / Clave' ?></th>
                <th style="width:40%"><?= $isEnglish ? 'English Reference (Primary Base)' : 'Referencia en inglés (Base principal)' ?></th>
                <th><?= $isEnglish ? 'Translation' : 'Traducción' ?></th>
              </tr>
            </thead>
            <tbody id="smLocaleTbody">
              <?php foreach($canonicalKeys as $k):
                $enVal = (string)($canonicalEn[$k] ?? '');
                $curVal = (string)($editDict[$k] ?? ($editLocale==='en_GB' ? $enVal : ''));
              ?>
                <tr class="sm-locale-row" data-key="<?= smcfg_h(strtolower($k)) ?>" data-en="<?= smcfg_h(strtolower($enVal)) ?>" data-val="<?= smcfg_h(strtolower($curVal)) ?>">
                  <td><code><?= smcfg_h($k) ?></code></td>
                  <td><span style="color:var(--muted)"><?= smcfg_h($enVal) ?></span></td>
                  <td><input class="sm-input" style="padding:8px 10px;font-size:13px" name="trans[<?= smcfg_h($k) ?>]" value="<?= smcfg_h($curVal) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="sm-actions-sticky">
          <div style="font-weight:900;color:var(--navy)">
            <span><?= count($canonicalKeys) ?> <?= $isEnglish ? 'keys loaded' : 'claves cargadas' ?></span>
            <span class="sm-pill" id="smMatchCount" style="margin-left:8px"><?= count($canonicalKeys) ?> <?= $isEnglish ? 'visible' : 'visibles' ?></span>
          </div>
          <button class="sm-btn" type="submit">💾 <?= $isEnglish ? 'Save Language Changes' : 'Guardar cambios en el idioma' ?></button>
        </div>
      </form>
    </div>

    <!-- Card: Installed Languages Table -->
    <div class="sm-card">
      <h2>📋 <?= $isEnglish ? 'Installed & Registered Languages' : 'Idiomas instalados y registrados' ?></h2>
      <table class="sm-table">
        <thead>
          <tr>
            <th><?= $isEnglish ? 'Code' : 'Código' ?></th>
            <th><?= $isEnglish ? 'Name' : 'Nombre' ?></th>
            <th><?= $isEnglish ? 'Type' : 'Tipo' ?></th>
            <th><?= $isEnglish ? 'Translated Keys' : 'Claves traducidas' ?></th>
            <th><?= $isEnglish ? 'Format' : 'Formato' ?></th>
            <th><?= $isEnglish ? 'Actions' : 'Acciones' ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($availableLocalesList as $code => $info): ?>
            <tr>
              <td><b><?= smcfg_h($code) ?></b></td>
              <td><?= smcfg_h($info['name']) ?></td>
              <td>
                <?php if($info['is_builtin']): ?>
                  <span class="sm-badge-builtin">⭐ <?= $isEnglish ? 'Official / Built-in' : 'Oficial / Nativo' ?></span>
                <?php else: ?>
                  <span class="sm-badge-custom">🛠 <?= $isEnglish ? 'Custom' : 'Personalizado' ?></span>
                <?php endif; ?>
              </td>
              <td><span class="sm-pill"><?= (int)$info['keys_count'] ?> / <?= count($canonicalKeys) ?></span></td>
              <td><code>.<?= smcfg_h($info['format']) ?></code></td>
              <td>
                <div class="sm-actions">
                  <a class="sm-btn small light" href="<?= smcfg_h($_SERVER['PHP_SELF']) ?>?tab=i18n&edit_locale=<?= rawurlencode($code) ?>">✏️ <?= $isEnglish ? 'Edit' : 'Editar' ?></a>
                  <a class="sm-btn small light" href="<?= smcfg_h($_SERVER['PHP_SELF']) ?>?action=download_locale&code=<?= rawurlencode($code) ?>">⬇ <?= $isEnglish ? 'Download' : 'Descargar' ?></a>
                  <?php if($info['is_custom']): ?>
                    <form method="post" onsubmit="return confirm('<?= $isEnglish ? 'Delete this custom language file?' : '¿Eliminar este archivo de idioma personalizado?' ?>');" style="display:inline">
                      <?php smcfg_token(); ?>
                      <input type="hidden" name="action" value="delete_custom_locale">
                      <input type="hidden" name="code" value="<?= smcfg_h($code) ?>">
                      <button class="sm-btn small red" type="submit">🗑 <?= $isEnglish ? 'Delete' : 'Eliminar' ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- TAB: MODULES -->
  <section class="sm-panel" id="tab-modules">
    <form method="post" class="sm-card"><?php smcfg_token(); ?><input type="hidden" name="action" value="save_modules">
      <h2>Módulos activos</h2><p class="sm-help">Desactiva lo que no quieras usar. Al guardar, también se bloquea el acceso directo a esas páginas.</p>
      <div class="sm-switches"><?php foreach($moduleLabels as $k=>$label): ?><label class="sm-switch"><input type="checkbox" name="features[]" value="<?= smcfg_h($k) ?>" <?= !empty($features[$k])?'checked':'' ?>> <span><?= smcfg_h($label) ?><br><small data-on="<?= smcfg_h(smcfg_tr('active','Activo')) ?>" data-off="<?= smcfg_h(smcfg_tr('disabled','Desactivado')) ?>"><?= !empty($features[$k])?smcfg_h(smcfg_tr('active','Activo')):smcfg_h(smcfg_tr('disabled','Desactivado')) ?></small></span></label><?php endforeach; ?></div>
      <button class="sm-btn" type="submit">Guardar módulos</button>
    </form>
  </section>

  <!-- TAB: VISUAL -->
  <section class="sm-panel" id="tab-visual">
    <div class="sm-grid">
      <div class="sm-card"><h2>Logo actual</h2><div class="sm-current-logo"><img class="sm-logo-preview" src="<?= smcfg_h($logoUrl) ?>" alt="Logo" onerror="this.onerror=null;this.src='<?= smcfg_h(plugin_schoolmanager_logo_data_uri()) ?>';"><div><p><b><?= smcfg_h($brand['logo'] ?? 'logo.png') ?></b></p><p class="sm-help">Este es el logo que se está usando ahora mismo en las cabeceras. El logo por defecto ya es el icono de School Manager.</p></div></div></div>
      <div class="sm-card"><h2>Subir logo</h2><form method="post" enctype="multipart/form-data"><?php smcfg_token(); ?><input type="hidden" name="action" value="upload_logo"><input class="sm-input" type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" required><p class="sm-help">Formatos: PNG, JPG, WEBP o SVG. Se guarda en maps/uploads y se actualiza con caché automática.</p><button class="sm-btn" type="submit">Subir logo</button></form><form method="post" style="margin-top:10px"><?php smcfg_token(); ?><input type="hidden" name="action" value="reset_logo"><button class="sm-btn light" type="submit">Volver al logo por defecto</button></form></div>
    </div>
    <div class="sm-card sm-theme-card sm-lock-card" id="smThemeForm">
      <div class="sm-lock-head">
        <div>
          <h2><?= $isEnglish ? 'Visual themes' : 'Temas visuales' ?></h2>
          <span class="sm-lock-badge">🔒 <?= $isEnglish ? 'Available soon · testing phase' : 'Disponible próximamente · en fase de pruebas' ?></span>
          <p class="sm-help" style="margin-top:12px"><?= $isEnglish ? 'The theme editor is temporarily locked in this stable release because it is still being tested. Branding, logo, modules, buildings, classrooms, plans and languages remain fully configurable.' : 'El editor de temas queda bloqueado temporalmente en esta versión estable porque sigue en fase de pruebas. La marca, logo, módulos, edificios, aulas, planos e idiomas siguen siendo totalmente configurables.' ?></p>
        </div>
        <button class="sm-lock-button" type="button" disabled>🔒 <?= $isEnglish ? 'Locked' : 'Bloqueado' ?></button>
      </div>
      <div class="sm-coming-list">
        <div class="sm-coming-item"><?= $isEnglish ? 'Full light/dark themes' : 'Temas claros y oscuros completos' ?></div>
        <div class="sm-coming-item"><?= $isEnglish ? 'Safer global stylesheet switching' : 'Cambio seguro de estilos globales' ?></div>
        <div class="sm-coming-item"><?= $isEnglish ? 'Live preview before saving' : 'Vista previa antes de guardar' ?></div>
      </div>
      <div class="sm-lock-muted" aria-hidden="true">
        <div class="sm-palette-grid" style="margin-top:18px">
          <div class="sm-palette-card active"><span>Classic teal / red</span><span class="sm-swatches"><i style="background:#07384D"></i><i style="background:#075D61"></i><i style="background:#B6252B"></i><i style="background:#F4FAFC"></i></span></div>
          <div class="sm-palette-card"><span>Ocean blue</span><span class="sm-swatches"><i style="background:#0B3A66"></i><i style="background:#0E7490"></i><i style="background:#2563EB"></i><i style="background:#EAF4FF"></i></span></div>
          <div class="sm-palette-card"><span>Midnight dark</span><span class="sm-swatches"><i style="background:#06101D"></i><i style="background:#2DD4BF"></i><i style="background:#F59E0B"></i><i style="background:#0B1120"></i></span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- TAB: PLANS -->
  <section class="sm-panel" id="tab-plans">
    <div class="sm-grid">
      <div class="sm-card"><h2>Subir plano</h2><form method="post" enctype="multipart/form-data"><?php smcfg_token(); ?><input type="hidden" name="action" value="upload_plan"><div class="sm-row"><label class="sm-field"><span>Edificio</span><select class="sm-select" name="plan_building" id="planBuilding"><?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?></select></label><label class="sm-field"><span>Planta</span><select class="sm-select" name="plan_floor" id="planFloor"></select></label></div><label class="sm-field"><span>Tipo de plano</span><select class="sm-select" name="plan_mode"><option value="normal">Plano normal</option><option value="select">Plano para seleccionar aulas</option></select></label><input class="sm-input" type="file" name="plan_file" accept=".html,.htm,.svg,.png,.jpg,.jpeg,.webp" required><p class="sm-help">Soporta HTML, SVG, PNG, JPG/JPEG y WEBP.</p><button class="sm-btn" type="submit">Subir plano</button></form></div>
      <div class="sm-card"><h2>Planos actuales</h2><table class="sm-table"><thead><tr><th>Edificio</th><th>Planta</th><th>Normal</th><th>Selección</th></tr></thead><tbody><?php foreach($planRows as $r): ?><tr><td><?= smcfg_h($r['building']) ?></td><td><?= smcfg_h($r['label']) ?> <span class="sm-pill"><?= smcfg_h($r['floor']) ?></span></td><td><?= $r['plan'] ? smcfg_h($r['plan']) : '<span class="sm-bad">Sin plano</span>' ?></td><td><?= $r['select_plan'] ? smcfg_h($r['select_plan']) : '<span class="sm-bad">Sin plano</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </section>

  <!-- TAB: STRUCTURE -->
  <section class="sm-panel" id="tab-structure">
    <div class="sm-card"><h2>Edificios, plantas y aulas</h2><p class="sm-help">Aquí no solo se guarda en el plugin: si marcas “crear también en GLPI”, el plugin crea ubicaciones nativas en GLPI y guarda sus IDs para enlazar aulas y activos.</p></div>
    <div class="sm-grid three">
      <form class="sm-card" method="post"><?php smcfg_token(); ?><input type="hidden" name="action" value="add_building"><h2>Añadir edificio</h2><label class="sm-field"><span>Código</span><input class="sm-input" name="building_code" placeholder="ED1" required></label><label class="sm-field"><span>Nombre ES</span><input class="sm-input" name="building_name_es" placeholder="Edificio 1" required></label><label class="sm-field"><span>Nombre EN</span><input class="sm-input" name="building_name_en" placeholder="Building 1"></label><label class="sm-switch"><input type="checkbox" name="create_glpi_location" value="1" checked><span>Crear también en GLPI<br><small>Crea una ubicación raíz para este edificio.</small></span></label><button class="sm-btn" type="submit">Añadir edificio</button></form>
      <form class="sm-card" method="post"><?php smcfg_token(); ?><input type="hidden" name="action" value="add_floor"><h2>Añadir planta</h2><label class="sm-field"><span>Edificio</span><select class="sm-select" name="floor_building"><?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?></select></label><div class="sm-row"><label class="sm-field"><span>Código</span><input class="sm-input" name="floor_code" placeholder="P0" required></label><label class="sm-field"><span>Número</span><input class="sm-input" name="floor_number" placeholder="0"></label></div><label class="sm-field"><span>Nombre ES</span><input class="sm-input" name="floor_label_es" placeholder="Planta baja" required></label><label class="sm-field"><span>Nombre EN</span><input class="sm-input" name="floor_label_en" placeholder="Ground floor"></label><label class="sm-switch"><input type="checkbox" name="create_glpi_location" value="1" checked><span>Crear también en GLPI<br><small>Crea la planta dentro del edificio.</small></span></label><button class="sm-btn" type="submit">Añadir planta</button></form>
      <form class="sm-card" method="post"><?php smcfg_token(); ?><input type="hidden" name="action" value="add_room"><h2>Añadir aula</h2><div class="sm-row"><label class="sm-field"><span>Edificio</span><select class="sm-select" name="room_building" id="roomBuilding"><?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?></select></label><label class="sm-field"><span>Planta</span><select class="sm-select" name="room_floor" id="roomFloor"></select></label></div><div class="sm-row"><label class="sm-field"><span>Aula</span><input class="sm-input" name="room" placeholder="101" required></label><label class="sm-field"><span>Código</span><input class="sm-input" name="room_code" placeholder="ED1-101"></label></div><label class="sm-field"><span>Nombre ES</span><input class="sm-input" name="room_name_es" placeholder="Aula 101" required></label><label class="sm-field"><span>Nombre EN</span><input class="sm-input" name="room_name_en" placeholder="Room 101"></label><label class="sm-field"><span>ID ubicación GLPI existente</span><input class="sm-input" name="room_glpi_id" placeholder="Opcional"></label><label class="sm-switch"><input type="checkbox" name="create_glpi_location" value="1" checked><span>Crear también en GLPI<br><small>Crea el aula dentro de su planta.</small></span></label><button class="sm-btn" type="submit">Añadir aula</button></form>
    </div>
    <div class="sm-structure">
      <div class="sm-card"><h2>Estructura actual</h2><?php foreach($buildings as $b): ?><div class="sm-building-card"><div class="sm-building-head"><div><b><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></b> <span class="sm-pill"><?= smcfg_h($b['code']??'') ?></span> <?= empty($b['enabled'])?'<span class="sm-bad">Desactivado</span>':'<span class="sm-ok">Activo</span>' ?><br><span class="sm-muted">GLPI ID: <?= smcfg_h($b['glpi_location_id'] ?? '-') ?></span></div><form method="post"><?php smcfg_token(); ?><input type="hidden" name="action" value="toggle_building"><input type="hidden" name="code" value="<?= smcfg_h($b['code']??'') ?>"><button class="sm-btn small light" type="submit"><?= empty($b['enabled'])?'Activar':'Desactivar' ?></button></form></div><div class="sm-floor-list"><?php foreach(($b['floors']??[]) as $f): ?><div class="sm-floor"><div class="sm-building-head"><div><?= smcfg_h(plugin_schoolmanager_label($f,'label',$f['code']??'')) ?> <span class="sm-pill"><?= smcfg_h($f['code']??'') ?></span> <?= empty($f['enabled'])?'<span class="sm-bad">Desactivada</span>':'<span class="sm-ok">Activa</span>' ?><br><span class="sm-muted">GLPI ID: <?= smcfg_h($f['glpi_location_id'] ?? '-') ?></span></div><form method="post"><?php smcfg_token(); ?><input type="hidden" name="action" value="toggle_floor"><input type="hidden" name="building" value="<?= smcfg_h($b['code']??'') ?>"><input type="hidden" name="floor" value="<?= smcfg_h($f['code']??'') ?>"><button class="sm-btn small light" type="submit"><?= empty($f['enabled'])?'Activar':'Desactivar' ?></button></form></div></div><?php endforeach; ?></div></div><?php endforeach; ?></div>
      <div class="sm-card"><h2>Aulas actuales</h2><table class="sm-table"><thead><tr><th>Aula</th><th>Edificio</th><th>Planta</th><th>GLPI</th><th></th></tr></thead><tbody><?php foreach($rooms as $i=>$r): $lid=(int)($r['glpi_location_id'] ?? 0); ?><tr><td><?= smcfg_h(plugin_schoolmanager_label($r,'name',$r['room']??'')) ?><br><span class="sm-muted"><?= smcfg_h($r['code']??'') ?></span></td><td><?= smcfg_h($r['building']??'') ?></td><td><?= smcfg_h($r['floor']??'') ?></td><td><?php if($lid): ?><a class="sm-pill" href="<?= smcfg_h(smcfg_location_link($lid)) ?>">#<?= $lid ?></a><?php else: ?><span class="sm-bad">Sin ID</span><?php endif; ?></td><td><form method="post" onsubmit="return confirm('¿Quitar aula del plugin? No borra la ubicación GLPI.');"><?php smcfg_token(); ?><input type="hidden" name="action" value="delete_room"><input type="hidden" name="idx" value="<?= (int)$i ?>"><button class="sm-btn small red" type="submit">Quitar</button></form></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </section>

  <!-- TAB: ADVANCED -->
  <section class="sm-panel" id="tab-advanced">
    <form method="post" class="sm-card"><?php smcfg_token(); ?><input type="hidden" name="action" value="save_json"><h2>JSON avanzado</h2><p class="sm-help">Solo para importar o ajustar estructura completa. Las opciones anteriores son más seguras.</p><textarea class="sm-textarea" name="structure_json"><?= smcfg_h($structure) ?></textarea><button class="sm-btn" type="submit">Guardar JSON</button></form>
    <div class="sm-card"><h2>Créditos y licencia</h2><p><b>Pablo Burgos y Alejandro Galán</b></p><p class="sm-help">Licencia del plugin: GPLv3+ (GNU GPL v3 o posterior). Los créditos se mantienen en la documentación y en esta página.</p></div>
  </section>
</div>

<script>
(function(){
  document.querySelectorAll('.sm-table').forEach(tbl=>{ const hs=[...tbl.querySelectorAll('thead th')].map(th=>th.textContent.trim()); tbl.querySelectorAll('tbody tr').forEach(tr=>[...tr.children].forEach((td,i)=>td.setAttribute('data-label',hs[i]||''))); });
  document.querySelectorAll('form').forEach(f=>{ f.addEventListener('submit',()=>{ const b=f.querySelector('button[type=submit]'); if(b){ b.dataset.oldText=b.textContent; b.textContent='Guardando...'; b.setAttribute('aria-disabled','true'); } }); });
  document.querySelectorAll('.sm-switch input[type=checkbox]').forEach(cb=>{ const update=()=>{ const sm=cb.closest('.sm-switch')?.querySelector('small[data-on]'); if(sm) sm.textContent=cb.checked?sm.dataset.on:sm.dataset.off; }; cb.addEventListener('change',update); update(); });
  const buildings = <?= json_encode($buildings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  function floorsOf(code){ const b = buildings.find(x => String(x.code) === String(code)); return b && Array.isArray(b.floors) ? b.floors : []; }
  function fillFloors(buildSel, floorSel){ if(!buildSel||!floorSel) return; const fs=floorsOf(buildSel.value); floorSel.innerHTML=''; fs.forEach(f=>{ const o=document.createElement('option'); o.value=f.code||''; o.textContent=(f.label_es||f.label_en||f.code||'')+' ('+(f.code||'')+')'; floorSel.appendChild(o); }); }
  
  window.smActivateTab = function(tab){
    const btn=document.querySelector('.sm-tab[data-tab="'+tab+'"]');
    if(!btn) return;
    document.querySelectorAll('.sm-tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.sm-panel').forEach(x=>x.classList.remove('active'));
    btn.classList.add('active');
    const p=document.getElementById('tab-'+tab);
    if(p) p.classList.add('active');
  };

  document.querySelectorAll('.sm-tab').forEach(btn=>btn.addEventListener('click',()=>{
    window.smActivateTab(btn.dataset.tab);
    history.replaceState(null,'',location.pathname+'?tab='+encodeURIComponent(btn.dataset.tab));
  }));

  const params=new URLSearchParams(location.search);
  window.smActivateTab(params.get('tab') || (location.hash?location.hash.replace('#',''):'general'));

  const rb=document.getElementById('roomBuilding'), rf=document.getElementById('roomFloor'), pb=document.getElementById('planBuilding'), pf=document.getElementById('planFloor');
  if(rb&&rf){ fillFloors(rb,rf); rb.addEventListener('change',()=>fillFloors(rb,rf)); }
  if(pb&&pf){ fillFloors(pb,pf); pb.addEventListener('change',()=>fillFloors(pb,pf)); }

  // Language editor helpers
  window.smSwitchEditLocale = function(code){
    location.href = location.pathname + '?tab=i18n&edit_locale=' + encodeURIComponent(code) + '#sm-language-editor-box';
  };

  window.smFilterLocaleRows = function(q){
    q = (q || '').trim().toLowerCase();
    const rows = document.querySelectorAll('.sm-locale-row');
    let count = 0;
    rows.forEach(r => {
      const k = r.dataset.key || '', en = r.dataset.en || '', val = (r.querySelector('input')?.value || '').toLowerCase();
      const match = !q || k.includes(q) || en.includes(q) || val.includes(q);
      r.style.display = match ? '' : 'none';
      if(match) count++;
    });
    const mc = document.getElementById('smMatchCount');
    if(mc) mc.textContent = count + ' <?= $isEnglish ? 'visible' : 'visibles' ?>';
  };
})();
</script>
<?php Html::footer(); ?>
