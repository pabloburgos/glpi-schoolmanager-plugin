<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');

$aulasAll = require(__DIR__ . '/../inc/aulas_data.php');
$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$message = '';
$messageType = 'info';

function pc_req($key, $default = '') { return isset($_REQUEST[$key]) ? trim((string)$_REQUEST[$key]) : $default; }
function pc_token_field() { static $tok = null; if (method_exists('Session', 'getNewCSRFToken')) { if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . htmlspecialchars($tok, ENT_QUOTES, 'UTF-8') . '">'; } }

function pc_direct_asset_insert_if_allowed($itemtype, array $current, array $input) {
    global $DB;
    if (!function_exists('plugin_schoolmanager_is_tic_user') || !plugin_schoolmanager_is_tic_user()) { return 0; }
    if (!isset($DB) || !method_exists($DB, 'insert')) { return 0; }
    $table = (string)($current['table'] ?? '');
    if ($table === '' || !method_exists($DB, 'tableExists') || !$DB->tableExists($table)) { return 0; }
    $data = [];
    foreach ($input as $k => $v) {
        if ($k === 'id') { continue; }
        try {
            if (!method_exists($DB, 'fieldExists') || $DB->fieldExists($table, $k)) { $data[$k] = $v; }
        } catch (Throwable $e) {}
    }
    foreach (['date_creation','date_mod'] as $f) {
        try { if (method_exists($DB, 'fieldExists') && $DB->fieldExists($table, $f)) { $data[$f] = date('Y-m-d H:i:s'); } } catch (Throwable $e) {}
    }
    if (!$data || empty($data['name'])) { return 0; }
    try {
        $ok = $DB->insert($table, $data);
        if ($ok !== false) {
            if (method_exists($DB, 'insertId')) { return (int)$DB->insertId(); }
            if (method_exists($DB, 'insert_id')) { return (int)$DB->insert_id(); }
        }
    } catch (Throwable $e) {}
    return 0;
}

$assetTypes = [
    'Computer' => ['table'=>'glpi_computers', 'label'=>'Ordenador', 'icon'=>'pc-i-computer', 'list'=>'/front/computer.php', 'form'=>'/front/computer.form.php', 'type_field'=>'computertypes_id', 'type_class'=>'ComputerType', 'model_field'=>'computermodels_id', 'model_class'=>'ComputerModel'],
    'Monitor' => ['table'=>'glpi_monitors', 'label'=>'Monitor', 'icon'=>'pc-i-monitor', 'list'=>'/front/monitor.php', 'form'=>'/front/monitor.form.php', 'type_field'=>'monitortypes_id', 'type_class'=>'MonitorType', 'model_field'=>'monitormodels_id', 'model_class'=>'MonitorModel'],
    'Printer' => ['table'=>'glpi_printers', 'label'=>'Impresora', 'icon'=>'pc-i-printer', 'list'=>'/front/printer.php', 'form'=>'/front/printer.form.php', 'type_field'=>'printertypes_id', 'type_class'=>'PrinterType', 'model_field'=>'printermodels_id', 'model_class'=>'PrinterModel'],
    'NetworkEquipment' => ['table'=>'glpi_networkequipments', 'label'=>'Dispositivo de red', 'icon'=>'pc-i-network', 'list'=>'/front/networkequipment.php', 'form'=>'/front/networkequipment.form.php', 'type_field'=>'networkequipmenttypes_id', 'type_class'=>'NetworkEquipmentType', 'model_field'=>'networkequipmentmodels_id', 'model_class'=>'NetworkEquipmentModel'],
    'Peripheral' => ['table'=>'glpi_peripherals', 'label'=>'Periférico', 'icon'=>'pc-i-keyboard', 'list'=>'/front/peripheral.php', 'form'=>'/front/peripheral.form.php', 'type_field'=>'peripheraltypes_id', 'type_class'=>'PeripheralType', 'model_field'=>'peripheralmodels_id', 'model_class'=>'PeripheralModel'],
];

$itemtype = pc_req('itemtype', 'Computer');
if (!isset($assetTypes[$itemtype])) { $itemtype = 'Computer'; }
$current = $assetTypes[$itemtype];
if (!plugin_schoolmanager_can_create_asset($itemtype)) {
    plugin_schoolmanager_access_denied_page('Alta de activos restringida');
}

if (isset($_REQUEST['pc_create']) && $_REQUEST['pc_create'] === '1') {
    $itemtype = pc_req('itemtype', 'Computer');
    if (!isset($assetTypes[$itemtype])) { $itemtype = 'Computer'; }
    $current = $assetTypes[$itemtype];
    $name = pc_req('name');
    $locationId = (int)pc_req('locations_id', 0);
    if ($name === '') { $message = 'Falta el nombre del activo.'; $messageType = 'error'; }
    elseif ($locationId <= 0) { $message = 'Selecciona una ubicación desde el plano o la lista.'; $messageType = 'error'; }
    elseif (!class_exists($itemtype)) { $message = 'No se encuentra la clase GLPI ' . $itemtype . '.'; $messageType = 'error'; }
    else {
        $obj = new $itemtype();
        $input = [
            'name' => $name,
            'entities_id' => Session::getActiveEntity(),
            'locations_id' => $locationId,
            'serial' => pc_req('serial'),
            'otherserial' => pc_req('otherserial'),
            'comment' => pc_req('comment'),
            'is_deleted' => 0,
            'is_template' => 0,
        ];
        foreach (['states_id' => 'State', 'manufacturers_id' => 'Manufacturer'] as $field => $class) {
            $value = (int)pc_req($field, 0);
            if ($value > 0) { $input[$field] = $value; }
        }
        foreach ([$current['type_field'], $current['model_field']] as $field) {
            $value = (int)pc_req($field, 0);
            if ($value > 0) { $input[$field] = $value; }
        }
        $newId = $obj->add($input);
        if (!$newId) { $newId = pc_direct_asset_insert_if_allowed($itemtype, $current, $input); }
        if ($newId) { Html::redirect($root . $current['form'] . '?id=' . (int)$newId); }
        $detail = '';
        if (method_exists($obj, 'getErrorMessages')) {
            $errs = $obj->getErrorMessages();
            if (is_array($errs) && $errs) { $detail = ' Detalle: ' . implode(' | ', array_map('strval', $errs)); }
        } elseif (method_exists($obj, 'getErrorMessage')) {
            $err = $obj->getErrorMessage(); if ($err) { $detail = ' Detalle: ' . $err; }
        }
        $message = 'No se pudo crear el activo. Revisa permisos o campos obligatorios.' . $detail;
        $messageType = 'error';
    }
}

Html::header('Alta de activo guiada', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
$roomsJson = json_encode(array_values(array_filter($aulasAll, static fn($a) => !empty($a['id']))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<style>
.pc-form{--teal:#2a9d8f;--teal2:#075d61;--gold:#efa300;--ink:#073c44;--muted:#617781;--line:#d3ece8;--bg:#f4fbfa;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);min-height:calc(100vh - 80px);padding:clamp(8px,1vw,16px);background:linear-gradient(135deg,#f8fffe,#fffaf0)}.pc-form *{box-sizing:border-box}.pc-card{max-width:1480px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 14px 40px rgba(15,111,103,.08);overflow:hidden}.pc-head{display:flex;align-items:center;gap:18px;padding:14px 20px;border-bottom:1px solid var(--line);background:linear-gradient(90deg,#fff,#f1fffd)}.pc-logo{height:56px;max-width:190px;object-fit:contain}.pc-head small{font-weight:950;color:var(--teal2);letter-spacing:.08em}.pc-head h1{margin:2px 0 0;font-size:clamp(26px,3vw,42px);line-height:1;color:#075d61}.pc-body{padding:14px}.pc-types{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin-bottom:12px}.pc-type{border:1px solid var(--line);background:#fff;color:#075d61;border-radius:15px;padding:10px;display:flex;gap:8px;align-items:center;justify-content:center;font-weight:950;text-decoration:none!important;min-height:50px}.pc-type.active{background:#0b938b;color:#fff;border-color:#0b938b}.pc-section{border:1px solid var(--line);border-radius:18px;background:#fff;padding:14px}.pc-section h2{margin:0 0 12px;color:#075d61}.pc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pc-field.full{grid-column:1/-1}.pc-label{display:block;font-weight:950;color:#19353d;margin-bottom:6px}.pc-input,.pc-textarea{width:100%;border:1px solid #d8e7e5;border-radius:13px;padding:11px 13px;font-weight:800;color:#1b3841;background:#fff;outline:none}.pc-textarea{min-height:90px;resize:vertical}.pc-location{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end}.pc-selected{border:1px solid var(--line);border-radius:15px;background:#f4fbfa;padding:10px;min-height:62px}.pc-selected b{display:block;color:#075d61;font-size:19px}.pc-selected span{font-weight:850;color:var(--muted)}.pc-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:13px;padding:11px 15px;font-weight:950;text-decoration:none!important;cursor:pointer;white-space:nowrap}.pc-btn.primary{background:#0b756d;color:#fff!important}.pc-btn.secondary{background:#eef9f7;color:#075d61!important;border:1px solid var(--line)}.pc-btn.gold{background:var(--gold);color:#fff!important}.pc-submit{display:flex;gap:10px;justify-content:flex-end;padding:14px}.pc-message{margin:14px;border-radius:15px;padding:13px 15px;font-weight:900}.pc-message.error{background:#ffe8e8;color:#9b1c1c;border:1px solid #ffb8b8}.pc-message.info{background:#eaf8ff;color:#0b5d70;border:1px solid #bdeeff}.pc-dropdown-wrap select,.pc-dropdown-wrap .select2-container{width:100%!important}.pc-modal-backdrop{position:fixed;inset:0;background:rgba(0,25,30,.42);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}.pc-modal-backdrop.show{display:flex}.pc-modal{width:min(1180px,96vw);height:min(760px,92vh);background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 28px 90px rgba(0,0,0,.28);display:grid;grid-template-rows:auto 1fr}.pc-modal-head{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--line);background:#f5fffd}.pc-modal-head h2{margin:0;flex:1;color:#075d61}.pc-modal-body{display:grid;grid-template-columns:360px minmax(0,1fr);min-height:0}.pc-modal-left{padding:12px;border-right:1px solid var(--line);display:grid;grid-template-rows:auto auto minmax(0,1fr);gap:10px;min-height:0}.pc-tabs{display:grid;grid-template-columns:1fr 1fr;gap:6px;background:#effaf8;border:1px solid var(--line);padding:5px;border-radius:16px}.pc-tab{border:0;border-radius:12px;background:transparent;color:#075d61;font-weight:950;padding:10px;cursor:pointer}.pc-tab.active{background:var(--teal);color:#fff}.pc-room-search{border:1px solid var(--line);border-radius:14px;padding:12px;font-weight:850}.pc-room-list{overflow:auto;border:1px solid var(--line);border-radius:16px}.pc-room{width:100%;display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:8px;align-items:center;border:0;border-bottom:1px solid #eef5f3;background:#fff;padding:10px;cursor:pointer;text-align:left}.pc-room:hover,.pc-room.active{background:#fff8df}.pc-avatar{display:grid;place-items:center;width:42px;height:42px;border-radius:14px;background:#e8f7f5;color:#075d61;font-weight:950}.pc-room-name{display:block;font-weight:950;color:#075d61;font-size:17px;line-height:1.05}.pc-room-desc{display:block;font-weight:800;color:#617781;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;margin-top:2px}.pc-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;background:#edf8f6;border-radius:9px;padding:6px 8px;font-weight:900;color:#075d61}.pc-modal-right{display:grid;grid-template-rows:auto minmax(0,1fr) auto;gap:10px;padding:12px;min-height:0}.pc-floorbar{display:flex;gap:8px;flex-wrap:wrap}.pc-floor{border:1px solid var(--line);background:#fff;border-radius:12px;padding:9px 12px;font-weight:950;color:#075d61;cursor:pointer}.pc-floor.active{background:var(--gold);border-color:var(--gold);color:#fff}.pc-plan{border:1px solid var(--line);border-radius:18px;overflow:hidden;min-height:0;background:#fff}.pc-plan iframe{width:100%;height:100%;border:0}.pc-choice{border:1px solid var(--line);border-radius:18px;background:#f7fffd;padding:12px;display:flex;gap:12px;align-items:center}.pc-choice div{flex:1}.pc-choice b{font-size:22px;color:#075d61}.pc-choice span{display:block;font-weight:850;color:var(--muted)}@media(max-width:1100px){.pc-types{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.pc-fields,.pc-location{grid-template-columns:1fr}.pc-types{grid-template-columns:repeat(2,1fr)}.pc-submit{display:grid}.pc-modal{width:100vw;height:100vh;border-radius:0}.pc-modal-body{grid-template-columns:1fr}.pc-modal-left{max-height:310px}.pc-choice{display:grid}.pc-btn{width:100%}}@media(max-width:480px){.pc-types{grid-template-columns:1fr}.pc-logo{height:44px}.pc-head{align-items:flex-start}}

/* v180: selector de tipo de activo más profesional */
.pc-types{display:grid!important;grid-template-columns:repeat(6,minmax(0,1fr))!important;gap:10px!important;margin:4px 0 14px!important;padding:7px!important;border:1px solid var(--line)!important;border-radius:20px!important;background:linear-gradient(135deg,#f7fbfd,#ffffff)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.8)!important}
.pc-type{position:relative!important;min-height:58px!important;border-radius:16px!important;border:1px solid #d5e6ec!important;background:#fff!important;color:#07384d!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;gap:10px!important;padding:10px 12px!important;text-align:left!important;box-shadow:0 8px 18px rgba(7,56,77,.04)!important;transition:.18s ease!important;overflow:hidden!important}
.pc-type:before{content:"";position:absolute;left:0;top:0;right:0;height:4px;background:linear-gradient(90deg,#07384d,#0b5f7a,#efa300);opacity:.55!important}.pc-type span{display:grid!important;place-items:center!important;width:34px!important;height:34px!important;border-radius:13px!important;background:#eef6fa!important;border:1px solid #d6e9ef!important;flex:0 0 auto!important;font-size:17px!important}.pc-type:hover{transform:translateY(-2px)!important;border-color:#0b5f7a!important;box-shadow:0 14px 30px rgba(7,56,77,.10)!important;background:#fdfefe!important}.pc-type.active{background:linear-gradient(135deg,#07384d,#0b5f7a)!important;color:#fff!important;border-color:#07384d!important;box-shadow:0 16px 38px rgba(7,56,77,.24)!important}.pc-type.active:before{height:5px!important;background:#efa300!important;opacity:1!important}.pc-type.active span{background:rgba(255,255,255,.16)!important;border-color:rgba(255,255,255,.25)!important;color:#fff!important}.pc-section h2{display:flex!important;align-items:center!important;gap:12px!important}.pc-section h2:after{content:"";height:3px;flex:1;border-radius:999px;background:linear-gradient(90deg,#efa300,transparent);opacity:.65}
@media(max-width:1180px){.pc-types{grid-template-columns:repeat(3,minmax(0,1fr))!important}}@media(max-width:680px){.pc-types{grid-template-columns:1fr!important}.pc-type{justify-content:flex-start!important}}

</style>
<style id="gestion-schoolmanager-global-override"><?php @readfile(__DIR__ . '/../css/gestion-schoolmanager-theme.css'); ?></style>

<div class="pc-form" id="pcForm" data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>' data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>" data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>">
  <div class="pc-card">
    <div class="pc-head pc-head-v253"><div class="pc-head-brand"><img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" class="pc-logo" alt="Logo"><div><small>GLPI SCHOOL MANAGER</small><h1>Alta guiada de activos</h1></div></div><div class="pc-head-actions"><a class="pc-btn pc-btn-home pc-btn-icon" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>" aria-label="Inicio"><svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.75 10.5 12 4.25l8.25 6.25"/><path d="M6.75 9.75v9.5h10.5v-9.5"/><path d="M10 19.25V14.5a2 2 0 0 1 4 0v4.75"/></svg><span>Inicio</span></a></div></div>
    <?php if ($message): ?><div class="pc-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="pc-body">
      <div class="pc-types">
        <?php foreach ($assetTypes as $type => $info): ?><a class="pc-type <?= $type === $itemtype ? 'active' : '' ?>" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/nuevo_activo.php?itemtype=' . urlencode($type) . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><span class="pc-svgicon <?= htmlspecialchars($info['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span> <?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>
      </div>
      <form method="get" id="assetGuidedForm">
        <?php pc_token_field(); ?><input type="hidden" name="pc_create" value="1"><input type="hidden" name="v" value="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="itemtype" value="<?= htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="locations_id" id="pcLocationId" value=""><input type="hidden" name="location_label" id="pcLocationLabel" value=""><input type="hidden" name="location_code" id="pcLocationCode" value="">
        <section class="pc-section"><h2><span class="pc-svgicon <?= htmlspecialchars($current['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span> <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></h2><div class="pc-fields">
          <label class="pc-field"><span class="pc-label">Nombre *</span><input class="pc-input" name="name" placeholder="Ej: <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> aula 206" required></label>
          <div class="pc-field"><span class="pc-label">Ubicación *</span><div class="pc-location"><div class="pc-selected" id="pcSelectedLocation"><b>Sin ubicación</b><span>Selecciona desde plano o lista</span></div><button type="button" class="pc-btn primary" id="pcOpenSelector"><svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Elegir ubicación</span></button></div></div>
          <label class="pc-field"><span class="pc-label">Número de serie</span><input class="pc-input" name="serial" placeholder="Número de serie / etiqueta del fabricante"></label>
          <label class="pc-field"><span class="pc-label">Número de inventario</span><input class="pc-input" name="otherserial" placeholder="Código interno / etiqueta de inventario"></label>
          <div class="pc-field pc-dropdown-wrap"><span class="pc-label">Estado</span><?php if (class_exists('State')) { Dropdown::show('State', ['name'=>'states_id', 'value'=>0]); } ?></div>
          <div class="pc-field pc-dropdown-wrap"><span class="pc-label">Fabricante</span><?php if (class_exists('Manufacturer')) { Dropdown::show('Manufacturer', ['name'=>'manufacturers_id', 'value'=>0]); } ?></div>
          <div class="pc-field pc-dropdown-wrap"><span class="pc-label">Tipo</span><?php if (class_exists($current['type_class'])) { Dropdown::show($current['type_class'], ['name'=>$current['type_field'], 'value'=>0]); } ?></div>
          <div class="pc-field pc-dropdown-wrap"><span class="pc-label">Modelo</span><?php if (class_exists($current['model_class'])) { Dropdown::show($current['model_class'], ['name'=>$current['model_field'], 'value'=>0]); } ?></div>
          <label class="pc-field full"><span class="pc-label">Comentarios</span><textarea class="pc-textarea" name="comment" placeholder="Observaciones, puesto, uso previsto, aula, periféricos asociados..."></textarea></label>
        </div></section>
        <div class="pc-submit"><a class="pc-btn secondary pc-btn-cancel" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>" onclick="if(history.length>1){history.back();return false;}"><svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg><span>Cancelar</span></a><button class="pc-btn gold pc-btn-create pc-btn-icon" type="submit"><svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6v12"/><path d="M6 12h12"/></svg><span>Crear <?= htmlspecialchars(mb_strtolower($current['label'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span></button></div>
      </form>
    </div>
  </div>
  <?php include(__DIR__ . '/../inc/location_modal_markup.php'); ?>
</div>
<?php include(__DIR__ . '/../inc/location_modal_script.php'); ?>
<script>document.getElementById('assetGuidedForm')?.addEventListener('submit',function(e){if(!document.getElementById('pcLocationId').value){e.preventDefault();alert('Selecciona una ubicación primero.');return;}const b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.classList.add('is-loading');const label=b.querySelector('span:last-child');if(label){label.textContent='Creando activo...';}}});</script>
<script src="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/js/custom-combobox.js?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"></script>

<style id="v194-nuevo-activo-cleanup">
/* v194: quitar colorines de las tarjetas superiores y mejorar responsive */
.pc-form,.pc-card,.pc-body,.pc-types,.pc-fields,.pc-location{min-width:0 !important;}
.pc-card{overflow:hidden !important;}
.pc-body{overflow-x:hidden !important;}
.pc-types{grid-template-columns:repeat(6,minmax(0,1fr)) !important;gap:10px !important;}
.pc-type{min-width:0 !important;white-space:normal !important;word-break:break-word !important;align-items:center !important;}
.pc-type:before{display:none !important;content:none !important;}
.pc-type.active{background:linear-gradient(135deg,#c62828,#a91824) !important;border-color:#b9202a !important;}
.pc-type.active span{background:rgba(255,255,255,.14) !important;border-color:rgba(255,255,255,.22) !important;}
.pc-section h2:after{display:none !important;content:none !important;}
@media (max-width: 1280px){.pc-types{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}}
@media (max-width: 820px){.pc-fields,.pc-location,.pc-submit{grid-template-columns:1fr !important;display:grid !important;}.pc-types{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}.pc-btn{width:100% !important;}.pc-head{display:block !important;}.pc-logo{margin-bottom:10px !important;}}
@media (max-width: 560px){.pc-types{grid-template-columns:1fr !important;}.pc-type{justify-content:flex-start !important;}}
</style>


<style id="v197-nuevo-activo-smooth-fix">
.pc-form .pc-type:before{display:none!important;content:none!important}.pc-form .pc-type.active{background:linear-gradient(135deg,#b6252b,#951923)!important;color:#fff!important;border-color:#951923!important}.pc-form .pc-type.active span{background:#fff!important;border-color:#fff!important;color:#b6252b!important}.pc-form .pc-type.active .pc-svgicon,.pc-form .pc-type.active svg{color:#b6252b!important;stroke:#b6252b!important}.pc-form .pc-head small{color:#b6252b!important;-webkit-text-fill-color:#b6252b!important}.pc-form{animation:pcFormFade .16s ease both}@keyframes pcFormFade{from{opacity:.96}to{opacity:1}}
</style>


<style id="v198-nuevo-activo-final-fix">
.pc-form .pc-head small{color:#b6252b!important;-webkit-text-fill-color:#b6252b!important}.pc-form .pc-type:before,.pc-form .pc-section h2:after{display:none!important;content:none!important}.pc-form .pc-type.active{background:linear-gradient(135deg,#b6252b,#951923)!important;color:#fff!important;border-color:#951923!important}.pc-form .pc-type.active span{background:#fff!important;color:#b6252b!important;border-color:#fff!important}.pc-form .pc-type.active .pc-svgicon,.pc-form .pc-type.active svg{color:#b6252b!important;stroke:#b6252b!important}.pc-form .pc-type{transition:background-color .14s ease,border-color .14s ease,color .14s ease!important}
</style>
<style id="v253-nuevo-activo">

/* v253: alta de activos unificada con gestionar activos */
.pc-form > .pc-home-floating,
.pc-form ~ .pc-home-floating,
body > .pc-home-floating{display:none!important;}
.pc-head-v253{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:18px!important;padding:18px 22px!important;background:linear-gradient(135deg,#ffffff 0%,#f8fbfd 72%,#fff8e8 100%)!important;border-bottom:1px solid #d7e6ec!important;}
.pc-head-brand{display:flex!important;align-items:center!important;gap:18px!important;min-width:0!important;}
.pc-head-actions{display:flex!important;align-items:center!important;gap:12px!important;flex:0 0 auto!important;}
.pc-head-v253 .pc-logo{height:70px!important;max-width:220px!important;object-fit:contain!important;background:transparent!important;box-shadow:none!important;}
.pc-head-v253 h1{color:#07384d!important;letter-spacing:-.045em!important;}
.pc-head-v253 small{color:#b6252b!important;-webkit-text-fill-color:#b6252b!important;}
.pc-form .pc-svgicon{display:inline-block!important;width:22px!important;height:22px!important;background:currentColor!important;mask:var(--pc-icon) center/contain no-repeat!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;flex:0 0 auto!important;}
.pc-form .pc-i-computer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273%27%20y%3D%274%27%20width%3D%2712.8%27%20height%3D%279.4%27%20rx%3D%271.8%27%2F%3E%3Cpath%20d%3D%27M7.2%2017.7h4.4M9.4%2013.4v4.3%27%2F%3E%3Crect%20x%3D%2717.2%27%20y%3D%276.2%27%20width%3D%273.8%27%20height%3D%2710.8%27%20rx%3D%271.2%27%2F%3E%3Cpath%20d%3D%27M18.5%2014.2h1.2M5.3%2020h13.4%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-form .pc-i-monitor{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='4' y='5' width='16' height='11' rx='2'/%3E%3Cpath d='M8 21h8M12 16v5'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-printer{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 8V4h10v4'/%3E%3Crect x='7' y='14' width='10' height='6' rx='1'/%3E%3Cpath d='M7 18H5a2 2 0 0 1-2-2v-5a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v5a2 2 0 0 1-2 2h-2'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-network{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='5' y='4' width='14' height='6' rx='2'/%3E%3Crect x='5' y='14' width='14' height='6' rx='2'/%3E%3Cpath d='M8 7h.01M8 17h.01M12 10v4M9 12h6'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-keyboard{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='6' width='18' height='12' rx='2'/%3E%3Cpath d='M7 10h.01M11 10h.01M15 10h.01M17 14H7'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-phone{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.7.6 2.5a2 2 0 0 1-.4 2.1L8 9.6a16 16 0 0 0 6.4 6.4l1.3-1.3a2 2 0 0 1 2.1-.4c.8.3 1.6.5 2.5.6a2 2 0 0 1 1.7 2z'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-home{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3.75 10.5 12 4.25l8.25 6.25'/%3E%3Cpath d='M6.75 9.75v9.5h10.5v-9.5'/%3E%3Cpath d='M10 19.25V14.5a2 2 0 0 1 4 0v4.75'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-plus{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='M12 5v14M5 12h14'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-arrow-left{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.35' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M15 6l-6 6 6 6'/%3E%3Cpath d='M9 12h11'/%3E%3C/svg%3E")!important;}
.pc-form .pc-i-location{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z'/%3E%3Ccircle cx='12' cy='10' r='2.5'/%3E%3C/svg%3E")!important;}
.pc-form .pc-type{border-radius:20px!important;min-height:72px!important;justify-content:flex-start!important;padding:12px 14px!important;gap:12px!important;background:#fff!important;border-color:#d7e6ec!important;box-shadow:0 10px 24px rgba(7,56,77,.055)!important;transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, border-color .22s ease, background .22s ease!important;}
.pc-form .pc-type span.pc-svgicon{width:46px!important;height:46px!important;border-radius:16px!important;padding:0!important;background:#07384d!important;color:#fff!important;box-shadow:0 12px 26px rgba(7,56,77,.12)!important;mask:none!important;-webkit-mask:none!important;display:grid!important;place-items:center!important;position:relative!important;}
.pc-form .pc-type span.pc-svgicon:before{content:"";width:24px;height:24px;background:currentColor;mask:var(--pc-icon) center/contain no-repeat;-webkit-mask:var(--pc-icon) center/contain no-repeat;display:block;}
.pc-form .pc-type:hover{transform:translateY(-4px)!important;box-shadow:0 20px 42px rgba(7,56,77,.12)!important;border-color:#b9d2dc!important;}
.pc-form .pc-type.active{background:#fff8f8!important;color:#07384d!important;border-color:#e9b9bd!important;box-shadow:0 18px 42px rgba(139,30,30,.12)!important;}
.pc-form .pc-type.active span.pc-svgicon{background:#8b1e1e!important;color:#fff!important;border-color:#8b1e1e!important;}
.pc-form .pc-section h2 .pc-svgicon{width:42px!important;height:42px!important;border-radius:15px!important;background:#07384d!important;color:#fff!important;mask:none!important;-webkit-mask:none!important;display:grid!important;place-items:center!important;}
.pc-form .pc-section h2 .pc-svgicon:before{content:"";width:23px;height:23px;background:currentColor;mask:var(--pc-icon) center/contain no-repeat;-webkit-mask:var(--pc-icon) center/contain no-repeat;display:block;}
.pc-form .pc-btn{border-radius:18px!important;min-height:54px!important;padding:0 22px!important;font-size:16px!important;position:relative!important;overflow:hidden!important;transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, border-color .22s ease, background .22s ease!important;}
.pc-form .pc-btn:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.06) 30%,rgba(255,255,255,.20) 48%,rgba(255,255,255,.06) 66%,transparent 100%);transform:translateX(-135%);transition:transform .55s ease;pointer-events:none;}
.pc-form .pc-btn:hover:before{transform:translateX(135%);}
.pc-form .pc-btn span{position:relative;z-index:1;}
.pc-form .pc-btn .pc-svgicon{width:19px!important;height:19px!important;}
.pc-form .pc-btn:hover{transform:translateY(-4px)!important;}
.pc-form .pc-btn-home{background:linear-gradient(135deg,#8b1e1e 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-form .pc-btn-home:hover{background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;}
.pc-form #pcOpenSelector,.pc-form .pc-btn-create{background:linear-gradient(135deg,#07384d 0%,#0b5f7a 100%)!important;border:1px solid #0a526a!important;color:#fff!important;box-shadow:0 18px 38px rgba(7,56,77,.16)!important;}
.pc-form #pcOpenSelector:hover,.pc-form .pc-btn-create:hover{background:linear-gradient(135deg,#0b5f7a 0%,#117a80 100%)!important;box-shadow:0 26px 46px rgba(7,56,77,.22)!important;}
.pc-form .pc-btn-back{background:#fff!important;color:#07384d!important;border:1px solid #d7e6ec!important;box-shadow:0 10px 26px rgba(7,56,77,.07)!important;}
.pc-form .pc-btn-back:hover{background:#fff8f6!important;color:#8b1e1e!important;border-color:#e1b1b1!important;box-shadow:0 18px 38px rgba(139,30,30,.10)!important;}
.pc-form .pc-btn.gold{background:linear-gradient(135deg,#07384d 0%,#0b5f7a 100%)!important;color:#fff!important;border:1px solid #0a526a!important;}
.pc-form .pc-submit{border:1px solid #d7e6ec!important;border-radius:24px!important;background:#fff!important;box-shadow:0 16px 44px rgba(8,59,84,.07)!important;margin:18px 0 0!important;padding:16px 20px!important;}
@media(max-width:820px){.pc-head-v253{display:grid!important}.pc-head-brand{align-items:flex-start!important}.pc-head-actions{width:100%!important}.pc-head-actions .pc-btn{width:100%!important}.pc-form .pc-types{grid-template-columns:repeat(2,minmax(0,1fr))!important}.pc-form .pc-submit{display:grid!important}.pc-form .pc-submit .pc-btn{width:100%!important;}}
@media(max-width:560px){.pc-head-brand{display:grid!important}.pc-form .pc-types{grid-template-columns:1fr!important}.pc-head-v253 .pc-logo{height:62px!important;}}

</style>

<style id="v253-alta-assets-final-fix">
.pc-home-return, body > a.pc-header-home, body > .pc-header-home{display:none!important;}
.pc-form .pc-head .pc-btn-home{display:inline-flex!important;}
.pc-form .pc-type{font-size:16px!important;}
.pc-form .pc-type span:not(.pc-svgicon){line-height:1.1!important;}
.pc-form .pc-btn-create .pc-svgicon,.pc-form .pc-btn-back .pc-svgicon{background:currentColor!important;}

/* v254: sistema unico de iconos y boton Inicio como Avisos */
.pc-form .pc-head-actions .pc-header-home-clean,
.pc-form .pc-head-actions .pc-header-home-red{
  position:relative!important;overflow:hidden!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:52px!important;border-radius:18px!important;padding:0 22px!important;background:linear-gradient(135deg,#8f1d1d 0%,#b72e2e 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;text-decoration:none!important;transition:transform .22s cubic-bezier(.2,.8,.2,1),box-shadow .22s ease,background .22s ease!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:before,
.pc-form .pc-head-actions .pc-header-home-red:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.05) 30%,rgba(255,255,255,.18) 48%,rgba(255,255,255,.05) 66%,transparent 100%);transform:translateX(-135%);transition:transform .55s ease;pointer-events:none}
.pc-form .pc-head-actions .pc-header-home-clean:hover,
.pc-form .pc-head-actions .pc-header-home-red:hover{transform:translateY(-4px)!important;background:linear-gradient(135deg,#a32323 0%,#c23636 100%)!important;box-shadow:0 24px 46px rgba(165,31,36,.28)!important}
.pc-form .pc-head-actions .pc-header-home-clean:hover:before,
.pc-form .pc-head-actions .pc-header-home-red:hover:before{transform:translateX(135%)}
.pc-form .pc-head-actions .pc-header-home-clean i,
.pc-form .pc-head-actions .pc-header-home-red i{display:inline-block!important;position:relative!important;z-index:1!important;color:#fff!important;font-size:18px!important;line-height:1!important;background:transparent!important;border:0!important;box-shadow:none!important;width:auto!important;height:auto!important}
.pc-form .pc-head-actions .pc-header-home-clean svg,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge,
.pc-form .pc-head-actions .pc-header-home-red .pc-home-badge{display:none!important}
.pc-form .pc-head-actions .pc-header-home-clean span,
.pc-form .pc-head-actions .pc-header-home-red span{position:relative!important;z-index:1!important;color:#fff!important;font-weight:950!important;line-height:1!important}
.pc-form .pc-type-icon i,
.pc-form .pc-form-icon i,
.pc-form .pc-icon i{font-size:30px!important;line-height:1!important;color:#07384d!important}
.pc-form .pc-type-icon,
.pc-form .pc-form-icon,
.pc-form .pc-icon{display:inline-grid!important;place-items:center!important;background:transparent!important;border:0!important;box-shadow:none!important}
.pc-form .bi-hdd-network:before{content:"\F6B3"!important;font-family:"bootstrap-icons"!important}
.pc-form .pc-btn,.pc-form .pc-btn-create,.pc-form .pc-btn-cancel,.pc-form .pc-submit a,.pc-form .pc-submit button{border-radius:18px!important;transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, background .22s ease, border-color .22s ease!important}
.pc-form .pc-btn:hover,.pc-form .pc-btn-create:hover,.pc-form .pc-btn-cancel:hover,.pc-form .pc-submit a:hover,.pc-form .pc-submit button:hover{transform:translateY(-4px)!important}
@media(max-width:760px){.pc-form .pc-head-actions .pc-header-home-clean,.pc-form .pc-head-actions .pc-header-home-red{width:100%!important}.pc-form .pc-type-icon i,.pc-form .pc-form-icon i,.pc-form .pc-icon i{font-size:26px!important}}
</style>

<style id="v255-alta-iconos-unificados">
/* v255: repara los iconos ocultos y unifica Inicio con el estilo de Avisos */
.pc-form .pc-head .pc-btn-home{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;
  min-height:54px!important;padding:0 26px!important;border-radius:18px!important;
  background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%)!important;
  border:1px solid #7c1b1b!important;color:#fff!important;text-decoration:none!important;
  box-shadow:0 18px 38px rgba(139,30,30,.24)!important;
}
.pc-form .pc-head .pc-btn-home:hover{background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%)!important;transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(139,30,30,.30)!important;}
.pc-form .pc-head .pc-btn-home .pc-svgicon{width:19px!important;height:19px!important;color:#fff!important;background:#fff!important;mask:var(--pc-icon) center/contain no-repeat!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;box-shadow:none!important;border:0!important;}
.pc-form .pc-head .pc-btn-home .pc-svgicon:before{display:none!important;content:none!important;}
.pc-form .pc-type .pc-svgicon:before,.pc-form .pc-section h2 .pc-svgicon:before{display:block!important;content:""!important;}
.pc-form .pc-type span.pc-svgicon{background:#07384d!important;color:#fff!important;}
.pc-form .pc-type.active span.pc-svgicon{background:#8b1e1e!important;color:#fff!important;}
.pc-form .pc-type:hover span.pc-svgicon{transform:translateY(-1px) scale(1.03)!important;}
.pc-form .pc-type{font-size:17px!important;}
.pc-form .pc-btn .pc-svgicon:not(.pc-i-home){background:currentColor!important;}
@media(max-width:760px){.pc-form .pc-head .pc-btn-home{width:100%!important;}}
</style>


<style id="v256-alta-activos-polish">
/* v256: alta de activos optimizada, sin telefono y con iconos diferenciados */
.pc-form .pc-i-computer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273%27%20y%3D%274%27%20width%3D%2712.8%27%20height%3D%279.4%27%20rx%3D%271.8%27%2F%3E%3Cpath%20d%3D%27M7.2%2017.7h4.4M9.4%2013.4v4.3%27%2F%3E%3Crect%20x%3D%2717.2%27%20y%3D%276.2%27%20width%3D%273.8%27%20height%3D%2710.8%27%20rx%3D%271.2%27%2F%3E%3Cpath%20d%3D%27M18.5%2014.2h1.2M5.3%2020h13.4%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-form .pc-i-monitor{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273.5%27%20y%3D%274.5%27%20width%3D%2717%27%20height%3D%2711.2%27%20rx%3D%272.2%27%2F%3E%3Cpath%20d%3D%27M8.4%2020h7.2M12%2015.7V20%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-form .pc-i-printer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Cpath%20d%3D%27M7%208V4h10v4%27%2F%3E%3Crect%20x%3D%277%27%20y%3D%2714%27%20width%3D%2710%27%20height%3D%276%27%20rx%3D%271.2%27%2F%3E%3Cpath%20d%3D%27M7%2018H5a2%202%200%200%201-2-2v-5a3%203%200%200%201%203-3h12a3%203%200%200%201%203%203v5a2%202%200%200%201-2%202h-2%27%2F%3E%3Cpath%20d%3D%27M17%2011h.01%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-form .pc-i-network{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%274%27%20y%3D%2712.5%27%20width%3D%2716%27%20height%3D%276.5%27%20rx%3D%272%27%2F%3E%3Cpath%20d%3D%27M7.5%2015.8h.01M10.5%2015.8h.01M13.5%2015.8h.01%27%2F%3E%3Cpath%20d%3D%27M8%208.8a6%206%200%200%201%208%200%27%2F%3E%3Cpath%20d%3D%27M10.2%2010.9a3%203%200%200%201%203.6%200%27%2F%3E%3Cpath%20d%3D%27M12%2012.7v-.05%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-form .pc-i-keyboard{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273%27%20y%3D%276%27%20width%3D%2718%27%20height%3D%2712%27%20rx%3D%272.2%27%2F%3E%3Cpath%20d%3D%27M7%2010h.01M10%2010h.01M13%2010h.01M16%2010h.01M7%2014h10%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-form .pc-type[href*="Phone"],.pc-form .pc-type[href*="phone"]{display:none!important;}
.pc-form .pc-types{grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:14px!important;padding:10px!important;border-radius:24px!important;background:#fff!important;box-shadow:0 12px 34px rgba(7,56,77,.055)!important;}
.pc-form .pc-type{min-height:76px!important;border-radius:20px!important;padding:14px 16px!important;gap:14px!important;font-size:18px!important;line-height:1.08!important;background:linear-gradient(135deg,#fff,#fbfdfe)!important;border-color:#cfdee6!important;box-shadow:0 10px 24px rgba(7,56,77,.045)!important;}
.pc-form .pc-type:before{display:none!important;content:none!important;}
.pc-form .pc-type span.pc-svgicon{width:54px!important;height:54px!important;border-radius:16px!important;background:#07384d!important;color:#fff!important;box-shadow:0 14px 28px rgba(7,56,77,.16)!important;transition:transform .2s ease,background .2s ease,box-shadow .2s ease!important;}
.pc-form .pc-type span.pc-svgicon:before{width:29px!important;height:29px!important;background:currentColor!important;display:block!important;content:""!important;mask:var(--pc-icon) center/contain no-repeat!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;}
.pc-form .pc-type:hover{transform:translateY(-4px)!important;border-color:#aac5d0!important;box-shadow:0 22px 46px rgba(7,56,77,.12)!important;background:#fff!important;}
.pc-form .pc-type:hover span.pc-svgicon{transform:scale(1.045)!important;background:#0b5f7a!important;}
.pc-form .pc-type.active{background:#fff8f8!important;color:#07384d!important;border-color:#e6b6ba!important;box-shadow:0 18px 42px rgba(139,30,30,.11)!important;}
.pc-form .pc-type.active span.pc-svgicon{background:#8b1e1e!important;box-shadow:0 16px 30px rgba(139,30,30,.18)!important;}
.pc-form .pc-section{border-radius:24px!important;padding:24px 26px!important;box-shadow:0 14px 38px rgba(7,56,77,.055)!important;}
.pc-form .pc-section h2{font-size:clamp(30px,3vw,44px)!important;color:#07384d!important;margin-bottom:22px!important;}
.pc-form .pc-section h2:after{display:none!important;content:none!important;}
.pc-form .pc-section h2 .pc-svgicon{width:54px!important;height:54px!important;border-radius:16px!important;background:#07384d!important;color:#fff!important;box-shadow:0 14px 28px rgba(7,56,77,.14)!important;}
.pc-form .pc-section h2 .pc-svgicon:before{width:29px!important;height:29px!important;}
.pc-form .pc-fields{gap:18px 22px!important;}
.pc-form .pc-input,.pc-form .pc-textarea{border-radius:18px!important;min-height:54px!important;border-color:#d3e2e9!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.8)!important;}
.pc-form .pc-input:focus,.pc-form .pc-textarea:focus{border-color:#0b5f7a!important;box-shadow:0 0 0 4px rgba(11,95,122,.10)!important;}
.pc-form .pc-selected{border-radius:20px!important;background:linear-gradient(135deg,#fff,#f8fbfd)!important;border-color:#d3e2e9!important;padding:18px 20px!important;}
.pc-form .pc-selected b{font-size:24px!important;color:#07384d!important;}
.pc-form .pc-submit{justify-content:flex-end!important;gap:14px!important;padding:18px 22px!important;margin-top:18px!important;border-radius:24px!important;background:#fff!important;box-shadow:0 16px 44px rgba(8,59,84,.07)!important;}
.pc-form .pc-btn{border-radius:18px!important;min-height:54px!important;font-size:17px!important;font-weight:950!important;}
.pc-form .pc-btn .pc-svgicon{width:19px!important;height:19px!important;background:currentColor!important;mask:var(--pc-icon) center/contain no-repeat!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;}
.pc-form .pc-btn .pc-svgicon:before{display:none!important;content:none!important;}
.pc-form .pc-btn-home .pc-svgicon{background:#fff!important;}
.pc-form .pc-btn-home{background:linear-gradient(135deg,#8b1e1e 0%,#b72c31 100%)!important;border-color:#7c1b1b!important;color:#fff!important;}
.pc-form .pc-btn-home:hover{background:linear-gradient(135deg,#a32323 0%,#c23636 100%)!important;}
.pc-form #pcOpenSelector,.pc-form .pc-btn-create{background:linear-gradient(135deg,#07384d 0%,#0b5f7a 100%)!important;border:1px solid #0a526a!important;color:#fff!important;}
.pc-form .pc-btn-back{background:#fff!important;color:#07384d!important;border:1px solid #d7e6ec!important;}
.pc-form .pc-head-v253{padding:30px 34px!important;background:linear-gradient(120deg,#fff,#f7fbfd 70%,#fff9ec)!important;}
.pc-form .pc-head-v253 .pc-logo{height:92px!important;max-width:230px!important;}
@media(max-width:1260px){.pc-form .pc-types{grid-template-columns:repeat(3,minmax(0,1fr))!important;}.pc-form .pc-head-v253{align-items:flex-start!important;}}
@media(max-width:860px){.pc-form{padding:8px!important;}.pc-form .pc-head-v253{display:grid!important;gap:18px!important;padding:22px!important;}.pc-form .pc-types{grid-template-columns:repeat(2,minmax(0,1fr))!important;}.pc-form .pc-fields,.pc-form .pc-location{grid-template-columns:1fr!important;}.pc-form .pc-submit{display:grid!important;}.pc-form .pc-submit .pc-btn{width:100%!important;}.pc-form .pc-head-actions .pc-btn-home{width:100%!important;}.pc-form .pc-section{padding:20px!important;}}
@media(max-width:560px){.pc-form .pc-types{grid-template-columns:1fr!important;}.pc-form .pc-type{min-height:68px!important;}.pc-form .pc-type span.pc-svgicon{width:48px!important;height:48px!important;}.pc-form .pc-head-v253 .pc-logo{height:72px!important;}.pc-form .pc-section h2{font-size:30px!important;}.pc-form .pc-section h2 .pc-svgicon{width:48px!important;height:48px!important;}}
</style>


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



<style id="v258-alta-activos-final">
.pc-form .pc-types{grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:14px!important;align-items:stretch!important;}
.pc-form .pc-type[href*="Phone"],.pc-form .pc-type[href*="phone"],.pc-form .pc-type[title*="Telefono"],.pc-form .pc-type[title*="Tel"]{display:none!important;}
.pc-form .pc-location{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:18px!important;align-items:center!important;padding:18px 20px!important;border:1px solid #d4e4eb!important;background:linear-gradient(135deg,#fff,#f8fbfd)!important;border-radius:24px!important;box-shadow:0 16px 42px rgba(8,59,84,.075)!important;}
.pc-form .pc-selected{padding:0!important;background:transparent!important;border:0!important;box-shadow:none!important;min-height:auto!important;}
.pc-form .pc-selected b{font-size:clamp(24px,2.5vw,33px)!important;color:#07384d!important;letter-spacing:-.025em!important;}
.pc-form .pc-selected span{display:block!important;margin-top:6px!important;color:#5e7482!important;font-weight:950!important;}
.pc-form #pcOpenSelector{min-height:58px!important;border-radius:18px!important;padding:0 26px!important;background:linear-gradient(135deg,#07384d 0%,#0b5f7a 100%)!important;border:1px solid #0a526a!important;color:#fff!important;box-shadow:0 18px 38px rgba(7,56,77,.18)!important;gap:10px!important;}
.pc-form #pcOpenSelector:hover{background:linear-gradient(135deg,#0b5f7a 0%,#117a80 100%)!important;transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(7,56,77,.24)!important;}
.pc-form .pc-submit{display:flex!important;justify-content:flex-end!important;gap:14px!important;margin-top:20px!important;padding:18px 22px!important;border:1px solid #d7e6ec!important;border-radius:24px!important;background:#fff!important;box-shadow:0 16px 44px rgba(8,59,84,.075)!important;}
.pc-form .pc-submit .pc-btn{min-height:58px!important;border-radius:18px!important;padding:0 28px!important;font-size:17px!important;font-weight:950!important;gap:11px!important;position:relative!important;overflow:hidden!important;}
.pc-form .pc-submit .pc-btn:before,.pc-form #pcOpenSelector:before{content:""!important;position:absolute!important;inset:0!important;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.06) 30%,rgba(255,255,255,.20) 48%,rgba(255,255,255,.06) 66%,transparent 100%)!important;transform:translateX(-135%)!important;transition:transform .55s ease!important;pointer-events:none!important;}
.pc-form .pc-submit .pc-btn:hover:before,.pc-form #pcOpenSelector:hover:before{transform:translateX(135%)!important;}
.pc-form .pc-btn-cancel{background:#fff!important;color:#07384d!important;border:1px solid #d7e6ec!important;box-shadow:0 10px 26px rgba(7,56,77,.07)!important;}
.pc-form .pc-btn-cancel:hover{background:#fff8f6!important;color:#8b1e1e!important;border-color:#e1b1b1!important;box-shadow:0 20px 38px rgba(139,30,30,.10)!important;transform:translateY(-4px)!important;}
.pc-form .pc-btn-create{background:linear-gradient(135deg,#07384d 0%,#0b5f7a 100%)!important;color:#fff!important;border:1px solid #0a526a!important;box-shadow:0 18px 38px rgba(7,56,77,.18)!important;}
.pc-form .pc-btn-create:hover{background:linear-gradient(135deg,#0b5f7a 0%,#117a80 100%)!important;box-shadow:0 26px 46px rgba(7,56,77,.24)!important;transform:translateY(-4px)!important;}
.pc-form .pc-type span.pc-svgicon{width:58px!important;height:58px!important;border-radius:17px!important;background:#07384d!important;color:#fff!important;box-shadow:0 14px 28px rgba(7,56,77,.16)!important;}
.pc-form .pc-type span.pc-svgicon:before{width:32px!important;height:32px!important;}
.pc-form .pc-section h2 .pc-svgicon{width:58px!important;height:58px!important;border-radius:17px!important;background:#07384d!important;color:#fff!important;box-shadow:0 14px 28px rgba(7,56,77,.14)!important;}
.pc-form .pc-section h2 .pc-svgicon:before{width:32px!important;height:32px!important;}
@media(max-width:900px){.pc-form .pc-location{grid-template-columns:1fr!important}.pc-form #pcOpenSelector{width:100%!important}.pc-form .pc-submit{display:grid!important}.pc-form .pc-submit .pc-btn{width:100%!important}.pc-form .pc-types{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:560px){.pc-form .pc-types{grid-template-columns:1fr!important}.pc-form .pc-type span.pc-svgicon{width:52px!important;height:52px!important}.pc-form .pc-section h2 .pc-svgicon{width:52px!important;height:52px!important}}
</style>

