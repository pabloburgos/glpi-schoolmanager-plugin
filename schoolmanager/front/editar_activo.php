<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');

$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$types = plugin_schoolmanager_asset_types();
$itemtype = plugin_schoolmanager_req('itemtype', 'Computer');
$id = (int)plugin_schoolmanager_req('id', 0);
if (!isset($types[$itemtype])) { $itemtype = 'Computer'; }
$current = $types[$itemtype];
if (!plugin_schoolmanager_can_update_asset($itemtype)) {
    plugin_schoolmanager_access_denied_page('Edición de activo restringida', 'Tu perfil no tiene permisos para modificar este tipo de activo.');
}
if ($id <= 0 || !class_exists($itemtype)) {
    plugin_schoolmanager_access_denied_page('Activo no encontrado', 'No se ha indicado un activo válido.');
}


function pc_direct_asset_update_if_allowed($table, $id, array $input) {
    global $DB;
    if (!function_exists('plugin_schoolmanager_is_tic_user') || !plugin_schoolmanager_is_tic_user()) { return false; }
    if (!isset($DB) || !method_exists($DB, 'update')) { return false; }
    $id = (int)$id;
    if ($id <= 0 || $table === '' || !method_exists($DB, 'tableExists') || !$DB->tableExists($table)) { return false; }
    $data = [];
    foreach ($input as $k => $v) {
        if ($k === 'id') { continue; }
        try { if (!method_exists($DB, 'fieldExists') || $DB->fieldExists($table, $k)) { $data[$k] = $v; } } catch (Throwable $e) {}
    }
    try { if (method_exists($DB, 'fieldExists') && $DB->fieldExists($table, 'date_mod')) { $data['date_mod'] = date('Y-m-d H:i:s'); } } catch (Throwable $e) {}
    if (!$data) { return false; }
    try { return $DB->update($table, $data, ['id' => $id]) !== false; } catch (Throwable $e) { return false; }
}

$message = '';
$messageType = 'info';
$obj = new $itemtype();
if (!$obj->getFromDB($id)) {
    plugin_schoolmanager_access_denied_page('Activo no encontrado', 'No se ha encontrado el activo solicitado o no tienes acceso.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && plugin_schoolmanager_post('pc_update') === '1') {
    // CSRF token is emitted; avoid false positives on plugin forms.
    $name = plugin_schoolmanager_post('name');
    $locationId = (int)plugin_schoolmanager_post('locations_id', 0);
    if ($name === '') {
        $message = 'Falta el nombre del activo.';
        $messageType = 'error';
    } else {
        $input = [
            'id' => $id,
            'name' => $name,
            'locations_id' => $locationId,
            'serial' => plugin_schoolmanager_post('serial'),
            'otherserial' => plugin_schoolmanager_post('otherserial'),
            'comment' => plugin_schoolmanager_post('comment'),
        ];
        foreach (['states_id', 'manufacturers_id', $current['type_field'], $current['model_field']] as $field) {
            if ($field) { $input[$field] = (int)plugin_schoolmanager_post($field, 0); }
        }
        $ok = $obj->update($input);
        if (!$ok) { $ok = pc_direct_asset_update_if_allowed((string)($current['table'] ?? ''), $id, $input); }
        if ($ok) {
            $message = 'Activo actualizado correctamente.';
            $messageType = 'ok';
            $obj->getFromDB($id);
        } else {
            $message = 'No se pudo actualizar el activo. Revisa permisos o campos obligatorios.';
            $messageType = 'error';
        }
    }
}

$fields = $obj->fields;
$locId = (int)($fields['locations_id'] ?? 0);
$locLabel = plugin_schoolmanager_asset_location_label($locId);
$aulasAll = require(__DIR__ . '/../inc/aulas_data.php');
$roomsJson = json_encode(array_values(array_filter($aulasAll, static fn($a) => !empty($a['id']))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

Html::header('Modificar activo', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo plugin_schoolmanager_home_button();

function gsm_edit_svg($name, $title = '') {
    $icons = [
        'home' => '<path d="M3 10.6 12 3l9 7.6"/><path d="M5.5 9.2V21h13V9.2"/><path d="M9.4 21v-6.2h5.2V21"/>',
        'back' => '<path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>',
        'external' => '<path d="M14 4h6v6"/><path d="m10 14 10-10"/><path d="M20 14v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5"/>',
        'map' => '<path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15M15 6v15"/>',
        'pin' => '<path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.3"/>',
        'name' => '<path d="M4 6h16M4 12h10M4 18h7"/><path d="M16 16.5 18.3 19 22 15"/>',
        'serial' => '<path d="M4 7h1M8 7h1M12 7h1M16 7h4M4 12h4M11 12h1M15 12h5M4 17h1M8 17h5M16 17h1M20 17h0"/>',
        'inventory' => '<path d="M7 3h10l3 3v15H4V3h3Z"/><path d="M17 3v4h4"/><path d="M8 12h8M8 16h8M8 20h4"/>',
        'state' => '<circle cx="12" cy="12" r="8"/><path d="m8.7 12.2 2.2 2.2 4.7-5"/>',
        'factory' => '<path d="M3 21h18"/><path d="M5 21V9l5 3V9l5 3V6h4v15"/><path d="M8 17h1M12 17h1M16 17h1"/>',
        'type' => '<path d="m12 3 8 4-8 4-8-4 8-4Z"/><path d="m4 12 8 4 8-4"/><path d="m4 17 8 4 8-4"/>',
        'model' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>',
        'comment' => '<path d="M4 5h16v11H8l-4 4V5Z"/><path d="M8 9h8M8 13h5"/>',
        'save' => '<path d="M5 3h12l2 2v16H5V3Z"/><path d="M8 3v6h8V3"/><path d="M8 21v-7h8v7"/>',
        'cancel' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'summary' => '<path d="M8 3h8l1 3h3v15H4V6h3l1-3Z"/><path d="M8 11h8M8 15h8M8 19h5"/>',
        'hash' => '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16"/>',
        'computer' => '<rect x="3" y="4" width="12.8" height="9.4" rx="1.8"/><path d="M7.2 17.7h4.4M9.4 13.4v4.3"/><rect x="17.2" y="6.2" width="3.8" height="10.8" rx="1.2"/><path d="M18.5 14.2h1.2M5.3 20h13.4"/>',
        'monitor' => '<rect x="4" y="5" width="16" height="11" rx="2"/><path d="M9 21h6M12 16v5"/>',
        'printer' => '<path d="M7 8V3h10v5"/><rect x="6" y="14" width="12" height="7" rx="1.5"/><rect x="4" y="8" width="16" height="8" rx="2"/><path d="M8 17h8"/>',
        'network' => '<rect x="5" y="4" width="14" height="6" rx="2"/><rect x="5" y="14" width="14" height="6" rx="2"/><path d="M8 10v4M16 10v4M9 7h.01M9 17h.01M15 7h.01M15 17h.01"/>',
        'keyboard' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h.01M11 10h.01M15 10h.01M7 14h10"/>',
    ];
    $body = $icons[$name] ?? $icons['type'];
    $label = $title !== '' ? ' aria-label="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : ' aria-hidden="true"';
    return '<svg class="gsm-ui-icon gsm-ui-icon-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none"' . $label . '>' . $body . '</svg>';
}
function gsm_edit_asset_svg($itemtype) {
    $map = ['Computer'=>'computer','Monitor'=>'monitor','Printer'=>'printer','NetworkEquipment'=>'network','Peripheral'=>'keyboard','Projector'=>'monitor'];
    return gsm_edit_svg($map[$itemtype] ?? 'type');
}

function pc_token_hidden_edit() {
    if (method_exists('Session', 'getNewCSRFToken')) {
        static $tok = null; if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . htmlspecialchars($tok, ENT_QUOTES, 'UTF-8') . '">';
    }
}
?>
<style>
.gsm-edit{--primary:#07384d;--primary2:#0b4f6c;--accent:#efa300;--danger:#e53935;--ok:#25a96b;--ink:#082f3f;--muted:#617386;--line:#dbe9ef;--soft:#f3f8fb;min-height:calc(100vh - 80px);padding:clamp(10px,1.4vw,22px);background:radial-gradient(circle at bottom right,rgba(239,163,0,.13),transparent 30%),linear-gradient(135deg,#f7fafc,#eef6fa);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}.gsm-edit *{box-sizing:border-box}.gsm-wrap{max-width:1480px;margin:0 auto;display:grid;gap:14px}.gsm-hero{position:relative;overflow:hidden;display:flex;justify-content:space-between;gap:18px;align-items:center;background:linear-gradient(120deg,#fff,#f6fbfd);border:1px solid var(--line);border-radius:26px;padding:18px 22px;box-shadow:0 18px 56px rgba(7,56,77,.08)}.gsm-hero:before{content:"";position:absolute;left:0;top:0;bottom:0;width:9px;background:linear-gradient(180deg,var(--primary),var(--accent),var(--danger))}.gsm-brand{display:flex;align-items:center;gap:18px;min-width:0}.gsm-logo{height:62px;max-width:200px;object-fit:contain}.gsm-kicker{font-weight:950;color:var(--primary2);letter-spacing:.09em}.gsm-hero h1{margin:2px 0 0;font-size:clamp(30px,3.5vw,52px);line-height:.96;color:var(--primary)}.gsm-hero p{margin:6px 0 0;color:var(--muted);font-weight:850}.gsm-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.gsm-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--line);border-radius:15px;padding:11px 14px;font-weight:950;text-decoration:none!important;cursor:pointer;white-space:nowrap}.gsm-btn.primary{background:var(--primary);color:#fff!important;border-color:var(--primary)}.gsm-btn.soft{background:#fff;color:var(--primary)!important}.gsm-btn.gold{background:var(--accent);color:#4a3300!important;border-color:var(--accent)}.gsm-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:14px}.gsm-panel{background:#fff;border:1px solid var(--line);border-radius:24px;padding:16px;box-shadow:0 14px 42px rgba(7,56,77,.07)}.gsm-panel h2{margin:0 0 6px;color:var(--primary);font-size:25px}.gsm-panel small{color:var(--muted);font-weight:850}.gsm-message{border-radius:18px;padding:13px 16px;font-weight:950}.gsm-message.ok{background:#e7f8ef;border:1px solid #b7e9cb;color:#0b6b3d}.gsm-message.error{background:#fff0f0;border:1px solid #ffc8c8;color:#9b1c1c}.gsm-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}.gsm-field.full{grid-column:1/-1}.gsm-label{display:flex;justify-content:space-between;gap:8px;font-weight:950;color:var(--primary);margin:0 0 5px}.gsm-input,.gsm-textarea{width:100%;border:1px solid var(--line);border-radius:15px;padding:12px 13px;background:#fff;color:var(--ink);font-weight:850}.gsm-textarea{min-height:110px;resize:vertical}.gsm-input:focus,.gsm-textarea:focus{outline:2px solid rgba(11,79,108,.14);border-color:var(--primary2)}.gsm-location{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid var(--line);border-radius:18px;padding:10px;background:linear-gradient(135deg,#fff,#f7fbfd)}.gsm-selected b{display:block;color:var(--primary);font-size:22px}.gsm-selected span{display:block;color:var(--muted);font-weight:850}.gsm-dropdown-wrap{border:1px solid var(--line);border-radius:18px;padding:10px;background:#fbfdfe}.gsm-dropdown-wrap .select2-container{max-width:100%!important}.gsm-submit{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding:12px;border-top:1px solid var(--line);background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-radius:0 0 22px 22px}.gsm-info-grid{display:grid;gap:10px}.gsm-info{border:1px solid var(--line);border-radius:18px;padding:13px;background:#f8fbfd}.gsm-info b{display:block;color:var(--primary);font-size:20px}.gsm-info span{color:var(--muted);font-weight:850}.gsm-native{display:grid;gap:10px;margin-top:12px}.gsm-room-modal{--teal:#0b4f6c;--teal2:#07384d;--gold:#efa300;--line:#dbe9ef;--muted:#617386}.pc-modal-overlay{z-index:9999!important}.pc-modal{font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif!important}@media(max-width:1050px){.gsm-layout{grid-template-columns:1fr}.gsm-info-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:720px){.gsm-hero{display:grid}.gsm-brand{align-items:flex-start}.gsm-logo{height:48px}.gsm-actions{justify-content:flex-start}.gsm-fields,.gsm-info-grid,.gsm-location{grid-template-columns:1fr}.gsm-submit{display:grid}.gsm-btn{width:100%}}
</style>

<style id="v267-edit-icons-polish">
/* v267: iconos y microinteracciones de Modificar activo */
.gsm-edit{padding:clamp(8px,1vw,16px)!important;background:linear-gradient(135deg,#f7fbfc 0%,#eef7f8 100%)!important;}
.gsm-edit .gsm-wrap{max-width:1460px!important;gap:12px!important;}
.gsm-edit .gsm-hero{border-radius:24px!important;padding:18px 22px!important;box-shadow:0 16px 44px rgba(7,56,77,.075)!important;background:linear-gradient(120deg,#fff 0%,#f8fcfd 72%,#fff8ea 100%)!important;}
.gsm-edit .gsm-hero:before{display:none!important;}
.gsm-edit .gsm-logo{height:56px!important;max-width:180px!important;filter:drop-shadow(0 10px 18px rgba(7,56,77,.06));}
.gsm-edit .gsm-kicker{color:#a51f27!important;font-size:14px!important;letter-spacing:.10em!important;}
.gsm-edit .gsm-hero h1{font-size:clamp(32px,4.4vw,58px)!important;letter-spacing:-.045em!important;line-height:.9!important;}
.gsm-edit .gsm-hero p{font-size:16px!important;color:#5d7082!important;}
.gsm-ui-icon{width:21px;height:21px;min-width:21px;display:inline-block;vertical-align:-.18em;stroke:currentColor;stroke-width:2.25;stroke-linecap:round;stroke-linejoin:round;fill:none;flex:0 0 auto;transition:transform .22s ease,opacity .22s ease,color .22s ease;}
.gsm-edit .gsm-btn{min-height:48px!important;padding:11px 18px!important;border-radius:16px!important;gap:10px!important;font-size:15px!important;line-height:1!important;box-shadow:0 12px 28px rgba(7,56,77,.08)!important;transition:transform .22s cubic-bezier(.2,.8,.2,1),box-shadow .22s ease,background-color .22s ease,border-color .22s ease,color .22s ease!important;}
.gsm-edit .gsm-btn:hover{transform:translateY(-3px)!important;box-shadow:0 18px 38px rgba(7,56,77,.14)!important;}
.gsm-edit .gsm-btn:hover .gsm-ui-icon{transform:scale(1.08)!important;}
.gsm-edit .gsm-btn.primary{background:linear-gradient(135deg,#07384d 0%,#0b6077 100%)!important;border-color:#07384d!important;color:#fff!important;}
.gsm-edit .gsm-btn.primary:hover{background:linear-gradient(135deg,#0a4f68 0%,#0e7287 100%)!important;}
.gsm-edit .gsm-btn.soft{background:#fff!important;color:#07384d!important;border-color:#d5e4eb!important;}
.gsm-edit .gsm-btn.soft:hover{background:#f7fbfd!important;border-color:#b9d2df!important;color:#0b6077!important;}
.gsm-edit .gsm-btn.gold{background:linear-gradient(135deg,#a51f27 0%,#b72c31 100%)!important;color:#fff!important;border-color:#8f1d1d!important;box-shadow:0 16px 34px rgba(165,31,39,.18)!important;}
.gsm-edit .gsm-btn.gold:hover{background:linear-gradient(135deg,#b72c31 0%,#c94346 100%)!important;box-shadow:0 22px 44px rgba(165,31,39,.25)!important;}
.gsm-edit .gsm-panel{border-radius:22px!important;padding:17px!important;box-shadow:0 12px 34px rgba(7,56,77,.06)!important;}
.gsm-edit .gsm-panel h2{display:flex!important;align-items:center!important;gap:10px!important;font-size:25px!important;margin-bottom:4px!important;}
.gsm-edit .gsm-panel h2 .gsm-ui-icon{width:27px;height:27px;min-width:27px;color:#07384d;stroke-width:2.15;}
.gsm-edit .gsm-label{align-items:center!important;justify-content:flex-start!important;gap:8px!important;font-size:15px!important;}
.gsm-edit .gsm-label .gsm-ui-icon{width:18px;height:18px;min-width:18px;color:#0b6077;stroke-width:2.2;}
.gsm-edit .gsm-input,.gsm-edit .gsm-textarea{min-height:44px!important;padding:10px 13px!important;border-radius:14px!important;transition:border-color .2s ease,box-shadow .2s ease,background-color .2s ease!important;}
.gsm-edit .gsm-input:hover,.gsm-edit .gsm-textarea:hover,.gsm-edit .gsm-dropdown-wrap:hover,.gsm-edit .gsm-info:hover,.gsm-edit .gsm-location:hover{border-color:#bfd7e2!important;box-shadow:0 10px 24px rgba(7,56,77,.06)!important;}
.gsm-edit .gsm-fields{gap:11px!important;margin-top:13px!important;}
.gsm-edit .gsm-location{border-radius:18px!important;padding:12px!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.7);}
.gsm-edit .gsm-selected{position:relative;padding-left:34px;}
.gsm-edit .gsm-selected:before{content:"";position:absolute;left:0;top:5px;width:22px;height:22px;background:#0b6077;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11Z'/%3E%3Ccircle cx='12' cy='10' r='2.3'/%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11Z'/%3E%3Ccircle cx='12' cy='10' r='2.3'/%3E%3C/svg%3E") center/contain no-repeat;}
.gsm-edit .gsm-selected b{font-size:21px!important;}
.gsm-edit .gsm-dropdown-wrap{border-radius:17px!important;padding:11px!important;transition:border-color .2s ease,box-shadow .2s ease!important;}
.gsm-edit .gsm-submit{align-items:center!important;gap:12px!important;padding:13px!important;}
.gsm-edit .gsm-info{position:relative;padding:15px 15px 15px 48px!important;min-height:76px;border-radius:17px!important;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease!important;}
.gsm-edit .gsm-info:hover{transform:translateY(-2px)!important;}
.gsm-edit .gsm-info .gsm-ui-icon{position:absolute;left:15px;top:18px;width:23px;height:23px;color:#0b6077;stroke-width:2.2;}
.gsm-edit .gsm-info b{font-size:20px!important;line-height:1.1;}
.gsm-edit .gsm-native .gsm-btn{width:100%;}
@media(max-width:900px){.gsm-edit .gsm-hero{display:grid!important}.gsm-edit .gsm-brand{gap:14px!important}.gsm-edit .gsm-actions{justify-content:stretch!important}.gsm-edit .gsm-actions .gsm-btn{flex:1 1 180px!important}.gsm-edit .gsm-fields{grid-template-columns:1fr!important}.gsm-edit .gsm-location{grid-template-columns:1fr!important}.gsm-edit .gsm-submit{display:grid!important;grid-template-columns:1fr!important}.gsm-edit .gsm-submit .gsm-btn{width:100%!important}}
@media(max-width:560px){.gsm-edit .gsm-brand{display:grid!important}.gsm-edit .gsm-logo{height:48px!important}.gsm-edit .gsm-hero h1{font-size:38px!important}.gsm-edit .gsm-actions{display:grid!important;grid-template-columns:1fr!important}.gsm-edit .gsm-btn{width:100%!important}}
</style>

<style id="gestion-schoolmanager-global-override"><?php @readfile(__DIR__ . '/../css/gestion-schoolmanager-theme.css'); ?></style>
<div class="gsm-edit" id="pcForm" data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>' data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>" data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>"><div class="gsm-wrap">
  <section class="gsm-hero"><div class="gsm-brand"><img class="gsm-logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo"><div><div class="gsm-kicker">MODIFICAR ACTIVO · <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></div><h1><?= htmlspecialchars(plugin_schoolmanager_asset_clean_title($itemtype, $fields), ENT_QUOTES, 'UTF-8') ?></h1><p>Edita ubicación, datos de inventario, estado, fabricante, tipo y modelo.</p></div></div><div class="gsm-actions"><a class="gsm-btn soft" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/gestion_activos.php?itemtype=' . urlencode($itemtype) . '&v=267', ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('back') ?> Volver a gestión</a><a class="gsm-btn primary" href="<?= htmlspecialchars($root . $current['native'] . '?id=' . $id, ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('external') ?> Vista nativa GLPI</a></div></section>
  <?php if ($message): ?><div class="gsm-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <section class="gsm-layout"><main class="gsm-panel"><h2><?= gsm_edit_asset_svg($itemtype) ?> Datos editables</h2><small>Los cambios se guardan directamente sobre el activo de GLPI.</small>
    <form method="post" id="assetEditForm"><input type="hidden" name="pc_update" value="1"><input type="hidden" name="itemtype" value="<?= htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= $id ?>"><?php pc_token_hidden_edit(); ?><input type="hidden" name="locations_id" id="pcLocationId" value="<?= $locId ?>"><input type="hidden" name="location_label" id="pcLocationLabel" value="<?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="location_code" id="pcLocationCode" value="">
      <div class="gsm-fields">
        <label class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('name') ?> Nombre *</span><input class="gsm-input" name="name" value="<?= htmlspecialchars((string)($fields['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
        <div class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('pin') ?> Ubicación</span><div class="gsm-location"><div class="gsm-selected" id="pcSelectedLocation"><b><?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?></b><span><?= $locId > 0 ? 'ID GLPI ' . $locId : 'Selecciona desde plano o lista' ?></span></div><button type="button" class="gsm-btn primary" id="pcOpenSelector"><?= gsm_edit_svg('map') ?> Abrir plano de clases</button></div></div>
        <label class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('serial') ?> Número de serie</span><input class="gsm-input" name="serial" value="<?= htmlspecialchars((string)($fields['serial'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <label class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('inventory') ?> Número de inventario</span><input class="gsm-input" name="otherserial" value="<?= htmlspecialchars((string)($fields['otherserial'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <div class="gsm-field gsm-dropdown-wrap"><span class="gsm-label"><?= gsm_edit_svg('state') ?> Estado</span><?php if (class_exists('State')) { Dropdown::show('State', ['name'=>'states_id', 'value'=>(int)($fields['states_id'] ?? 0)]); } ?></div>
        <div class="gsm-field gsm-dropdown-wrap"><span class="gsm-label"><?= gsm_edit_svg('factory') ?> Fabricante</span><?php if (class_exists('Manufacturer')) { Dropdown::show('Manufacturer', ['name'=>'manufacturers_id', 'value'=>(int)($fields['manufacturers_id'] ?? 0)]); } ?></div>
        <div class="gsm-field gsm-dropdown-wrap"><span class="gsm-label"><?= gsm_edit_svg('type') ?> Tipo</span><?php if (class_exists($current['type_class'])) { Dropdown::show($current['type_class'], ['name'=>$current['type_field'], 'value'=>(int)($fields[$current['type_field']] ?? 0)]); } ?></div>
        <div class="gsm-field gsm-dropdown-wrap"><span class="gsm-label"><?= gsm_edit_svg('model') ?> Modelo</span><?php if (class_exists($current['model_class'])) { Dropdown::show($current['model_class'], ['name'=>$current['model_field'], 'value'=>(int)($fields[$current['model_field']] ?? 0)]); } ?></div>
        <label class="gsm-field full"><span class="gsm-label"><?= gsm_edit_svg('comment') ?> Comentarios</span><textarea class="gsm-textarea" name="comment"><?= htmlspecialchars((string)($fields['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
      </div><div class="gsm-submit"><a class="gsm-btn soft" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/gestion_activos.php?itemtype=' . urlencode($itemtype) . '&v=234', ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('cancel') ?> Cancelar</a><button class="gsm-btn gold" type="submit"><?= gsm_edit_svg('save') ?> Guardar cambios</button></div>
    </form></main>
    <aside class="gsm-panel"><h2><?= gsm_edit_svg('summary') ?> Resumen rápido</h2><small>Información útil para revisar antes de guardar.</small><div class="gsm-info-grid" style="margin-top:14px"><div class="gsm-info"><?= gsm_edit_asset_svg($itemtype) ?><span>Tipo</span><b><?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></b></div><div class="gsm-info"><?= gsm_edit_svg('hash') ?><span>ID GLPI</span><b>#<?= $id ?></b></div><div class="gsm-info"><?= gsm_edit_svg('pin') ?><span>Ubicación actual</span><b><?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?></b></div><div class="gsm-info"><?= gsm_edit_svg('inventory') ?><span>Inventario</span><b><?= htmlspecialchars((string)($fields['otherserial'] ?? 'Sin dato'), ENT_QUOTES, 'UTF-8') ?></b></div></div><div class="gsm-native"><a class="gsm-btn primary" href="<?= htmlspecialchars($root . $current['native'] . '?id=' . $id, ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('external') ?> Abrir ficha nativa</a><a class="gsm-btn soft" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/nuevo_activo.php?itemtype=' . urlencode($itemtype) . '&v=267', ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('plus') ?> Crear otro <?= htmlspecialchars(mb_strtolower($current['label'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></a></div></aside>
  </section>
</div><?php include(__DIR__ . '/../inc/location_modal_markup.php'); ?></div>
<?php include(__DIR__ . '/../inc/location_modal_script.php'); ?>
<script>document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('pcUseLocation');if(b)b.textContent='Aplicar ubicación al activo';});</script>
<script>document.getElementById('assetEditForm')?.addEventListener('submit',function(){const b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.classList.add('is-loading');b.innerHTML='<?= gsm_edit_svg('save') ?> Guardando...';}});</script>
<script src="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/js/custom-combobox.js?v=267"></script>

<?php Html::footer(); ?>


<style id="v258-iconos-finales">
/* v258: icon system final - no solid squares */
.pc-svgicon,.gsm-svgicon{
  display:inline-block!important;width:20px!important;height:20px!important;min-width:20px!important;
  background:transparent!important;color:currentColor!important;border:0!important;box-shadow:none!important;
  -webkit-mask:none!important;mask:none!important;overflow:visible!important;text-indent:0!important;line-height:1!important;
  flex:0 0 auto!important;position:relative!important;vertical-align:middle!important;
}
.pc-svgicon:before,.gsm-svgicon:before{
  content:""!important;display:block!important;width:100%!important;height:100%!important;
  background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;
}
.av .av-btn .pc-svgicon,.av .av-btn.home .pc-svgicon,.av .av-btn.primary .pc-svgicon,
.pc-form .pc-btn .pc-svgicon,.pc-form #pcOpenSelector .pc-svgicon,.pc-form .pc-btn-create .pc-svgicon,.pc-form .pc-btn-cancel .pc-svgicon,.pc-form .pc-btn-back .pc-svgicon,
.pc-home .pc-action .pc-svgicon,.pc-home .pc-asset-icon .pc-svgicon,.pc-home .pc-ico.pc-svgicon{
  background:transparent!important;-webkit-mask:none!important;mask:none!important;color:currentColor!important;
}
.av .av-btn .pc-svgicon:before,.av .av-btn.home .pc-svgicon:before,.av .av-btn.primary .pc-svgicon:before,
.pc-form .pc-btn .pc-svgicon:before,.pc-form #pcOpenSelector .pc-svgicon:before,.pc-form .pc-btn-create .pc-svgicon:before,.pc-form .pc-btn-cancel .pc-svgicon:before,.pc-form .pc-btn-back .pc-svgicon:before,
.pc-home .pc-action .pc-svgicon:before,.pc-home .pc-asset-icon .pc-svgicon:before,.pc-home .pc-ico.pc-svgicon:before{
  display:block!important;content:""!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;
}
.av .av-btn.home .pc-svgicon,.pc-form .pc-btn-home .pc-svgicon,.pc-form .pc-btn-create .pc-svgicon,.pc-form #pcOpenSelector .pc-svgicon,.av .av-btn.primary .pc-svgicon{color:#fff!important;}
.pc-btn-svg{width:20px;height:20px;display:block;stroke:currentColor;stroke-width:2.35;stroke-linecap:round;stroke-linejoin:round;fill:none;flex:0 0 auto;position:relative;z-index:1;}
</style>

