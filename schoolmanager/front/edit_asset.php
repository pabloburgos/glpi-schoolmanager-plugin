<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

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

$assetTitle = plugin_schoolmanager_asset_clean_title($itemtype, $fields);
$topActions = '
<a class="sm-btn sm-btn-secondary" href="' . htmlspecialchars($root . '/plugins/schoolmanager/front/asset_management.php?itemtype=' . urlencode($itemtype) . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') . '">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="m15 18-6-6 6-6"/></svg>
  <span>Volver a Gestión</span>
</a>
<a class="sm-btn sm-btn-secondary" href="' . htmlspecialchars($root . $current['native'] . '?id=' . $id, ENT_QUOTES, 'UTF-8') . '">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="M14 5h5v5M10 14 19 5M19 13v5H5V5h5"/></svg>
  <span>Vista Nativa GLPI</span>
</a>';

sm_page_header([
    'title'      => 'Modificar: ' . $assetTitle,
    'subtitle'   => 'Edita ubicación, datos de inventario, estado, fabricante, tipo y modelo.',
    'active_tab' => 'gestion_activos',
    'actions'    => $topActions,
]);
require_once(__DIR__ . '/../inc/ui_helpers.php');

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
.gsm-edit {
  --primary: #0F2951;
  --teal: #088E91;
  --ink: #0F2951;
  --muted: #64748B;
  --line: #D7E6EC;
  min-height: calc(100vh - 80px);
  padding: 0 !important;
  width: 100% !important;
  margin: 0 !important;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
  color: var(--ink);
}
.gsm-edit * { box-sizing: border-box; }
.gsm-ui-icon {
  width: 18px;
  height: 18px;
  min-width: 18px;
  display: inline-block;
  vertical-align: -0.18em;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
  fill: none;
  flex: 0 0 auto;
}
.gsm-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
}
.gsm-panel {
  background: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 14px rgba(15, 41, 81, 0.04);
}
.gsm-panel h2 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 4px;
  color: var(--primary);
  font-size: 18px;
  font-weight: 850;
}
.gsm-panel small {
  color: var(--muted);
  font-weight: 650;
  font-size: 13px;
}
.gsm-message {
  border-radius: 14px;
  padding: 13px 16px;
  font-weight: 750;
  margin-bottom: 16px;
}
.gsm-message.ok { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
.gsm-message.error { background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; }
.gsm-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 18px;
  margin-top: 16px;
}
.gsm-field { display: flex; flex-direction: column; gap: 6px; }
.gsm-field.full { grid-column: 1 / -1; }
.gsm-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-weight: 800;
  font-size: 13px;
  color: #173844;
  margin: 0;
}
.gsm-input, .gsm-textarea {
  width: 100%;
  border: 1px solid #CBD5E1;
  border-radius: 12px;
  padding: 10px 14px;
  background: #FFFFFF;
  color: var(--ink);
  font-weight: 600;
  font-size: 14px;
  min-height: 42px;
}
.gsm-textarea { min-height: 100px; resize: vertical; }
.gsm-input:focus, .gsm-textarea:focus {
  outline: none;
  border-color: #088E91;
  box-shadow: 0 0 0 3px rgba(8, 142, 145, 0.14);
}
.pc-location-interactive-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 16px;
  border-radius: 14px;
  background: #F8FAFC;
  border: 1.5px solid #CBD5E1;
  flex-wrap: wrap;
}
.pc-location-interactive-card.is-selected {
  border-color: #86EFAC;
  background: #F0FDF4;
}
.pc-loc-meta-box {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 200px;
  flex: 1;
}
.pc-loc-icon-bubble {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: inline-grid;
  place-items: center;
  background: #E0F2FE;
  color: #0F2951;
  flex-shrink: 0;
}
.pc-location-interactive-card.is-selected .pc-loc-icon-bubble {
  background: #10B981;
  color: #FFFFFF;
}
.pc-loc-name { margin: 0; font-size: 14.5px; font-weight: 850; color: #0F2951; }
.pc-loc-meta { margin: 2px 0 0; font-size: 12px; font-weight: 650; color: #64748B; }
.pc-loc-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pc-loc-quick-select {
  min-width: 210px;
  height: 40px;
  border: 1px solid #CBD5E1;
  border-radius: 10px;
  padding: 0 32px 0 12px;
  font-size: 13px;
  font-weight: 650;
  color: #0F2951;
  background: #FFFFFF;
}
.gsm-submit {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #E2E8F0;
}
.gsm-btn {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  min-height: 42px !important;
  padding: 0 20px !important;
  border-radius: 10px !important;
  font-weight: 700 !important;
  font-size: 13.5px !important;
  text-decoration: none !important;
  cursor: pointer !important;
  white-space: nowrap !important;
}
.gsm-btn.primary, .gsm-btn.gold {
  background: #0F2951 !important;
  color: #FFFFFF !important;
  border: 1px solid #0F2951 !important;
}
.gsm-btn.primary:hover, .gsm-btn.gold:hover {
  background: #088E91 !important;
  border-color: #088E91 !important;
  color: #FFFFFF !important;
}
.gsm-btn.soft {
  background: #FFFFFF !important;
  color: #0F2951 !important;
  border: 1px solid #CBD5E1 !important;
}
.gsm-btn.soft:hover {
  background: #F1F5F9 !important;
  border-color: #94A3B8 !important;
}
.gsm-info-grid { display: grid; gap: 10px; }
.gsm-info {
  position: relative;
  padding: 14px 14px 14px 44px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #F8FAFC;
}
.gsm-info .gsm-ui-icon {
  position: absolute;
  left: 14px;
  top: 16px;
  width: 20px;
  height: 20px;
  color: #088E91;
}
.gsm-info b { display: block; color: var(--primary); font-size: 16px; font-weight: 850; margin-top: 2px; }
.gsm-info span { color: var(--muted); font-weight: 700; font-size: 12px; }
.gsm-native { display: grid; gap: 10px; margin-top: 14px; }
.gsm-native .gsm-btn { width: 100%; }
@media (max-width: 1020px) {
  .gsm-layout { grid-template-columns: 1fr; }
  .gsm-info-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .gsm-fields, .gsm-info-grid { grid-template-columns: 1fr; }
  .pc-location-interactive-card, .pc-loc-controls { flex-direction: column; align-items: stretch; width: 100%; }
  .pc-loc-quick-select, .gsm-btn { width: 100% !important; }
  .gsm-submit { flex-direction: column-reverse; }
}
</style>
<?php
$bLabelsForSelect = function_exists('plugin_schoolmanager_building_labels') ? plugin_schoolmanager_building_labels() : ['A' => 'Edificio A', 'B' => 'Edificio B', 'C' => 'Edificio C'];
$roomsByBuilding = [];
foreach ($aulasAll as $rRow) {
    if (empty($rRow['id'])) { continue; }
    $bKey = (string)($rRow['building'] ?? 'A');
    $roomsByBuilding[$bKey][] = $rRow;
}
?>
<div class="gsm-edit" id="pcForm" data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>' data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>" data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($message): ?><div class="gsm-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <section class="gsm-layout"><main class="gsm-panel"><h2><?= gsm_edit_asset_svg($itemtype) ?> Datos editables</h2><small>Los cambios se guardan directamente sobre el activo de GLPI.</small>
    <form method="post" id="assetEditForm"><input type="hidden" name="pc_update" value="1"><input type="hidden" name="itemtype" value="<?= htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= $id ?>"><?php pc_token_hidden_edit(); ?><input type="hidden" name="locations_id" id="pcLocationId" value="<?= $locId ?>"><input type="hidden" name="location_label" id="pcLocationLabel" value="<?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="location_code" id="pcLocationCode" value="">
      <div class="gsm-fields">
        <label class="gsm-field full"><span class="gsm-label"><?= gsm_edit_svg('name') ?> Nombre del activo *</span><input class="gsm-input" name="name" value="<?= htmlspecialchars((string)($fields['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
        <div class="gsm-field full">
          <span class="gsm-label"><?= gsm_edit_svg('pin') ?> Ubicación en el Centro</span>
          <div class="pc-location-interactive-card <?= $locId > 0 ? 'is-selected' : '' ?>" id="pcSelectedLocationCard">
            <div class="pc-loc-meta-box" id="pcSelectedLocation">
              <div class="pc-loc-icon-bubble"><?= gsm_edit_svg('pin') ?></div>
              <div class="pc-loc-info-text">
                <h4 class="pc-loc-name" id="pcLocTitle"><?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="pc-loc-meta" id="pcLocMeta"><?= $locId > 0 ? 'ID GLPI #' . $locId : 'Selecciona desde el desplegable rápido o el plano' ?></p>
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
                      <option value="<?= $rId ?>" <?= $locId === $rId ? 'selected' : '' ?>><?= htmlspecialchars($rText, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <?php $plansActive = function_exists('plugin_schoolmanager_feature_enabled') ? plugin_schoolmanager_feature_enabled('plans') : true; ?>
              <button type="button" class="gsm-btn primary" id="pcOpenSelector"><?= gsm_edit_svg($plansActive ? 'map' : 'pin') ?> <span><?= $plansActive ? 'Abrir plano y buscador' : 'Seleccionar aula en lista' ?></span></button>
            </div>
          </div>
        </div>
        <label class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('serial') ?> Número de serie</span><input class="gsm-input" name="serial" value="<?= htmlspecialchars((string)($fields['serial'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <label class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('inventory') ?> Número de inventario</span><input class="gsm-input" name="otherserial" value="<?= htmlspecialchars((string)($fields['otherserial'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <div class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('state') ?> Estado</span><?php if (class_exists('State')) { Dropdown::show('State', ['name'=>'states_id', 'value'=>(int)($fields['states_id'] ?? 0)]); } ?></div>
        <div class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('factory') ?> Fabricante</span><?php if (class_exists('Manufacturer')) { Dropdown::show('Manufacturer', ['name'=>'manufacturers_id', 'value'=>(int)($fields['manufacturers_id'] ?? 0)]); } ?></div>
        <div class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('type') ?> Tipo</span><?php if (class_exists($current['type_class'])) { Dropdown::show($current['type_class'], ['name'=>$current['type_field'], 'value'=>(int)($fields[$current['type_field']] ?? 0)]); } ?></div>
        <div class="gsm-field"><span class="gsm-label"><?= gsm_edit_svg('model') ?> Modelo</span><?php if (class_exists($current['model_class'])) { Dropdown::show($current['model_class'], ['name'=>$current['model_field'], 'value'=>(int)($fields[$current['model_field']] ?? 0)]); } ?></div>
        <label class="gsm-field full"><span class="gsm-label"><?= gsm_edit_svg('comment') ?> Comentarios</span><textarea class="gsm-textarea" name="comment"><?= htmlspecialchars((string)($fields['comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
      </div><div class="gsm-submit"><a class="gsm-btn soft" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/asset_management.php?itemtype=' . urlencode($itemtype) . '&v=234', ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('cancel') ?> Cancelar</a><button class="gsm-btn primary" type="submit"><?= gsm_edit_svg('save') ?> Guardar cambios</button></div>
    </form></main>
    <aside class="gsm-panel"><h2><?= gsm_edit_svg('summary') ?> Resumen rápido</h2><small>Información útil para revisar antes de guardar.</small><div class="gsm-info-grid" style="margin-top:14px"><div class="gsm-info"><?= gsm_edit_asset_svg($itemtype) ?><span>Tipo</span><b><?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></b></div><div class="gsm-info"><?= gsm_edit_svg('hash') ?><span>ID GLPI</span><b>#<?= $id ?></b></div><div class="gsm-info"><?= gsm_edit_svg('pin') ?><span>Ubicación actual</span><b><?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?></b></div><div class="gsm-info"><?= gsm_edit_svg('inventory') ?><span>Inventario</span><b><?= htmlspecialchars((string)($fields['otherserial'] ?? 'Sin dato'), ENT_QUOTES, 'UTF-8') ?></b></div></div><div class="gsm-native"><a class="gsm-btn primary" href="<?= htmlspecialchars($root . $current['native'] . '?id=' . $id, ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('external') ?> Abrir ficha nativa</a><a class="gsm-btn soft" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/new_asset.php?itemtype=' . urlencode($itemtype) . '&v=267', ENT_QUOTES, 'UTF-8') ?>"><?= gsm_edit_svg('plus') ?> Crear otro <?= htmlspecialchars(mb_strtolower($current['label'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></a></div></aside>
  </section>
</div><?php include(__DIR__ . '/../inc/location_modal_markup.php'); ?>
<?php include(__DIR__ . '/../inc/location_modal_script.php'); ?>
<script>document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('pcUseLocation');if(b)b.textContent='Aplicar ubicación al activo';});</script>
<script>document.getElementById('assetEditForm')?.addEventListener('submit',function(){const b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.classList.add('is-loading');b.innerHTML='<?= gsm_edit_svg('save') ?> Guardando...';}});</script>
<script src="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/js/custom-combobox.js?v=267"></script>
<?php sm_page_footer(); ?>

