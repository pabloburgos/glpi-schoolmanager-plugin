<?php
include('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');

if (!class_exists('Session') || !Session::getLoginUserID()) { Html::redirect($CFG_GLPI['root_doc'] . '/index.php'); }
if (function_exists('Session::checkRight')) {}
if (method_exists('Session', 'checkRight')) { Session::checkRight('config', UPDATE); }

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
    Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/configuracion.php?' . $msg . '&tab=' . rawurlencode($tab));
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

$cfg = plugin_schoolmanager_config(true);
$errors = [];
$info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Token is emitted in every form. Avoid GLPI CSRF false positives with multiple forms/uploads on the same page.
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_general') {
        $name = trim((string)($_POST['title'] ?? 'School Manager'));
        if ($name === '') { $name = 'School Manager'; }
        $menuTitle = trim((string)($_POST['menu_title'] ?? $name));
        if ($menuTitle === '') { $menuTitle = $name; }
        $cfg['brand']['title'] = $name;
        $cfg['brand']['menu_title'] = $menuTitle;
        // Keep old keys synchronized so older pages and saved configs do not split languages.
        $cfg['brand']['title_es'] = $name;
        $cfg['brand']['title_en'] = $name;
        $cfg['brand']['menu_title_es'] = $menuTitle;
        $cfg['brand']['menu_title_en'] = $menuTitle;
        $cfg['brand']['organization'] = trim((string)($_POST['organization'] ?? 'Educational center')) ?: 'Educational center';
        $cfg['brand']['show_logo'] = smcfg_bool('show_logo');
        $locale = (string)($_POST['locale'] ?? 'auto');
        $cfg['locale'] = in_array($locale, ['auto','es_ES','en_GB'], true) ? $locale : 'auto';
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

    $cfg = plugin_schoolmanager_config(true);
}

$features = $cfg['features'] ?? [];
$theme = $cfg['theme'] ?? [];
$brand = $cfg['brand'] ?? [];
$isEnglish = plugin_schoolmanager_locale() === 'en_GB';
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

Html::header(smcfg_tr('settings_title','Configuración de School Manager'), $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
?>
<style>
.smset{--navy:var(--sm-primary,#07384d);--teal:var(--sm-secondary,#075d61);--red:var(--sm-accent,#b6252b);--soft:var(--sm-soft,#f4fafc);--line:#d7e6ec;--muted:#5f7180;max-width:1500px;margin:0 auto;padding:18px;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#07384d}.smset *{box-sizing:border-box}.sm-hero{background:linear-gradient(135deg,#fff,var(--soft));border:1px solid var(--line);border-radius:28px;padding:26px;display:flex;gap:22px;align-items:center;justify-content:space-between;box-shadow:0 20px 50px rgba(7,56,77,.08);margin-bottom:18px}.sm-hero h1{font-size:clamp(32px,4vw,64px);line-height:.98;margin:0;color:#07384d;font-weight:950}.sm-hero p{font-weight:850;color:var(--muted);font-size:16px}.sm-logo-preview{width:120px;height:120px;object-fit:contain;border-radius:24px;background:#fff;border:1px solid var(--line);padding:10px}.sm-kicker{text-transform:uppercase;letter-spacing:.16em;color:#b6252b;font-weight:950}.sm-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}.sm-tab{border:1px solid var(--line);background:#fff;color:#07384d;padding:12px 16px;border-radius:16px;font-weight:950;cursor:pointer;transition:.18s}.sm-tab:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(7,56,77,.12)}.sm-tab.active{background:#07384d;color:#fff;border-color:#07384d}.sm-panel{display:none}.sm-panel.active{display:block}.sm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.sm-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.sm-card{background:#fff;border:1px solid var(--line);border-radius:24px;padding:20px;box-shadow:0 14px 36px rgba(7,56,77,.06);margin-bottom:16px}.sm-card h2{margin:0 0 14px;font-size:24px;color:#07384d}.sm-card h3{margin:16px 0 10px;font-size:18px}.sm-field{display:block;margin:0 0 12px}.sm-field span,.sm-label{display:block;font-weight:950;margin:0 0 6px;color:#07384d}.sm-input,.sm-select,.sm-textarea{width:100%;border:1px solid #d9e5ea;border-radius:14px;padding:12px 14px;font-weight:800;background:#fff;color:#243244}.sm-textarea{min-height:320px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px}.sm-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sm-row.three{grid-template-columns:repeat(3,minmax(0,1fr))}.sm-help{font-weight:800;color:#687887;margin-top:6px}.sm-btn{border:0;background:#07384d;color:#fff;border-radius:15px;padding:12px 16px;font-weight:950;cursor:pointer;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:.18s}.sm-btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(7,56,77,.2);color:#fff}.sm-btn.red{background:#b6252b}.sm-btn.light{background:#fff;color:#07384d;border:1px solid var(--line)}.sm-btn.small{font-size:13px;padding:8px 10px;border-radius:12px}.sm-alert{padding:14px 16px;border-radius:16px;margin:12px 0;font-weight:950}.sm-alert.ok{background:#e9fbf1;color:#14783b;border:1px solid #b5eac8}.sm-alert.error{background:#fff0f0;color:#b6252b;border:1px solid #ffb4b4}.sm-switches{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sm-switch{display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:18px;background:#fbfdff;font-weight:950}.sm-switch input{width:20px;height:20px}.sm-switch small{color:#687887}.sm-table{width:100%;border-collapse:separate;border-spacing:0 8px}.sm-table th{text-align:left;color:#607080;font-size:12px;text-transform:uppercase;letter-spacing:.05em}.sm-table td{background:#fbfdff;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:12px;font-weight:850}.sm-table td:first-child{border-left:1px solid var(--line);border-radius:14px 0 0 14px}.sm-table td:last-child{border-right:1px solid var(--line);border-radius:0 14px 14px 0}.sm-pill{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:#f8fbfd;border-radius:999px;padding:5px 9px;font-weight:950}.sm-ok{color:#168346}.sm-bad{color:#b6252b}.sm-current-logo{display:flex;gap:18px;align-items:center}.sm-structure{display:grid;grid-template-columns:1fr 1fr;gap:16px}.sm-building-card{border:1px solid var(--line);border-radius:18px;padding:14px;margin-bottom:12px;background:#fbfdff}.sm-building-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.sm-actions{display:flex;gap:8px;flex-wrap:wrap}.sm-floor-list{display:grid;gap:8px;margin-top:10px}.sm-floor{border:1px dashed #cfe0e7;border-radius:14px;padding:10px;background:#fff}.sm-muted{color:#687887;font-weight:800}@media(max-width:1050px){.sm-grid,.sm-grid.three,.sm-structure{grid-template-columns:1fr}.sm-switches{grid-template-columns:1fr 1fr}.sm-hero{align-items:flex-start;flex-direction:column}}@media(max-width:650px){.sm-row,.sm-row.three,.sm-switches{grid-template-columns:1fr}.smset{padding:10px}.sm-card{padding:14px}.sm-hero{padding:18px}.sm-logo-preview{width:86px;height:86px}}

.smset{background:linear-gradient(180deg,#f7fbfd 0,#eef6f9 100%);border-radius:32px}.sm-panel{animation:smFade .18s ease}.sm-card{overflow:hidden}.sm-card h2{display:flex;align-items:center;gap:10px}.sm-grid,.sm-grid.three,.sm-structure{align-items:start}.sm-field{min-width:0}.sm-input:focus,.sm-select:focus,.sm-textarea:focus{outline:3px solid color-mix(in srgb,var(--teal) 18%,transparent);border-color:var(--teal);box-shadow:0 0 0 4px rgba(7,93,97,.08)}.sm-switch{transition:.16s ease;min-height:76px}.sm-switch:hover{transform:translateY(-2px);box-shadow:0 14px 26px rgba(7,56,77,.08);border-color:var(--teal)}.sm-switch input:checked+span{color:var(--navy)}.sm-current-logo{padding:16px;border:1px dashed var(--line);border-radius:22px;background:#fbfdff}.sm-logo-preview{box-shadow:0 12px 28px rgba(7,56,77,.08)}.sm-hero{position:relative;overflow:hidden}.sm-hero:after{content:"";position:absolute;right:-80px;top:-80px;width:220px;height:220px;border-radius:50%;background:color-mix(in srgb,var(--teal) 12%,transparent);pointer-events:none}.sm-tab.active{background:linear-gradient(135deg,var(--navy),var(--teal));box-shadow:0 14px 30px rgba(7,56,77,.16)}.sm-building-card,.sm-floor,.sm-table td{transition:.14s ease}.sm-building-card:hover,.sm-floor:hover,.sm-table tr:hover td{background:#fff;box-shadow:0 12px 24px rgba(7,56,77,.07)}.sm-actions-sticky{position:sticky;bottom:12px;background:rgba(255,255,255,.86);backdrop-filter:blur(10px);border:1px solid var(--line);border-radius:20px;padding:10px;z-index:4}@keyframes smFade{from{opacity:.5;transform:translateY(4px)}to{opacity:1;transform:none}}@media(max-width:850px){.sm-table,.sm-table thead,.sm-table tbody,.sm-table tr,.sm-table td{display:block}.sm-table thead{display:none}.sm-table tr{border:1px solid var(--line);border-radius:18px;margin-bottom:12px;background:#fbfdff}.sm-table td{border:0!important;border-radius:0!important}.sm-table td:before{content:attr(data-label);display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:950;margin-bottom:3px}}
.smset{padding:24px}.sm-hero-side{display:flex;align-items:center;gap:14px;position:relative;z-index:1}.sm-hero-actions{display:flex;gap:10px;justify-content:flex-end}.sm-btn.red{background:linear-gradient(135deg,var(--red),#8f1e23)}.sm-palette-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0 18px}.sm-palette-card{border:1px solid var(--line);background:#fbfdff;border-radius:18px;padding:14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;font-weight:950;transition:.18s ease}.sm-palette-card input{position:absolute;opacity:0;pointer-events:none}.sm-palette-card:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(7,56,77,.10)}.sm-palette-card.active{border-color:var(--teal);box-shadow:0 0 0 3px color-mix(in srgb,var(--teal) 18%,transparent),0 14px 30px rgba(7,56,77,.10);background:#fff}.sm-swatches{display:flex;gap:4px}.sm-swatches i{display:block;width:18px;height:18px;border-radius:999px;border:1px solid rgba(0,0,0,.08)}.sm-preview-card{border:1px solid var(--line);border-radius:22px;background:linear-gradient(135deg,#fff,var(--soft));padding:18px;min-height:110px;display:grid;align-content:center;gap:8px}.sm-preview-card b{font-size:24px;color:var(--navy)}.sm-preview-card span{font-weight:850;color:var(--muted)}.sm-preview-card button{border:0;background:linear-gradient(135deg,var(--navy),var(--teal));color:#fff;border-radius:14px;padding:10px 14px;font-weight:950;width:max-content}.sm-color-field input[type=color]{height:52px;padding:4px;cursor:pointer}.sm-actions-sticky{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:8px}.sm-card,.sm-switch,.sm-table td{border-color:color-mix(in srgb,var(--teal) 18%,#d7e6ec)!important}.sm-tabs{padding:8px;background:rgba(255,255,255,.55);border:1px solid var(--line);border-radius:22px}.sm-panel{padding-top:4px}@media(max-width:900px){.sm-palette-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sm-hero-side{width:100%;justify-content:space-between}}@media(max-width:560px){.sm-palette-grid{grid-template-columns:1fr}.smset{padding:12px}.sm-hero-side{align-items:flex-start;flex-direction:column}.sm-hero-actions{justify-content:flex-start}}

.sm-info-callout{display:flex;gap:12px;align-items:flex-start;border:1px solid color-mix(in srgb,var(--teal) 24%,#d7e6ec);background:linear-gradient(135deg,#f7fcff,#eef8f8);border-radius:18px;padding:13px 15px;margin:8px 0 14px;font-weight:850;color:var(--navy)}
.sm-info-callout i{font-style:normal;display:inline-grid;place-items:center;min-width:28px;height:28px;border-radius:999px;background:color-mix(in srgb,var(--teal) 14%,#fff);color:var(--teal);font-weight:950}
.sm-lock-card{position:relative;border:1px solid #ffd48a!important;background:linear-gradient(135deg,#fffaf0,#f7fbfd)!important;overflow:hidden}.sm-lock-card:before{content:"";position:absolute;right:-70px;top:-90px;width:230px;height:230px;border-radius:50%;background:rgba(245,158,11,.13);pointer-events:none}.sm-lock-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;position:relative;z-index:1}.sm-lock-badge{display:inline-flex;align-items:center;gap:8px;border:1px solid #f2c36b;background:#fff6df;color:#8a5800;border-radius:999px;padding:9px 13px;font-weight:950;white-space:nowrap}.sm-lock-button{border:0;background:#8a5800;color:#fff;border-radius:15px;padding:12px 16px;font-weight:950;display:inline-flex;align-items:center;gap:8px;opacity:.96;cursor:not-allowed}.sm-lock-muted{opacity:.48;filter:saturate(.4);pointer-events:none}.sm-coming-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}.sm-coming-item{border:1px solid var(--line);background:#fff;border-radius:16px;padding:12px;font-weight:900;color:var(--navy)}@media(max-width:850px){.sm-lock-head{flex-direction:column}.sm-coming-list{grid-template-columns:1fr}}


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

  <?php if(isset($_GET['saved'])): ?><div class="sm-alert ok"><?= smcfg_h(smcfg_tr('configuration_saved','Configuración guardada correctamente.')) ?></div><?php endif; ?>
  <?php if(!$canWrite): ?><div class="sm-alert error"><?= smcfg_h(smcfg_tr('write_warning','El plugin no puede escribir la configuración. Revisa permisos de:')) ?> <code><?= smcfg_h($dataDir) ?></code></div><?php endif; ?>
  <?php if(!$canUpload): ?><div class="sm-alert error">No se puede escribir en <code><?= smcfg_h($uploadDir) ?></code>. Los logos no se podrán guardar.</div><?php endif; ?>
  <?php if(!$canPlans): ?><div class="sm-alert error">No se puede escribir en <code><?= smcfg_h($planDir) ?></code>. Los planos no se podrán guardar.</div><?php endif; ?>
  <?php foreach($errors as $e): ?><div class="sm-alert error"><?= smcfg_h($e) ?></div><?php endforeach; ?>

  <div class="sm-tabs">
    <button class="sm-tab active" data-tab="general" type="button">General</button>
    <button class="sm-tab" data-tab="modules" type="button">Módulos</button>
    <button class="sm-tab" data-tab="visual" type="button">Logo y estilo</button>
    <button class="sm-tab" data-tab="plans" type="button">Planos</button>
    <button class="sm-tab" data-tab="structure" type="button">Edificios y aulas</button>
    <button class="sm-tab" data-tab="advanced" type="button">Avanzado</button>
  </div>

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
        <label class="sm-field"><span><?= $isEnglish ? 'Plugin language' : 'Idioma del plugin' ?></span><select class="sm-select" name="locale"><option value="auto" <?= ($cfg['locale']??'auto')==='auto'?'selected':'' ?>><?= $isEnglish ? 'Automatic from GLPI' : 'Automático según GLPI' ?></option><option value="es_ES" <?= ($cfg['locale']??'')==='es_ES'?'selected':'' ?>>Español</option><option value="en_GB" <?= ($cfg['locale']??'')==='en_GB'?'selected':'' ?>>English</option></select></label>
      </div>
      <label class="sm-switch"><input type="checkbox" name="show_logo" value="1" <?= !empty($brand['show_logo'])?'checked':'' ?>> <span><?= $isEnglish ? 'Show logo in headers' : 'Mostrar logo en cabeceras' ?><br><small><?= $isEnglish ? 'Disable it to hide the plugin logo on main pages.' : 'Si lo desactivas se oculta el logo del plugin en las páginas principales.' ?></small></span></label>
      <button class="sm-btn" type="submit"><?= $isEnglish ? 'Save general settings' : 'Guardar ajustes generales' ?></button>
    </form>
  </section>

  <section class="sm-panel" id="tab-modules">
    <form method="post" class="sm-card"><?php smcfg_token(); ?><input type="hidden" name="action" value="save_modules">
      <h2>Módulos activos</h2><p class="sm-help">Desactiva lo que no quieras usar. Al guardar, también se bloquea el acceso directo a esas páginas.</p>
      <div class="sm-switches"><?php foreach($moduleLabels as $k=>$label): ?><label class="sm-switch"><input type="checkbox" name="features[]" value="<?= smcfg_h($k) ?>" <?= !empty($features[$k])?'checked':'' ?>> <span><?= smcfg_h($label) ?><br><small data-on="<?= smcfg_h(smcfg_tr('active','Activo')) ?>" data-off="<?= smcfg_h(smcfg_tr('disabled','Desactivado')) ?>"><?= !empty($features[$k])?smcfg_h(smcfg_tr('active','Activo')):smcfg_h(smcfg_tr('disabled','Desactivado')) ?></small></span></label><?php endforeach; ?></div>
      <button class="sm-btn" type="submit">Guardar módulos</button>
    </form>
  </section>

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
          <p class="sm-help" style="margin-top:12px"><?= $isEnglish ? 'The theme editor is temporarily locked in this stable release because it is still being tested. Branding, logo, modules, buildings, classrooms and maps remain fully configurable.' : 'El editor de temas queda bloqueado temporalmente en esta versión estable porque sigue en fase de pruebas. La marca, logo, módulos, edificios, aulas y planos siguen siendo totalmente configurables.' ?></p>
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

  <section class="sm-panel" id="tab-plans">
    <div class="sm-grid">
      <div class="sm-card"><h2>Subir plano</h2><form method="post" enctype="multipart/form-data"><?php smcfg_token(); ?><input type="hidden" name="action" value="upload_plan"><div class="sm-row"><label class="sm-field"><span>Edificio</span><select class="sm-select" name="plan_building" id="planBuilding"><?php foreach($buildings as $b): ?><option value="<?= smcfg_h($b['code']??'') ?>"><?= smcfg_h(plugin_schoolmanager_label($b,'name',$b['code']??'')) ?></option><?php endforeach; ?></select></label><label class="sm-field"><span>Planta</span><select class="sm-select" name="plan_floor" id="planFloor"></select></label></div><label class="sm-field"><span>Tipo de plano</span><select class="sm-select" name="plan_mode"><option value="normal">Plano normal</option><option value="select">Plano para seleccionar aulas</option></select></label><input class="sm-input" type="file" name="plan_file" accept=".html,.htm,.svg,.png,.jpg,.jpeg,.webp" required><p class="sm-help">Soporta HTML, SVG, PNG, JPG/JPEG y WEBP.</p><button class="sm-btn" type="submit">Subir plano</button></form></div>
      <div class="sm-card"><h2>Planos actuales</h2><table class="sm-table"><thead><tr><th>Edificio</th><th>Planta</th><th>Normal</th><th>Selección</th></tr></thead><tbody><?php foreach($planRows as $r): ?><tr><td><?= smcfg_h($r['building']) ?></td><td><?= smcfg_h($r['label']) ?> <span class="sm-pill"><?= smcfg_h($r['floor']) ?></span></td><td><?= $r['plan'] ? smcfg_h($r['plan']) : '<span class="sm-bad">Sin plano</span>' ?></td><td><?= $r['select_plan'] ? smcfg_h($r['select_plan']) : '<span class="sm-bad">Sin plano</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </section>

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
  function activateTab(tab){ const btn=document.querySelector('.sm-tab[data-tab="'+tab+'"]'); if(!btn) return; document.querySelectorAll('.sm-tab').forEach(x=>x.classList.remove('active')); document.querySelectorAll('.sm-panel').forEach(x=>x.classList.remove('active')); btn.classList.add('active'); const p=document.getElementById('tab-'+tab); if(p)p.classList.add('active'); }
  document.querySelectorAll('.sm-tab').forEach(btn=>btn.addEventListener('click',()=>{ activateTab(btn.dataset.tab); history.replaceState(null,'',location.pathname+'?tab='+encodeURIComponent(btn.dataset.tab)); }));
  const params=new URLSearchParams(location.search); activateTab(params.get('tab') || (location.hash?location.hash.replace('#',''):'general'));
  const rb=document.getElementById('roomBuilding'), rf=document.getElementById('roomFloor'), pb=document.getElementById('planBuilding'), pf=document.getElementById('planFloor');
  if(rb&&rf){ fillFloors(rb,rf); rb.addEventListener('change',()=>fillFloors(rb,rf)); }
  if(pb&&pf){ fillFloors(pb,pf); pb.addEventListener('change',()=>fillFloors(pb,pf)); }

  const paletteCards=[...document.querySelectorAll('[data-palette-card]')];
  const preview=document.getElementById('smThemePreview');
  function applyPreview(){
    const vals={}; document.querySelectorAll('[data-theme-color]').forEach(i=>vals[i.dataset.themeColor]=i.value);
    if(preview){ preview.style.setProperty('--navy', vals.primary || '#07384D'); preview.style.setProperty('--teal', vals.secondary || '#075D61'); preview.style.setProperty('--red', vals.accent || '#B6252B'); preview.style.setProperty('--soft', vals.soft || '#F4FAFC'); }
    document.documentElement.style.setProperty('--sm-primary', vals.primary || '#07384D');
    document.documentElement.style.setProperty('--sm-secondary', vals.secondary || '#075D61');
    document.documentElement.style.setProperty('--sm-accent', vals.accent || '#B6252B');
    document.documentElement.style.setProperty('--sm-soft', vals.soft || '#F4FAFC');
  }
  paletteCards.forEach(card=>{
    const radio=card.querySelector('input[type=radio]');
    card.addEventListener('click',()=>{
      paletteCards.forEach(c=>c.classList.remove('active')); card.classList.add('active');
      if(radio && radio.dataset.palette !== 'custom'){
        ['primary','secondary','accent','soft'].forEach(k=>{ const input=document.querySelector('[data-theme-color="'+k+'"]'); if(input && radio.dataset[k]) input.value=radio.dataset[k]; });
      }
      applyPreview();
    });
  });
  document.querySelectorAll('[data-theme-color]').forEach(input=>input.addEventListener('input',()=>{ const custom=document.querySelector('input[data-palette="custom"]'); if(custom){custom.checked=true; paletteCards.forEach(c=>c.classList.toggle('active', c.contains(custom)));} applyPreview(); }));
  applyPreview();
})();
</script>
<?php Html::footer(); ?>
