<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

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
        'desc'        => 'Torre, portátil',
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
        'desc'        => 'Pantalla, TV',
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
        'desc'        => 'Láser, tinta, 3D',
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
        'desc'        => 'Switch, router, AP',
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
        'desc'        => 'Proyector, teclado',
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

// Current values from request (preserves user inputs across tab switches)
$nameVal        = pc_req('name');
$locationId     = (int)pc_req('locations_id', 0);
$locationLabel  = pc_req('location_label');
$locationCode   = pc_req('location_code');
$serialVal      = pc_req('serial');
$otherserialVal = pc_req('otherserial');
$statesIdVal    = (int)pc_req('states_id', 0);
$mfgIdVal       = (int)pc_req('manufacturers_id', 0);
$typeIdVal      = (int)pc_req($current['type_field'], 0);
$modelIdVal     = (int)pc_req($current['model_field'], 0);
$commentVal     = pc_req('comment');

// Form creation handler
if (isset($_REQUEST['pc_create']) && $_REQUEST['pc_create'] === '1') {
    if ($nameVal === '') {
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
            'name'         => $nameVal,
            'entities_id'  => Session::getActiveEntity(),
            'locations_id' => $locationId,
            'serial'       => $serialVal,
            'otherserial'  => $otherserialVal,
            'comment'      => $commentVal,
            'is_deleted'   => 0,
            'is_template'  => 0,
        ];
        if ($statesIdVal > 0) { $input['states_id'] = $statesIdVal; }
        if ($mfgIdVal > 0) { $input['manufacturers_id'] = $mfgIdVal; }
        if ($typeIdVal > 0) { $input[$current['type_field']] = $typeIdVal; }
        if ($modelIdVal > 0) { $input[$current['model_field']] = $modelIdVal; }

        $newId = $obj->add($input);
        if (!$newId) { $newId = pc_direct_asset_insert_if_allowed($itemtype, $current, $input); }
        if ($newId) {
            Html::redirect($root . $current['form'] . '?id=' . (int)$newId);
            exit;
        }
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

sm_page_header([
    'title'      => plugin_schoolmanager_tr('menu_create_asset', 'Alta Guiada de Activos'),
    'subtitle'   => plugin_schoolmanager_tr('create_asset_desc', 'Registra equipos informáticos y periféricos preservando datos entre tipos.'),
    'active_tab' => 'nuevo_activo',
]);
$roomsJson = json_encode(array_values(array_filter($aulasAll, static fn($a) => !empty($a['id']))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function pc_tab_url($type, array $current) {
    global $root;
    $params = [
        'itemtype'       => $type,
        'v'              => defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.2.1',
        'name'           => $current['name'] ?? '',
        'locations_id'   => $current['locations_id'] ?? '',
        'location_label' => $current['location_label'] ?? '',
        'location_code'  => $current['location_code'] ?? '',
        'serial'         => $current['serial'] ?? '',
        'otherserial'    => $current['otherserial'] ?? '',
        'states_id'      => $current['states_id'] ?? '',
        'manufacturers_id'=> $current['manufacturers_id'] ?? '',
        'comment'        => $current['comment'] ?? '',
    ];
    $clean = array_filter($params, static fn($v) => (string)$v !== '' && (string)$v !== '0');
    $clean['itemtype'] = $type;
    return ($root . '/plugins/schoolmanager/front/new_asset.php?' . http_build_query($clean));
}

$tabState = [
    'name'           => $nameVal,
    'locations_id'   => $locationId,
    'location_label' => $locationLabel,
    'location_code'  => $locationCode,
    'serial'         => $serialVal,
    'otherserial'    => $otherserialVal,
    'states_id'      => $statesIdVal,
    'manufacturers_id'=> $mfgIdVal,
    'comment'        => $commentVal,
];

$egTemplate = plugin_schoolmanager_tr('asset_name_placeholder', 'Ej: %s aula 206');
$createTemplate = plugin_schoolmanager_tr('btn_create_item', 'Crear %s');
?>
<style>
/* ==========================================================================
   SCHOOL MANAGER - ALTA DE ACTIVOS (Diseño Moderno, Elegante e Interactivo)
   ========================================================================== */
.pc-form-view {
  --sm-navy: #07384D;
  --sm-navy-dark: #052634;
  --sm-navy-light: #0B4F6C;
  --sm-teal: #075D61;
  --sm-teal-light: #0E7B82;
  --sm-red: #B6252B;
  --sm-red-dark: #8B1E1E;
  --sm-bg: #F8FAFC;
  --sm-card: #FFFFFF;
  --sm-border: #D7E6EC;
  --sm-border-subtle: #E8F0F4;
  --sm-muted: #627386;
  --sm-ink: #07384D;
  
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: var(--sm-ink);
  min-height: calc(100vh - 75px);
  padding: 0 !important;
  width: 100% !important;
  max-width: 100% !important;
  margin: 0 !important;
  background: transparent !important;
  box-sizing: border-box;
}
.pc-form-view * { box-sizing: border-box; }

.pc-form-container {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
}

/* Header Card */
.pc-hero-header {
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 24px;
  padding: 18px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  box-shadow: 0 10px 30px rgba(7, 56, 77, 0.05);
  margin-bottom: 20px;
}
.pc-hero-brand {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
}
.pc-hero-logo {
  height: 48px;
  max-width: 170px;
  object-fit: contain;
  background: transparent;
}
.pc-hero-kicker {
  font-size: 11px;
  font-weight: 900;
  color: var(--sm-red);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin: 0 0 2px;
}
.pc-hero-title {
  margin: 0;
  font-size: clamp(20px, 2.4vw, 28px);
  font-weight: 950;
  color: var(--sm-navy);
  letter-spacing: -0.03em;
  line-height: 1.15;
}

/* Home Button - Clean Flat Secondary Style */
.pc-btn-home-red {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 7px !important;
  min-height: 40px !important;
  padding: 0 16px !important;
  border-radius: 10px !important;
  font-size: 13.5px !important;
  font-weight: 650 !important;
  background: #FFFFFF !important;
  background-image: none !important;
  border: 1px solid #CBD5E1 !important;
  color: #0F2951 !important;
  text-decoration: none !important;
  box-shadow: 0 1px 2px rgba(15, 41, 81, 0.05) !important;
  transition: background-color 0.16s ease, border-color 0.16s ease !important;
}
.pc-btn-home-red:hover {
  background: #F1F5F9 !important;
  border-color: #94A3B8 !important;
  color: #0F2951 !important;
}
.pc-btn-home-red svg {
  width: 16px;
  height: 16px;
  stroke: #0F2951;
  stroke-width: 2.2;
  fill: none;
}

/* Interactive Asset Type Cards */
.pc-types-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 22px;
}
.pc-type-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 14px 16px;
  background: #FFFFFF;
  border: 1.5px solid var(--sm-border);
  border-radius: 16px;
  text-decoration: none !important;
  color: var(--sm-navy) !important;
  box-shadow: 0 2px 8px rgba(7, 56, 77, 0.03);
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
  position: relative;
  overflow: hidden;
}
.pc-type-card:hover {
  border-color: #94A3B8;
  box-shadow: 0 6px 16px rgba(7, 56, 77, 0.07);
}
.pc-type-card.active {
  background: #FFFDFD !important;
  border-color: var(--sm-red) !important;
  box-shadow: 0 4px 14px rgba(182, 37, 43, 0.10) !important;
}
.pc-type-card.active::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3.5px;
  background: var(--sm-red);
}
.pc-type-icon-badge {
  display: inline-grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #EEF6FA;
  color: var(--sm-navy);
  margin-bottom: 8px;
  transition: background-color 0.18s ease, color 0.18s ease;
}
.pc-type-card:hover .pc-type-icon-badge {
  background: #E1EFF6;
}
.pc-type-card.active .pc-type-icon-badge {
  background: var(--sm-red);
  color: #FFFFFF;
  box-shadow: 0 2px 6px rgba(182, 37, 43, 0.20);
}
.pc-type-card-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--sm-navy);
  line-height: 1.2;
}
.pc-type-card-desc {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--sm-muted);
  margin-top: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  width: 100%;
}
.pc-type-card.active .pc-type-card-title {
  color: var(--sm-red-dark) !important;
}
.pc-type-card.active .pc-type-card-desc {
  color: #475569 !important;
}

/* SVG Icons for asset types */
.pc-type-svg {
  width: 20px;
  height: 20px;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  fill: none;
}

/* Form Layout & Cards */
.pc-form-sections {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pc-section-card {
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
}
.pc-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--sm-border-subtle);
}
.pc-section-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pc-section-step {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: var(--sm-navy);
  color: #FFFFFF;
  font-size: 12px;
  font-weight: 800;
}
.pc-section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--sm-navy);
  letter-spacing: -0.015em;
}

/* Form Fields */
.pc-fields-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 18px;
}
.pc-field-item {
  display: flex;
  flex-direction: column;
}
.pc-field-item.full-width {
  grid-column: 1 / -1;
}
.pc-field-label {
  display: block;
  font-size: 13px;
  font-weight: 850;
  color: #173844;
  margin-bottom: 6px;
}
.pc-field-label .req {
  color: #B6252B;
  font-weight: 900;
  margin-left: 2px;
}
.pc-field-input, .pc-field-textarea {
  width: 100%;
  border: 1px solid #D5E4EB;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 600;
  color: #07384D;
  background: #FFFFFF;
  outline: none;
  min-height: 42px;
  transition: all 0.16s ease;
}
.pc-field-input:focus, .pc-field-textarea:focus {
  border-color: var(--sm-teal);
  box-shadow: 0 0 0 3.5px rgba(7, 93, 97, 0.14);
}
.pc-field-textarea {
  min-height: 80px;
  resize: vertical;
  line-height: 1.45;
}

/* Dropdown containers inside GLPI */
.pc-field-item select,
.pc-field-item .select2-container,
.pc-field-item .btn-group {
  width: 100% !important;
}

/* Interactive Step Progress Bar */
.pc-steps-tracker {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 18px;
}
.pc-step-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 12px;
  cursor: pointer;
  text-align: left;
  transition: all 0.16s ease;
  box-shadow: 0 2px 6px rgba(7, 56, 77, 0.03);
}
.pc-step-pill:hover {
  border-color: #088E91;
  background: #F8FAFC;
}
.pc-step-pill-num {
  width: 24px;
  height: 24px;
  border-radius: 7px;
  background: #E2E8F0;
  color: #0F2951;
  font-size: 12px;
  font-weight: 850;
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
  transition: all 0.16s ease;
}
.pc-step-pill.is-done {
  border-color: #99F6E4;
  background: #F0FDFA;
}
.pc-step-pill.is-done .pc-step-pill-num {
  background: #088E91;
  color: #FFFFFF;
}
.pc-step-pill-text {
  min-width: 0;
}
.pc-step-pill-text strong {
  display: block;
  font-size: 12.5px;
  font-weight: 800;
  color: #0F2951;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pc-step-pill-text small {
  display: block;
  font-size: 11px;
  font-weight: 650;
  color: #64748B;
  margin-top: 1px;
}
.pc-step-pill.is-done .pc-step-pill-text small {
  color: #065F46;
}

/* Quick Name Suggestion Chips */
.pc-quick-chips {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.pc-quick-chip-label {
  font-size: 11.5px;
  font-weight: 750;
  color: #64748B;
  margin-right: 2px;
}
.pc-quick-chip {
  border: 1px solid #CBD5E1;
  background: #F8FAFC;
  color: #0F2951;
  border-radius: 8px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.pc-quick-chip:hover {
  background: #0F2951;
  border-color: #0F2951;
  color: #FFFFFF;
}

/* Location Card UI */
.pc-location-interactive-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  border-radius: 14px;
  background: #F8FAFC;
  border: 1.5px solid #CBD5E1;
  transition: all 0.2s ease;
  flex-wrap: wrap;
}
.pc-location-interactive-card.is-selected {
  border-style: solid;
  border-color: #088E91;
  background: #F0FDFA;
  box-shadow: 0 4px 14px rgba(8, 142, 145, 0.08);
}
.pc-loc-meta-box {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 220px;
  flex: 1;
}
.pc-loc-icon-bubble {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: inline-grid;
  place-items: center;
  background: #E0F2FE;
  color: #0F2951;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.pc-location-interactive-card.is-selected .pc-loc-icon-bubble {
  background: #0F2951;
  color: #FFFFFF;
  box-shadow: 0 4px 12px rgba(15, 41, 81, 0.24);
}
.pc-loc-info-text {
  min-width: 0;
}
.pc-loc-name {
  margin: 0;
  font-size: 15px;
  font-weight: 850;
  color: var(--sm-navy);
  line-height: 1.25;
}
.pc-loc-meta {
  margin: 3px 0 0;
  font-size: 12.5px;
  font-weight: 650;
  color: var(--sm-muted);
  line-height: 1.3;
}
.pc-location-interactive-card.is-selected .pc-loc-name {
  color: #065F46;
}
.pc-loc-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.pc-loc-quick-select {
  min-width: 240px;
  max-width: 320px;
  height: 42px;
  border: 1px solid #CBD5E1;
  border-radius: 10px;
  padding: 0 34px 0 12px;
  font-size: 13.5px;
  font-weight: 650;
  color: #0F2951;
  background-color: #FFFFFF;
}
.pc-loc-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 800;
  border: 1px solid transparent;
}
.pc-loc-badge.ready {
  background: #E0F2FE;
  color: #0369A1;
  border-color: #BAE6FD;
}
.pc-loc-badge.selected {
  background: #F0FDFA;
  color: #0F766E;
  border-color: #99F6E4;
}

/* Action Buttons */
.pc-action-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
  padding: 16px 24px;
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 16px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
}
.pc-btn-action {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  min-height: 42px !important;
  padding: 0 22px !important;
  border-radius: 10px !important;
  font-size: 13.5px !important;
  font-weight: 700 !important;
  text-decoration: none !important;
  cursor: pointer !important;
  white-space: nowrap !important;
  overflow: visible !important;
  min-width: max-content !important;
  transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease !important;
}
.pc-btn-secondary {
  background: #FFFFFF !important;
  background-image: none !important;
  color: #0F2951 !important;
  border: 1px solid #CBD5E1 !important;
  box-shadow: 0 1px 2px rgba(15, 41, 81, 0.05) !important;
}
.pc-btn-secondary:hover {
  border-color: #94A3B8 !important;
  color: #0F2951 !important;
  background: #F1F5F9 !important;
  box-shadow: 0 2px 5px rgba(15, 41, 81, 0.08) !important;
}
.pc-btn-primary {
  background: #0F2951 !important;
  background-image: none !important;
  border: 1px solid #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 1px 3px rgba(15, 41, 81, 0.18) !important;
}
.pc-btn-primary:hover {
  background: #088E91 !important;
  background-image: none !important;
  border-color: #088E91 !important;
  box-shadow: 0 3px 8px rgba(8, 142, 145, 0.24) !important;
  color: #FFFFFF !important;
}
.pc-btn-action svg {
  width: 17px;
  height: 17px;
  stroke: currentColor;
  stroke-width: 2.2;
  fill: none;
  flex-shrink: 0;
}

/* Spinner */
.pc-action-spinner {
  display: inline-block;
  width: 15px;
  height: 15px;
  border: 2px solid rgba(255, 255, 255, 0.35);
  border-top-color: #FFFFFF;
  border-radius: 50%;
  animation: pcSpin 0.6s linear infinite;
  margin-right: 6px;
}
@keyframes pcSpin { to { transform: rotate(360deg); } }

/* Alert Box */
.pc-alert-box {
  border-radius: 14px;
  padding: 12px 18px;
  font-weight: 850;
  font-size: 13px;
  margin-bottom: 18px;
}
.pc-alert-box.error {
  background: #FEE2E2;
  color: #991B1B;
  border: 1px solid #FCA5A5;
}
.pc-alert-box.info {
  background: #E0F2FE;
  color: #0369A1;
  border: 1px solid #7DD3FC;
}

/* Responsive Clean Base - Handled uniformly by form_mobile_ux.php */
.pc-field-item.full-width { grid-column: 1 / -1; }
</style>
<?php require_once __DIR__ . '/../inc/form_mobile_ux.php'; ?>

<?php
$plansActive = function_exists('plugin_schoolmanager_feature_enabled') ? plugin_schoolmanager_feature_enabled('plans') : true;
$bLabelsForSelect = function_exists('plugin_schoolmanager_building_labels') ? plugin_schoolmanager_building_labels() : ['A' => 'Edificio A', 'B' => 'Edificio B', 'C' => 'Edificio C'];
$roomsByBuilding = [];
foreach ($aulasAll as $rRow) {
    if (empty($rRow['id'])) { continue; }
    $bKey = (string)($rRow['building'] ?? 'A');
    $roomsByBuilding[$bKey][] = $rRow;
}
?>

<div class="pc-form-view" id="pcForm"
     data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>'
     data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>"
     data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>"
     data-msg-select-location="<?= htmlspecialchars(plugin_schoolmanager_tr('select_location_first', 'Selecciona una ubicación primero.'), ENT_QUOTES, 'UTF-8') ?>"
     data-msg-creating="<?= htmlspecialchars(plugin_schoolmanager_tr('creating_asset', 'Creando activo...'), ENT_QUOTES, 'UTF-8') ?>">

  <div class="pc-form-container">

    <?php if ($message): ?>
      <div class="pc-alert-box <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" id="pcServerAlert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="pc-alert-box error" id="pcClientAlert" style="display:none"></div>

    <!-- Interactive Type Tabs (Saves inputs across tabs) -->
    <div class="pc-types-grid" role="tablist">
      <?php foreach ($assetTypes as $type => $info): 
          $isActive = ($type === $itemtype);
      ?>
        <a class="pc-type-card <?= $isActive ? 'active' : '' ?>" 
           href="<?= htmlspecialchars(pc_tab_url($type, $tabState), ENT_QUOTES, 'UTF-8') ?>" 
           data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
           role="tab" 
           aria-selected="<?= $isActive ? 'true' : 'false' ?>">
          <div class="pc-type-icon-badge">
            <?php if ($type === 'Computer'): ?>
              <svg class="pc-type-svg" viewBox="0 0 24 24"><rect x="3" y="4" width="13" height="9.5" rx="1.8"/><path d="M7 17.5h4.5M9.2 13.5v4"/><rect x="17.5" y="6" width="3.5" height="11" rx="1.2"/><path d="M5 20h14"/></svg>
            <?php elseif ($type === 'Monitor'): ?>
              <svg class="pc-type-svg" viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="11.5" rx="2"/><path d="M8.5 20h7M12 16v4"/></svg>
            <?php elseif ($type === 'Printer'): ?>
              <svg class="pc-type-svg" viewBox="0 0 24 24"><path d="M7 8V4h10v4"/><rect x="7" y="14" width="10" height="6" rx="1"/><path d="M7 18H5a2 2 0 0 1-2-2v-5a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v5a2 2 0 0 1-2 2h-2"/><path d="M17 11h.01"/></svg>
            <?php elseif ($type === 'NetworkEquipment'): ?>
              <svg class="pc-type-svg" viewBox="0 0 24 24"><rect x="4" y="12.5" width="16" height="6.5" rx="2"/><path d="M7.5 15.8h.01M10.5 15.8h.01M13.5 15.8h.01"/><path d="M8 8.8a6 6 0 0 1 8 0M10.2 10.9a3 3 0 0 1 3.6 0"/></svg>
            <?php else: ?>
              <svg class="pc-type-svg" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2.2"/><path d="M7 10h.01M10 10h.01M13 10h.01M16 10h.01M7 14h10"/></svg>
            <?php endif; ?>
          </div>
          <div class="pc-type-card-text">
            <span class="pc-type-card-title"><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="pc-type-card-desc"><?= htmlspecialchars($info['desc'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Interactive Step Progress Bar -->
    <div class="pc-steps-tracker" aria-label="Progreso del formulario">
      <button type="button" class="pc-step-pill" id="pcStepPill1" data-scroll-target="#pcSection1">
        <span class="pc-step-pill-num">1</span>
        <span class="pc-step-pill-text">
          <strong>Identificación</strong>
          <small id="pcStepSub1">Nombre y modelo</small>
        </span>
      </button>
      <button type="button" class="pc-step-pill" id="pcStepPill2" data-scroll-target="#pcSection2">
        <span class="pc-step-pill-num">2</span>
        <span class="pc-step-pill-text">
          <strong>Ubicación</strong>
          <small id="pcStepSub2"><?= $locationId > 0 ? 'Aula asignada' : 'Elige aula o plano' ?></small>
        </span>
      </button>
      <button type="button" class="pc-step-pill" id="pcStepPill3" data-scroll-target="#pcSection3">
        <span class="pc-step-pill-num">3</span>
        <span class="pc-step-pill-text">
          <strong>Inventario y S/N</strong>
          <small id="pcStepSub3">Opcional</small>
        </span>
      </button>
      <button type="button" class="pc-step-pill" id="pcStepPill4" data-scroll-target="#pcSection4">
        <span class="pc-step-pill-num">4</span>
        <span class="pc-step-pill-text">
          <strong>Observaciones</strong>
          <small id="pcStepSub4">Notas adicionales</small>
        </span>
      </button>
    </div>

    <!-- Asset Creation Form -->
    <form method="post" id="assetGuidedForm">
      <?php pc_token_field(); ?>
      <input type="hidden" name="pc_create" value="1">
      <input type="hidden" name="v" value="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="itemtype" id="pcItemType" value="<?= htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="locations_id" id="pcLocationId" value="<?= htmlspecialchars((string)$locationId, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="location_label" id="pcLocationLabel" value="<?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="location_code" id="pcLocationCode" value="<?= htmlspecialchars($locationCode, ENT_QUOTES, 'UTF-8') ?>">

      <div class="pc-form-sections">
        
        <!-- CARD 1: Identification & Types -->
        <section class="pc-section-card" id="pcSection1">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">1</span>
              <h2 class="pc-section-title"><?= htmlspecialchars(sprintf(plugin_schoolmanager_tr('asset_details_heading', 'Identificación de %s'), $current['label']), ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
          </div>

          <div class="pc-fields-grid">
            <div class="pc-field-item full-width">
              <label class="pc-field-label" for="pcAssetName">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_asset_name', 'Nombre del activo'), ENT_QUOTES, 'UTF-8') ?> <span class="req">*</span>
              </label>
              <input type="text" 
                     class="pc-field-input" 
                     id="pcAssetName" 
                     name="name" 
                     value="<?= htmlspecialchars($nameVal, ENT_QUOTES, 'UTF-8') ?>" 
                     placeholder="<?= htmlspecialchars(sprintf($egTemplate, $current['label']), ENT_QUOTES, 'UTF-8') ?>" 
                     required>
              <div class="pc-quick-chips">
                <span class="pc-quick-chip-label">Sugerencias rápidas:</span>
                <button type="button" class="pc-quick-chip" data-prefix="<?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> Profesor">+ <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> Profesor</button>
                <button type="button" class="pc-quick-chip" data-prefix="<?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> Puesto 01">+ Puesto 01</button>
                <button type="button" class="pc-quick-chip" data-prefix="<?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> Aula">+ <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> Aula</button>
              </div>
            </div>

            <!-- Native Type Dropdown -->
            <div class="pc-field-item">
              <label class="pc-field-label">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_type', 'Tipo'), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <div>
                <?php if (class_exists($current['type_class'])) {
                    Dropdown::show($current['type_class'], ['name' => $current['type_field'], 'value' => $typeIdVal]);
                } ?>
              </div>
            </div>

            <!-- Native Model Dropdown -->
            <div class="pc-field-item">
              <label class="pc-field-label">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_model', 'Modelo'), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <div>
                <?php if (class_exists($current['model_class'])) {
                    Dropdown::show($current['model_class'], ['name' => $current['model_field'], 'value' => $modelIdVal]);
                } ?>
              </div>
            </div>
          </div>
        </section>

        <!-- CARD 2: Center Location (Interactive Card + Direct Select + Map Modal) -->
        <section class="pc-section-card" id="pcSection2">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">2</span>
              <h2 class="pc-section-title"><?= htmlspecialchars(plugin_schoolmanager_tr('field_location', 'Ubicación en el Centro Educativo'), ENT_QUOTES, 'UTF-8') ?> <span class="req">*</span></h2>
            </div>
            <span id="pcLocStatusBadge" class="pc-loc-badge <?= $locationId > 0 ? 'selected' : 'ready' ?>">
              <?= $locationId > 0 ? '✓ Aula asignada' : '📍 Selector activo' ?>
            </span>
          </div>

          <div class="pc-location-interactive-card <?= $locationId > 0 ? 'is-selected' : '' ?>" id="pcSelectedLocationCard">
            <div class="pc-loc-meta-box" id="pcSelectedLocation">
              <div class="pc-loc-icon-bubble">
                <svg class="pc-type-svg" viewBox="0 0 24 24"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
              </div>
              <div class="pc-loc-info-text">
                <h4 class="pc-loc-name" id="pcLocTitle">
                  <?= $locationId > 0 ? htmlspecialchars($locationLabel ?: ('Ubicación ID #' . $locationId), ENT_QUOTES, 'UTF-8') : htmlspecialchars(plugin_schoolmanager_tr('no_location', 'Selecciona el aula o espacio del centro'), ENT_QUOTES, 'UTF-8') ?>
                </h4>
                <p class="pc-loc-meta" id="pcLocMeta">
                  <?= $locationId > 0 ? htmlspecialchars($locationCode ?: ('ID GLPI #' . $locationId), ENT_QUOTES, 'UTF-8') : 'Puedes elegir directamente del desplegable rápido o abrir el plano interactivo' ?>
                </p>
              </div>
            </div>

            <div class="pc-loc-controls">
              <select class="pc-loc-quick-select" id="pcQuickLocationSelect" aria-label="Selección rápida de aula">
                <option value="0">— Selección rápida de aula —</option>
                <?php foreach ($roomsByBuilding as $bCode => $bRooms): ?>
                  <optgroup label="<?= htmlspecialchars((string)($bLabelsForSelect[$bCode] ?? ('Edificio ' . $bCode)), ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($bRooms as $rItem):
                      $rId = (int)($rItem['id'] ?? 0);
                      $rText = trim(($rItem['aula'] ?? $rItem['codigo'] ?? '') . (!empty($rItem['descripcion']) ? ' · ' . $rItem['descripcion'] : '') . ' (' . ($rItem['codigo'] ?? '') . ')');
                    ?>
                      <option value="<?= $rId ?>" <?= $locationId === $rId ? 'selected' : '' ?>><?= htmlspecialchars($rText, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>

              <button type="button" class="pc-btn-action pc-btn-primary" id="pcOpenSelector">
                <?php if ($plansActive): ?>
                  <svg viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                  <span id="pcBtnLocText"><?= $locationId > 0 ? htmlspecialchars(plugin_schoolmanager_tr('change_location', 'Ver en plano / Cambiar'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(plugin_schoolmanager_tr('btn_choose_location', 'Abrir plano y buscador'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                  <svg viewBox="0 0 24 24"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                  <span id="pcBtnLocText"><?= $locationId > 0 ? htmlspecialchars(plugin_schoolmanager_tr('change_classroom_list', 'Cambiar aula'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(plugin_schoolmanager_tr('select_classroom_list', 'Seleccionar aula en lista'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </button>
            </div>
          </div>
        </section>

        <!-- CARD 3: Inventory, Serial & Hardware State -->
        <section class="pc-section-card" id="pcSection3">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">3</span>
              <h2 class="pc-section-title"><?= htmlspecialchars(plugin_schoolmanager_tr('inventory_serial_status', 'Inventario, Serie y Estado'), ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
          </div>

          <div class="pc-fields-grid">
            <div class="pc-field-item">
              <label class="pc-field-label" for="pcSerial">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_serial_number', 'Número de serie'), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <input type="text" 
                     class="pc-field-input" 
                     id="pcSerial" 
                     name="serial" 
                     value="<?= htmlspecialchars($serialVal, ENT_QUOTES, 'UTF-8') ?>" 
                     placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('serial_number_placeholder', 'Etiqueta del fabricante (S/N)'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="pc-field-item">
              <label class="pc-field-label" for="pcOtherSerial">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_inventory_number', 'Número de inventario / Código interno'), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <input type="text" 
                     class="pc-field-input" 
                     id="pcOtherSerial" 
                     name="otherserial" 
                     value="<?= htmlspecialchars($otherserialVal, ENT_QUOTES, 'UTF-8') ?>" 
                     placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('inventory_number_placeholder', 'Código interno / etiqueta de inventario'), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="pc-field-item">
              <label class="pc-field-label">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_status', 'Estado operativo'), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <div>
                <?php if (class_exists('State')) {
                    Dropdown::show('State', ['name' => 'states_id', 'value' => $statesIdVal]);
                } ?>
              </div>
            </div>

            <div class="pc-field-item">
              <label class="pc-field-label">
                <?= htmlspecialchars(plugin_schoolmanager_tr('field_manufacturer', 'Fabricante'), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <div>
                <?php if (class_exists('Manufacturer')) {
                    Dropdown::show('Manufacturer', ['name' => 'manufacturers_id', 'value' => $mfgIdVal]);
                } ?>
              </div>
            </div>
          </div>
        </section>

        <!-- CARD 4: Observations -->
        <section class="pc-section-card" id="pcSection4">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">4</span>
              <h2 class="pc-section-title"><?= htmlspecialchars(plugin_schoolmanager_tr('field_comments', 'Observaciones y Accesorios'), ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
          </div>
          <div>
            <textarea class="pc-field-textarea" 
                      id="pcComment"
                      name="comment" 
                      placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('comments_placeholder', 'Puesto de trabajo, monitor asociado, observaciones específicas...'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($commentVal, ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
        </section>

      </div>

      <!-- Action Bar -->
      <footer class="pc-action-bar">
        <a class="pc-btn-action pc-btn-secondary" 
           href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/dashboard.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>" 
           onclick="if(history.length>1){history.back();return false;}">
          <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
          <span><?= htmlspecialchars(plugin_schoolmanager_tr('btn_cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        
        <button class="pc-btn-action pc-btn-primary" type="submit" id="pcSubmitBtn">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          <span id="pcSubmitBtnText"><?= htmlspecialchars(sprintf($createTemplate, mb_strtolower($current['label'], 'UTF-8')), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
      </footer>
    </form>

  </div>

  <?php include(__DIR__ . '/../inc/location_modal_markup.php'); ?>
</div>

<?php include(__DIR__ . '/../inc/location_modal_script.php'); ?>
<script src="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/js/custom-combobox.js?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"></script>

<script>
(function(){
  const formView = document.getElementById('pcForm');
  if (!formView) return;

  const msgSelectLocation = formView.dataset.msgSelectLocation || 'Selecciona una ubicación primero.';
  const msgCreating = formView.dataset.msgCreating || 'Creando activo...';

  function updateStepTracker() {
    const nameInput = document.getElementById('pcAssetName');
    const locIdInput = document.getElementById('pcLocationId');
    const serialInput = document.getElementById('pcSerial');
    const otherSerialInput = document.getElementById('pcOtherSerial');
    const commentInput = document.getElementById('pcComment');

    const hasName = !!(nameInput && nameInput.value.trim() !== '');
    const hasLoc = !!(locIdInput && parseInt(locIdInput.value || '0', 10) > 0);
    const hasInv = !!((serialInput && serialInput.value.trim() !== '') || (otherSerialInput && otherSerialInput.value.trim() !== ''));
    const hasComment = !!(commentInput && commentInput.value.trim() !== '');

    const p1 = document.getElementById('pcStepPill1');
    const p2 = document.getElementById('pcStepPill2');
    const p3 = document.getElementById('pcStepPill3');
    const p4 = document.getElementById('pcStepPill4');

    if (p1) p1.classList.toggle('is-done', hasName);
    if (p2) p2.classList.toggle('is-done', hasLoc);
    if (p3) p3.classList.toggle('is-done', hasInv);
    if (p4) p4.classList.toggle('is-done', hasComment);

    const sub1 = document.getElementById('pcStepSub1');
    const sub2 = document.getElementById('pcStepSub2');
    if (sub1) sub1.textContent = hasName ? '✓ Completado' : 'Nombre y modelo';
    if (sub2) sub2.textContent = hasLoc ? '✓ Aula asignada' : 'Elige aula o plano';
  }

  // Smooth scroll on step tracker click
  document.querySelectorAll('.pc-step-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      const target = document.querySelector(pill.dataset.scrollTarget || '');
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  // Quick Naming Chips
  document.querySelectorAll('.pc-quick-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const nameInput = document.getElementById('pcAssetName');
      if (!nameInput) return;
      const prefix = chip.dataset.prefix || '';
      const locCode = (document.getElementById('pcLocationCode')?.value || '').trim();
      const locLabel = (document.getElementById('pcLocationLabel')?.value || '').trim();
      const suffix = locCode ? (' - ' + locCode) : (locLabel ? (' - ' + locLabel.split('·')[1]?.trim() || '') : '');
      nameInput.value = (prefix + suffix).trim();
      nameInput.focus();
      updateStepTracker();
    });
  });

  // Dynamic preserve inputs when clicking type cards
  document.querySelectorAll('.pc-type-card').forEach(tab => {
    tab.addEventListener('click', function() {
      const form = document.getElementById('assetGuidedForm');
      if (!form) return;
      const nameVal = form.querySelector('[name=name]')?.value || '';
      const locId = document.getElementById('pcLocationId')?.value || '';
      const locLabel = document.getElementById('pcLocationLabel')?.value || '';
      const locCode = document.getElementById('pcLocationCode')?.value || '';
      const serial = form.querySelector('[name=serial]')?.value || '';
      const otherserial = form.querySelector('[name=otherserial]')?.value || '';
      const comment = form.querySelector('[name=comment]')?.value || '';

      const url = new URL(this.href, location.href);
      if (nameVal) url.searchParams.set('name', nameVal);
      if (locId) url.searchParams.set('locations_id', locId);
      if (locLabel) url.searchParams.set('location_label', locLabel);
      if (locCode) url.searchParams.set('location_code', locCode);
      if (serial) url.searchParams.set('serial', serial);
      if (otherserial) url.searchParams.set('otherserial', otherserial);
      if (comment) url.searchParams.set('comment', comment);
      this.href = url.toString();
    });
  });

  // Location selector reactive updates
  window.addEventListener('schoolmanager:location-selected', function(ev) {
    const r = ev.detail || {};
    const card = document.getElementById('pcSelectedLocationCard');
    const badge = document.getElementById('pcLocStatusBadge');
    const title = document.getElementById('pcLocTitle');
    const meta = document.getElementById('pcLocMeta');
    const btnText = document.getElementById('pcBtnLocText');
    const clientAlert = document.getElementById('pcClientAlert');

    if (clientAlert) clientAlert.style.display = 'none';
    if (card) card.classList.add('is-selected');
    if (badge) {
      badge.className = 'pc-loc-badge selected';
      badge.textContent = '✓ Aula asignada';
    }
    if (title) {
      title.textContent = (r.aula || r.codigo || 'Aula') + (r.descripcion ? ' · ' + r.descripcion : '');
    }
    if (meta) {
      meta.textContent = (r.building ? 'Edificio ' + r.building : '') + (r.planta || r.floor ? ' · ' + (r.planta || r.floor) : '') + (r.codigo ? ' · ' + r.codigo : '');
    }
    if (btnText) {
      btnText.textContent = <?= json_encode($plansActive ? 'Ver en plano / Cambiar' : 'Cambiar aula') ?>;
    }
    updateStepTracker();
  });

  document.addEventListener('input', updateStepTracker);
  document.addEventListener('change', updateStepTracker);
  updateStepTracker();

  // Form submit handling
  const form = document.getElementById('assetGuidedForm');
  if (form) {
    form.addEventListener('submit', function(e){
      const locId = (document.getElementById('pcLocationId') || {}).value;
      const clientAlert = document.getElementById('pcClientAlert');
      if (!locId || parseInt(locId, 10) <= 0) {
        e.preventDefault();
        if (clientAlert) {
          clientAlert.textContent = msgSelectLocation;
          clientAlert.style.display = 'block';
        }
        const sec2 = document.getElementById('pcSection2');
        if (sec2) sec2.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      const submitBtn = document.getElementById('pcSubmitBtn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="pc-action-spinner"></span> ' + msgCreating;
      }
    });
  }
})();
</script>
<?php sm_page_footer(); ?>
