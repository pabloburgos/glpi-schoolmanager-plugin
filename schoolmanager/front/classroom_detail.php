<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) { http_response_code(500); }
        echo '<div style="margin:24px;padding:20px;border:1px solid #efc9cc;border-radius:18px;background:#fff6f6;color:#07384d;font-family:system-ui">'
           . '<h2 style="margin:0 0 8px;color:#b6252b">No se pudo cargar el detalle del aula</h2>'
           . '<p>Se ha producido un error PHP en la ficha del aula. Revisa el log de Apache para ver la línea exacta.</p>'
           . '<a style="display:inline-block;margin-top:10px;padding:10px 14px;border-radius:12px;background:#07384d;color:white;text-decoration:none;font-weight:800" href="/plugins/schoolmanager/front/dashboard.php">Volver a Inicio</a>'
           . '</div>';
    }
});

global $CFG_GLPI, $DB;
$root = $CFG_GLPI['root_doc'] ?? '';
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '2.0.1';

function pcda_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pcda_req($k, $d=''): string { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function pcda_short($name): string {
    if (function_exists('plugin_schoolmanager_short_location')) { return plugin_schoolmanager_short_location($name); }
    $plain = html_entity_decode(strip_tags((string)$name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $plain))));
    return $parts ? end($parts) : ($plain !== '' ? $plain : 'Ubicación');
}
function pcda_location_name($id): string {
    $id = (int)$id;
    if ($id <= 0) { return ''; }
    try {
        if (class_exists('Dropdown')) {
            $n = Dropdown::getDropdownName('glpi_locations', $id);
            if (trim((string)$n) !== '') { return pcda_short($n); }
        }
    } catch (Throwable $e) {}
    return 'Ubicación GLPI #' . $id;
}
function pcda_count_assets($locationId, array $types): array {
    global $DB;
    $out = [];
    foreach ($types as $type => $info) {
        $out[$type] = 0;
        $table = $info['table'] ?? '';
        try {
            if ((int)$locationId <= 0 || $table === '' || !isset($DB) || !method_exists($DB, 'request')) { continue; }
            if (method_exists($DB, 'tableExists') && !$DB->tableExists($table)) { continue; }
            $where = ['locations_id' => (int)$locationId];
            if (method_exists($DB, 'fieldExists') && $DB->fieldExists($table, 'is_deleted')) { $where['is_deleted'] = 0; }
            $it = $DB->request(['FROM' => $table, 'WHERE' => $where]);
            $n = 0; foreach ($it as $r) { $n++; }
            $out[$type] = $n;
        } catch (Throwable $e) { $out[$type] = 0; }
    }
    return $out;
}
function pcda_preview($locationId, array $types, int $limit = 12): array {
    global $DB;
    $out = [];
    foreach ($types as $type => $info) {
        $table = $info['table'] ?? '';
        try {
            if ((int)$locationId <= 0 || $table === '' || !isset($DB) || !method_exists($DB, 'request')) { continue; }
            if (method_exists($DB, 'tableExists') && !$DB->tableExists($table)) { continue; }
            $where = ['locations_id' => (int)$locationId];
            if (method_exists($DB, 'fieldExists') && $DB->fieldExists($table, 'is_deleted')) { $where['is_deleted'] = 0; }
            $it = $DB->request(['FROM' => $table, 'WHERE' => $where, 'LIMIT' => $limit]);
            foreach ($it as $row) {
                $name = function_exists('plugin_schoolmanager_asset_display_title')
                    ? plugin_schoolmanager_asset_display_title($type, $row)
                    : trim((string)($row['name'] ?? ''));
                if ($name === '') { $name = ($info['label'] ?? $type) . ' #' . (int)($row['id'] ?? 0); }
                $assetId = (int)($row['id'] ?? 0);
                $url = '';
                if ($assetId > 0 && !empty($info['native'])) {
                    $url = ($GLOBALS['CFG_GLPI']['root_doc'] ?? '') . $info['native'] . '?id=' . $assetId;
                }
                $out[] = [
                    'name'     => $name,
                    'id'       => $assetId,
                    'itemtype' => $type,
                    'label'    => $info['label'] ?? $type,
                    'icon'     => $info['icon'] ?? 'computer',
                    'url'      => $url,
                ];
                if (count($out) >= $limit) { return $out; }
            }
        } catch (Throwable $e) {}
    }
    return $out;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0 && isset($_GET['location'])) { $id = (int)$_GET['location']; }
$code = pcda_req('code');
$building = preg_replace('/[^A-Z0-9]/', '', strtoupper(pcda_req('building')));
$floor = strtoupper(pcda_req('floor'));
$q = pcda_req('q', pcda_req('room'));

$aulas = [];
try {
    $tmp = require(__DIR__ . '/../inc/aulas_data.php');
    if (is_array($tmp)) { $aulas = $tmp; }
} catch (Throwable $e) { $aulas = []; }

$room = null;
foreach ($aulas as $a) {
    $aid = (int)($a['id'] ?? 0);
    $acode = trim((string)($a['codigo'] ?? ''));
    if ($id > 0 && $aid === $id) { $room = $a; break; }
    if ($code !== '' && strcasecmp($acode, $code) === 0) { $room = $a; break; }
}
if (!$room && ($code !== '' || $q !== '')) {
    $needle = mb_strtolower($code !== '' ? $code : $q, 'UTF-8');
    foreach ($aulas as $a) {
        $hay = mb_strtolower(($a['codigo'] ?? '') . ' ' . ($a['aula'] ?? '') . ' ' . ($a['descripcion'] ?? ''), 'UTF-8');
        if ($needle !== '' && strpos($hay, $needle) !== false) { $room = $a; break; }
    }
}
$nativeName = $id > 0 ? pcda_location_name($id) : '';
if (!$room) {
    $room = [
        'id'          => $id,
        'aula'        => $nativeName ?: ($q ?: ($code ?: 'Ubicación')),
        'descripcion' => 'Ubicación registrada en GLPI.',
        'codigo'      => $code,
        'building'    => $building,
        'floor'       => $floor,
        'planta'      => $floor,
    ];
}
$roomId = (int)($room['id'] ?? $id);
$roomName = trim((string)($room['aula'] ?? ''));
if ($roomName === '' && $nativeName !== '') { $roomName = $nativeName; }
if ($roomName === '') { $roomName = $roomId > 0 ? 'Ubicación GLPI #' . $roomId : 'Ubicación'; }
$roomDesc = trim((string)($room['descripcion'] ?? 'Ubicación registrada en GLPI.'));
$roomBuilding = trim((string)($room['building'] ?? $building));
$roomFloor = trim((string)($room['floor'] ?? $floor));
$roomCode = trim((string)($room['codigo'] ?? $code));

$types = function_exists('plugin_schoolmanager_asset_types') ? plugin_schoolmanager_asset_types() : [];
$counts = pcda_count_assets($roomId, $types);
$total = array_sum(array_map('intval', $counts));
$preview = pcda_preview($roomId, $types, 12);
$canNative = function_exists('plugin_schoolmanager_can_open_native_locations') ? plugin_schoolmanager_can_open_native_locations() : false;

$defaultBuilding = function_exists('plugin_schoolmanager_default_building_code') ? plugin_schoolmanager_default_building_code() : 'MAIN';
$defaultFloor = function_exists('plugin_schoolmanager_default_floor_code') ? plugin_schoolmanager_default_floor_code($roomBuilding ?: $defaultBuilding) : 'G';

$mapUrl = $root . '/plugins/schoolmanager/front/selector.php?building=' . rawurlencode($roomBuilding ?: $defaultBuilding) . '&floor=' . rawurlencode($roomFloor ?: $defaultFloor) . '&room=' . rawurlencode($roomName) . '&v=' . rawurlencode($version);
$incidentUrl = $root . '/plugins/schoolmanager/front/new_ticket.php?location_id=' . rawurlencode((string)$roomId) . '&v=' . rawurlencode($version);
$assetsUrl = $root . '/plugins/schoolmanager/front/asset_management.php?location=' . rawurlencode((string)$roomId) . '&sort=location&v=' . rawurlencode($version);
$listUrl = $root . '/plugins/schoolmanager/front/classrooms.php?building=' . rawurlencode($roomBuilding ?: $defaultBuilding) . '&v=' . rawurlencode($version);
$nativeUrl = $roomId > 0 ? $root . '/front/location.form.php?id=' . $roomId : '';

$actions = '<a class="sm-btn-secondary" href="' . pcda_h($listUrl) . '">'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>'
         . '<span>' . pcda_h(plugin_schoolmanager_tr('classroom_list', 'Lista de aulas')) . '</span></a>';

sm_page_header([
    'title'      => plugin_schoolmanager_tr('classroom_details', 'Ficha del Aula') . ' · ' . $roomName,
    'subtitle'   => $roomDesc,
    'active_tab' => 'aulas',
    'actions'    => $actions,
]);
?>

<!-- Metadata Top Bar -->
<div class="sm-kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:18px">
  <div class="sm-kpi-card">
    <div class="sm-kpi-label"><?= pcda_h(plugin_schoolmanager_tr('classroom', 'Aula')) ?></div>
    <div class="sm-kpi-value" style="font-size:22px"><?= pcda_h($roomName) ?></div>
  </div>
  <div class="sm-kpi-card">
    <div class="sm-kpi-label">ID GLPI</div>
    <div class="sm-kpi-value"><?= $roomId > 0 ? '#' . (int)$roomId : '—' ?></div>
  </div>
  <div class="sm-kpi-card">
    <div class="sm-kpi-label"><?= pcda_h(plugin_schoolmanager_tr('code', 'Código')) ?></div>
    <div class="sm-kpi-value"><?= pcda_h($roomCode ?: '—') ?></div>
  </div>
  <div class="sm-kpi-card">
    <div class="sm-kpi-label"><?= pcda_h(plugin_schoolmanager_tr('building', 'Edificio')) ?> · <?= pcda_h(plugin_schoolmanager_tr('floor', 'Planta')) ?></div>
    <div class="sm-kpi-value"><?= pcda_h($roomBuilding ?: '—') ?> · <?= pcda_h($roomFloor ?: '—') ?></div>
  </div>
  <div class="sm-kpi-card" style="border-color:var(--sm-accent,#b6252b);background:#fff1f2">
    <div class="sm-kpi-label" style="color:var(--sm-accent,#b6252b)"><?= pcda_h(plugin_schoolmanager_tr('total_assets', 'Total Activos')) ?></div>
    <div class="sm-kpi-value" style="color:var(--sm-accent,#b6252b)"><?= (int)$total ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1fr) clamp(280px,28vw,360px);gap:18px;align-items:start">
  <!-- Left Column: Asset Breakdown & Previews -->
  <div style="display:grid;gap:18px">
    <!-- Assets by Type -->
    <div class="sm-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <h2 style="margin:0;font-size:18px;font-weight:950;color:var(--sm-primary,#07384d);display:flex;align-items:center;gap:8px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          <span><?= pcda_h(plugin_schoolmanager_tr('linked_inventory', 'Inventario Vinculado')) ?></span>
        </h2>
        <?php if ($roomId > 0 && $total > 0): ?>
          <a class="sm-btn-secondary" style="font-size:12px;padding:6px 12px" href="<?= pcda_h($assetsUrl) ?>">
            <?= pcda_h(plugin_schoolmanager_tr('view_all_assets', 'Ver todos')) ?> &rarr;
          </a>
        <?php endif; ?>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px">
        <?php foreach ($types as $type => $info):
          $count = (int)($counts[$type] ?? 0);
          if ($type === 'Projector' && $count === 0) { continue; }
        ?>
          <div style="padding:14px;border-radius:16px;border:1px solid var(--sm-border,#d7e6ec);background:var(--sm-soft,#f4fafc);display:flex;flex-direction:column;justify-content:space-between;min-height:78px">
            <div style="font-size:12px;font-weight:900;color:var(--sm-muted,#5c7280);text-transform:uppercase;letter-spacing:0.04em">
              <?= pcda_h($info['plural'] ?? $info['label'] ?? $type) ?>
            </div>
            <div style="font-size:24px;font-weight:950;color:var(--sm-primary,#07384d);line-height:1">
              <?= $count ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:14px;padding:10px 14px;border-radius:12px;background:#f8fbfc;border:1px solid var(--sm-border,#d7e6ec);color:var(--sm-muted,#5c7280);font-size:12px;font-weight:800;display:flex;align-items:center;gap:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex:0 0 16px" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span><?= pcda_h(plugin_schoolmanager_tr('location_assets_notice', 'Estos datos se leen directamente desde GLPI para la ubicación asociada a este aula.')) ?></span>
      </div>
    </div>

    <!-- Featured Assets List -->
    <div class="sm-card">
      <h2 style="margin:0 0 14px;font-size:18px;font-weight:950;color:var(--sm-primary,#07384d);display:flex;align-items:center;gap:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= pcda_h(plugin_schoolmanager_tr('featured_assets', 'Activos Destacados del Aula')) ?></span>
      </h2>

      <?php if (!$preview): ?>
        <p style="margin:0;color:var(--sm-muted,#5c7280);font-size:14px;font-weight:800;padding:14px 0">
          <?= pcda_h(plugin_schoolmanager_tr('no_assets_linked', 'No hay activos vinculados a esta ubicación o no se pueden leer con tu perfil actual.')) ?>
        </p>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($preview as $asset):
            $assetItemtype = (string)($asset['itemtype'] ?? '');
            $assetId = (int)($asset['id'] ?? 0);
            $canOpenAsset = $assetItemtype !== '' && $assetId > 0 && function_exists('plugin_schoolmanager_can_update_asset') && plugin_schoolmanager_can_update_asset($assetItemtype);
            $assetOpenUrl = $canOpenAsset ? ($root . '/plugins/schoolmanager/front/edit_asset.php?itemtype=' . rawurlencode($assetItemtype) . '&id=' . $assetId . '&v=' . rawurlencode($version)) : '';
          ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-radius:14px;border:1px solid var(--sm-border,#d7e6ec);background:#fff;transition:box-shadow .15s ease">
              <div style="display:flex;align-items:center;gap:12px;min-width:0">
                <div style="width:38px;height:38px;border-radius:12px;background:var(--sm-soft,#f4fafc);border:1px solid var(--sm-border,#d7e6ec);display:grid;place-items:center;color:var(--sm-primary,#07384d);flex:0 0 38px">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <div style="min-width:0">
                  <div style="font-weight:950;color:var(--sm-primary,#07384d);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= pcda_h($asset['name'] ?? 'Activo') ?>
                  </div>
                  <div style="font-size:12px;color:var(--sm-muted,#5c7280);font-weight:800">
                    <?= pcda_h($asset['label'] ?? '') ?> · ID <?= (int)($asset['id'] ?? 0) ?>
                  </div>
                </div>
              </div>

              <?php if ($assetOpenUrl !== ''): ?>
                <a class="sm-btn-secondary" style="font-size:12px;padding:6px 12px;white-space:nowrap" href="<?= pcda_h($assetOpenUrl) ?>">
                  <?= pcda_h(plugin_schoolmanager_tr('edit', 'Editar')) ?>
                </a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right Column: Quick Actions Card -->
  <div class="sm-card">
    <h2 style="margin:0 0 14px;font-size:18px;font-weight:950;color:var(--sm-primary,#07384d);display:flex;align-items:center;gap:8px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span><?= pcda_h(plugin_schoolmanager_tr('actions', 'Acciones Rápidas')) ?></span>
    </h2>

    <div style="display:grid;gap:10px">
      <?php if (plugin_schoolmanager_feature_enabled('plans')): ?>
        <a class="sm-btn-primary" style="justify-content:center" href="<?= pcda_h($mapUrl) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px" aria-hidden="true"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
          <span><?= pcda_h(plugin_schoolmanager_tr('view_on_plan', 'Ver en el plano')) ?></span>
        </a>
      <?php endif; ?>

      <?php if (plugin_schoolmanager_feature_enabled('tickets') && plugin_schoolmanager_can_create_ticket()): ?>
        <a class="sm-btn-danger" style="justify-content:center" href="<?= pcda_h($incidentUrl) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:16px;height:16px" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          <span><?= pcda_h(plugin_schoolmanager_tr('create_incident_here', 'Crear incidencia en este aula')) ?></span>
        </a>
      <?php endif; ?>

      <?php if ($roomId > 0 && plugin_schoolmanager_feature_enabled('assets')): ?>
        <a class="sm-btn-secondary" style="justify-content:center" href="<?= pcda_h($assetsUrl) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          <span><?= pcda_h(plugin_schoolmanager_tr('view_room_assets', 'Ver activos del aula')) ?></span>
        </a>
      <?php endif; ?>

      <?php if ($canNative && $nativeUrl): ?>
        <a class="sm-btn-secondary" style="justify-content:center" href="<?= pcda_h($nativeUrl) ?>" target="_blank">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          <span><?= pcda_h(plugin_schoolmanager_tr('open_glpi_native', 'Abrir GLPI nativo')) ?></span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php sm_page_footer(); ?>
