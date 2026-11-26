<?php
if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}
include('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

if (!class_exists('Session') || !Session::getLoginUserID()) { Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/index.php'); }
if (!function_exists('plugin_schoolmanager_can_configure') || !plugin_schoolmanager_can_configure()) {
    plugin_schoolmanager_access_denied_page(smcfg_tr('access_denied_config', 'Configuración de School Manager restringida'), smcfg_tr('access_denied_config_desc', 'Solo los administradores TIC y perfiles autorizados pueden acceder a los ajustes de School Manager.'));
}

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
function smcfg_token(): void {
    if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    }
}
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
    Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/settings.php?' . $msg . '&tab=' . rawurlencode($tab) . $extra);
}
function smcfg_save(array $cfg, array &$errors): bool {
    $cfg['schema'] = 6;
    $cfg['setup_complete'] = true;
    $cfg['configured_at'] = date('Y-m-d H:i:s');
    if (isset($_SESSION['glpimenu'])) {
        unset($_SESSION['glpimenu']);
    }
    if (!plugin_schoolmanager_save_config($cfg)) {
        $errors[] = smcfg_tr('save_error', 'Could not save configuration. Check permissions.');
        return false;
    }
    return true;
}
function smcfg_find_building_index(array $cfg, string $code): int { foreach (($cfg['buildings'] ?? []) as $i=>$b) if (strtoupper((string)($b['code']??'')) === $code) return (int)$i; return -1; }
function smcfg_find_floor_index(array $b, string $code): int { foreach (($b['floors'] ?? []) as $i=>$f) if (strtoupper((string)($f['code']??'')) === $code) return (int)$i; return -1; }
function smcfg_location_link($id): string { global $CFG_GLPI; $id=(int)$id; return $id>0 ? (($CFG_GLPI['root_doc'] ?? '') . '/front/location.form.php?id=' . $id) : ''; }

function smcfg_parse_uploaded_locale(string $tmpPath, string $ext, ?string &$error = null): ?array {
    if (!is_file($tmpPath)) {
        $error = smcfg_tr('file_not_found', 'File does not exist.');
        return null;
    }
    $raw = @file_get_contents($tmpPath);
    if (!is_string($raw) || trim($raw) === '') {
        $error = smcfg_tr('file_empty', 'File is empty.');
        return null;
    }

    if ($ext === 'json') {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $error = smcfg_tr('invalid_json', 'JSON file is not valid.');
            return null;
        }
        return $data;
    }

    if ($ext === 'php') {
        if (preg_match('/(system|exec|passthru|shell_exec|eval|popen|proc_open|base64_decode|assert)\s*\(/i', $raw)) {
            $error = smcfg_tr('disallowed_functions', 'Disallowed PHP functions detected.');
            return null;
        }
        $data = @include $tmpPath;
        if (!is_array($data)) {
            $error = smcfg_tr('invalid_php_array', 'PHP file must return an associative array.');
            return null;
        }
        return $data;
    }

    $error = smcfg_tr('unsupported_format', 'Unsupported format.');
    return null;
}

$cfg = plugin_schoolmanager_config(true);
$isSetupWizard = false;
$errors = [];
$info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'complete_wizard') {
        if (smcfg_save($cfg, $errors)) {
            plugin_schoolmanager_write_theme_css($cfg);
            plugin_schoolmanager_write_runtime_config_js($cfg);
            Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/dashboard.php?wizard_completed=1');
        }
    }

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
        if (smcfg_save($cfg, $errors)) {
            plugin_schoolmanager_write_theme_css($cfg);
            plugin_schoolmanager_write_runtime_config_js($cfg);
            if (!empty($_POST['enter_plugin_after_save'])) {
                Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/dashboard.php?wizard_completed=1');
            }
            smcfg_redirect('saved=general');
        }
    }

    if ($action === 'save_theme') {
        $palette = trim((string)($_POST['theme_palette'] ?? 'teal-red'));
        $validPresets = plugin_schoolmanager_theme_presets();
        if (!isset($validPresets[$palette])) {
            $palette = 'teal-red';
        }
        $cfg['theme']['palette'] = $palette;
        foreach (['primary','secondary','accent','soft','background','card','text','muted','border','dark'] as $k) {
            if (isset($validPresets[$palette][$k])) {
                $cfg['theme'][$k] = $validPresets[$palette][$k];
            }
        }
        $density = trim((string)($_POST['theme_density'] ?? 'comfortable'));
        if (in_array($density, ['compact', 'comfortable', 'large'], true)) {
            $cfg['theme']['density'] = $density;
        }
        $radius = trim((string)($_POST['theme_radius'] ?? '18px'));
        if (preg_match('/^[0-9]+px$/', $radius)) {
            $cfg['theme']['radius'] = $radius;
        }
        if (smcfg_save($cfg, $errors)) {
            plugin_schoolmanager_write_theme_css($cfg);
            plugin_schoolmanager_write_runtime_config_js($cfg);
            if (!empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'    => true,
                    'palette'    => $palette,
                    'csrf_token' => (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) ? Session::getNewCSRFToken() : '',
                    'message'    => smcfg_tr('theme_auto_saved', 'Tema aplicado y guardado')
                ]);
                exit;
            }
            smcfg_redirect('saved=theme_saved&tab=visual');
        } else {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save theme']);
                exit;
            }
            $errors[] = smcfg_tr('theme_save_error', 'Failed to save theme settings.');
        }
    }

    if ($action === 'save_modules') {
        $keys = ['tickets','classrooms','plans','assets','stock','tic_panel','tic_assignment_rules','versions','file_editor'];
        $enabled = $_POST['features'] ?? [];
        if (!is_array($enabled)) { $enabled = []; }
        $enabled = array_map('strval', $enabled);
        foreach ($keys as $k) { $cfg['features'][$k] = in_array($k, $enabled, true); }
        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=modules');
    }

    if ($action === 'toggle_plans') {
        $newPlans = !empty($_POST['plans_enabled']);
        $cfg['features']['plans'] = $newPlans;
        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=plans_toggled');
    }

    if ($action === 'upload_logo') {
        if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
            $ext = strtolower(pathinfo((string)($_FILES['logo_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp','svg'], true)) { $errors[] = smcfg_tr('invalid_file','Invalid file format.'); }
            else {
                $dir = plugin_schoolmanager_user_upload_dir(); if (!is_dir($dir)) @mkdir($dir, 0775, true); @chmod($dir, 0775);
                $name = 'logo-' . date('Ymd-His') . '-' . substr(sha1((string)microtime(true)), 0, 6) . '.' . $ext;
                $dest = $dir . '/' . $name;
                if (@move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                    @chmod($dest, 0664);
                    $cfg['brand']['logo'] = $name;
                    if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=logo');
                } else { $errors[] = smcfg_tr('upload_error','Error saving uploaded file.'); }
            }
        } else { $errors[] = smcfg_tr('no_file_uploaded','No file uploaded.'); }
    }

    if ($action === 'reset_logo') {
        $cfg['brand']['logo'] = 'logo.png';
        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=logo_reset');
    }

    if ($action === 'upload_locale') {
        $code = trim((string)($_POST['locale_code'] ?? ''));
        $name = trim((string)($_POST['locale_name'] ?? ''));
        if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
            $errors[] = smcfg_tr('invalid_locale_code', 'Invalid locale code format. Expected e.g. pt_PT or ca_ES.');
        } elseif (empty($_FILES['locale_file']['tmp_name']) || !is_uploaded_file($_FILES['locale_file']['tmp_name'])) {
            $errors[] = smcfg_tr('no_file_uploaded', 'No file was uploaded.');
        } else {
            $ext = strtolower(pathinfo((string)($_FILES['locale_file']['name'] ?? ''), PATHINFO_EXTENSION));
            $parsed = smcfg_parse_uploaded_locale($_FILES['locale_file']['tmp_name'], $ext, $parseErr);
            if ($parsed === null) {
                $errors[] = $parseErr ?: smcfg_tr('parse_error', 'Error parsing locale file.');
            } else {
                $saveErr = null;
                if (plugin_schoolmanager_save_locale_file($code, $parsed, $name ?: null, $saveErr)) {
                    smcfg_redirect('saved=locale_uploaded&edit_locale=' . rawurlencode($code));
                } else {
                    $errors[] = $saveErr ?: smcfg_tr('locale_save_error', 'Error saving locale file.');
                }
            }
        }
    }

    if ($action === 'save_locale_editor') {
        $code = trim((string)($_POST['locale_code'] ?? ''));
        $name = trim((string)($_POST['locale_name'] ?? ''));
        $trans = $_POST['trans'] ?? [];
        if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $code)) {
            $errors[] = smcfg_tr('invalid_locale_code', 'Invalid locale code format.');
        } elseif (!is_array($trans) || empty($trans)) {
            $errors[] = smcfg_tr('no_translations_provided', 'No translation values provided.');
        } else {
            $saveErr = null;
            if (plugin_schoolmanager_save_locale_file($code, $trans, $name ?: null, $saveErr)) {
                smcfg_redirect('saved=locale_saved&edit_locale=' . rawurlencode($code));
            } else {
                $errors[] = $saveErr ?: smcfg_tr('locale_save_error', 'Error saving translations.');
            }
        }
    }

    if ($action === 'delete_custom_locale') {
        $code = trim((string)($_POST['locale_code'] ?? ''));
        $delErr = null;
        if (plugin_schoolmanager_delete_custom_locale($code, $delErr)) {
            smcfg_redirect('saved=locale_deleted');
        } else {
            $errors[] = $delErr ?: smcfg_tr('locale_delete_error', 'Error removing custom locale.');
        }
    }

    if ($action === 'upload_plan') {
        $building = strtoupper(trim((string)($_POST['plan_building'] ?? '')));
        $floor = strtoupper(trim((string)($_POST['plan_floor'] ?? '')));
        $mode = ($_POST['plan_mode'] ?? 'normal') === 'select' ? 'select' : 'normal';
        $bIdx = smcfg_find_building_index($cfg, $building);
        if ($bIdx === -1) { $errors[] = smcfg_tr('building_not_found', 'Building not found.'); }
        else {
            $fIdx = smcfg_find_floor_index($cfg['buildings'][$bIdx], $floor);
            if ($fIdx === -1) { $errors[] = smcfg_tr('floor_not_found', 'Floor not found.'); }
            elseif (empty($_FILES['plan_file']['tmp_name']) || !is_uploaded_file($_FILES['plan_file']['tmp_name'])) { $errors[] = smcfg_tr('no_file_uploaded', 'No file uploaded.'); }
            else {
                $ext = strtolower(pathinfo((string)($_FILES['plan_file']['name'] ?? ''), PATHINFO_EXTENSION));
                $allowed = ['html','htm','svg','png','jpg','jpeg','webp'];
                if (!in_array($ext, $allowed, true)) { $errors[] = smcfg_tr('unsupported_plan_format', 'Unsupported plan format.'); }
                else {
                    $dir = plugin_schoolmanager_plan_root(); if (!is_dir($dir)) @mkdir($dir, 0775, true); @chmod($dir, 0775);
                    $prefix = $mode === 'select' ? 'select-' : '';
                    $name = $prefix . strtolower($building) . '-' . strtolower($floor) . '-' . date('Ymd-His') . '.' . $ext;
                    $dest = $dir . '/' . $name;
                    if (@move_uploaded_file($_FILES['plan_file']['tmp_name'], $dest)) {
                        @chmod($dest, 0664);
                        if ($mode === 'select') { $cfg['buildings'][$bIdx]['floors'][$fIdx]['select_plan'] = $name; }
                        else { $cfg['buildings'][$bIdx]['floors'][$fIdx]['plan'] = $name; }
                        if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=plan');
                    } else { $errors[] = smcfg_tr('save_plan_error', 'Error saving plan file.'); }
                }
            }
        }
    }

    if ($action === 'add_building') {
        $code = smcfg_slug($_POST['building_code'] ?? '');
        $nameEs = trim((string)($_POST['building_name_es'] ?? ''));
        $nameEn = trim((string)($_POST['building_name_en'] ?? $nameEs));
        if ($code === '' || $nameEs === '') { $errors[] = smcfg_tr('missing_building_code', 'Building code and name are required.'); }
        elseif (smcfg_find_building_index($cfg, $code) !== -1) { $errors[] = smcfg_tr('building_exists', 'Building code already exists.'); }
        else {
            $glpiLoc = !empty($_POST['create_glpi_location']) ? plugin_schoolmanager_create_glpi_location($nameEs, 0) : 0;
            $cfg['buildings'][] = [
                'code' => $code, 'name' => $nameEs, 'name_es' => $nameEs, 'name_en' => $nameEn,
                'enabled' => true, 'glpi_location_id' => $glpiLoc ?: null,
                'floors' => [
                    ['code'=>'G', 'number'=>0, 'label'=>$nameEs . ' - Ground', 'label_es'=>$nameEs . ' - Planta baja', 'label_en'=>$nameEn . ' - Ground floor', 'plan'=>'', 'select_plan'=>'', 'enabled'=>true, 'glpi_location_id'=>null]
                ]
            ];
            if ($glpiLoc && !empty($_POST['create_glpi_location'])) {
                $floc = plugin_schoolmanager_create_glpi_location($nameEs . ' - Planta baja', $glpiLoc);
                if ($floc) { $cfg['buildings'][array_key_last($cfg['buildings'])]['floors'][0]['glpi_location_id'] = $floc; }
            }
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=building');
        }
    }

    if ($action === 'add_floor') {
        $bCode = strtoupper(trim((string)($_POST['floor_building'] ?? '')));
        $bIdx = smcfg_find_building_index($cfg, $bCode);
        $code = smcfg_slug($_POST['floor_code'] ?? '');
        $labelEs = trim((string)($_POST['floor_label_es'] ?? ''));
        $labelEn = trim((string)($_POST['floor_label_en'] ?? $labelEs));
        $num = (int)($_POST['floor_number'] ?? 0);
        if ($bIdx === -1) { $errors[] = smcfg_tr('building_not_found', 'Building not found.'); }
        elseif ($code === '' || $labelEs === '') { $errors[] = smcfg_tr('missing_floor_fields', 'Floor code and label are required.'); }
        elseif (smcfg_find_floor_index($cfg['buildings'][$bIdx], $code) !== -1) { $errors[] = smcfg_tr('floor_exists', 'Floor code already exists in this building.'); }
        else {
            $parent = (int)($cfg['buildings'][$bIdx]['glpi_location_id'] ?? 0);
            $glpiLoc = (!empty($_POST['create_glpi_location']) && $parent > 0) ? plugin_schoolmanager_create_glpi_location($labelEs, $parent) : 0;
            $cfg['buildings'][$bIdx]['floors'][] = [
                'code' => $code, 'number' => $num, 'label' => $labelEs, 'label_es' => $labelEs, 'label_en' => $labelEn,
                'plan' => '', 'select_plan' => '', 'enabled' => true, 'glpi_location_id' => $glpiLoc ?: null
            ];
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=floor');
        }
    }

    if ($action === 'add_room') {
        $bCode = strtoupper(trim((string)($_POST['room_building'] ?? '')));
        $fCode = strtoupper(trim((string)($_POST['room_floor'] ?? '')));
        $room = trim((string)($_POST['room'] ?? ''));
        $code = smcfg_slug($_POST['room_code'] ?? $room);
        $nameEs = trim((string)($_POST['room_name_es'] ?? $room));
        $nameEn = trim((string)($_POST['room_name_en'] ?? $nameEs));
        $glpiId = (int)($_POST['room_glpi_id'] ?? 0);
        $bIdx = smcfg_find_building_index($cfg, $bCode);
        if ($bIdx === -1) { $errors[] = smcfg_tr('building_not_found', 'Building not found.'); }
        elseif ($room === '') { $errors[] = smcfg_tr('missing_room_name', 'Classroom name is required.'); }
        else {
            $fIdx = smcfg_find_floor_index($cfg['buildings'][$bIdx], $fCode);
            if ($fIdx === -1) { $errors[] = smcfg_tr('floor_not_found', 'Floor not found.'); }
            else {
                if ($glpiId <= 0 && !empty($_POST['create_glpi_location'])) {
                    $parent = (int)($cfg['buildings'][$bIdx]['floors'][$fIdx]['glpi_location_id'] ?? $cfg['buildings'][$bIdx]['glpi_location_id'] ?? 0);
                    $glpiId = plugin_schoolmanager_create_glpi_location($nameEs, $parent);
                }
                $cfg['rooms'][] = [
                    'building' => $bCode, 'floor' => $fCode, 'room' => $room, 'code' => $code ?: $room,
                    'name' => $nameEs, 'name_es' => $nameEs, 'name_en' => $nameEn,
                    'glpi_location_id' => $glpiId > 0 ? $glpiId : null
                ];
                if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=room');
            }
        }
    }

    if ($action === 'delete_room') {
        $idx = (int)($_POST['idx'] ?? -1);
        if (isset($cfg['rooms'][$idx])) {
            array_splice($cfg['rooms'], $idx, 1);
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=room_deleted');
        }
    }

    if ($action === 'toggle_building') {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $bIdx = smcfg_find_building_index($cfg, $code);
        if ($bIdx !== -1) {
            $cfg['buildings'][$bIdx]['enabled'] = empty($cfg['buildings'][$bIdx]['enabled']);
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=building_toggled');
        }
    }

    if ($action === 'toggle_floor') {
        $bCode = strtoupper(trim((string)($_POST['building'] ?? '')));
        $fCode = strtoupper(trim((string)($_POST['floor'] ?? '')));
        $bIdx = smcfg_find_building_index($cfg, $bCode);
        if ($bIdx !== -1) {
            $fIdx = smcfg_find_floor_index($cfg['buildings'][$bIdx], $fCode);
            if ($fIdx !== -1) {
                $cfg['buildings'][$bIdx]['floors'][$fIdx]['enabled'] = empty($cfg['buildings'][$bIdx]['floors'][$fIdx]['enabled']);
                if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=floor_toggled');
            }
        }
    }

    if ($action === 'save_json') {
        $raw = trim((string)($_POST['structure_json'] ?? ''));
        $data = json_decode($raw, true);
        if (!is_array($data)) { $errors[] = smcfg_tr('invalid_json', 'Invalid JSON syntax.'); }
        else {
            if (isset($data['buildings']) && is_array($data['buildings'])) { $cfg['buildings'] = $data['buildings']; }
            if (isset($data['rooms']) && is_array($data['rooms'])) { $cfg['rooms'] = $data['rooms']; }
            if (smcfg_save($cfg, $errors)) smcfg_redirect('saved=json');
        }
    }

    $cfg = plugin_schoolmanager_config(true);
}

$features = $cfg['features'] ?? [];
$plansActive = !empty($features['plans']);
$theme = $cfg['theme'] ?? [];
$brand = $cfg['brand'] ?? [];
$currentLocale = plugin_schoolmanager_locale();
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

// Canonical base dictionaries for editor (English en_GB and Spanish es_ES)
$canonicalEn = (include __DIR__ . '/../locales/en_GB.php') ?: [];
$canonicalEs = (include __DIR__ . '/../locales/es_ES.php') ?: [];
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

$root = $CFG_GLPI['root_doc'] ?? '';

sm_page_header([
    'kicker'      => 'GLPI School Manager',
    'title'       => plugin_schoolmanager_app_name() . ' · ' . smcfg_tr('settings_heading', 'Ajustes'),
    'subtitle'    => smcfg_tr('settings_subtitle', 'Configura marca, módulos, aulas, planos, idiomas y temas visuales.'),
    'active_tab'  => 'configuracion',
    'show_home'   => true,
]);
?>
<style>
.smset{--navy:#0F2951;--teal:#088E91;--emerald:#088E91;--soft:#F8FAFC;--line:#E2E8F0;--muted:#64748B;max-width:100%!important;width:100%!important;margin:0!important;padding:0!important;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#0F172A}.smset *{box-sizing:border-box}
.sm-tabs{display:inline-flex!important;background:rgba(255,255,255,.88)!important;backdrop-filter:blur(16px)!important;-webkit-backdrop-filter:blur(16px)!important;padding:6px!important;border-radius:16px!important;gap:6px!important;margin:12px 0 22px 0!important;border:1px solid rgba(226,232,240,.9)!important;flex-wrap:wrap!important;box-shadow:0 4px 14px rgba(15,41,81,.04)!important}
.sm-tab{border:1.5px solid transparent!important;background:transparent!important;color:#475569!important;padding:10px 20px!important;border-radius:12px!important;font-weight:800!important;font-size:13.5px!important;cursor:pointer!important;display:inline-flex!important;align-items:center!important;gap:8px!important;transition:all .18s cubic-bezier(.16,1,.3,1)!important}
.sm-tab:hover{color:#088E91!important;background:#F0FDFA!important}
.sm-tab.active{background:#0F2951!important;color:#ffffff!important;border-color:transparent!important;box-shadow:0 4px 14px rgba(15,41,81,.2)!important}
.sm-panel{display:none}.sm-panel.active{display:block;animation:smFade .2s ease}
.sm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.sm-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}
.sm-card{background:rgba(255,255,255,.88)!important;backdrop-filter:blur(18px) saturate(180%)!important;-webkit-backdrop-filter:blur(18px) saturate(180%)!important;border:1px solid rgba(255,255,255,.75)!important;border-radius:22px!important;padding:26px!important;box-shadow:0 10px 30px -5px rgba(15,41,81,.05),0 4px 12px -2px rgba(15,41,81,.03)!important;margin-bottom:20px!important}
.sm-card h2{margin:0 0 16px;font-size:20px;color:#0F2951;font-weight:850;letter-spacing:-.015em}.sm-card h3{margin:16px 0 10px;font-size:16px;font-weight:800;color:#0F2951}
.sm-field{display:block;margin:0 0 14px}.sm-field span,.sm-label{display:block;font-weight:800;margin:0 0 6px;color:#0F2951;font-size:13.5px}
.sm-input,.sm-select,.sm-textarea{width:100%!important;border:1.5px solid #CBD5E1!important;border-radius:12px!important;padding:11px 16px!important;font-weight:650!important;background:rgba(255,255,255,.95)!important;color:#0F172A!important;font-size:14px!important;box-sizing:border-box!important;transition:all .18s ease!important}
.sm-input:focus,.sm-select:focus,.sm-textarea:focus{border-color:#088E91!important;background:#FFFFFF!important;box-shadow:0 0 0 3.5px rgba(8,142,145,.18)!important;outline:none!important}
.sm-textarea{min-height:300px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px}
.sm-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sm-row.three{grid-template-columns:repeat(3,minmax(0,1fr))}
.sm-help{font-weight:600;color:var(--muted);font-size:12.5px;margin-top:6px;line-height:1.4}
.sm-btn{border:1px solid #088E91!important;background:#088E91!important;background-image:none!important;color:#fff!important;border-radius:12px!important;padding:10px 20px!important;font-weight:800!important;font-size:13.5px!important;cursor:pointer!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;text-decoration:none!important;transition:all .2s cubic-bezier(.16,1,.3,1)!important;white-space:nowrap!important;flex-shrink:0!important;line-height:1.2!important;min-height:42px!important;box-sizing:border-box!important;box-shadow:0 4px 14px rgba(8,142,145,.22)!important}
.sm-btn:hover{background:#0F2951!important;border-color:#0F2951!important;box-shadow:0 6px 18px rgba(15,41,81,.26)!important;color:#fff!important;transform:translateY(-1px)!important}
.sm-btn.red{background:#DC2626!important;background-image:none!important;color:#fff!important;border:1px solid #DC2626!important;border-radius:12px!important;box-shadow:0 2px 6px rgba(220,38,38,.18)!important}
.sm-btn.red:hover{background:#B91C1C!important;border-color:#B91C1C!important;color:#fff!important}
.sm-btn.light,button.sm-btn.light,a.sm-btn.light{background:rgba(255,255,255,.9)!important;background-image:none!important;color:#0F2951!important;border:1.5px solid #CBD5E1!important;border-radius:12px!important;box-shadow:0 1px 3px rgba(0,0,0,.04)!important;opacity:1!important;visibility:visible!important}
.sm-btn.light:hover,button.sm-btn.light:hover,a.sm-btn.light:hover{background:#F0FDFA!important;border-color:#088E91!important;color:#0F2951!important}
.sm-btn.small{font-size:12.5px!important;min-height:36px!important;padding:6px 14px!important;border-radius:10px!important}
.sm-alert{padding:14px 18px;border-radius:14px;margin:12px 0 18px;font-weight:800;display:flex;align-items:center;gap:10px}
.sm-alert.ok{background:#F0FDFA;color:#0F766E;border:1px solid #99F6E4}
.sm-alert.error{background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}
.sm-switches{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.sm-switch{display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:14px;background:#fbfdff;font-weight:800;transition:border-color .18s ease}
.sm-switch:hover{border-color:#088E91}
.sm-switch input{width:18px;height:18px;margin-top:2px;accent-color:#088E91}
.sm-switch small{color:var(--muted);font-weight:600}
.sm-table{width:100%;border-collapse:separate;border-spacing:0 8px}
.sm-table th{text-align:left;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em;padding:8px 12px}
.sm-table th:last-child,.sm-table td:last-child{width:140px!important;min-width:140px!important;text-align:right!important;white-space:nowrap!important}
.sm-table td{background:#fbfdff;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:12px;font-weight:800}
.sm-table td:first-child{border-left:1px solid var(--line);border-radius:10px 0 0 10px}
.sm-table td:last-child{border-right:1px solid var(--line);border-radius:0 10px 10px 0}
.sm-table td:last-child form{margin:0!important;display:inline-flex!important;justify-content:flex-end!important}
.sm-table td:last-child .sm-btn{min-width:86px!important;padding:7px 15px!important;border-radius:10px!important;overflow:visible!important}
.sm-pill{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:#f8fbfd;border-radius:8px;padding:4px 10px;font-weight:800;font-size:12px}
.sm-ok{color:#088E91}.sm-bad{color:#dc2626}
.sm-current-logo{display:flex;gap:18px;align-items:center}
.sm-logo-preview{width:96px;height:96px;object-fit:contain;border-radius:14px;background:#fff;border:1px solid var(--line);padding:8px;box-shadow:0 2px 8px rgba(15,41,81,.06)}
.sm-structure{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.sm-building-card{border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:14px;background:#fbfdff}
.sm-building-head{display:flex;align-items:center;justify-content:space-between;gap:14px}
.sm-building-head form{margin:0;flex-shrink:0;display:inline-flex}
.sm-floor-list{display:grid;gap:8px;margin-top:10px}
.sm-floor{border:1px dashed #cfe0e7;border-radius:12px;padding:12px 14px;background:#fff}
.sm-muted{color:var(--muted);font-weight:600}
.sm-palette-card{border:2px solid var(--line)!important;border-radius:14px!important;padding:16px!important;cursor:pointer!important;display:block!important;transition:all .18s ease!important}
.sm-palette-card:hover{transform:translateY(-2px)!important;box-shadow:0 8px 24px rgba(15,41,81,.08)!important}
.sm-palette-card.active{border:2.5px solid #088E91!important;box-shadow:0 0 0 4px rgba(8,142,145,.18),0 10px 24px rgba(15,41,81,.12)!important}
@media(max-width:1050px){.sm-grid,.sm-grid.three,.sm-structure{grid-template-columns:1fr}.sm-switches{grid-template-columns:1fr 1fr}}
@media(max-width:650px){.sm-row,.sm-row.three,.sm-switches{grid-template-columns:1fr}.smset{padding:8px}.sm-card{padding:16px}.sm-logo-preview{width:72px;height:72px}}
.sm-info-callout{display:flex;gap:12px;align-items:flex-start;border:1px solid #99F6E4;background:#F0FDFA;border-radius:14px;padding:14px 16px;margin:8px 0 16px;font-weight:750;color:#0F2951}
.sm-editor-table{width:100%;border-collapse:collapse}
.sm-editor-table th{background:var(--soft);color:#0F2951;font-weight:850;padding:12px;text-align:left;border-bottom:2px solid var(--line);font-size:12.5px;text-transform:uppercase;letter-spacing:.04em}
.sm-editor-table td{padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:middle;font-weight:750}
.sm-editor-table tr:hover td{background:#f8fcfe}
.sm-badge-builtin{background:#F0FDFA;color:#0F766E;padding:4px 9px;border-radius:8px;font-size:11.5px;font-weight:800;border:1px solid #99F6E4}
.sm-badge-custom{background:#fef3e7;color:#b45309;padding:4px 9px;border-radius:8px;font-size:11.5px;font-weight:800;border:1px solid #fed7aa}
.sm-code-snippet{background:#0F2951;color:#f8fafc;padding:14px;border-radius:12px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;line-height:1.5;overflow-x:auto;margin:10px 0}
.sm-actions-sticky{position:sticky;bottom:12px;background:rgba(255,255,255,.95);backdrop-filter:blur(16px);border:1px solid var(--line);border-radius:16px;padding:14px 20px;z-index:4;box-shadow:0 10px 30px rgba(15,41,81,.12);display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px}
.sm-toast{position:fixed;bottom:24px;right:24px;z-index:999999;background:#0F2951;color:#fff;padding:14px 22px;border-radius:14px;font-weight:800;display:flex;align-items:center;gap:12px;box-shadow:0 20px 50px rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.18);transform:translateY(100px);opacity:0;transition:all .32s cubic-bezier(.16,1,.3,1);pointer-events:none}
.sm-toast.show{transform:translateY(0);opacity:1;pointer-events:auto}
.sm-toast.sm-toast-ok{border-left:5px solid #088E91;box-shadow:0 20px 50px rgba(8,142,145,.25),0 10px 24px rgba(15,41,81,.2)}
.sm-btn-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:smSpin .6s linear infinite;margin-right:6px;vertical-align:middle}
@keyframes smSpin{to{transform:rotate(360deg)}}
@keyframes smFade{from{opacity:.5;transform:translateY(4px)}to{opacity:1;transform:none}}
</style>

<div class="smset schoolmanager-configured">

  <?php if(isset($_GET['saved'])): ?>
    <div class="sm-alert ok">
      <?php if($_GET['saved'] === 'locale_uploaded'): ?>
        <?= smcfg_h(smcfg_tr('locale_uploaded_msg', 'Language file uploaded and registered successfully.')) ?>
      <?php elseif($_GET['saved'] === 'locale_saved'): ?>
        <?= smcfg_h(smcfg_tr('locale_saved_msg', 'Language translations saved successfully.')) ?>
      <?php elseif($_GET['saved'] === 'locale_deleted'): ?>
        <?= smcfg_h(smcfg_tr('locale_deleted_msg', 'Custom language removed successfully.')) ?>
      <?php elseif($_GET['saved'] === 'theme_saved'): ?>
        <?= smcfg_h(smcfg_tr('theme_saved_msg', 'Visual theme updated and compiled successfully.')) ?>
      <?php elseif($_GET['saved'] === 'plans_toggled'): ?>
        <?= smcfg_h(smcfg_tr('plans_toggled_msg', 'Modo de planos interactivos (diagrams.net) actualizado correctamente.')) ?>
      <?php else: ?>
        <?= smcfg_h(smcfg_tr('configuration_saved', 'Configuration saved successfully.')) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if(!$canWrite): ?><div class="sm-alert error"><?= smcfg_h(smcfg_tr('write_warning', 'Plugin cannot write configuration. Check permissions:')) ?> <code><?= smcfg_h($dataDir) ?></code></div><?php endif; ?>
  <?php if(!$canUpload): ?><div class="sm-alert error"><?= smcfg_h(smcfg_tr('upload_warning', 'Upload directory is not writable. Logos cannot be stored:')) ?> <code><?= smcfg_h($uploadDir) ?></code></div><?php endif; ?>
  <?php if(!$canPlans): ?><div class="sm-alert error"><?= smcfg_h(smcfg_tr('plans_warning', 'Plans directory is not writable. Floor plans cannot be stored:')) ?> <code><?= smcfg_h($planDir) ?></code></div><?php endif; ?>
  <?php foreach($errors as $e): ?><div class="sm-alert error"><?= smcfg_h($e) ?></div><?php endforeach; ?>

  <!-- Unified tabs with consistent emojis and pure SSR localization -->
  <div class="sm-tabs">
    <button class="sm-tab active" data-tab="general" type="button">⚙️ <?= smcfg_h(smcfg_tr('tab_settings', 'Configuración')) ?></button>
    <button class="sm-tab" data-tab="i18n" type="button">🌐 <?= smcfg_h(smcfg_tr('tab_languages', 'Languages')) ?></button>
    <button class="sm-tab" data-tab="modules" type="button">🧩 <?= smcfg_h(smcfg_tr('tab_modules', 'Modules')) ?></button>
    <button class="sm-tab" data-tab="visual" type="button">🎨 <?= smcfg_h(smcfg_tr('tab_visual', 'Branding & Themes')) ?></button>
    <button class="sm-tab" data-tab="plans" type="button">🗺️ <?= smcfg_h(smcfg_tr('tab_plans', 'Floor Plans')) ?></button>
    <button class="sm-tab" data-tab="structure" type="button">🏫 <?= smcfg_h(smcfg_tr('tab_structure', 'Buildings & Classrooms')) ?></button>
    <button class="sm-tab" data-tab="advanced" type="button">⚡ <?= smcfg_h(smcfg_tr('tab_advanced', 'Advanced')) ?></button>
  </div>

  <!-- TAB: GENERAL -->
  <section class="sm-panel active" id="tab-general">
    <form method="post" class="sm-card"><?php smcfg_token(); ?><input type="hidden" name="action" value="save_general">
      <h2>⚙️ <?= smcfg_h(smcfg_tr('main_config', 'Configuración Principal')) ?></h2>
      <div class="sm-row">
        <label class="sm-field">
          <span><?= smcfg_h(smcfg_tr('app_name', 'Plugin name')) ?></span>
          <input class="sm-input" name="title" value="<?= smcfg_h($brand['title'] ?? ($brand['title_es'] ?? 'School Manager')) ?>">
          <small class="sm-help"><?= smcfg_h(smcfg_tr('app_name_help', 'Recommended: School Manager. This name is used consistently across pages.')) ?></small>
        </label>
        <label class="sm-field">
          <span><?= smcfg_h(smcfg_tr('menu_title', 'Left menu label')) ?></span>
          <input class="sm-input" name="menu_title" value="<?= smcfg_h($brand['menu_title'] ?? ($brand['menu_title_es'] ?? ($brand['title'] ?? 'School Manager'))) ?>">
          <small class="sm-help"><?= smcfg_h(smcfg_tr('menu_title_help', 'Name shown in the GLPI left sidebar.')) ?></small>
        </label>
      </div>

      <div class="sm-row">
        <label class="sm-field">
          <span><?= smcfg_h(smcfg_tr('organization', 'Organization')) ?></span>
          <input class="sm-input" name="organization" value="<?= smcfg_h($brand['organization'] ?? 'Educational center') ?>">
        </label>
        <label class="sm-field">
          <span><?= smcfg_h(smcfg_tr('plugin_language', 'Plugin language')) ?></span>
          <select class="sm-select" name="locale">
            <option value="auto" <?= ($cfg['locale']??'auto')==='auto'?'selected':'' ?>><?= smcfg_h(smcfg_tr('locale_auto', 'Automatic (detect from GLPI session)')) ?></option>
            <?php foreach($availableLocalesList as $lcode => $linfo): ?>
              <option value="<?= smcfg_h($lcode) ?>" <?= ($cfg['locale']??'')===$lcode?'selected':'' ?>><?= smcfg_h($linfo['name']) ?> (<?= smcfg_h($lcode) ?>)<?= $linfo['is_custom'] ? ' [' . smcfg_h(smcfg_tr('custom', 'Custom')) . ']' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <small class="sm-help"><?= smcfg_h(smcfg_tr('builtin_languages_help', 'Official languages: English (UK), Español, Français, Deutsch, Italiano, Português.')) ?></small>
          <small class="sm-help" style="display:block;margin-top:5px;color:var(--navy);font-weight:800">🤖 <?= smcfg_h(smcfg_tr('ai_translation_notice', 'Additional language translations were generated with AI assistance. Found an issue? Report it at:')) ?> <a href="https://github.com/pabloburgos/glpi-schoolmanager-plugin/issues" target="_blank" rel="noopener" style="color:var(--red);text-decoration:underline">GitHub Issues</a></small>
        </label>
      </div>

      <!-- Rescued, high-visibility Language Center Callout -->
      <div class="sm-lang-banner" style="display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--soft);border:1px solid var(--line);border-radius:18px;padding:14px 18px;margin:8px 0 16px">
        <div>
          <b style="font-size:15px;color:var(--navy)">🌐 <?= smcfg_h(smcfg_tr('custom_language_title', 'Missing your language or want to customize translations?')) ?></b>
          <p class="sm-help" style="margin:4px 0 0"><?= smcfg_h(smcfg_tr('custom_language_desc', 'You can download the base English template, translate it with AI, upload custom locale files, or edit translations online.')) ?></p>
        </div>
        <button type="button" class="sm-btn small light" onclick="window.smActivateTab('i18n')">🌐 <?= smcfg_h(smcfg_tr('open_language_center', 'Open Language Center')) ?></button>
      </div>

      <label class="sm-switch" style="margin-bottom:16px">
        <input type="checkbox" name="show_logo" value="1" <?= !empty($brand['show_logo'])?'checked':'' ?>>
        <span><?= smcfg_h(smcfg_tr('show_logo_header', 'Show logo in headers')) ?><br><small><?= smcfg_h(smcfg_tr('show_logo_help', 'Display the school / center logo on main screens.')) ?></small></span>
      </label>

      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('save_settings', 'Guardar configuración')) ?></button>
      </div>
    </form>
  </section>

  <!-- TAB: LANGUAGES & VISUAL I18N EDITOR -->
  <section class="sm-panel" id="tab-i18n">
    <div class="sm-card">
      <h2>🌐 <?= smcfg_h(smcfg_tr('language_center_title', 'Language Center & Visual Translation Editor')) ?></h2>
      <p class="sm-help"><?= smcfg_h(smcfg_tr('language_center_desc', 'School Manager natively includes 6 official languages: English (primary base), Spanish, French, German, Italian, and Portuguese. You can download the English translation template, translate it manually or using an AI model (ChatGPT, Claude, Gemini), upload custom files here, or edit any language directly from this screen.')) ?></p>

      <!-- AI Disclaimer and GitHub Issues Callout -->
      <div class="sm-alert" style="background:#eef8fc;border:1.5px solid #bce1f1;color:#07384d;margin-top:14px;border-radius:18px;padding:16px 20px;display:flex;align-items:flex-start;gap:14px">
        <span style="font-size:26px;line-height:1;flex-shrink:0">🤖</span>
        <div style="font-size:14px;line-height:1.55">
          <strong style="display:block;font-size:15px;margin-bottom:3px;color:#07384d"><?= smcfg_h(smcfg_tr('ai_disclaimer_title', 'AI-Generated Translations Notice & Community Contributions')) ?></strong>
          <?= smcfg_h(smcfg_tr('ai_disclaimer_desc', 'Language packs for French, German, Italian, and Portuguese have been structured with AI assistance. If you notice any awkward phrasing, grammar issues, or missing terminology, you can edit them directly in the Visual Editor below or submit an issue/PR at our GitHub repository:')) ?>
          <a href="https://github.com/pabloburgos/glpi-schoolmanager-plugin/issues" target="_blank" rel="noopener" style="display:inline-block;margin-top:4px;color:#b6252b;font-weight:950;text-decoration:underline">https://github.com/pabloburgos/glpi-schoolmanager-plugin/issues</a>
        </div>
      </div>
    </div>

    <div class="sm-grid">
      <!-- Card: Download Template -->
      <div class="sm-card">
        <h2>📥 <?= smcfg_h(smcfg_tr('download_template_title', 'Download English Template')) ?></h2>
        <p class="sm-help"><?= smcfg_h(smcfg_tr('download_template_desc', 'Official base template in English (en_GB.php) with all plugin translation keys and structured prompt instructions for AI.')) ?></p>
        <a class="sm-btn" href="<?= smcfg_h($_SERVER['PHP_SELF']) ?>?action=download_template" style="margin-top:10px">⬇ <?= smcfg_h(smcfg_tr('download_template_btn', 'Download template.php')) ?></a>
        
        <h3 style="margin-top:20px"><?= smcfg_h(smcfg_tr('ai_prompt_tip_title', '🤖 Tip: Translate in seconds with AI')) ?></h3>
        <p class="sm-help"><?= smcfg_h(smcfg_tr('ai_prompt_tip_desc', 'Open ChatGPT, Claude or Gemini and send this prompt with the template:')) ?></p>
        <div class="sm-code-snippet">Translate this PHP associative array of GLPI School Manager to [YOUR LANGUAGE] (e.g. Portuguese pt_PT). Keep all keys intact, only translate values, and set '_language_name' => '[Language Name]'. Return valid PHP.</div>
      </div>

      <!-- Card: Upload Language File -->
      <div class="sm-card">
        <h2>📤 <?= smcfg_h(smcfg_tr('upload_language_title', 'Upload Language File')) ?></h2>
        <form method="post" enctype="multipart/form-data">
          <?php smcfg_token(); ?>
          <input type="hidden" name="action" value="upload_locale">
          <div class="sm-row">
            <label class="sm-field">
              <span><?= smcfg_h(smcfg_tr('locale_code_label', 'Locale code (e.g. pt_PT, ca_ES)')) ?></span>
              <input class="sm-input" name="locale_code" placeholder="pt_PT" required pattern="[a-z]{2}(_[A-Z]{2})?">
            </label>
            <label class="sm-field">
              <span><?= smcfg_h(smcfg_tr('language_name_label', 'Language name')) ?></span>
              <input class="sm-input" name="locale_name" placeholder="Português">
            </label>
          </div>
          <label class="sm-field">
            <span><?= smcfg_h(smcfg_tr('translation_file_label', 'Translation file (.php or .json)')) ?></span>
            <input class="sm-input" type="file" name="locale_file" accept=".php,.json" required>
          </label>
          <p class="sm-help"><?= smcfg_h(smcfg_tr('upload_locale_help', 'Uploaded files are automatically validated and registered into the plugin locales directory.')) ?></p>
          <button class="sm-btn primary" type="submit">📤 <?= smcfg_h(smcfg_tr('upload_locale_btn', 'Upload & Register Language')) ?></button>
        </form>
      </div>
    </div>

    <!-- Card: In-Browser Visual Editor -->
    <div class="sm-card" id="sm-language-editor-box">
      <h2>✏️ <?= smcfg_h(smcfg_tr('visual_editor_title', 'Visual Language Editor')) ?></h2>
      <p class="sm-help"><?= smcfg_h(smcfg_tr('visual_editor_desc', 'Select a language to edit or create a brand new one online. Changes are saved directly to the locale file.')) ?></p>

      <div class="sm-row" style="margin-top:14px;align-items:flex-end">
        <label class="sm-field" style="margin-bottom:0">
          <span><?= smcfg_h(smcfg_tr('language_to_edit', 'Language to edit')) ?></span>
          <select class="sm-select" id="smEditLocaleSelect" onchange="window.smSwitchEditLocale(this.value)">
            <?php foreach($availableLocalesList as $lcode => $linfo): ?>
              <option value="<?= smcfg_h($lcode) ?>" <?= $editLocale===$lcode?'selected':'' ?>><?= smcfg_h($linfo['name']) ?> (<?= smcfg_h($lcode) ?>) <?= $linfo['is_builtin'] ? '· ' . smcfg_h(smcfg_tr('builtin', 'Built-in')) : '· ' . smcfg_h(smcfg_tr('custom', 'Custom')) ?></option>
            <?php endforeach; ?>
            <option value="new" <?= $editLocale==='new'?'selected':'' ?>>➕ <?= smcfg_h(smcfg_tr('create_new_language', 'Create new language...')) ?></option>
          </select>
        </label>
        <label class="sm-field" style="margin-bottom:0">
          <span>🔍 <?= smcfg_h(smcfg_tr('filter_strings', 'Filter strings')) ?></span>
          <input class="sm-input" id="smLocaleSearch" placeholder="<?= smcfg_h(smcfg_tr('filter_search_placeholder', 'Search key, English text or translation...')) ?>" oninput="window.smFilterLocaleRows(this.value)">
        </label>
      </div>

      <form method="post" style="margin-top:18px">
        <?php smcfg_token(); ?>
        <input type="hidden" name="action" value="save_locale_editor">

        <div class="sm-row" style="margin-bottom:14px;background:var(--soft);padding:14px;border-radius:16px;border:1px solid var(--line)">
          <label class="sm-field" style="margin-bottom:0">
            <span><?= smcfg_h(smcfg_tr('locale_code_exact', 'Locale Code (e.g. pt_PT, ca_ES, it_IT)')) ?></span>
            <input class="sm-input" name="locale_code" value="<?= smcfg_h($editLocale==='new' ? '' : $editLocale) ?>" required pattern="[a-z]{2}(_[A-Z]{2})?" placeholder="pt_PT" <?= ($editLocale!=='new' && in_array($editLocale, $builtinLocalesList, true)) ? 'readonly' : '' ?>>
          </label>
          <label class="sm-field" style="margin-bottom:0">
            <span><?= smcfg_h(smcfg_tr('locale_display_name', 'Language display name (e.g. Português, Català)')) ?></span>
            <input class="sm-input" name="locale_name" value="<?= smcfg_h($editLangName) ?>" required placeholder="Português">
          </label>
        </div>

        <div style="overflow-x:auto;max-height:550px;border:1px solid var(--line);border-radius:16px">
          <!-- translate="no" prevents client DOM translator from altering reference column -->
          <table class="sm-editor-table" translate="no">
            <thead>
              <tr>
                <th style="width:230px"><?= smcfg_h(smcfg_tr('key_identifier', 'Key identifier')) ?></th>
                <th style="width:42%"><?= smcfg_h(smcfg_tr('reference_col', 'Reference (English / Español)')) ?></th>
                <th><?= smcfg_h(smcfg_tr('translation_col', 'Translation')) ?></th>
              </tr>
            </thead>
            <tbody id="smLocaleTbody">
              <?php foreach($canonicalKeys as $k):
                $enVal = (string)($canonicalEn[$k] ?? '');
                $esVal = (string)($canonicalEs[$k] ?? '');
                $curVal = (string)($editDict[$k] ?? ($editLocale==='en_GB' ? $enVal : ($editLocale==='es_ES' ? $esVal : '')));
              ?>
                <tr class="sm-locale-row" data-key="<?= smcfg_h(strtolower($k)) ?>" data-es="<?= smcfg_h(strtolower($esVal)) ?>" data-en="<?= smcfg_h(strtolower($enVal)) ?>" data-val="<?= smcfg_h(strtolower($curVal)) ?>">
                  <td><code><?= smcfg_h($k) ?></code></td>
                  <td>
                    <div style="font-weight:800;color:var(--navy);display:flex;align-items:flex-start;gap:6px">
                      <span class="sm-pill" style="font-size:10px;padding:2px 6px;background:#e8f4fd;color:#0b5cab;border-color:#c2e0f8;flex-shrink:0">EN</span>
                      <span><?= smcfg_h($enVal ?: $esVal) ?></span>
                    </div>
                    <?php if ($esVal && $esVal !== $enVal): ?>
                      <div style="font-size:12px;color:var(--muted);margin-top:4px;display:flex;align-items:flex-start;gap:6px">
                        <span class="sm-pill" style="font-size:10px;padding:2px 6px;background:#fef3e7;color:#b45309;border-color:#fed7aa;flex-shrink:0">ES</span>
                        <span><?= smcfg_h($esVal) ?></span>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td><input class="sm-input" style="padding:8px 10px;font-size:13px" name="trans[<?= smcfg_h($k) ?>]" value="<?= smcfg_h($curVal) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="sm-actions-sticky">
          <div style="font-weight:900;color:var(--navy)">
            <span><?= count($canonicalKeys) ?> <?= smcfg_h(smcfg_tr('keys_loaded', 'keys loaded')) ?></span>
            <span class="sm-pill" id="smMatchCount" style="margin-left:8px"><?= count($canonicalKeys) ?> <?= smcfg_h(smcfg_tr('visible', 'visible')) ?></span>
          </div>
          <button class="sm-btn primary" type="submit">💾 <?= smcfg_h(smcfg_tr('save_language_changes', 'Save Language Changes')) ?></button>
        </div>
      </form>
    </div>

    <!-- Card: Installed Languages Table -->
    <div class="sm-card">
      <h2>📋 <?= smcfg_h(smcfg_tr('installed_languages_title', 'Installed & Registered Languages')) ?></h2>
      <table class="sm-table">
        <thead>
          <tr>
            <th><?= smcfg_h(smcfg_tr('code', 'Code')) ?></th>
            <th><?= smcfg_h(smcfg_tr('name', 'Name')) ?></th>
            <th><?= smcfg_h(smcfg_tr('type', 'Type')) ?></th>
            <th><?= smcfg_h(smcfg_tr('translated_keys', 'Translated Keys')) ?></th>
            <th><?= smcfg_h(smcfg_tr('format', 'Format')) ?></th>
            <th><?= smcfg_h(smcfg_tr('actions', 'Actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($availableLocalesList as $lcode => $linfo): ?>
            <tr>
              <td><code><?= smcfg_h($lcode) ?></code></td>
              <td><b><?= smcfg_h($linfo['name']) ?></b></td>
              <td><?= $linfo['is_builtin'] ? '<span class="sm-badge-builtin">' . smcfg_h(smcfg_tr('builtin', 'Built-in')) . '</span>' : '<span class="sm-badge-custom">' . smcfg_h(smcfg_tr('custom', 'Custom')) . '</span>' ?></td>
              <td><?= (int)$linfo['keys_count'] ?> / <?= count($canonicalKeys) ?></td>
              <td><span class="sm-pill"><?= smcfg_h(strtoupper($linfo['format'])) ?></span></td>
              <td>
                <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:nowrap">
                  <a class="sm-btn small light" href="<?= smcfg_h($_SERVER['PHP_SELF']) ?>?tab=i18n&edit_locale=<?= smcfg_h($lcode) ?>#sm-language-editor-box" style="white-space:nowrap">✏️ <?= smcfg_h(smcfg_tr('edit', 'Edit')) ?></a>
                  <a class="sm-btn small light" href="<?= smcfg_h($_SERVER['PHP_SELF']) ?>?action=download_locale&code=<?= smcfg_h($lcode) ?>" title="<?= smcfg_h(smcfg_tr('download', 'Download')) ?>">⬇</a>
                  <?php if($linfo['is_custom']): ?>
                    <form method="post" onsubmit="return confirm('<?= smcfg_h(smcfg_tr('confirm_delete_locale', 'Delete this custom language?')) ?>');" style="margin:0;display:inline-flex">
                      <?php smcfg_token(); ?>
                      <input type="hidden" name="action" value="delete_custom_locale">
                      <input type="hidden" name="locale_code" value="<?= smcfg_h($lcode) ?>">
                      <button class="sm-btn small red" type="submit" title="<?= smcfg_h(smcfg_tr('delete', 'Delete')) ?>" style="min-width:36px;padding:6px 10px">🗑</button>
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
      <h2>🧩 <?= smcfg_h(smcfg_tr('active_modules', 'Active Modules')) ?></h2>
      <p class="sm-help"><?= smcfg_h(smcfg_tr('modules_description', 'Disable any features you do not need. Disabled sections are safely blocked from direct navigation.')) ?></p>
      <div class="sm-switches">
        <?php foreach($moduleLabels as $k=>$label): ?>
          <label class="sm-switch">
            <input type="checkbox" name="features[]" value="<?= smcfg_h($k) ?>" <?= !empty($features[$k])?'checked':'' ?>>
            <span><?= smcfg_h($label) ?><br><small data-on="<?= smcfg_h(smcfg_tr('active','Active')) ?>" data-off="<?= smcfg_h(smcfg_tr('disabled','Disabled')) ?>"><?= !empty($features[$k])?smcfg_h(smcfg_tr('active','Active')):smcfg_h(smcfg_tr('disabled','Disabled')) ?></small></span>
          </label>
        <?php endforeach; ?>
      </div>
      <button class="sm-btn primary" type="submit" style="margin-top:16px"><?= smcfg_h(smcfg_tr('save_modules', 'Save modules')) ?></button>
    </form>
  </section>

  <!-- TAB: VISUAL (Branding & 4 Selectable CSS Themes) -->
  <section class="sm-panel" id="tab-visual">
    <div class="sm-grid">
      <div class="sm-card">
        <h2>Logo</h2>
        <div class="sm-current-logo">
          <img class="sm-logo-preview" src="<?= smcfg_h($logoUrl) ?>" alt="Logo" onerror="this.onerror=null;this.src='<?= smcfg_h(plugin_schoolmanager_logo_data_uri()) ?>';">
          <div>
            <p><b><?= smcfg_h($brand['logo'] ?? 'logo.png') ?></b></p>
            <p class="sm-help"><?= smcfg_h(smcfg_tr('logo_help', 'This logo is displayed in headers across the plugin.')) ?></p>
          </div>
        </div>
      </div>
      <div class="sm-card">
        <h2><?= smcfg_h(smcfg_tr('upload_logo', 'Upload logo')) ?></h2>
        <form method="post" enctype="multipart/form-data">
          <?php smcfg_token(); ?>
          <input type="hidden" name="action" value="upload_logo">
          <input class="sm-input" type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg" required>
          <p class="sm-help"><?= smcfg_h(smcfg_tr('logo_formats_help', 'Formats: PNG, JPG, WEBP, SVG.')) ?></p>
          <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('upload_logo_btn', 'Upload logo')) ?></button>
        </form>
        <form method="post" style="margin-top:10px">
          <?php smcfg_token(); ?>
          <input type="hidden" name="action" value="reset_logo">
          <button class="sm-btn light" type="submit"><?= smcfg_h(smcfg_tr('reset_default_logo', 'Reset to default logo')) ?></button>
        </form>
      </div>
    </div>

    <!-- Themes Section (Disabled - In Development) -->
    <div class="sm-card sm-theme-card" id="smThemeForm" style="position:relative;overflow:hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px">
        <h2 style="margin:0;display:flex;align-items:center;gap:10px">
          <span>🎨 <?= smcfg_h(smcfg_tr('visual_themes', 'Temas visuales')) ?></span>
          <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:#FEF3C7;border:1px solid #F59E0B;color:#92400E;font-size:12px;font-weight:800;letter-spacing:0.3px;text-transform:uppercase">🚧 En desarrollo</span>
        </h2>
      </div>
      <div style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);border:1.5px solid #FCD34D;border-radius:14px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;color:#92400E">
        <span style="font-size:22px;line-height:1">🚧</span>
        <div style="font-size:13.5px;line-height:1.5;font-weight:600">
          <strong style="display:block;font-size:14px;color:#78350F;margin-bottom:2px">Selector de temas visuales en desarrollo</strong>
          Los temas adicionales están desactivados temporalmente mientras se completa su adaptación visual. Actualmente se aplica el diseño institucional oficial <b>Signature (Navy &amp; Teal)</b> en todo el plugin.
        </div>
      </div>

      <div style="opacity:0.5;pointer-events:none;user-select:none;filter:grayscale(0.2)" aria-disabled="true">
        <div class="sm-palette-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:12px">
          <?php
            $currentPalette = 'teal-red';
            $presets = plugin_schoolmanager_theme_presets();
            $themeLabels = [
              'teal-red' => ['title' => smcfg_tr('theme_teal_title', 'Signature (Navy & Teal)'), 'desc' => smcfg_tr('theme_teal_desc', 'Paleta oficial School Manager: azul marino profundo, turquesa líquido y toques dorados / ámbar')],
              'ocean'    => ['title' => smcfg_tr('theme_ocean_title', 'Nordic Blue (Ocean Ice)'), 'desc' => smcfg_tr('theme_ocean_desc', 'Azul ártico cristalino con acentos cielo y superficies translúcidas refrescantes')],
              'forest'   => ['title' => smcfg_tr('theme_forest_title', 'Campus Emerald (Botanical)'), 'desc' => smcfg_tr('theme_forest_desc', 'Verde esmeralda universitario y menta natural con reflejos dorados botánicos')],
              'sunset'   => ['title' => smcfg_tr('theme_sunset_title', 'Warm Sunset (Amber & Coral)'), 'desc' => smcfg_tr('theme_sunset_desc', 'Cálido degradado terracota y ámbar solar con cristales ahumados y vibrantes')],
              'graphite' => ['title' => smcfg_tr('theme_graphite_title', 'Graphite Dark (Cyber Glass)'), 'desc' => smcfg_tr('theme_graphite_desc', 'Modo oscuro pizarra de alto contraste con reflejos cian neón y violeta suave (WCAG AA)')],
              'midnight' => ['title' => smcfg_tr('theme_midnight_title', 'Deep Cyber Midnight'), 'desc' => smcfg_tr('theme_midnight_desc', 'Oscuridad espacial profunda con glassmorphism púrpura holográfico y destellos magenta')],
            ];
            foreach ($themeLabels as $palKey => $palInfo):
              $p = $presets[$palKey] ?? null;
              if (!$p) continue;
              $isActive = ($currentPalette === $palKey);
              $cardBg = $p['dark'] ? 'rgba(17, 24, 39, 0.85)' : 'rgba(255, 255, 255, 0.82)';
              $cardBorder = $isActive ? '#0F2951' : ($p['dark'] ? 'rgba(255,255,255,0.12)' : 'rgba(15,41,81,0.12)');
          ?>
            <label class="sm-palette-card <?= $isActive ? 'active' : '' ?>" style="border:2px solid <?= $cardBorder ?>;border-radius:16px;padding:18px;background:<?= $cardBg ?>;display:block;position:relative">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <input type="radio" name="theme_palette" value="<?= smcfg_h($palKey) ?>" <?= $isActive ? 'checked' : '' ?> disabled style="width:18px;height:18px">
                <span class="sm-pill" style="font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px"><?= $isActive ? '✓ Activo por defecto' : 'En desarrollo' ?></span>
              </div>
              <b style="font-size:15px;color:<?= $p['dark'] ? '#F1F5F9' : '#0F2951' ?>;display:block"><?= smcfg_h($palInfo['title']) ?></b>
              <span class="sm-help" style="font-size:12px;display:block;margin:6px 0 14px;color:<?= $p['dark'] ? '#94A3B8' : '#5F7180' ?>;line-height:1.45"><?= smcfg_h($palInfo['desc']) ?></span>
              <span class="sm-swatches" style="display:flex;gap:7px;align-items:center">
                <i style="width:24px;height:24px;border-radius:50%;display:inline-block;background:<?= $p['primary'] ?>;border:2px solid rgba(255,255,255,0.7)"></i>
                <i style="width:24px;height:24px;border-radius:50%;display:inline-block;background:<?= $p['secondary'] ?>;border:2px solid rgba(255,255,255,0.7)"></i>
                <i style="width:24px;height:24px;border-radius:50%;display:inline-block;background:<?= $p['accent'] ?>;border:2px solid rgba(255,255,255,0.7)"></i>
                <i style="width:24px;height:24px;border-radius:50%;display:inline-block;background:<?= $p['background'] ?>;border:2px solid rgba(255,255,255,0.7)"></i>
              </span>
            </label>
          <?php endforeach; ?>
        </div>

        <button class="sm-btn primary" type="button" disabled style="margin-top:16px">🚧 Temas en desarrollo</button>
      </div>
    </div>
  </section>

  <!-- TAB: PLANS -->
  <section class="sm-panel" id="tab-plans">
    <!-- Master diagrams.net plans logic card -->
    <div class="sm-card" style="border-left: 5px solid <?= $plansActive ? '#088E91' : '#94A3B8' ?>; margin-bottom: 20px;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap">
        <div style="flex:1;min-width:280px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
            <h2 style="margin:0;font-size:20px;display:flex;align-items:center;gap:8px">
              <span>📐</span>
              <span><?= smcfg_h(smcfg_tr('plans_diagrams_title', 'Lógica de Planos Interactivos (diagrams.net)')) ?></span>
            </h2>
            <span class="sm-pill" style="font-size:12px;padding:4px 12px;border-radius:999px;font-weight:800;background:<?= $plansActive ? '#EFFBFB;color:#088E91;border:1.5px solid #99F6E4' : '#F1F5F9;color:#64748B;border:1.5px solid #CBD5E1' ?>">
              <?= $plansActive ? '✓ Lógica diagrams.net ACTIVADA' : '✕ Modo Solo Lista de Aulas (Sin planos)' ?>
            </span>
          </div>
          <p class="sm-help" style="margin:0 0 10px 0;font-size:13.5px;line-height:1.5">
            <?= smcfg_h(smcfg_tr('plans_diagrams_desc', 'Permite subir planos de planta diseñados en diagrams.net (draw.io). Cada aula etiquetada en el diagrama se detecta automáticamente permitiendo pulsar sobre ella para seleccionarla en incidencias, activos o abrir su ficha.')) ?>
          </p>
          <div style="background:<?= $plansActive ? '#F0FDFA' : '#F8FAFC' ?>;border:1px solid <?= $plansActive ? '#CCFBF1' : '#E2E8F0' ?>;border-radius:12px;padding:12px 14px;font-size:12.5px;color:<?= $plansActive ? '#0F766E' : '#475569' ?>;line-height:1.45">
            <b><?= $plansActive ? '💡 Al estar ACTIVADOS:' : 'ℹ️ Al estar DESACTIVADOS (Modo Solo Lista):' ?></b>
            <ul style="margin:6px 0 0 18px;padding:0">
              <?php if ($plansActive): ?>
                <li>El selector de ubicación de incidencias y activos abre el visor interactivo de diagrams.net con detección de aulas en tiempo real.</li>
                <li>En el directorio de aulas aparece el botón directo <b>"Ver en el plano"</b>.</li>
                <li>En el panel principal (dashboard) se muestra el acceso directo a <b>Planos de Planta</b>.</li>
              <?php else: ?>
                <li>En el selector de ubicación de incidencias y activos <b>NO se carga ningún plano</b>: se muestra directamente la lista limpia de aulas con búsqueda instantánea y filtros por edificio y planta.</li>
                <li>En el directorio de aulas se <b>oculta por completo</b> el botón "Ver en el plano".</li>
                <li>En el panel principal (dashboard) se <b>elimina el módulo de Planos</b> tanto de las tarjetas como de accesos rápidos.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <form method="post" style="margin:0;flex-shrink:0">
          <?php smcfg_token(); ?>
          <input type="hidden" name="action" value="toggle_plans">
          <input type="hidden" name="plans_enabled" value="<?= $plansActive ? '0' : '1' ?>">
          <button type="submit" class="sm-btn <?= $plansActive ? 'light' : 'primary' ?>" style="min-height:44px;padding:10px 20px;font-size:14px">
            <?= $plansActive ? '🚫 Desactivar planos (Usar solo lista)' : '✅ Activar planos diagrams.net' ?>
          </button>
        </form>
      </div>
    </div>

    <?php if (!$plansActive): ?>
      <div class="sm-card" style="background:#F8FAFC;border:2px dashed #CBD5E1;text-align:center;padding:36px 20px">
        <div style="font-size:36px;margin-bottom:10px">📋</div>
        <h3 style="margin:0 0 8px;font-size:17px;color:#0F2951">Modo Solo Lista de Aulas Activo</h3>
        <p class="sm-help" style="max-width:620px;margin:0 auto 16px;font-size:13px">
          El plugin está configurado para operar sin planos interactivos. La subida y visualización de planos está pausada. Puedes volver a activarla en cualquier momento con el botón superior.
        </p>
      </div>
    <?php endif; ?>

    <div class="sm-grid" style="<?= !$plansActive ? 'opacity:0.55;pointer-events:none;filter:grayscale(0.3)' : '' ?>">
      <div class="sm-card">
        <h2>🗺️ <?= smcfg_h(smcfg_tr('upload_plan_title', 'Upload floor plan')) ?></h2>
        <p class="sm-help" style="margin-top:-6px;margin-bottom:14px">
          Exporta tu diagrama desde <b>diagrams.net (draw.io)</b> como archivo <code>.html</code> o <code>.svg</code> e insértalo para cada planta:
        </p>
        <form method="post" enctype="multipart/form-data">
          <?php smcfg_token(); ?>
          <input type="hidden" name="action" value="upload_plan">
          <div class="sm-row">
            <label class="sm-field">
              <span><?= smcfg_h(smcfg_tr('building', 'Building')) ?></span>
              <select class="sm-select" name="plan_building" id="planBuilding">
                <?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label class="sm-field">
              <span><?= smcfg_h(smcfg_tr('floor', 'Floor')) ?></span>
              <select class="sm-select" name="plan_floor" id="planFloor"></select>
            </label>
          </div>
          <label class="sm-field">
            <span><?= smcfg_h(smcfg_tr('plan_type', 'Plan type')) ?></span>
            <select class="sm-select" name="plan_mode">
              <option value="normal"><?= smcfg_h(smcfg_tr('normal_plan', 'Standard plan')) ?></option>
              <option value="select"><?= smcfg_h(smcfg_tr('select_plan', 'Interactive classroom selection plan')) ?></option>
            </select>
          </label>
          <input class="sm-input" type="file" name="plan_file" accept=".html,.htm,.svg,.png,.jpg,.jpeg,.webp" required>
          <p class="sm-help"><?= smcfg_h(smcfg_tr('plan_formats_help', 'Supports HTML (diagrams.net export), SVG, PNG, JPG/JPEG and WEBP.')) ?></p>
          <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('upload_plan_btn', 'Upload plan')) ?></button>
        </form>
      </div>

      <div class="sm-card">
        <h2>🗺️ <?= smcfg_h(smcfg_tr('current_plans', 'Current floor plans')) ?></h2>
        <table class="sm-table">
          <thead>
            <tr>
              <th><?= smcfg_h(smcfg_tr('building', 'Building')) ?></th>
              <th><?= smcfg_h(smcfg_tr('floor', 'Floor')) ?></th>
              <th><?= smcfg_h(smcfg_tr('normal_plan', 'Standard')) ?></th>
              <th><?= smcfg_h(smcfg_tr('select_plan', 'Selection')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($planRows as $r): ?>
              <tr>
                <td><?= smcfg_h($r['building']) ?></td>
                <td><?= smcfg_h($r['label']) ?> <span class="sm-pill"><?= smcfg_h($r['floor']) ?></span></td>
                <td><?= $r['plan'] ? smcfg_h($r['plan']) : '<span class="sm-bad">' . smcfg_h(smcfg_tr('no_plan', 'No plan')) . '</span>' ?></td>
                <td><?= $r['select_plan'] ? smcfg_h($r['select_plan']) : '<span class="sm-bad">' . smcfg_h(smcfg_tr('no_plan', 'No plan')) . '</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- TAB: STRUCTURE (Buildings & Classrooms) -->
  <section class="sm-panel" id="tab-structure">
    <div class="sm-card">
      <h2>🏫 <?= smcfg_h(smcfg_tr('structure_title', 'Buildings, floors and classrooms')) ?></h2>
      <p class="sm-help"><?= smcfg_h(smcfg_tr('structure_help', 'If you check "Create in GLPI as well", the plugin creates native locations in GLPI and links their IDs for asset mapping.')) ?></p>
    </div>

    <div class="sm-grid three">
      <form class="sm-card" method="post">
        <?php smcfg_token(); ?>
        <input type="hidden" name="action" value="add_building">
        <h2><?= smcfg_h(smcfg_tr('add_building', 'Add building')) ?></h2>
        <label class="sm-field"><span><?= smcfg_h(smcfg_tr('code', 'Code')) ?></span><input class="sm-input" name="building_code" placeholder="ED1" required></label>
        <label class="sm-field"><span><?= smcfg_h(smcfg_tr('name', 'Name')) ?></span><input class="sm-input" name="building_name_es" placeholder="Building 1" required></label>
        <label class="sm-switch"><input type="checkbox" name="create_glpi_location" value="1" checked><span><?= smcfg_h(smcfg_tr('create_glpi_loc', 'Create also in GLPI')) ?><br><small><?= smcfg_h(smcfg_tr('create_glpi_loc_root_help', 'Creates a root location for this building.')) ?></small></span></label>
        <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('add_building_btn', 'Add building')) ?></button>
      </form>

      <form class="sm-card" method="post">
        <?php smcfg_token(); ?>
        <input type="hidden" name="action" value="add_floor">
        <h2><?= smcfg_h(smcfg_tr('add_floor', 'Add floor')) ?></h2>
        <label class="sm-field"><span><?= smcfg_h(smcfg_tr('building', 'Building')) ?></span><select class="sm-select" name="floor_building"><?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?></select></label>
        <div class="sm-row"><label class="sm-field"><span><?= smcfg_h(smcfg_tr('code', 'Code')) ?></span><input class="sm-input" name="floor_code" placeholder="P0" required></label><label class="sm-field"><span><?= smcfg_h(smcfg_tr('number', 'Number')) ?></span><input class="sm-input" name="floor_number" placeholder="0"></label></div>
        <label class="sm-field"><span><?= smcfg_h(smcfg_tr('label', 'Label')) ?></span><input class="sm-input" name="floor_label_es" placeholder="Ground floor" required></label>
        <label class="sm-switch"><input type="checkbox" name="create_glpi_location" value="1" checked><span><?= smcfg_h(smcfg_tr('create_glpi_loc', 'Create also in GLPI')) ?><br><small><?= smcfg_h(smcfg_tr('create_glpi_loc_floor_help', 'Creates floor location inside the building.')) ?></small></span></label>
        <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('add_floor_btn', 'Add floor')) ?></button>
      </form>

      <form class="sm-card" method="post">
        <?php smcfg_token(); ?>
        <input type="hidden" name="action" value="add_room">
        <h2><?= smcfg_h(smcfg_tr('add_classroom', 'Add classroom')) ?></h2>
        <div class="sm-row"><label class="sm-field"><span><?= smcfg_h(smcfg_tr('building', 'Building')) ?></span><select class="sm-select" name="room_building" id="roomBuilding"><?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?></select></label><label class="sm-field"><span><?= smcfg_h(smcfg_tr('floor', 'Floor')) ?></span><select class="sm-select" name="room_floor" id="roomFloor"></select></label></div>
        <div class="sm-row"><label class="sm-field"><span><?= smcfg_h(smcfg_tr('classroom_name', 'Classroom')) ?></span><input class="sm-input" name="room" placeholder="101" required></label><label class="sm-field"><span><?= smcfg_h(smcfg_tr('code', 'Code')) ?></span><input class="sm-input" name="room_code" placeholder="ED1-101"></label></div>
        <label class="sm-field"><span><?= smcfg_h(smcfg_tr('label', 'Label')) ?></span><input class="sm-input" name="room_name_es" placeholder="Room 101" required></label>
        <label class="sm-field"><span><?= smcfg_h(smcfg_tr('existing_glpi_loc_id', 'Existing GLPI location ID (optional)')) ?></span><input class="sm-input" name="room_glpi_id" placeholder="Optional ID"></label>
        <label class="sm-switch"><input type="checkbox" name="create_glpi_location" value="1" checked><span><?= smcfg_h(smcfg_tr('create_glpi_loc', 'Create also in GLPI')) ?><br><small><?= smcfg_h(smcfg_tr('create_glpi_loc_room_help', 'Creates classroom inside floor location.')) ?></small></span></label>
        <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('add_classroom_btn', 'Add classroom')) ?></button>
      </form>
    </div>

    <div class="sm-structure">
      <!-- Buildings & Floors List -->
      <div class="sm-card">
        <h2>🏫 <?= smcfg_h(smcfg_tr('current_structure', 'Current structure')) ?></h2>
        <?php foreach($buildings as $b): ?>
          <div class="sm-building-card">
            <div class="sm-building-head">
              <div>
                <b><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></b>
                <span class="sm-pill"><?= smcfg_h($b['code']??'') ?></span>
                <?= empty($b['enabled'])?'<span class="sm-bad">' . smcfg_h(smcfg_tr('disabled', 'Disabled')) . '</span>':'<span class="sm-ok">' . smcfg_h(smcfg_tr('active', 'Active')) . '</span>' ?>
                <br><span class="sm-muted">GLPI ID: <?= smcfg_h($b['glpi_location_id'] ?? '-') ?></span>
              </div>
              <form method="post">
                <?php smcfg_token(); ?>
                <input type="hidden" name="action" value="toggle_building">
                <input type="hidden" name="code" value="<?= smcfg_h($b['code']??'') ?>">
                <button class="sm-btn small light" type="submit"><?= empty($b['enabled'])?smcfg_h(smcfg_tr('enable', 'Enable')):smcfg_h(smcfg_tr('disable', 'Disable')) ?></button>
              </form>
            </div>
            <div class="sm-floor-list">
              <?php foreach(($b['floors']??[]) as $f): ?>
                <div class="sm-floor">
                  <div class="sm-building-head">
                    <div>
                      <?= smcfg_h(plugin_schoolmanager_label($f,'label',$f['code']??'')) ?>
                      <span class="sm-pill"><?= smcfg_h($f['code']??'') ?></span>
                      <?= empty($f['enabled'])?'<span class="sm-bad">' . smcfg_h(smcfg_tr('disabled', 'Disabled')) . '</span>':'<span class="sm-ok">' . smcfg_h(smcfg_tr('active', 'Active')) . '</span>' ?>
                      <br><span class="sm-muted">GLPI ID: <?= smcfg_h($f['glpi_location_id'] ?? '-') ?></span>
                    </div>
                    <form method="post">
                      <?php smcfg_token(); ?>
                      <input type="hidden" name="action" value="toggle_floor">
                      <input type="hidden" name="building" value="<?= smcfg_h($b['code']??'') ?>">
                      <input type="hidden" name="floor" value="<?= smcfg_h($f['code']??'') ?>">
                      <button class="sm-btn small light" type="submit"><?= empty($f['enabled'])?smcfg_h(smcfg_tr('enable', 'Enable')):smcfg_h(smcfg_tr('disable', 'Disable')) ?></button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Classrooms List -->
      <div class="sm-card">
        <h2>🏫 <?= smcfg_h(smcfg_tr('current_classrooms', 'Current classrooms')) ?></h2>
        <table class="sm-table">
          <thead>
            <tr>
              <th><?= smcfg_h(smcfg_tr('classroom_name', 'Classroom')) ?></th>
              <th><?= smcfg_h(smcfg_tr('building', 'Building')) ?></th>
              <th><?= smcfg_h(smcfg_tr('floor', 'Floor')) ?></th>
              <th>GLPI</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rooms as $i=>$r): $lid=(int)($r['glpi_location_id'] ?? 0); ?>
              <tr>
                <td>
                  <?= smcfg_h(plugin_schoolmanager_label($r,'name',$r['room']??'')) ?>
                  <br><span class="sm-muted"><?= smcfg_h($r['code']??'') ?></span>
                </td>
                <td><?= smcfg_h($r['building']??'') ?></td>
                <td><?= smcfg_h($r['floor']??'') ?></td>
                <td>
                  <?php if($lid): ?>
                    <a class="sm-pill" href="<?= smcfg_h(smcfg_location_link($lid)) ?>">#<?= $lid ?></a>
                  <?php else: ?>
                    <span class="sm-bad"><?= smcfg_h(smcfg_tr('no_id', 'No ID')) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" onsubmit="return confirm('<?= smcfg_h(smcfg_tr('confirm_delete_classroom', 'Remove classroom from plugin? Does not delete GLPI location.')) ?>');">
                    <?php smcfg_token(); ?>
                    <input type="hidden" name="action" value="delete_room">
                    <input type="hidden" name="idx" value="<?= (int)$i ?>">
                    <button class="sm-btn small red" type="submit"><?= smcfg_h(smcfg_tr('remove', 'Remove')) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- TAB: ADVANCED -->
  <section class="sm-panel" id="tab-advanced">
    <form method="post" class="sm-card">
      <?php smcfg_token(); ?>
      <input type="hidden" name="action" value="save_json">
      <h2>⚡ <?= smcfg_h(smcfg_tr('advanced_json_title', 'Advanced JSON')) ?></h2>
      <p class="sm-help"><?= smcfg_h(smcfg_tr('advanced_json_help', 'For advanced structure import/export only. The previous tabs are safer.')) ?></p>
      <textarea class="sm-textarea" name="structure_json"><?= smcfg_h($structure) ?></textarea>
      <button class="sm-btn primary" type="submit"><?= smcfg_h(smcfg_tr('save_json', 'Save JSON')) ?></button>
    </form>
    <div class="sm-card">
      <h2>📜 <?= smcfg_h(smcfg_tr('credits_and_license', 'Credits and license')) ?></h2>
      <p><b>Pablo Burgos</b> (<a href="mailto:pabloburgostena@faltax.app" style="color:var(--sm-primary,#07384d);text-decoration:underline">pabloburgostena@faltax.app</a>) · <b>Alejandro Galán</b></p>
      <p class="sm-help"><?= smcfg_h(smcfg_tr('license_notice', 'Plugin license: GPLv3+ (GNU General Public License v3 or later). Credits are retained in documentation and settings.')) ?></p>
    </div>
  </section>
</div>

<script>
(function(){
  document.querySelectorAll('.sm-table').forEach(tbl=>{ const hs=[...tbl.querySelectorAll('thead th')].map(th=>th.textContent.trim()); tbl.querySelectorAll('tbody tr').forEach(tr=>[...tr.children].forEach((td,i)=>td.setAttribute('data-label',hs[i]||''))); });
  document.querySelectorAll('form').forEach(f=>{
    f.addEventListener('submit', function(){
      const b=f.querySelector('button[type=submit]');
      if(b && !b.dataset.submitting){
        b.dataset.submitting='1';
        b.style.minWidth=b.offsetWidth+'px';
        b.classList.add('loading');
        b.innerHTML='<span class="sm-btn-spinner" aria-hidden="true"></span> ' + (b.getAttribute('data-saving') || '<?= smcfg_h(smcfg_tr("saving", "Guardando...")) ?>');
        setTimeout(function(){ b.disabled=true; }, 0);
      }
    });
  });
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
      const k = r.dataset.key || '', en = r.dataset.en || '', es = r.dataset.es || '', val = (r.querySelector('input')?.value || '').toLowerCase();
      const match = !q || k.includes(q) || en.includes(q) || es.includes(q) || val.includes(q);
      r.style.display = match ? '' : 'none';
      if(match) count++;
    });
    const mc = document.getElementById('smMatchCount');
    if(mc) mc.textContent = count + ' <?= smcfg_h(smcfg_tr("visible", "visible")) ?>';
  };

  // Toast feedback helper
  function showToast(msg, isOk = true){
    let t = document.getElementById('smToast');
    if(!t){
      t = document.createElement('div');
      t.id = 'smToast';
      document.body.appendChild(t);
    }
    t.innerHTML = (isOk ? '✅ ' : '⚠️ ') + msg;
    t.className = 'sm-toast show' + (isOk ? ' sm-toast-ok' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 4200);
  }

  const savedParam = params.get('saved');
  if(savedParam){
    const msgs = {
      'general': '<?= smcfg_h(smcfg_tr("configuration_saved", "Configuration saved successfully.")) ?>',
      'theme_saved': '<?= smcfg_h(smcfg_tr("theme_saved_msg", "Visual theme updated and compiled successfully.")) ?>',
      'modules': '<?= smcfg_h(smcfg_tr("configuration_saved", "Configuration saved successfully.")) ?>',
      'locale_saved': '<?= smcfg_h(smcfg_tr("locale_saved_msg", "Language translations saved successfully.")) ?>',
      'locale_uploaded': '<?= smcfg_h(smcfg_tr("locale_uploaded_msg", "Language file uploaded and registered successfully.")) ?>',
      'locale_deleted': '<?= smcfg_h(smcfg_tr("locale_deleted_msg", "Custom language removed successfully.")) ?>',
      'plans_toggled': '<?= smcfg_h(smcfg_tr("plans_toggled_msg", "Modo de planos interactivos (diagrams.net) actualizado correctamente.")) ?>'
    };
    showToast('✓ ' + (msgs[savedParam] || '<?= smcfg_h(smcfg_tr("configuration_saved", "Configuration saved successfully.")) ?>'));
  }
})();
</script>
<?php sm_page_footer(); ?>
