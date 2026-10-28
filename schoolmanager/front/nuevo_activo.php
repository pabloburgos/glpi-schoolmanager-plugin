<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');

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
    'Computer' => [
        'table'       => 'glpi_computers',
        'label'       => plugin_schoolmanager_tr('asset_computer', 'Ordenador'),
        'icon'        => 'pc-i-computer',
        'list'        => '/front/computer.php',
        'form'        => '/front/computer.form.php',
        'type_field'  => 'computertypes_id',
        'type_class'  => 'ComputerType',
        'model_field' => 'computermodels_id',
        'model_class' => 'ComputerModel'
    ],
    'Monitor' => [
        'table'       => 'glpi_monitors',
        'label'       => plugin_schoolmanager_tr('asset_monitor', 'Monitor'),
        'icon'        => 'pc-i-monitor',
        'list'        => '/front/monitor.php',
        'form'        => '/front/monitor.form.php',
        'type_field'  => 'monitortypes_id',
        'type_class'  => 'MonitorType',
        'model_field' => 'monitormodels_id',
        'model_class' => 'MonitorModel'
    ],
    'Printer' => [
        'table'       => 'glpi_printers',
        'label'       => plugin_schoolmanager_tr('asset_printer', 'Impresora'),
        'icon'        => 'pc-i-printer',
        'list'        => '/front/printer.php',
        'form'        => '/front/printer.form.php',
        'type_field'  => 'printertypes_id',
        'type_class'  => 'PrinterType',
        'model_field' => 'printermodels_id',
        'model_class' => 'PrinterModel'
    ],
    'NetworkEquipment' => [
        'table'       => 'glpi_networkequipments',
        'label'       => plugin_schoolmanager_tr('asset_network', 'Dispositivo de red'),
        'icon'        => 'pc-i-network',
        'list'        => '/front/networkequipment.php',
        'form'        => '/front/networkequipment.form.php',
        'type_field'  => 'networkequipmenttypes_id',
        'type_class'  => 'NetworkEquipmentType',
        'model_field' => 'networkequipmentmodels_id',
        'model_class' => 'NetworkEquipmentModel'
    ],
    'Peripheral' => [
        'table'       => 'glpi_peripherals',
        'label'       => plugin_schoolmanager_tr('asset_peripheral', 'Periférico'),
        'icon'        => 'pc-i-keyboard',
        'list'        => '/front/peripheral.php',
        'form'        => '/front/peripheral.form.php',
        'type_field'  => 'peripheraltypes_id',
        'type_class'  => 'PeripheralType',
        'model_field' => 'peripheralmodels_id',
        'model_class' => 'PeripheralModel'
    ],
];

$itemtype = pc_req('itemtype', 'Computer');
if (!isset($assetTypes[$itemtype])) { $itemtype = 'Computer'; }
$current = $assetTypes[$itemtype];

if (!plugin_schoolmanager_can_create_asset($itemtype)) {
    plugin_schoolmanager_access_denied_page(
        plugin_schoolmanager_tr('asset_creation_restricted', 'Alta de activos restringida'),
        plugin_schoolmanager_tr('asset_creation_restricted_desc', 'Tu perfil no tiene permisos para dar de alta activos.')
    );
}

if (isset($_REQUEST['pc_create']) && $_REQUEST['pc_create'] === '1') {
    $itemtype = pc_req('itemtype', 'Computer');
    if (!isset($assetTypes[$itemtype])) { $itemtype = 'Computer'; }
    $current = $assetTypes[$itemtype];
    $name = pc_req('name');
    $locationId = (int)pc_req('locations_id', 0);
    if ($name === '') {
        $message = plugin_schoolmanager_tr('missing_asset_name', 'Falta el nombre del activo.');
        $messageType = 'error';
    } elseif ($locationId <= 0) {
        $message = plugin_schoolmanager_tr('select_location_required', 'Selecciona una ubicación desde el plano o la lista.');
        $messageType = 'error';
    } elseif (!class_exists($itemtype)) {
        $message = 'No se encuentra la clase GLPI ' . htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') . '.';
        $messageType = 'error';
    } else {
        $obj = new $itemtype();
        $input = [
            'name'         => $name,
            'entities_id'  => Session::getActiveEntity(),
            'locations_id' => $locationId,
            'serial'       => pc_req('serial'),
            'otherserial'  => pc_req('otherserial'),
            'comment'      => pc_req('comment'),
            'is_deleted'   => 0,
            'is_template'  => 0,
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
        $message = plugin_schoolmanager_tr('asset_create_error', 'No se pudo crear el activo. Revisa permisos o campos obligatorios.') . $detail;
        $messageType = 'error';
    }
}

Html::header(plugin_schoolmanager_tr('menu_create_asset', 'Alta guiada de activos'), $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
$roomsJson = json_encode(array_values(array_filter($aulasAll, static fn($a) => !empty($a['id']))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$assetTypesJsData = [];
foreach ($assetTypes as $k => $v) {
    $assetTypesJsData[$k] = [
        'label' => $v['label'],
        'icon'  => $v['icon']
    ];
}
$typesJson = json_encode($assetTypesJsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$egTemplate = plugin_schoolmanager_tr('asset_name_placeholder', 'Ej: %s aula 206');
$createTemplate = plugin_schoolmanager_tr('btn_create_item', 'Crear %s');
?>
<style>
/* ========================================================
   SCHOOL MANAGER - NUEVO ACTIVO (Alta guiada equilibrada)
   Proporcional, elegante, instantáneo (0ms) y responsive
   ======================================================== */
.pc-form {
  --teal: var(--sm-secondary, #075d61);
  --navy: var(--sm-primary, #07384d);
  --red: var(--sm-accent, #b6252b);
  --line: #d7e6ec;
  --bg: #f8fafc;
  --muted: #627386;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
  color: var(--navy);
  padding: clamp(10px, 1.6vw, 24px);
  background: linear-gradient(135deg, #f7fbfd 0%, #ffffff 50%, #fffbf2 100%);
  min-height: calc(100vh - 75px);
  box-sizing: border-box;
}
.pc-form * { box-sizing: border-box; }
.pc-card {
  max-width: 1240px;
  margin: 0 auto;
  background: #ffffff;
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: 0 12px 36px rgba(7, 56, 77, 0.07);
  overflow: hidden;
}

/* Header */
.pc-head {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 16px !important;
  padding: 14px 22px !important;
  border-bottom: 1px solid var(--line) !important;
  background: linear-gradient(90deg, #ffffff 0%, #f4fafc 100%) !important;
}
.pc-head-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}
.pc-head .pc-logo {
  height: 44px !important;
  max-width: 160px !important;
  object-fit: contain !important;
  background: transparent !important;
  box-shadow: none !important;
}
.pc-head small {
  display: block;
  font-weight: 850;
  font-size: 11px;
  color: var(--red);
  letter-spacing: 0.10em;
  text-transform: uppercase;
  line-height: 1.1;
}
.pc-head h1 {
  margin: 2px 0 0;
  font-size: clamp(20px, 2.2vw, 26px);
  font-weight: 900;
  color: var(--navy);
  letter-spacing: -0.025em;
  line-height: 1.15;
}
.pc-head-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
}
.pc-btn-home {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  min-height: 38px !important;
  padding: 0 18px !important;
  border-radius: 12px !important;
  font-size: 13px !important;
  font-weight: 850 !important;
  background: linear-gradient(135deg, #b6252b 0%, #8b1e1e 100%) !important;
  border: 1px solid #7c1b1b !important;
  color: #ffffff !important;
  text-decoration: none !important;
  box-shadow: 0 4px 14px rgba(182, 37, 43, 0.22) !important;
  transition: transform 0.16s ease, box-shadow 0.16s ease !important;
}
.pc-btn-home:hover {
  transform: translateY(-2px) !important;
  box-shadow: 0 8px 22px rgba(182, 37, 43, 0.32) !important;
  background: linear-gradient(135deg, #cc2c33 0%, #9f1c22 100%) !important;
  color: #fff !important;
}
.pc-btn-home svg {
  width: 16px;
  height: 16px;
  stroke: #ffffff;
  stroke-width: 2.2;
  fill: none;
}

/* Body */
.pc-body {
  padding: 18px 22px;
}

/* Tabs: Selector de tipo de activo (Equilibrado, compacto, sin 76px) */
.pc-types {
  display: grid !important;
  grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
  gap: 10px !important;
  margin-bottom: 18px !important;
  padding: 6px !important;
  border: 1px solid var(--line) !important;
  border-radius: 16px !important;
  background: #f7fafc !important;
}
.pc-type {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 9px !important;
  min-height: 46px !important;
  padding: 8px 12px !important;
  border-radius: 12px !important;
  border: 1px solid transparent !important;
  background: #ffffff !important;
  color: var(--navy) !important;
  font-size: 14px !important;
  font-weight: 800 !important;
  cursor: pointer !important;
  text-decoration: none !important;
  transition: all 0.16s ease !important;
  box-shadow: 0 2px 6px rgba(7, 56, 77, 0.04) !important;
}
.pc-type:hover {
  transform: translateY(-1px) !important;
  border-color: #bdd5df !important;
  box-shadow: 0 6px 14px rgba(7, 56, 77, 0.08) !important;
}
.pc-type.active {
  background: linear-gradient(135deg, #b6252b 0%, #8b1e1e 100%) !important;
  border-color: #8b1e1e !important;
  color: #ffffff !important;
  box-shadow: 0 6px 18px rgba(182, 37, 43, 0.25) !important;
}

/* Icon badges inside tabs */
.pc-type .pc-icon-badge {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: #eef6fa;
  color: var(--navy);
  flex: 0 0 auto;
  transition: all 0.16s ease;
}
.pc-type.active .pc-icon-badge {
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff;
}
.pc-type:hover:not(.active) .pc-icon-badge {
  background: #e0f0f7;
}

/* SVG icons */
.pc-svgicon {
  display: inline-block;
  width: 18px;
  height: 18px;
  background: currentColor;
  mask: var(--pc-icon) center/contain no-repeat;
  -webkit-mask: var(--pc-icon) center/contain no-repeat;
}
.pc-i-computer { --pc-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='13' height='9.5' rx='1.8'/%3E%3Cpath d='M7 17.5h4.5M9.2 13.5v4'/%3E%3Crect x='17.5' y='6' width='3.5' height='11' rx='1.2'/%3E%3Cpath d='M5 20h14'/%3E%3C/svg%3E"); }
.pc-i-monitor  { --pc-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3.5' y='4.5' width='17' height='11.5' rx='2'/%3E%3Cpath d='M8.5 20h7M12 16v4'/%3E%3C/svg%3E"); }
.pc-i-printer  { --pc-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7 8V4h10v4'/%3E%3Crect x='7' y='14' width='10' height='6' rx='1'/%3E%3Cpath d='M7 18H5a2 2 0 0 1-2-2v-5a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v5a2 2 0 0 1-2 2h-2'/%3E%3Cpath d='M17 11h.01'/%3E%3C/svg%3E"); }
.pc-i-network  { --pc-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='4' y='12.5' width='16' height='6.5' rx='2'/%3E%3Cpath d='M7.5 15.8h.01M10.5 15.8h.01M13.5 15.8h.01'/%3E%3Cpath d='M8 8.8a6 6 0 0 1 8 0'/%3E%3Cpath d='M10.2 10.9a3 3 0 0 1 3.6 0'/%3E%3C/svg%3E"); }
.pc-i-keyboard { --pc-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='6' width='18' height='12' rx='2.2'/%3E%3Cpath d='M7 10h.01M10 10h.01M13 10h.01M16 10h.01M7 14h10'/%3E%3C/svg%3E"); }

/* Form section */
.pc-section {
  border: 1px solid var(--line);
  border-radius: 18px;
  background: #ffffff;
  padding: 18px 22px;
  box-shadow: 0 4px 16px rgba(7, 56, 77, 0.04);
}
.pc-section h2 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 16px;
  font-size: 18px;
  font-weight: 900;
  color: var(--navy);
  letter-spacing: -0.02em;
}
.pc-section-badge {
  display: inline-grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--navy), var(--teal));
  color: #ffffff;
}

/* Fields grid */
.pc-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.pc-field {
  display: flex;
  flex-direction: column;
}
.pc-field.full {
  grid-column: 1 / -1;
}
.pc-label {
  display: block;
  font-weight: 850;
  font-size: 13px;
  color: #1a3944;
  margin-bottom: 6px;
}
.pc-input, .pc-textarea {
  width: 100%;
  border: 1px solid #d5e4eb;
  border-radius: 10px;
  padding: 9px 12px;
  font-size: 14px;
  font-weight: 600;
  color: #142e37;
  background: #ffffff;
  outline: none;
  min-height: 40px;
  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}
.pc-input:focus, .pc-textarea:focus {
  border-color: #0b5f7a;
  box-shadow: 0 0 0 3px rgba(11, 95, 122, 0.12);
}
.pc-textarea {
  min-height: 75px;
  resize: vertical;
  line-height: 1.4;
}

/* Location box */
.pc-location {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
}
.pc-selected {
  border: 1px solid #d8e8ee;
  border-radius: 10px;
  background: linear-gradient(135deg, #ffffff, #f7fbfd);
  padding: 8px 12px;
  min-height: 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.pc-selected b {
  display: block;
  color: var(--navy);
  font-size: 14px;
  font-weight: 900;
  line-height: 1.2;
}
.pc-selected span {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  line-height: 1.2;
  margin-top: 2px;
}

/* Type & Model pairs */
.pc-type-model-pair {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.pc-dropdown-wrap select,
.pc-dropdown-wrap .select2-container {
  width: 100% !important;
}

/* Action buttons */
.pc-btn {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  border: 1px solid transparent !important;
  border-radius: 10px !important;
  padding: 8px 16px !important;
  font-size: 14px !important;
  font-weight: 850 !important;
  text-decoration: none !important;
  cursor: pointer !important;
  white-space: nowrap !important;
  overflow: visible !important;
  min-width: max-content !important;
  transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease !important;
}
.pc-btn:hover {
  transform: translateY(-2px) !important;
}
.pc-btn.primary {
  background: linear-gradient(135deg, var(--navy) 0%, var(--teal) 100%) !important;
  border-color: #052a3b !important;
  color: #ffffff !important;
  box-shadow: 0 4px 12px rgba(7, 56, 77, 0.16) !important;
}
.pc-btn.primary:hover {
  box-shadow: 0 8px 20px rgba(7, 56, 77, 0.24) !important;
}
.pc-btn.secondary {
  background: #ffffff !important;
  color: var(--navy) !important;
  border-color: #d7e6ec !important;
  box-shadow: 0 2px 6px rgba(7, 56, 77, 0.04) !important;
}
.pc-btn.secondary:hover {
  border-color: #e2b3b5 !important;
  color: var(--red) !important;
  background: #fff8f8 !important;
  box-shadow: 0 6px 14px rgba(182, 37, 43, 0.08) !important;
}
.pc-btn.gold {
  background: linear-gradient(135deg, #07384d 0%, #0b5f7a 100%) !important;
  border-color: #062b3a !important;
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.18) !important;
}
.pc-btn.gold:hover {
  background: linear-gradient(135deg, #0b5f7a 0%, #07384d 100%) !important;
  box-shadow: 0 8px 22px rgba(7, 56, 77, 0.26) !important;
}
.pc-btn-svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
  stroke-width: 2.2;
  fill: none;
  flex: 0 0 auto;
}

/* Bottom submit bar */
.pc-submit {
  display: flex !important;
  justify-content: flex-end !important;
  align-items: center !important;
  gap: 12px !important;
  margin-top: 16px !important;
  padding: 14px 20px !important;
  border: 1px solid var(--line) !important;
  border-radius: 16px !important;
  background: #ffffff !important;
  box-shadow: 0 6px 20px rgba(7, 56, 77, 0.04) !important;
}
.pc-submit .pc-btn {
  min-height: 42px !important;
  padding: 0 22px !important;
}

/* Spinner */
.pc-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: pcSpin 0.6s linear infinite;
  margin-right: 6px;
}
@keyframes pcSpin { to { transform: rotate(360deg); } }

/* Alerts */
.pc-message {
  margin: 14px 22px 0;
  border-radius: 12px;
  padding: 11px 15px;
  font-weight: 850;
  font-size: 13px;
}
.pc-message.error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}
.pc-message.info {
  background: #e0f2fe;
  color: #0369a1;
  border: 1px solid #7dd3fc;
}

/* Responsive */
@media (max-width: 960px) {
  .pc-types {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  }
}
@media (max-width: 760px) {
  .pc-head {
    flex-direction: column !important;
    align-items: stretch !important;
  }
  .pc-btn-home {
    width: 100% !important;
  }
  .pc-types {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
  .pc-fields, .pc-type-model-pair, .pc-location {
    grid-template-columns: 1fr !important;
  }
  .pc-submit {
    flex-direction: column-reverse !important;
  }
  .pc-submit .pc-btn {
    width: 100% !important;
  }
}
@media (max-width: 480px) {
  .pc-types {
    grid-template-columns: 1fr !important;
  }
}
</style>

<div class="pc-form" id="pcForm" 
     data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>' 
     data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>" 
     data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>"
     data-types='<?= htmlspecialchars($typesJson, ENT_QUOTES, 'UTF-8') ?>'
     data-eg-template="<?= htmlspecialchars($egTemplate, ENT_QUOTES, 'UTF-8') ?>"
     data-create-template="<?= htmlspecialchars($createTemplate, ENT_QUOTES, 'UTF-8') ?>"
     data-msg-select-location="<?= htmlspecialchars(plugin_schoolmanager_tr('select_location_first', 'Selecciona una ubicación primero.'), ENT_QUOTES, 'UTF-8') ?>"
     data-msg-creating="<?= htmlspecialchars(plugin_schoolmanager_tr('creating_asset', 'Creando activo...'), ENT_QUOTES, 'UTF-8') ?>">
  
  <div class="pc-card">
    <!-- Header -->
    <div class="pc-head">
      <div class="pc-head-brand">
        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" class="pc-logo" alt="Logo School Manager" onerror="this.onerror=null;this.src='<?= htmlspecialchars($root . '/plugins/schoolmanager/logo.png', ENT_QUOTES, 'UTF-8') ?>';">
        <div>
          <small>GLPI School Manager</small>
          <h1><?= htmlspecialchars(plugin_schoolmanager_tr('menu_create_asset', 'Alta guiada de activos'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
      </div>
      <div class="pc-head-actions">
        <a class="pc-btn-home" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(plugin_schoolmanager_tr('menu_home', 'Inicio'), ENT_QUOTES, 'UTF-8') ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.75 10.5 12 4.25l8.25 6.25"/><path d="M6.75 9.75v9.5h10.5v-9.5"/><path d="M10 19.25V14.5a2 2 0 0 1 4 0v4.75"/></svg>
          <span><?= htmlspecialchars(plugin_schoolmanager_tr('menu_home', 'Inicio'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="pc-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="pc-body">
      <!-- Itemtype Tabs (0ms client-side instant switch) -->
      <div class="pc-types" role="tablist">
        <?php foreach ($assetTypes as $type => $info): 
            $isActive = ($type === $itemtype);
        ?>
          <button type="button" 
                  class="pc-type <?= $isActive ? 'active' : '' ?>" 
                  data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" 
                  role="tab" 
                  aria-selected="<?= $isActive ? 'true' : 'false' ?>">
            <span class="pc-icon-badge">
              <span class="pc-svgicon <?= htmlspecialchars($info['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
            </span>
            <span><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Asset Form -->
      <form method="get" id="assetGuidedForm">
        <?php pc_token_field(); ?>
        <input type="hidden" name="pc_create" value="1">
        <input type="hidden" name="v" value="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="itemtype" id="pcItemType" value="<?= htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="locations_id" id="pcLocationId" value="">
        <input type="hidden" name="location_label" id="pcLocationLabel" value="">
        <input type="hidden" name="location_code" id="pcLocationCode" value="">

        <section class="pc-section">
          <h2>
            <span class="pc-section-badge">
              <span id="pcSectionIcon" class="pc-svgicon <?= htmlspecialchars($current['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
            </span>
            <span id="pcSectionTitle"><?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </h2>

          <div class="pc-fields">
            <!-- Asset Name -->
            <label class="pc-field">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_asset_name', 'Nombre'), ENT_QUOTES, 'UTF-8') ?> *</span>
              <input class="pc-input" 
                     name="name" 
                     id="pcAssetNameInput" 
                     placeholder="<?= htmlspecialchars(sprintf($egTemplate, $current['label']), ENT_QUOTES, 'UTF-8') ?>" 
                     required>
            </label>

            <!-- Location with Modal Trigger -->
            <div class="pc-field">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_location', 'Ubicación'), ENT_QUOTES, 'UTF-8') ?> *</span>
              <div class="pc-location">
                <div class="pc-selected" id="pcSelectedLocation">
                  <b><?= htmlspecialchars(plugin_schoolmanager_tr('no_location', 'Sin ubicación'), ENT_QUOTES, 'UTF-8') ?></b>
                  <span><?= htmlspecialchars(plugin_schoolmanager_tr('select_from_map_or_list', 'Selecciona desde plano o lista'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <button type="button" class="pc-btn primary" id="pcOpenSelector">
                  <svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                  <span><?= htmlspecialchars(plugin_schoolmanager_tr('btn_choose_location', 'Elegir ubicación'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
              </div>
            </div>

            <!-- Serial & Otherserial -->
            <label class="pc-field">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_serial_number', 'Número de serie'), ENT_QUOTES, 'UTF-8') ?></span>
              <input class="pc-input" name="serial" placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('serial_number_placeholder', 'Número de serie / etiqueta del fabricante'), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label class="pc-field">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_inventory_number', 'Número de inventario'), ENT_QUOTES, 'UTF-8') ?></span>
              <input class="pc-input" name="otherserial" placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('inventory_number_placeholder', 'Código interno / etiqueta de inventario'), ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <!-- State / Manufacturer -->
            <div class="pc-field pc-dropdown-wrap">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_status', 'Estado'), ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (class_exists('State')) { Dropdown::show('State', ['name'=>'states_id', 'value'=>0]); } ?>
            </div>
            <div class="pc-field pc-dropdown-wrap">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_manufacturer', 'Fabricante'), ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (class_exists('Manufacturer')) { Dropdown::show('Manufacturer', ['name'=>'manufacturers_id', 'value'=>0]); } ?>
            </div>

            <!-- Pre-rendered Type & Model pairs for all 5 item types -->
            <?php foreach ($assetTypes as $type => $info): 
                $isActive = ($type === $itemtype);
            ?>
              <div class="pc-type-model-pair" id="pcTypeModel_<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" style="<?= $isActive ? '' : 'display:none;' ?>">
                <div class="pc-field pc-dropdown-wrap">
                  <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_type', 'Tipo'), ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (class_exists($info['type_class'])) {
                      Dropdown::show($info['type_class'], ['name' => $info['type_field'], 'value' => 0]);
                  } ?>
                </div>
                <div class="pc-field pc-dropdown-wrap">
                  <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_model', 'Modelo'), ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (class_exists($info['model_class'])) {
                      Dropdown::show($info['model_class'], ['name' => $info['model_field'], 'value' => 0]);
                  } ?>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- Comments -->
            <label class="pc-field full">
              <span class="pc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('field_comments', 'Comentarios'), ENT_QUOTES, 'UTF-8') ?></span>
              <textarea class="pc-textarea" name="comment" placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('comments_placeholder', 'Observaciones, puesto, uso previsto, aula, periféricos asociados...'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
            </label>
          </div>
        </section>

        <!-- Actions -->
        <div class="pc-submit">
          <a class="pc-btn secondary pc-btn-cancel" 
             href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>" 
             onclick="if(history.length>1){history.back();return false;}">
            <svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
            <span><?= htmlspecialchars(plugin_schoolmanager_tr('btn_cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <button class="pc-btn gold pc-btn-create pc-btn-icon" type="submit" id="pcSubmitBtn">
            <svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6v12"/><path d="M6 12h12"/></svg>
            <span id="pcSubmitText"><?= htmlspecialchars(sprintf($createTemplate, mb_strtolower($current['label'], 'UTF-8')), ENT_QUOTES, 'UTF-8') ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include(__DIR__ . '/../inc/location_modal_markup.php'); ?>
</div>

<?php include(__DIR__ . '/../inc/location_modal_script.php'); ?>
<script src="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/js/custom-combobox.js?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"></script>

<script>
(function(){
  const formWrap = document.getElementById('pcForm');
  if (!formWrap) return;

  let typesData = {};
  try { typesData = JSON.parse(formWrap.dataset.types || '{}'); } catch(e) {}
  const egTemplate = formWrap.dataset.egTemplate || 'Ej: %s aula 206';
  const createTemplate = formWrap.dataset.createTemplate || 'Crear %s';
  const msgSelectLocation = formWrap.dataset.msgSelectLocation || 'Selecciona una ubicación primero.';
  const msgCreating = formWrap.dataset.msgCreating || 'Creando activo...';
  const version = formWrap.dataset.version || '1.0.0';

  // Instant itemtype switcher without page reload
  function switchItemtype(targetType) {
    if (!typesData[targetType]) return;

    // 1. Update tab active state
    document.querySelectorAll('.pc-type').forEach(tab => {
      const isTarget = (tab.dataset.type === targetType);
      tab.classList.toggle('active', isTarget);
      tab.setAttribute('aria-selected', isTarget ? 'true' : 'false');
    });

    // 2. Update hidden input
    const typeInput = document.getElementById('pcItemType');
    if (typeInput) typeInput.value = targetType;

    // 3. Update title, icon, placeholders
    const info = typesData[targetType];
    const sectionTitle = document.getElementById('pcSectionTitle');
    if (sectionTitle) sectionTitle.textContent = info.label;

    const sectionIcon = document.getElementById('pcSectionIcon');
    if (sectionIcon) sectionIcon.className = 'pc-svgicon ' + info.icon;

    const nameInput = document.getElementById('pcAssetNameInput');
    if (nameInput) nameInput.placeholder = egTemplate.replace('%s', info.label);

    const submitText = document.getElementById('pcSubmitText');
    if (submitText) submitText.textContent = createTemplate.replace('%s', info.label.toLowerCase());

    // 4. Toggle type/model pairs
    document.querySelectorAll('.pc-type-model-pair').forEach(pair => {
      const match = (pair.id === 'pcTypeModel_' + targetType);
      pair.style.display = match ? '' : 'none';
      pair.querySelectorAll('select, input').forEach(el => {
        el.disabled = !match;
      });
    });

    // 5. Update browser history cleanly
    try {
      const url = new URL(location.href);
      url.searchParams.set('itemtype', targetType);
      history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString());
    } catch(e) {}
  }

  // Bind tab click events
  document.querySelectorAll('.pc-type').forEach(tab => {
    tab.addEventListener('click', function(ev){
      ev.preventDefault();
      const type = this.dataset.type;
      if (type) switchItemtype(type);
    });
  });

  // Initial sync: disable non-active pairs
  const initialType = (document.getElementById('pcItemType') || {}).value || 'Computer';
  document.querySelectorAll('.pc-type-model-pair').forEach(pair => {
    const match = (pair.id === 'pcTypeModel_' + initialType);
    pair.querySelectorAll('select, input').forEach(el => {
      el.disabled = !match;
    });
  });

  // Form submit validation & interactive spinner
  const form = document.getElementById('assetGuidedForm');
  if (form) {
    form.addEventListener('submit', function(e){
      const locId = (document.getElementById('pcLocationId') || {}).value;
      if (!locId || parseInt(locId, 10) <= 0) {
        e.preventDefault();
        alert(msgSelectLocation);
        return;
      }
      const submitBtn = document.getElementById('pcSubmitBtn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="pc-spinner"></span> ' + msgCreating;
      }
    });
  }
})();
</script>
<?php Html::footer(); ?>
