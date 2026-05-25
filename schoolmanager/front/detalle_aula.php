<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
        if (!headers_sent()) { http_response_code(500); }
        echo '<div style="margin:24px;padding:20px;border:1px solid #efc9cc;border-radius:18px;background:#fff6f6;color:#07384d;font-family:system-ui"><h2 style="margin:0 0 8px;color:#b6252b">No se pudo cargar el detalle del aula</h2><p>Se ha producido un error PHP en la ficha del aula. Revisa el log de Apache para ver la linea exacta.</p><a style="display:inline-block;margin-top:10px;padding:10px 14px;border-radius:12px;background:#07384d;color:white;text-decoration:none;font-weight:800" href="/plugins/schoolmanager/front/formularios.php">Volver a Gestion School Manager</a></div>';
    }
});

global $CFG_GLPI, $DB;
$root = $CFG_GLPI['root_doc'] ?? '';
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '2.0.1';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');

function pcda198_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pcda198_req($k, $d='') { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function pcda198_short($name) {
    if (function_exists('plugin_schoolmanager_short_location')) { return plugin_schoolmanager_short_location($name); }
    $plain = html_entity_decode(strip_tags((string)$name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $plain))));
    return $parts ? end($parts) : ($plain !== '' ? $plain : 'Ubicación');
}
function pcda198_location_name($id) {
    $id = (int)$id;
    if ($id <= 0) { return ''; }
    try {
        if (class_exists('Dropdown')) {
            $n = Dropdown::getDropdownName('glpi_locations', $id);
            if (trim((string)$n) !== '') { return pcda198_short($n); }
        }
    } catch (Throwable $e) {}
    return 'Ubicación GLPI #' . $id;
}
function pcda198_count_assets($locationId, $types) {
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
            $it = $DB->request(['FROM'=>$table,'WHERE'=>$where]);
            $n = 0; foreach ($it as $r) { $n++; }
            $out[$type] = $n;
        } catch (Throwable $e) { $out[$type] = 0; }
    }
    return $out;
}
function pcda198_preview($locationId, $types, $limit=12) {
    global $DB;
    $out = [];
    foreach ($types as $type => $info) {
        $table = $info['table'] ?? '';
        try {
            if ((int)$locationId <= 0 || $table === '' || !isset($DB) || !method_exists($DB, 'request')) { continue; }
            if (method_exists($DB, 'tableExists') && !$DB->tableExists($table)) { continue; }
            $where = ['locations_id' => (int)$locationId];
            if (method_exists($DB, 'fieldExists') && $DB->fieldExists($table, 'is_deleted')) { $where['is_deleted'] = 0; }
            $it = $DB->request(['FROM'=>$table,'WHERE'=>$where,'LIMIT'=>$limit]);
            foreach ($it as $row) {
                $name = function_exists('plugin_schoolmanager_asset_display_title') ? plugin_schoolmanager_asset_display_title($type, $row) : trim((string)($row['name'] ?? ''));
                if ($name === '') { $name = ($info['label'] ?? $type) . ' #' . (int)($row['id'] ?? 0); }
                $assetId = (int)($row['id'] ?? 0);
                $url = '';
                if ($assetId > 0 && !empty($info['native'])) { $url = ($GLOBALS['CFG_GLPI']['root_doc'] ?? '') . $info['native'] . '?id=' . $assetId; }
                $out[] = ['name'=>$name,'id'=>$assetId,'itemtype'=>$type,'label'=>$info['label'] ?? $type,'icon'=>$info['icon'] ?? 'pc-i-computer','url'=>$url];
                if (count($out) >= $limit) { return $out; }
            }
        } catch (Throwable $e) {}
    }
    return $out;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0 && isset($_GET['location'])) { $id = (int)$_GET['location']; }
$code = pcda198_req('code');
$building = preg_replace('/[^A-Z0-9]/', '', strtoupper(pcda198_req('building')));
$floor = strtoupper(pcda198_req('floor'));
$q = pcda198_req('q', pcda198_req('room'));
$aulas = [];
try { $tmp = require(__DIR__ . '/../inc/aulas_data.php'); if (is_array($tmp)) { $aulas = $tmp; } } catch (Throwable $e) { $aulas = []; }
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
$nativeName = $id > 0 ? pcda198_location_name($id) : '';
if (!$room) {
    $room = ['id'=>$id,'aula'=>$nativeName ?: ($q ?: ($code ?: 'Ubicación')),'descripcion'=>'Ubicación registrada en GLPI.','codigo'=>$code,'building'=>$building,'floor'=>$floor,'planta'=>$floor];
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
$counts = pcda198_count_assets($roomId, $types);
$total = array_sum(array_map('intval', $counts));
$preview = pcda198_preview($roomId, $types, 12);
$canNative = function_exists('plugin_schoolmanager_can_open_native_locations') ? plugin_schoolmanager_can_open_native_locations() : false;
$defaultBuilding = function_exists('plugin_schoolmanager_default_building_code') ? plugin_schoolmanager_default_building_code() : 'MAIN';
$defaultFloor = function_exists('plugin_schoolmanager_default_floor_code') ? plugin_schoolmanager_default_floor_code($roomBuilding ?: $defaultBuilding) : 'G';
$mapUrl = $root . '/plugins/schoolmanager/front/selector.php?building=' . rawurlencode($roomBuilding ?: $defaultBuilding) . '&floor=' . rawurlencode($roomFloor ?: $defaultFloor) . '&room=' . rawurlencode($roomName) . '&v=' . rawurlencode($version);
$incidentUrl = $root . '/plugins/schoolmanager/front/nueva_incidencia.php?location_id=' . rawurlencode((string)$roomId) . '&v=' . rawurlencode($version);
$assetsUrl = $root . '/plugins/schoolmanager/front/gestion_activos.php?location=' . rawurlencode((string)$roomId) . '&sort=location&v=' . rawurlencode($version);
$listUrl = $root . '/plugins/schoolmanager/front/aulas.php?building=' . rawurlencode($roomBuilding ?: $defaultBuilding) . '&v=' . rawurlencode($version);
$nativeUrl = $roomId > 0 ? $root . '/front/location.form.php?id=' . $roomId : '';

Html::header('Detalle de aula', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo plugin_schoolmanager_home_button();
?>
<style id="pcda198-style">
.pcda{--navy:#07384d;--navy2:#0b5d72;--muted:#617384;--line:#d7e3ea;--soft:#f6f9fb;--red:#b6252b;--red2:#951923;--cream:#fff8ea;min-height:calc(100vh - 80px);padding:clamp(14px,2vw,26px);background:#f6f9fb;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--navy);opacity:0;transform:translateY(7px);animation:pcda-page-in .22s ease-out .04s forwards}.pcda *{box-sizing:border-box}.pcda-shell{max-width:1380px;margin:0 auto;display:grid;gap:16px}.pcda-hero,.pcda-card{background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:24px;box-shadow:0 12px 28px rgba(7,56,77,.055);transition:box-shadow .22s ease,border-color .22s ease,transform .22s ease}.pcda-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px}.pcda-card:hover{box-shadow:0 18px 38px rgba(7,56,77,.075);border-color:#c9dbe3}.pcda-brand{display:flex;align-items:center;gap:16px;min-width:0}.pcda-logo{height:64px;max-width:200px;object-fit:contain;border:0;border-radius:0;background:transparent;padding:0;filter:drop-shadow(0 10px 18px rgba(7,56,77,.07));transition:transform .22s ease}.pcda-logo:hover{transform:translateY(-2px)}.pcda-kicker{margin:0;color:var(--red);font-weight:950;text-transform:uppercase;letter-spacing:.1em;font-size:12px}.pcda h1{margin:2px 0 0;color:var(--navy);font-size:clamp(34px,4vw,58px);line-height:1}.pcda-sub{margin:7px 0 0;color:var(--muted);font-weight:850}.pcda-total{display:grid;place-items:center;min-width:112px;height:82px;border-radius:18px;background:#fff6f6;border:1px solid #efc9cc;color:var(--red2);font-weight:950;transition:transform .22s ease,box-shadow .22s ease}.pcda-total:hover{transform:translateY(-3px);box-shadow:0 18px 32px rgba(149,25,35,.12)}.pcda-total b{font-size:30px;line-height:1}.pcda-total span{font-size:12px;text-transform:uppercase;letter-spacing:.08em}.pcda-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:16px}.pcda-card{padding:18px;min-width:0}.pcda-title{font-size:24px;margin:0 0 12px;color:var(--navy);display:flex;align-items:center;gap:10px}.pcda-title .pc-svgicon{width:24px!important;height:24px!important;min-width:24px!important;color:var(--navy)}.pcda-desc{font-weight:850;color:#334b57;line-height:1.45}.pcda-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:14px 0}.pcda-meta div,.pcda-count{border:1px solid var(--line);background:#fbfdfe;border-radius:16px;padding:12px;min-width:0}.pcda-meta div{transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}.pcda-meta div:hover{transform:translateY(-2px);border-color:#c5d9e2;box-shadow:0 12px 22px rgba(7,56,77,.05)}.pcda-meta small{display:block;color:var(--muted);font-weight:950;text-transform:uppercase;font-size:11px;letter-spacing:.07em}.pcda-meta b{display:block;margin-top:4px;overflow-wrap:anywhere}.pcda-counts{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px}.pcda-count{display:grid;grid-template-columns:46px minmax(0,1fr);grid-template-rows:auto auto;column-gap:12px;align-items:center;min-height:92px;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease,background .2s ease}.pcda-count:hover{transform:translateY(-3px);border-color:#c3d9e2;box-shadow:0 14px 28px rgba(7,56,77,.065);background:#fff}.pcda-count .pc-svgicon{grid-row:1/3;width:40px!important;height:40px!important;min-width:40px!important;color:#6b7e8f!important;opacity:.92;transition:transform .2s ease,color .2s ease}.pcda-count:hover .pc-svgicon{transform:scale(1.05);color:var(--navy)!important}.pcda-count b{display:block;font-size:28px;line-height:1;color:var(--navy)}.pcda-count > span:not(.pc-svgicon){color:var(--muted);font-weight:900;line-height:1.15}.pcda-actions{display:grid;gap:10px}.pcda-btn{display:flex;align-items:center;justify-content:center;text-align:center;gap:10px;min-height:54px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--navy)!important;text-decoration:none!important;font-weight:950;padding:13px 16px;position:relative;overflow:hidden;box-shadow:0 10px 22px rgba(7,56,77,.045);transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s ease,background-color .2s ease,border-color .2s ease,color .2s ease}.pcda-btn:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.08) 35%,rgba(255,255,255,.28) 50%,rgba(255,255,255,.08) 65%,transparent 100%);transform:translateX(-130%);transition:transform .55s ease;pointer-events:none}.pcda-btn:hover{transform:translateY(-3px);box-shadow:0 18px 34px rgba(7,56,77,.11);border-color:#bed3dc}.pcda-btn:hover:before{transform:translateX(130%)}.pcda-btn:active{transform:translateY(-1px) scale(.985)}.pcda-btn .pc-svgicon{width:19px!important;height:19px!important;min-width:19px!important;color:currentColor!important;flex:0 0 19px!important}.pcda-btn.primary{background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);border-color:var(--navy);color:#fff!important;box-shadow:0 18px 32px rgba(7,56,77,.16)}.pcda-btn.primary:hover{box-shadow:0 24px 42px rgba(7,56,77,.22)}.pcda-btn.red{background:#fff6f6;border-color:#efc9cc;color:var(--red2)!important}.pcda-btn.red:hover{background:linear-gradient(135deg,#b6252b 0%,#951923 100%);border-color:#951923;color:#fff!important;box-shadow:0 22px 38px rgba(149,25,35,.18)}.pcda-list{display:grid;gap:8px}.pcda-asset{display:grid;grid-template-columns:42px minmax(0,1fr);gap:10px;align-items:center;border:1px solid var(--line);border-radius:14px;padding:10px;background:#fbfdfe;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}.pcda-asset:hover{transform:translateY(-2px);border-color:#c6dbe4;box-shadow:0 12px 22px rgba(7,56,77,.06)}.pcda-ico{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#f4f8fa;color:var(--navy);border:1px solid #e2edf1}.pcda-ico .pc-svgicon{width:24px!important;height:24px!important;min-width:24px!important;color:var(--navy)}.pcda-asset b{display:block;color:var(--navy);overflow-wrap:anywhere}.pcda-asset-link{text-decoration:none!important;color:inherit!important;display:grid;grid-template-columns:42px minmax(0,1fr);gap:10px;align-items:center}.pcda-asset small{color:var(--muted);font-weight:850}.pcda-note{margin-top:14px;background:#fff6f6;border:1px solid #efc9cc;color:#7e1b22;border-radius:15px;padding:12px;font-weight:850;line-height:1.35}.pcda .pc-svgicon{display:inline-block!important;vertical-align:middle!important;overflow:visible!important;background:transparent!important;contain:paint}.pcda .pc-svgicon:before{content:""!important;display:block!important;width:100%!important;height:100%!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important}@keyframes pcda-page-in{to{opacity:1;transform:none}}@media(max-width:1000px){.pcda-grid{grid-template-columns:1fr}.pcda-hero{align-items:flex-start;flex-direction:column}.pcda-total{width:100%}}@media(max-width:640px){.pcda-brand{display:block}.pcda-logo{margin-bottom:12px}.pcda-meta,.pcda-counts{grid-template-columns:1fr}.pcda-actions{grid-template-columns:1fr}.pcda h1{font-size:38px}.pcda-card{padding:14px}}
</style>

<style id="pcda268-icons">
.pcda .pc-i-tools{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14.7 6.3a4.2 4.2 0 0 0-5.7 5.7l-5.1 5.1a2 2 0 0 0 2.8 2.8l5.1-5.1a4.2 4.2 0 0 0 5.7-5.7l-2.7 2.7-2.9-.8-.8-2.9 2.7-2.7Z'/%3E%3C/svg%3E")!important}.pcda .pc-i-check{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E")!important}.pcda .pc-i-ticket{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2.2a2.8 2.8 0 0 0 0 5.6V17a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2.2a2.8 2.8 0 0 0 0-5.6V7Z'/%3E%3Cpath d='M13 5v14'/%3E%3C/svg%3E")!important}.pcda .pc-i-home{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3.75 10.5 12 4.25l8.25 6.25'/%3E%3Cpath d='M6.75 9.75v9.5h10.5v-9.5'/%3E%3Cpath d='M10 19.25V14.5a2 2 0 0 1 4 0v4.75'/%3E%3C/svg%3E")!important}
</style>
<div class="pcda"><div class="pcda-shell">
  <header class="pcda-hero"><div class="pcda-brand"><img class="pcda-logo" src="<?= pcda198_h($logoUrl) ?>" alt="Logo"><div><p class="pcda-kicker">Gestión School Manager · detalle de aula</p><h1><?= pcda198_h($roomName) ?></h1><p class="pcda-sub"><?= pcda198_h($roomDesc) ?></p></div></div><div class="pcda-total"><b><?= (int)$total ?></b><span>activos</span></div></header>
  <div class="pcda-grid"><main class="pcda-card"><h2 class="pcda-title"><span class="pc-svgicon pc-i-map" aria-hidden="true"></span><span>Ficha del aula</span></h2><p class="pcda-desc"><?= pcda198_h($roomDesc) ?></p><div class="pcda-meta"><div><small>ID GLPI</small><b><?= $roomId > 0 ? (int)$roomId : 'Sin ID' ?></b></div><div><small>Nombre</small><b><?= pcda198_h($roomName) ?></b></div></div><h2 class="pcda-title"><span class="pc-svgicon pc-i-computer" aria-hidden="true"></span><span>Inventario vinculado</span></h2><div class="pcda-counts"><?php foreach ($types as $type => $info): if (($type === 'Projector') && empty($counts[$type])) { continue; } ?><div class="pcda-count"><span class="pc-svgicon <?= pcda198_h($info['icon'] ?? 'pc-i-computer') ?>" aria-hidden="true"></span><b><?= (int)($counts[$type] ?? 0) ?></b><span><?= pcda198_h($info['plural'] ?? $info['label'] ?? $type) ?></span></div><?php endforeach; ?></div><div class="pcda-note">Estos datos se leen desde GLPI usando la ubicación seleccionada. Si falta algo, revisa la ubicación asignada en el activo.</div></main><aside class="pcda-card"><h2 class="pcda-title"><span class="pc-svgicon pc-i-tools" aria-hidden="true"></span><span>Acciones</span></h2><div class="pcda-actions"><a class="pcda-btn primary" href="<?= pcda198_h($mapUrl) ?>"><span class="pc-svgicon pc-i-map" aria-hidden="true"></span><span>Ver en el plano</span></a><a class="pcda-btn red" href="<?= pcda198_h($incidentUrl) ?>"><span class="pc-svgicon pc-i-ticket" aria-hidden="true"></span><span>Crear incidencia</span></a><?php if ($roomId > 0): ?><a class="pcda-btn" href="<?= pcda198_h($assetsUrl) ?>"><span class="pc-svgicon pc-i-computer" aria-hidden="true"></span><span>Activos del aula</span></a><?php endif; ?><a class="pcda-btn" href="<?= pcda198_h($listUrl) ?>"><span class="pc-svgicon pc-i-list" aria-hidden="true"></span><span>Volver a lista de aulas</span></a><a class="pcda-btn" href="<?= pcda198_h($root . '/plugins/schoolmanager/front/formularios.php?v=' . rawurlencode($version)) ?>"><span class="pc-svgicon pc-i-home" aria-hidden="true"></span><span>Volver a Gestión School Manager</span></a><?php if ($canNative && $nativeUrl): ?><a class="pcda-btn" href="<?= pcda198_h($nativeUrl) ?>"><span class="pc-svgicon pc-i-external" aria-hidden="true"></span><span>Abrir GLPI nativo</span></a><?php endif; ?></div><h2 class="pcda-title" style="margin-top:18px"><span class="pc-svgicon pc-i-check" aria-hidden="true"></span><span>Activos destacados</span></h2><?php if (!$preview): ?><p class="pcda-sub">No hay activos vinculados a esta ubicación o no se pueden leer con este perfil.</p><?php else: ?><div class="pcda-list"><?php foreach ($preview as $asset):
    $assetItemtype = (string)($asset['itemtype'] ?? '');
    $assetId = (int)($asset['id'] ?? 0);
    $canOpenAsset = $assetItemtype !== '' && $assetId > 0 && function_exists('plugin_schoolmanager_can_update_asset') && plugin_schoolmanager_can_update_asset($assetItemtype);
    $assetOpenUrl = $canOpenAsset ? ($root . '/plugins/schoolmanager/front/editar_activo.php?itemtype=' . rawurlencode($assetItemtype) . '&id=' . $assetId . '&v=' . rawurlencode($version)) : '';
?><div class="pcda-asset"><?php if ($assetOpenUrl !== ''): ?><a class="pcda-asset-link" href="<?= pcda198_h($assetOpenUrl) ?>"><?php else: ?><div class="pcda-asset-static"><?php endif; ?><span class="pcda-ico"><span class="pc-svgicon <?= pcda198_h($asset['icon'] ?? 'pc-i-computer') ?>" aria-hidden="true"></span></span><span><b><?= pcda198_h($asset['name'] ?? 'Activo') ?></b><small><?= pcda198_h($asset['label'] ?? '') ?> · ID <?= (int)($asset['id'] ?? 0) ?><?= $assetOpenUrl !== '' ? ' · abrir activo' : '' ?></small></span><?php if ($assetOpenUrl !== ''): ?><span class="pcda-open-chip">Abrir</span></a><?php else: ?></div><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></aside></div>
</div></div>

<style id="v256-iconos-global-final">
.pc-svgicon.pc-i-computer,.gsm-svgicon.pc-i-computer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273%27%20y%3D%274%27%20width%3D%2712.8%27%20height%3D%279.4%27%20rx%3D%271.8%27%2F%3E%3Cpath%20d%3D%27M7.2%2017.7h4.4M9.4%2013.4v4.3%27%2F%3E%3Crect%20x%3D%2717.2%27%20y%3D%276.2%27%20width%3D%273.8%27%20height%3D%2710.8%27%20rx%3D%271.2%27%2F%3E%3Cpath%20d%3D%27M18.5%2014.2h1.2M5.3%2020h13.4%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-svgicon.pc-i-monitor,.gsm-svgicon.pc-i-monitor{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273.5%27%20y%3D%274.5%27%20width%3D%2717%27%20height%3D%2711.2%27%20rx%3D%272.2%27%2F%3E%3Cpath%20d%3D%27M8.4%2020h7.2M12%2015.7V20%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-svgicon.pc-i-printer,.gsm-svgicon.pc-i-printer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Cpath%20d%3D%27M7%208V4h10v4%27%2F%3E%3Crect%20x%3D%277%27%20y%3D%2714%27%20width%3D%2710%27%20height%3D%276%27%20rx%3D%271.2%27%2F%3E%3Cpath%20d%3D%27M7%2018H5a2%202%200%200%201-2-2v-5a3%203%200%200%201%203-3h12a3%203%200%200%201%203%203v5a2%202%200%200%201-2%202h-2%27%2F%3E%3Cpath%20d%3D%27M17%2011h.01%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-svgicon.pc-i-network,.gsm-svgicon.pc-i-network{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%274%27%20y%3D%2712.5%27%20width%3D%2716%27%20height%3D%276.5%27%20rx%3D%272%27%2F%3E%3Cpath%20d%3D%27M7.5%2015.8h.01M10.5%2015.8h.01M13.5%2015.8h.01%27%2F%3E%3Cpath%20d%3D%27M8%208.8a6%206%200%200%201%208%200%27%2F%3E%3Cpath%20d%3D%27M10.2%2010.9a3%203%200%200%201%203.6%200%27%2F%3E%3Cpath%20d%3D%27M12%2012.7v-.05%27%2F%3E%3C%2Fsvg%3E")!important;}
.pc-svgicon.pc-i-keyboard,.gsm-svgicon.pc-i-keyboard{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20stroke%3D%27black%27%20stroke-width%3D%272.05%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%3E%3Crect%20x%3D%273%27%20y%3D%276%27%20width%3D%2718%27%20height%3D%2712%27%20rx%3D%272.2%27%2F%3E%3Cpath%20d%3D%27M7%2010h.01M10%2010h.01M13%2010h.01M16%2010h.01M7%2014h10%27%2F%3E%3C%2Fsvg%3E")!important;}
</style>

<style id="v272-detalle-aula-icons-logo-fix">
/* v272: iconos que faltaban en detalle de aula + logo integrado sin sombra/cuadrado */
.pcda .pcda-logo{
  filter:none!important;
  box-shadow:none!important;
  background:transparent!important;
  border:0!important;
  padding:0!important;
  mix-blend-mode:multiply!important;
}
.pcda .pcda-logo:hover{transform:none!important;filter:none!important;box-shadow:none!important;}

.pcda .pc-svgicon,
.pcda .pc-svgicon:before{
  background:transparent!important;
  -webkit-mask:none!important;
  mask:none!important;
}
.pcda .pc-svgicon:before{
  content:""!important;
  display:block!important;
  width:100%!important;
  height:100%!important;
  background:currentColor!important;
  -webkit-mask:var(--pc-icon) center/contain no-repeat!important;
  mask:var(--pc-icon) center/contain no-repeat!important;
}
.pcda .pc-i-map{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 18 3.5 20.5v-15L9 3l6 2.5 5.5-2.5v15L15 20.5 9 18Z'/%3E%3Cpath d='M9 3v15M15 5.5v15'/%3E%3C/svg%3E")!important;}
.pcda .pc-i-list{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 6h12M8 12h12M8 18h12'/%3E%3Cpath d='M4 6h.01M4 12h.01M4 18h.01'/%3E%3C/svg%3E")!important;}
.pcda .pc-i-external{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 5h5v5'/%3E%3Cpath d='M10 14 19 5'/%3E%3Cpath d='M19 13v5H5V4h5'/%3E%3C/svg%3E")!important;}
.pcda .pc-i-eye{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.15' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z'/%3E%3Ccircle cx='12' cy='12' r='2.8'/%3E%3C/svg%3E")!important;}
.pcda .pc-i-map:before,
.pcda .pc-i-list:before,
.pcda .pc-i-external:before,
.pcda .pc-i-eye:before{background:currentColor!important;}
</style>


<style id="v1046-aula-activos-fix">
/* v104.6: tarjetas de activos destacadas limpias, clicables completas y sin texto en vertical */
.pcda .pcda-list{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}
.pcda .pcda-asset{display:block!important;padding:0!important;overflow:hidden!important;border-radius:18px!important;background:#fff!important;border:1px solid #d7e3ea!important;box-shadow:0 8px 20px rgba(7,56,77,.045)!important}
.pcda .pcda-asset:hover{transform:translateY(-2px)!important;border-color:#afcbd7!important;box-shadow:0 16px 30px rgba(7,56,77,.10)!important}
.pcda .pcda-asset-link,.pcda .pcda-asset-static{display:grid!important;grid-template-columns:48px minmax(0,1fr) auto!important;gap:12px!important;align-items:center!important;width:100%!important;min-width:0!important;padding:12px 14px!important;color:inherit!important;text-decoration:none!important}
.pcda .pcda-asset b{font-size:15px!important;line-height:1.2!important;white-space:normal!important;word-break:normal!important;overflow-wrap:anywhere!important;letter-spacing:0!important}
.pcda .pcda-asset small{display:block!important;margin-top:4px!important;line-height:1.25!important;white-space:normal!important;word-break:normal!important;overflow-wrap:anywhere!important;color:#617384!important;font-weight:850!important}
.pcda .pcda-asset .pcda-open-chip{display:inline-flex!important;align-items:center!important;justify-content:center!important;padding:7px 10px!important;border-radius:999px!important;background:#eef8fb!important;border:1px solid #cfe4eb!important;color:#07384d!important;font-size:12px!important;font-weight:950!important;white-space:nowrap!important}
.pcda .pcda-ico{width:48px!important;height:48px!important;border-radius:15px!important;background:#f3f8fb!important}
@media(max-width:700px){.pcda .pcda-asset-link,.pcda .pcda-asset-static{grid-template-columns:44px minmax(0,1fr)!important}.pcda .pcda-asset .pcda-open-chip{grid-column:2;justify-self:start;margin-top:4px}}
</style>

<?php Html::footer(); ?>
