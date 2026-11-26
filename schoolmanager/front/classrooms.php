<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

global $CFG_GLPI;
$root = $CFG_GLPI['root_doc'] ?? '';
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.1.0';
$aulasAll = require(__DIR__ . '/../inc/aulas_data.php');
$buildings = plugin_schoolmanager_buildings();
$codes = plugin_schoolmanager_building_codes();
$building = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($_GET['building'] ?? plugin_schoolmanager_default_building_code()));
if (!in_array($building, $codes, true)) {
    $building = plugin_schoolmanager_default_building_code();
}
$aulas = array_values(array_filter($aulasAll, static fn($a) => ($a['building'] ?? '') === $building));
$floors = plugin_schoolmanager_floors($building);
$canNative = function_exists('plugin_schoolmanager_can_open_native_locations') ? plugin_schoolmanager_can_open_native_locations() : false;
$buildingName = plugin_schoolmanager_label(plugin_schoolmanager_building($building) ?: [], 'name', $building);

$actions = '';
if (plugin_schoolmanager_feature_enabled('plans')) {
    $planUrl = $root . '/plugins/schoolmanager/front/selector.php?building=' . rawurlencode($building) . '&v=' . rawurlencode($version);
    $actions = '<a class="sm-btn-primary" href="' . htmlspecialchars($planUrl, ENT_QUOTES, 'UTF-8') . '">'
             . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px" aria-hidden="true"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>'
             . '<span>' . htmlspecialchars(plugin_schoolmanager_tr('open_plan', 'Ver Planos'), ENT_QUOTES, 'UTF-8') . '</span>'
             . '</a>';
}

sm_page_header([
    'title'       => plugin_schoolmanager_tr('classroom_list', 'Aulas y Espacios') . ' · ' . $buildingName,
    'subtitle'    => (string)(plugin_schoolmanager_config()['brand']['organization'] ?? ''),
    'active_tab'  => 'aulas',
    'actions'     => $actions,
]);
?>

<div class="sm-subnav-bar">
  <div class="sm-subnav-scroll">
    <?php foreach ($buildings as $b):
      $bc = strtoupper((string)$b['code']);
      $isActive = ($building === $bc);
    ?>
      <a class="sm-subnav-pill <?= $isActive ? 'active' : '' ?>" href="?building=<?= htmlspecialchars(rawurlencode($bc), ENT_QUOTES, 'UTF-8') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/></svg>
        <span><?= htmlspecialchars(plugin_schoolmanager_label($b, 'name', $bc), ENT_QUOTES, 'UTF-8') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="sm-card" style="margin-bottom:16px;padding:16px 20px">
  <div class="sm-filter-row" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:240px;position:relative">
      <input id="alSearch" class="sm-input" style="padding-left:38px" placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('search_placeholder', 'Buscar aula por nombre o código...'), ENT_QUOTES, 'UTF-8') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--sm-muted);pointer-events:none" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <div style="min-width:180px">
      <select id="alFloor" class="sm-select">
        <option value=""><?= htmlspecialchars(plugin_schoolmanager_tr('all_floors', 'Todas las plantas'), ENT_QUOTES, 'UTF-8') ?></option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= htmlspecialchars(strtoupper((string)$f['code']), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(plugin_schoolmanager_label($f, 'label', (string)$f['code']), ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <a class="sm-btn-secondary" href="?building=<?= htmlspecialchars(rawurlencode($building), ENT_QUOTES, 'UTF-8') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      <span><?= htmlspecialchars(plugin_schoolmanager_tr('clear', 'Limpiar'), ENT_QUOTES, 'UTF-8') ?></span>
    </a>

    <span id="alCount" class="sm-count-badge" style="display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;background:var(--sm-soft,#f4fafc);border:1px solid var(--sm-border,#d7e6ec);color:var(--sm-primary,#07384d);font-weight:900;font-size:13px">
      0 <?= htmlspecialchars(plugin_schoolmanager_tr('classrooms', 'Aulas'), ENT_QUOTES, 'UTF-8') ?>
    </span>
  </div>
</div>

<div class="sm-card" style="padding:0;overflow:hidden">
  <table class="sm-table" id="alTable">
    <thead>
      <tr>
        <th style="min-width:170px"><?= htmlspecialchars(plugin_schoolmanager_tr('classroom', 'Aula'), ENT_QUOTES, 'UTF-8') ?></th>
        <th><?= htmlspecialchars(plugin_schoolmanager_tr('description', 'Descripción'), ENT_QUOTES, 'UTF-8') ?></th>
        <th style="width:130px"><?= htmlspecialchars(plugin_schoolmanager_tr('code', 'Código'), ENT_QUOTES, 'UTF-8') ?></th>
        <th style="width:100px">ID GLPI</th>
        <th style="width:140px"><?= htmlspecialchars(plugin_schoolmanager_tr('floor', 'Planta'), ENT_QUOTES, 'UTF-8') ?></th>
        <th style="width:280px;text-align:right"><?= htmlspecialchars(plugin_schoolmanager_tr('actions', 'Acciones'), ENT_QUOTES, 'UTF-8') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($aulas as $a):
        $id = (int)($a['id'] ?? 0);
        $room = (string)($a['aula'] ?? '');
        $detailUrl = $root . '/plugins/schoolmanager/front/classroom_detail.php?id=' . rawurlencode((string)$id) . '&code=' . rawurlencode((string)($a['codigo'] ?? '')) . '&building=' . rawurlencode($building) . '&v=' . rawurlencode($version);
        $mapUrl = $root . '/plugins/schoolmanager/front/selector.php?building=' . rawurlencode($building) . '&floor=' . rawurlencode((string)($a['floor'] ?? '')) . '&room=' . rawurlencode($room) . '&v=' . rawurlencode($version);
        $nativeUrl = $id > 0 ? $root . '/front/location.form.php?id=' . $id : '#';
      ?>
        <tr data-name="<?= htmlspecialchars(mb_strtolower($room, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
            data-code="<?= htmlspecialchars(mb_strtolower((string)($a['codigo'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
            data-desc="<?= htmlspecialchars(mb_strtolower(($a['descripcion'] ?? '') . ' ' . ($a['planta'] ?? '') . ' ' . ($a['building'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
            data-floor="<?= htmlspecialchars((string)($a['floor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <td data-label="<?= htmlspecialchars(plugin_schoolmanager_tr('classroom', 'Aula'), ENT_QUOTES, 'UTF-8') ?>">
            <div style="font-size:15.5px;font-weight:950;color:var(--sm-primary,#0F2951);letter-spacing:-0.01em"><?= htmlspecialchars($room, ENT_QUOTES, 'UTF-8') ?></div>
          </td>
          <td data-label="<?= htmlspecialchars(plugin_schoolmanager_tr('description', 'Descripción'), ENT_QUOTES, 'UTF-8') ?>">
            <span style="color:var(--sm-muted,#526580);font-weight:700;font-size:13.5px"><?= htmlspecialchars($a['descripcion'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
          </td>
          <td data-label="<?= htmlspecialchars(plugin_schoolmanager_tr('code', 'Código'), ENT_QUOTES, 'UTF-8') ?>">
            <span style="display:inline-flex;align-items:center;padding:5px 11px;border-radius:10px;background:#f0fdfa;border:1px solid #99f6e4;color:var(--sm-accent,#088E91);font-weight:900;font-size:12px;font-family:ui-monospace,monospace">
              <?= htmlspecialchars((string)($a['codigo'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
            </span>
          </td>
          <td data-label="ID">
            <span style="display:inline-flex;align-items:center;padding:5px 11px;border-radius:999px;background:var(--sm-soft,#EEF3F9);border:1px solid var(--sm-border,#D4DFED);color:var(--sm-primary,#0F2951);font-weight:800;font-size:12px">
              <?= $id > 0 ? '#' . $id : '—' ?>
            </span>
          </td>
          <td data-label="<?= htmlspecialchars(plugin_schoolmanager_tr('floor', 'Planta'), ENT_QUOTES, 'UTF-8') ?>">
            <span style="display:inline-flex;align-items:center;padding:5px 12px;border-radius:999px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:800;font-size:12px">
              <?= htmlspecialchars((string)($a['planta'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
            </span>
          </td>
          <td data-label="<?= htmlspecialchars(plugin_schoolmanager_tr('actions', 'Acciones'), ENT_QUOTES, 'UTF-8') ?>" style="text-align:right">
            <div style="display:inline-flex;gap:8px;flex-wrap:nowrap;justify-content:flex-end;align-items:center">
              <?php if ($canNative && $id > 0): ?>
                <a class="sm-btn-secondary sm-btn-sm" href="<?= htmlspecialchars($nativeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" title="<?= htmlspecialchars(plugin_schoolmanager_tr('open_glpi', 'GLPI'), ENT_QUOTES, 'UTF-8') ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13.5px;height:13.5px" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                  <span>GLPI</span>
                </a>
              <?php endif; ?>
              <a class="sm-btn-secondary sm-btn-sm" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13.5px;height:13.5px" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span><?= htmlspecialchars(plugin_schoolmanager_tr('details', 'Ficha'), ENT_QUOTES, 'UTF-8') ?></span>
              </a>
              <?php if (plugin_schoolmanager_feature_enabled('plans')): ?>
                <a class="sm-btn-primary sm-btn-sm" href="<?= htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8') ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13.5px;height:13.5px" aria-hidden="true"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
                  <span><?= htmlspecialchars(plugin_schoolmanager_tr('view_on_plan', 'Plano'), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div id="alEmpty" style="display:none;padding:48px 24px;text-align:center">
    <div style="width:54px;height:54px;border-radius:18px;background:var(--sm-soft,#f4fafc);border:1px solid var(--sm-border,#d7e6ec);display:inline-grid;place-items:center;margin-bottom:12px;color:var(--sm-muted)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:26px;height:26px" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
    </div>
    <h3 style="margin:0 0 6px;color:var(--sm-primary,#07384d);font-size:18px"><?= htmlspecialchars(plugin_schoolmanager_tr('no_results', 'No se encontraron aulas'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p style="margin:0;color:var(--sm-muted,#5c7280);font-size:14px"><?= htmlspecialchars(plugin_schoolmanager_tr('no_results_desc', 'Prueba a cambiar el filtro de planta o los términos de búsqueda.'), ENT_QUOTES, 'UTF-8') ?></p>
  </div>
</div>

<script>
(function(){
  const s = document.getElementById('alSearch');
  const f = document.getElementById('alFloor');
  const c = document.getElementById('alCount');
  const empty = document.getElementById('alEmpty');
  const table = document.getElementById('alTable');
  const rows = [...document.querySelectorAll('#alTable tbody tr')];

  const norm = x => (x || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  function run() {
    const q = norm(s ? s.value.trim() : '');
    const fl = f ? f.value : '';
    let n = 0;

    rows.forEach(r => {
      const ok = (!q || norm(r.dataset.name).includes(q) || norm(r.dataset.code).includes(q) || norm(r.dataset.desc).includes(q))
              && (!fl || r.dataset.floor === fl);
      r.style.display = ok ? '' : 'none';
      if (ok) n++;
    });

    if (c) {
      c.textContent = n + ' <?= htmlspecialchars(plugin_schoolmanager_tr("classrooms", "Aulas"), ENT_QUOTES, "UTF-8") ?>';
    }
    if (empty) {
      empty.style.display = (n === 0 && rows.length > 0) ? 'block' : 'none';
    }
    if (table) {
      const thead = table.querySelector('thead');
      if (thead) thead.style.display = (n === 0) ? 'none' : '';
    }
  }

  if (s) s.addEventListener('input', run);
  if (f) f.addEventListener('change', run);
  run();
})();
</script>

<?php sm_page_footer(); ?>
