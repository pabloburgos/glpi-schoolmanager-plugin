<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/plan_zones.php');
require_once(__DIR__ . '/../inc/config.php');

global $CFG_GLPI;
$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = plugin_schoolmanager_logo_url();
$all = require(__DIR__ . '/../inc/aulas_data.php');

$building = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($_GET['building'] ?? plugin_schoolmanager_default_building_code()));
$buildingCodes = plugin_schoolmanager_building_codes();
if (!in_array($building, $buildingCodes, true)) { $building = plugin_schoolmanager_default_building_code(); }
$mode = preg_replace('/[^a-z]/', '', strtolower($_GET['mode'] ?? 'normal'));
if (!in_array($mode, ['normal','select'], true)) { $mode = 'normal'; }
$embed = isset($_GET['embed']) && $_GET['embed'] !== '0';
if (!$embed) {
    require_once __DIR__ . '/../inc/layout.php';
    $headerActions = '<a class="sm-btn-secondary" href="' . htmlspecialchars($root . '/plugins/schoolmanager/front/classrooms.php?building=' . rawurlencode($building), ENT_QUOTES, 'UTF-8') . '">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>'
        . '<span>' . htmlspecialchars(plugin_schoolmanager_tr('menu_classrooms', 'Lista de aulas'), ENT_QUOTES, 'UTF-8') . '</span></a>';
    sm_page_header([
        'title'      => plugin_schoolmanager_tr('menu_plan', 'Planos de Planta'),
        'subtitle'   => (string)(plugin_schoolmanager_config()['brand']['organization'] ?? ''),
        'active_tab' => 'selector',
        'actions'    => $headerActions,
    ]);
}

$buildingFloors = [];
foreach (plugin_schoolmanager_buildings() as $b) {
    $bc = strtoupper((string)($b['code'] ?? ''));
    if ($bc === '') { continue; }
    $buildingFloors[$bc] = [];
    foreach (plugin_schoolmanager_floors($bc) as $f) {
        $fc = strtoupper((string)($f['code'] ?? ''));
        if ($fc === '') { continue; }
        $buildingFloors[$bc][$fc] = [
            'label' => plugin_schoolmanager_label($f, 'label', $fc),
            'num' => (string)($f['number'] ?? $fc),
            'ready' => true,
        ];
    }
}
$floors = $buildingFloors[$building] ?? [];
if (!$floors) { $floors = ['P0' => ['label'=>'P0', 'num'=>'0', 'ready'=>false]]; }
$defaultFloor = plugin_schoolmanager_default_floor_code($building);
if (!isset($floors[$defaultFloor])) { $defaultFloor = array_key_first($floors); }
$floor = strtoupper($_GET['floor'] ?? $defaultFloor);
if (!isset($floors[$floor])) { $floor = $defaultFloor; }

$items = array_values(array_filter($all, static fn($a) => ($a['building'] ?? '') === $building));
$itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$canOpenNative = function_exists('plugin_schoolmanager_can_open_native_locations') ? plugin_schoolmanager_can_open_native_locations() : false;
$planFile = $building . '-' . $floor . '-select.html';
$planUrl = $root . '/plugins/schoolmanager/front/plan_frame.php?building=' . rawurlencode($building) . '&floor=' . rawurlencode($floor) . '&mode=' . rawurlencode($mode) . '&v=' . rawurlencode(PLUGIN_SCHOOLMANAGER_VERSION) . '&force=282&ts=2026052101';
$planZonesData = function_exists('plugin_schoolmanager_plan_zones') ? plugin_schoolmanager_plan_zones() : [];
$zoneKey = $building . '-' . $floor;
$zoneInfo = $planZonesData[$zoneKey] ?? null;
$floorCodes = [];
foreach ($items as $it) {
    if (($it['floor'] ?? '') === $floor) {
        $floorCodes[strtoupper((string)($it['codigo'] ?? ''))] = true;
        $floorCodes[strtoupper((string)($it['aula'] ?? ''))] = true;
    }
}
$nativePlan = false;
$nativeZones = [];
// v1.8.9: el Edificio 2 vuelve a usar los planos HTML originales exportados por el usuario.
// No se genera un plano alternativo: se carga el iframe igual que en Edificio 1.
$nativeBbox = null;
if ($building === 'ED2' && is_array($zoneInfo) && !empty($zoneInfo['zones']) && !empty($zoneInfo['bbox'])) {
    $nativePlan = true;
    $nativeBbox = $zoneInfo['bbox'];
    foreach ($zoneInfo['zones'] as $z) {
        $zc = strtoupper((string)($z['code'] ?? ''));
        $zl = strtoupper((string)($z['label'] ?? ''));
        if (isset($floorCodes[$zc]) || isset($floorCodes[$zl]) || $floor === 'P0' || $floor === 'SOT') {
            // En P2 y P3 evitamos zonas heredadas de otras plantas si el exportado trae varias capas.
            if ($floor === 'P2' && !preg_match('/^ED2-2/', $zc)) { continue; }
            if ($floor === 'P3' && !preg_match('/^ED2-3/', $zc)) { continue; }
            if ($floor === 'P1' && !preg_match('/^ED2-1/', $zc)) { continue; }
            $nativeZones[] = $z;
        }
    }
    if ($nativeZones) {
        $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;
        foreach ($nativeZones as $z) {
            $x = (float)($z['x'] ?? 0); $y = (float)($z['y'] ?? 0); $w = (float)($z['w'] ?? 0); $h = (float)($z['h'] ?? 0);
            $minX = min($minX, $x); $minY = min($minY, $y); $maxX = max($maxX, $x + $w); $maxY = max($maxY, $y + $h);
        }
        if (is_finite($minX) && is_finite($minY) && is_finite($maxX) && is_finite($maxY)) {
            $nativeBbox = ['x' => $minX, 'y' => $minY, 'w' => max(120, $maxX - $minX), 'h' => max(120, $maxY - $minY)];
        }
    }
}

// v1.8.9: siempre usar el plano HTML real exportado, nunca el SVG rápido generado.
$nativePlan = false;
$nativeZones = [];
$nativeBbox = null;

// v2.1.5: ED2 carga SIEMPRE los HTML reales exportados, sin SVG generado,
// para que no se corte y permita seleccionar todas las aulas especiales.
if (false && $building === 'ED2' && in_array($floor, ['P0','P3'], true) && is_array($zoneInfo) && !empty($zoneInfo['zones']) && !empty($zoneInfo['bbox'])) {
    $nativePlan = true;
    $nativeBbox = $zoneInfo['bbox'];
    foreach ($zoneInfo['zones'] as $z) {
        $zc = strtoupper((string)($z['code'] ?? ''));
        if (($floor === 'P3' && preg_match('/^ED2-3/', $zc)) || ($floor === 'P0')) {
            $nativeZones[] = $z;
        }
    }
}
?>
<style id="pc-selector-v282-clean">
:root{--pc-teal:#088E91;--pc-teal2:#088E91;--pc-dark:#0F2951;--pc-ink:#0F172A;--pc-gold:#088E91;--pc-red:#B6252B;--pc-line:#E2E8F0;--pc-soft:#F8FAFC;--pc-muted:#64748B}
<?php if ($embed): ?>
html, body {
  overflow: hidden !important;
  margin: 0 !important;
  padding: 0 !important;
  height: 100% !important;
  width: 100% !important;
}
body { background: #F8FAFC !important; }
.pc-selector {
  height: 100% !important;
  min-height: 100% !important;
  max-height: 100% !important;
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 8px !important;
}
<?php else: ?>
html, body { overflow: auto !important; }
.pc-selector {
  height: var(--pc-app-h, calc(100dvh - 88px)) !important;
  min-height: 500px !important;
  max-height: calc(100dvh - 10px) !important;
  border: 1px solid var(--pc-line) !important;
  border-radius: 18px !important;
  box-shadow: 0 8px 24px rgba(15, 41, 81, .05) !important;
  padding: 10px !important;
}
<?php endif; ?>
.pc-selector {
  box-sizing: border-box !important;
  background: #F8FAFC !important;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif !important;
  color: var(--pc-ink) !important;
  overflow: hidden !important;
}
.pc-head{display:none!important}
.pc-sel-mobile-switch{display:none!important}
.pc-grid{height:100%!important;min-height:0!important;display:grid!important;grid-template-columns:clamp(282px,24vw,350px) minmax(0,1fr)!important;gap:12px!important;align-items:stretch!important;box-sizing:border-box!important}
.pc-side,.pc-main{min-height:0!important;background:#FFFFFF!important;border:1px solid var(--pc-line)!important;border-radius:16px!important;box-shadow:0 4px 16px rgba(15,41,81,.04)!important;box-sizing:border-box!important}
.pc-side{padding:10px 10px 12px!important;display:grid!important;grid-template-rows:auto auto auto minmax(0,1fr) auto!important;gap:8px!important;overflow:hidden!important}
.pc-side h2,.pc-filterrow{display:none!important}
.pc-tabs{display:grid!important;grid-template-columns:1fr 1fr!important;gap:6px!important;border:1px solid var(--pc-line)!important;background:var(--pc-soft)!important;border-radius:12px!important;padding:4px!important;min-height:48px!important;box-sizing:border-box!important}
.pc-tab{display:flex!important;justify-content:center!important;align-items:center!important;border-radius:9px!important;padding:0 10px!important;text-decoration:none!important;color:#334155!important;font-weight:800!important;font-size:14px!important;transition:background-color .15s ease,color .15s ease!important}
.pc-tab:hover{background:#FFFFFF!important;color:var(--pc-dark)!important}
.pc-tab.active{background:#0F2951!important;color:#fff!important;box-shadow:0 3px 10px rgba(15,41,81,.2)!important}
.pc-search{display:flex!important;gap:9px!important;align-items:center!important;border:1px solid #CBD5E1!important;border-radius:10px!important;background:#fff!important;padding:0 12px!important;min-height:44px!important;box-sizing:border-box!important}
.pc-search input{border:0!important;outline:0!important;background:transparent!important;width:100%!important;min-width:0!important;font-size:14px!important;font-weight:700!important;color:#0F172A!important;box-shadow:none!important;padding:0!important;height:28px!important}
.pc-floors{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:6px!important;align-items:stretch!important}
.pc-floor{display:flex!important;align-items:center!important;justify-content:center!important;border:1px solid var(--pc-line)!important;border-radius:10px!important;background:#fff!important;min-width:0!important;min-height:46px!important;text-decoration:none!important;color:#334155!important;font-weight:800!important;transition:background-color .15s ease,border-color .15s ease!important}
.pc-floor:hover{border-color:#94A3B8!important;background:#F8FAFC!important}
.pc-floor b{display:grid!important;place-items:center!important;width:30px!important;height:30px!important;border-radius:8px!important;background:#0F2951!important;color:#fff!important;font-size:14px!important;line-height:1!important;flex:0 0 auto!important}
.pc-floor span,.pc-floor small{display:none!important}
.pc-floor.active{border-color:#088E91!important;background:#F0FDFA!important;box-shadow:0 0 0 2px rgba(8,142,145,.15)!important}
.pc-floor.active b{background:#088E91!important;color:#FFFFFF!important}
.pc-list{min-height:0!important;overflow:auto!important;border:1px solid var(--pc-line)!important;border-radius:12px!important;background:#fff!important;padding:6px!important;box-sizing:border-box!important;scrollbar-gutter:stable!important}
.pc-list::-webkit-scrollbar{width:8px}.pc-list::-webkit-scrollbar-thumb{background:#CBD5E1;border-radius:99px}
.pc-item{display:grid!important;grid-template-columns:auto minmax(0,1fr) auto!important;gap:10px!important;align-items:center!important;width:100%!important;border:1px solid transparent!important;border-bottom-color:#F1F5F9!important;border-radius:10px!important;background:#fff!important;text-align:left!important;padding:8px 9px!important;min-height:58px!important;cursor:pointer!important;box-sizing:border-box!important;transition:background-color .15s ease,border-color .15s ease!important}
.pc-item:hover{background:#F8FAFC!important;border-color:#CBD5E1!important}
.pc-item.active{background:#F0FDFA!important;border-color:#088E91!important;box-shadow:inset 4px 0 0 #088E91!important}
.pc-avatar{display:grid!important;place-items:center!important;width:36px!important;height:36px!important;border-radius:10px!important;background:#F1F5F9!important;color:var(--pc-dark)!important;font-weight:800!important;line-height:1!important;flex:0 0 auto!important}.pc-item.active .pc-avatar{background:#088E91!important;color:#fff!important}
.pc-room{font-size:15px!important;font-weight:800!important;color:#0F2951!important;line-height:1.1!important}.pc-desc{font-size:12px!important;color:#64748B!important;font-weight:700!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;max-width:100%!important;margin-top:3px!important}.pc-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace!important;background:#F1F5F9!important;color:#334155!important;border-radius:8px!important;padding:4px 7px!important;font-size:11px!important;font-weight:800!important;white-space:nowrap!important}
.pc-empty{display:none!important;padding:14px!important;text-align:center!important;color:#64748B!important;font-weight:700!important}
.pc-actions{display:grid!important;grid-template-columns:1fr 1fr!important;gap:8px!important;padding-top:8px!important;border-top:1px solid #F1F5F9!important;background:#fff!important;min-height:50px!important;box-sizing:border-box!important;align-items:end!important}
.pc-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;border:1px solid #CBD5E1!important;background:#fff!important;border-radius:10px!important;min-height:42px!important;padding:0 14px!important;font-size:13.5px!important;font-weight:800!important;line-height:1!important;text-align:center!important;text-decoration:none!important;color:var(--pc-dark)!important;cursor:pointer!important;white-space:nowrap!important;box-sizing:border-box!important;transition:background-color .15s ease,border-color .15s ease,color .15s ease!important}
.pc-btn:hover{background:#F8FAFC!important;border-color:#94A3B8!important}
.pc-btn.primary{background:#088E91!important;border:none!important;color:#fff!important;box-shadow:0 3px 10px rgba(8,142,145,.2)!important}.pc-btn.primary:hover{background:#0F2951!important;color:#fff!important}
.pc-main{padding:10px!important;display:grid!important;grid-template-rows:auto minmax(0,1fr) 78px!important;gap:8px!important;overflow:hidden!important}
.pc-mainbar{min-height:42px!important;padding:0 10px!important;display:flex!important;align-items:center!important;gap:12px!important;box-sizing:border-box!important}.pc-mainbar h2{margin:0!important;flex:1!important;min-width:0!important;font-size:clamp(22px,2vw,30px)!important;line-height:1.1!important;letter-spacing:-.025em!important;color:var(--pc-dark)!important;font-weight:800!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}.pc-hint{border:1px solid #99F6E4!important;background:#F0FDFA!important;border-radius:999px!important;color:#088E91!important;padding:6px 12px!important;font-size:12.5px!important;font-weight:800!important;white-space:nowrap!important}
.pc-planbox{height:100%!important;min-height:0!important;margin:0 10px!important;border:1px solid var(--pc-line)!important;border-radius:14px!important;overflow:hidden!important;background:#fff!important;position:relative!important;display:block!important}.pc-planbar{display:none!important}.pc-frame{width:100%!important;height:100%!important;min-height:0!important;border:0!important;display:block!important;background:#fff!important;overflow:hidden!important}.pc-svgplan{height:100%!important;width:100%!important;display:grid!important;place-items:center!important;background:#F8FAFC!important;overflow:hidden!important;padding:8px!important;contain:layout paint style!important;box-sizing:border-box!important}.pc-ed2-svg{width:100%!important;height:100%!important;display:block!important;touch-action:manipulation!important;user-select:none!important}.pc-ed2-shell{fill:#f7faf9;stroke:#d2dfdd;stroke-width:1.5}.pc-ed2-corridor{fill:#f2f5f4;stroke:#d8e4e2;stroke-width:1.2;opacity:.92}.pc-ed2-zone{cursor:pointer;outline:none}.pc-ed2-zone rect{fill:#fff;stroke:#2f3b4b;stroke-width:2.4;rx:8;ry:8;filter:drop-shadow(0 4px 7px rgba(8,47,58,.08));transition:fill .12s,stroke .12s,stroke-width .12s}.pc-ed2-zone text{fill:#101820;font-family:Inter,Arial,sans-serif;font-weight:950;text-anchor:middle;dominant-baseline:middle;pointer-events:none;paint-order:stroke;stroke:#fff;stroke-width:2px;stroke-linejoin:round}.pc-ed2-zone:hover rect,.pc-ed2-zone:focus rect{fill:#F0FDFA;stroke:#088E91;stroke-width:4}.pc-ed2-zone.active rect{fill:#CCFBF1;stroke:#088E91;stroke-width:4.5}.pc-ed2-planline{stroke:#aebbb9;stroke-width:1.5;fill:none;opacity:.65}.pc-click-overlay{position:absolute!important;inset:0!important;z-index:8!important;pointer-events:none!important}.pc-click-zone{position:absolute!important;display:block!important;border:2px solid transparent!important;border-radius:10px!important;background:rgba(255,255,255,.01)!important;pointer-events:auto!important;cursor:pointer!important}.pc-click-zone:hover,.pc-click-zone.active{border-color:#088E91!important;background:rgba(8,142,145,.10)!important;box-shadow:0 0 0 3px rgba(8,142,145,.16)!important}
.pc-under{height:100%!important;display:grid!important;place-items:center!important;background:#F8FAFC!important;padding:22px!important;text-align:center!important}.pc-under-card{max-width:560px!important;background:#fff!important;border:1px solid var(--pc-line)!important;border-radius:16px!important;padding:22px!important;box-shadow:0 8px 24px rgba(15,41,81,.06)!important}.pc-under-card h3{margin:8px 0!important;color:var(--pc-dark)!important;font-size:22px!important}.pc-under-card p{margin:0!important;color:#64748B!important;font-weight:700!important}
.pc-loading{position:absolute!important;inset:0!important;display:none!important;place-items:center!important;background:rgba(248,250,252,.78)!important;z-index:4!important;pointer-events:none!important}.pc-loading.show{display:grid!important}.pc-plan-error{position:absolute!important;inset:0!important;display:none!important;place-items:center!important;background:#fff!important;z-index:5!important;text-align:center!important;padding:24px!important}.pc-plan-error.show{display:grid!important}.pc-plan-error-card{max-width:540px!important;border:1px solid var(--pc-line)!important;border-radius:16px!important;background:#FFFFFF!important;padding:22px!important;box-shadow:0 10px 28px rgba(15,41,81,.08)!important}.pc-plan-error-card h3{margin:0 0 8px!important;color:#991B1B!important;font-size:20px!important}.pc-plan-error-card p{margin:0 0 14px!important;color:#64748B!important;font-weight:700!important}.pc-spinner{width:38px!important;height:38px!important;border-radius:50%!important;border:4px solid #E2E8F0!important;border-top-color:#088E91!important;animation:spin .8s linear infinite!important;margin:0 auto 12px!important}@keyframes spin{to{transform:rotate(360deg)}}
.pc-result{height:78px!important;min-height:78px!important;margin:0 10px 2px!important;padding:8px 12px!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:12px!important;align-items:center!important;border:1px solid var(--pc-line)!important;border-radius:14px!important;background:#F8FAFC!important;overflow:hidden!important;box-sizing:border-box!important}.pc-result.empty{display:grid!important;place-items:center!important;text-align:center!important;color:#64748B!important;font-weight:800!important}.pc-room-details{min-width:0!important;overflow:hidden!important}.pc-room-details small{display:block!important;font-size:11.5px!important;font-weight:800!important;color:#64748B!important;margin-bottom:2px!important;text-transform:uppercase!important;letter-spacing:.04em!important}.pc-room-details h3{margin:0 0 5px!important;font-size:clamp(18px,1.5vw,22px)!important;line-height:1.1!important;color:var(--pc-dark)!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}.pc-room-details p{margin:0!important;display:flex!important;flex-wrap:nowrap!important;gap:7px!important;overflow:hidden!important}.pc-room-details p span{display:inline-flex!important;align-items:center!important;gap:5px!important;padding:4px 9px!important;border:1px solid var(--pc-line)!important;border-radius:999px!important;background:#FFFFFF!important;color:#334155!important;font-size:12px!important;font-weight:800!important;white-space:nowrap!important;min-width:0!important}.pc-room-details p span b{font-weight:800!important;color:var(--pc-dark)!important}.pc-result-actions{display:flex!important;visibility:visible!important;opacity:1!important;align-items:center!important;justify-content:flex-end!important;gap:8px!important;flex-wrap:nowrap!important;min-width:max-content!important;max-width:50%!important}.pc-result-actions:empty{display:none!important}.pc-result-actions .pc-btn{min-height:40px!important;padding:0 13px!important;border-radius:10px!important;font-size:13px!important}.pc-result-actions .pc-btn.glpi{background:#fff!important;border-color:#CBD5E1!important;color:var(--pc-dark)!important}.pc-result-actions .pc-btn.glpi:hover{border-color:var(--pc-dark)!important;background:#F8FAFC!important}.pc-result-actions .pc-btn.select{background:#088E91!important;border-color:#088E91!important;color:#fff!important}.pc-result-actions .pc-btn.select:hover{background:var(--pc-dark)!important;border-color:var(--pc-dark)!important}.pc-result-actions .pc-btn:before,.pc-btn:before{display:none!important;content:none!important}
.pc-svgicon{display:inline-block!important;width:18px!important;height:18px!important;min-width:18px!important;flex:0 0 18px!important;color:currentColor!important;background:transparent!important;border:0!important;box-shadow:none!important;text-indent:0!important;overflow:visible!important;-webkit-mask:none!important;mask:none!important}.pc-svgicon:before{content:""!important;display:block!important;width:100%!important;height:100%!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important}.pc-i-search{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.3' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E")}.pc-i-home{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.35' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 11.5 12 5l8 6.5'/%3E%3Cpath d='M6.5 10.5V20h11v-9.5'/%3E%3Cpath d='M10 20v-5h4v5'/%3E%3C/svg%3E")}.pc-i-list{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.35' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 6h12M8 12h12M8 18h12'/%3E%3Cpath d='M4 6h.01M4 12h.01M4 18h.01'/%3E%3C/svg%3E")}.pc-i-info{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.3' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='9'/%3E%3Cpath d='M12 10v6'/%3E%3Cpath d='M12 7.5h.01'/%3E%3C/svg%3E")}.pc-i-external{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 5h5v5'/%3E%3Cpath d='M10 14 19 5'/%3E%3Cpath d='M19 13v5H5V5h5'/%3E%3C/svg%3E")}.pc-i-check{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.55' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E")}.pc-i-construction{--pc-icon:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 21h18'/%3E%3Cpath d='M5 21V8l7-4 7 4v13'/%3E%3Cpath d='M9 21v-6h6v6'/%3E%3C/svg%3E")}
.pc-toast{position:fixed!important;top:18px!important;right:18px!important;bottom:auto!important;background:#0F2951!important;color:#fff!important;border-radius:12px!important;padding:12px 16px!important;box-shadow:0 12px 30px rgba(15,41,81,.22)!important;font-weight:800!important;display:none!important;z-index:99999!important}.pc-toast.show{display:block!important}
@media(max-height:760px) and (min-width:981px){.pc-selector{padding:8px!important;min-height:0!important}.pc-grid{gap:9px!important;grid-template-columns:clamp(270px,23vw,330px) minmax(0,1fr)!important}.pc-side,.pc-main{border-radius:14px!important}.pc-side{padding:8px!important;gap:7px!important}.pc-tabs{min-height:44px!important}.pc-search{min-height:40px!important}.pc-floor{min-height:42px!important}.pc-floor b{width:28px!important;height:28px!important}.pc-item{min-height:52px!important;padding:7px!important}.pc-avatar{width:32px!important;height:32px!important}.pc-main{grid-template-rows:auto minmax(0,1fr) 70px!important;padding:8px!important}.pc-mainbar{min-height:38px!important}.pc-mainbar h2{font-size:clamp(20px,1.8vw,26px)!important}.pc-hint{padding:5px 10px!important;font-size:12px!important}.pc-planbox{margin:0 7px!important}.pc-result{height:70px!important;min-height:70px!important;margin:0 7px 0!important;padding:7px 10px!important}.pc-room-details h3{font-size:clamp(16px,1.4vw,20px)!important;margin-bottom:4px!important}.pc-room-details small{font-size:11px!important}.pc-room-details p span{font-size:11px!important;padding:4px 8px!important}.pc-result-actions .pc-btn{min-height:36px!important;font-size:12px!important;padding:0 10px!important}}
@media(max-width:1220px){.pc-grid{grid-template-columns:300px minmax(0,1fr)!important}.pc-result{grid-template-columns:1fr!important;height:auto!important;min-height:92px!important;overflow:visible!important}.pc-room-details p{flex-wrap:wrap!important}.pc-result-actions{justify-content:flex-start!important;min-width:0!important;width:100%!important;max-width:none!important;flex-wrap:wrap!important}}
@media(max-width:860px){
  .pc-selector{height:auto!important;max-height:none!important;min-height:calc(100dvh - 110px)!important;overflow:visible!important;padding:8px!important}
  .pc-sel-mobile-switch{display:grid!important;grid-template-columns:1fr 1fr!important;gap:6px!important;background:rgba(255,255,255,0.85)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;border:1px solid #CBD5E1!important;border-radius:14px!important;padding:4px!important;margin-bottom:10px!important}
  .pc-sel-mobile-btn{min-height:40px!important;border:0!important;border-radius:10px!important;background:transparent!important;color:#475569!important;font-size:13px!important;font-weight:800!important;cursor:pointer!important;transition:all .18s ease!important}
  .pc-sel-mobile-btn.active{background:#0F2951!important;color:#FFFFFF!important;box-shadow:0 3px 10px rgba(15,41,81,.2)!important}
  .pc-grid{grid-template-columns:1fr!important;height:auto!important}
  .pc-selector[data-mobile-view="list"] .pc-planbox,.pc-selector[data-mobile-view="list"] .pc-mainbar{display:none!important}
  .pc-selector[data-mobile-view="list"] .pc-main{order:2!important;grid-template-rows:auto!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important}
  .pc-selector[data-mobile-view="list"] .pc-side{order:1!important}
  .pc-selector[data-mobile-view="plan"] .pc-side{order:2!important}
  .pc-selector[data-mobile-view="plan"] .pc-main{order:1!important;grid-template-rows:auto minmax(340px,56vh) auto!important;overflow:visible!important}
  .pc-side{overflow:visible!important;max-height:none!important}
  .pc-list{max-height:380px!important}
  .pc-planbox{height:clamp(340px,56vh,520px)!important;margin:0 4px!important}
  .pc-frame{min-height:340px!important}
  .pc-result{margin:8px 0 4px!important;height:auto!important;min-height:0!important;background:#FFFFFF!important}
  .pc-result-actions{display:grid!important;grid-template-columns:1fr 1fr!important;width:100%!important}
  .pc-result-actions .pc-btn{width:100%!important}
  .pc-actions{grid-template-columns:1fr 1fr!important}
}
@media(max-width:540px){.pc-mainbar{align-items:flex-start!important;flex-direction:column!important}.pc-hint{white-space:normal!important}.pc-result-actions{grid-template-columns:1fr!important}.pc-room-details p{flex-wrap:wrap!important}.pc-room-details p span{width:100%!important;justify-content:center!important;border-radius:10px!important}.pc-actions{grid-template-columns:1fr!important}}
</style>

<div class="pc-selector" id="pcSelector" data-mobile-view="plan">
  <div class="pc-sel-mobile-switch" role="tablist" aria-label="Vista en móvil">
    <button type="button" class="pc-sel-mobile-btn active" data-sel-view="plan">🗺️ Plano interactivo</button>
    <button type="button" class="pc-sel-mobile-btn" data-sel-view="list">📋 Lista de aulas</button>
  </div>
  <?php if ($embed): ?>
  <div class="pc-head">
    <img class="pc-logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
    <div class="pc-title"><small><?= htmlspecialchars(plugin_schoolmanager_app_name(), ENT_QUOTES, 'UTF-8') ?></small><h1><?= htmlspecialchars(plugin_schoolmanager_tr('menu_plan'), ENT_QUOTES, 'UTF-8') ?></h1></div>
    <div class="pc-pill author"><?= htmlspecialchars((string)(plugin_schoolmanager_config()['brand']['organization'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="pc-pill"><?= $mode === 'select' ? htmlspecialchars(plugin_schoolmanager_tr('mode_select', 'Modo Selección'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(plugin_schoolmanager_tr('mode_view', 'Modo Consulta'), ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <?php endif; ?>
  <div class="pc-grid">
    <aside class="pc-side">
      <h2><?= htmlspecialchars(plugin_schoolmanager_tr('selector_plan_and_rooms', 'Plano y aulas'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="pc-tabs" style="grid-template-columns:repeat(<?= max(1, count(plugin_schoolmanager_buildings())) ?>,minmax(0,1fr))!important">
        <?php foreach (plugin_schoolmanager_buildings() as $b): $bc = strtoupper((string)$b['code']); $df = plugin_schoolmanager_default_floor_code($bc); ?>
          <a class="pc-tab <?= $building===$bc?'active':'' ?>" href="?building=<?= urlencode($bc) ?>&floor=<?= urlencode($df) ?>&mode=<?= urlencode($mode) ?><?= $embed ? '&embed=1' : '' ?>"><?= htmlspecialchars(plugin_schoolmanager_label($b, 'name', $bc), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </div>
      <div class="pc-search"><span class="pc-svgicon pc-i-search" aria-hidden="true"></span><input id="pcSearch" autocomplete="off" placeholder="<?= htmlspecialchars(plugin_schoolmanager_tr('selector_search_placeholder', 'Buscar: 101, biblioteca, ESO...'), ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="pc-filterrow">
        <select class="pc-select" id="pcFloorFilter"><option value=""><?= htmlspecialchars(plugin_schoolmanager_tr('all_floors', 'Todas las plantas'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($floors as $key => $f): ?><option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $key===$floor?'selected':'' ?>><?= htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
        <select class="pc-select" id="pcSort"><option value="floor"><?= htmlspecialchars(plugin_schoolmanager_tr('sort_by_floor', 'Orden por planta'), ENT_QUOTES, 'UTF-8') ?></option><option value="name"><?= htmlspecialchars(plugin_schoolmanager_tr('sort_by_room', 'Orden por aula'), ENT_QUOTES, 'UTF-8') ?></option><option value="desc"><?= htmlspecialchars(plugin_schoolmanager_tr('sort_by_desc', 'Orden por descripción'), ENT_QUOTES, 'UTF-8') ?></option></select>
      </div>
      <div class="pc-floors">
        <?php foreach ($floors as $key => $f): ?>
          <a class="pc-floor <?= $key===$floor?'active':'' ?>" href="?building=<?= urlencode($building) ?>&floor=<?= urlencode($key) ?>&mode=<?= urlencode($mode) ?><?= $embed ? '&embed=1' : '' ?>"><b><?= htmlspecialchars($f['num'], ENT_QUOTES, 'UTF-8') ?></b><span><?= htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8') ?><small><?= htmlspecialchars($building . '-' . $key, ENT_QUOTES, 'UTF-8') ?></small></span></a>
        <?php endforeach; ?>
      </div>
      <div class="pc-list" id="pcList"></div>
      <div class="pc-empty" id="pcEmpty"><?= htmlspecialchars(plugin_schoolmanager_tr('no_results'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="pc-actions" style="<?= !$embed ? 'grid-template-columns:1fr!important' : '' ?>">
        <a class="pc-btn" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/classrooms.php?building=' . rawurlencode($building), ENT_QUOTES, 'UTF-8') ?>"><span class="pc-svgicon pc-i-list" aria-hidden="true"></span><span><?= htmlspecialchars(plugin_schoolmanager_tr('menu_classrooms'), ENT_QUOTES, 'UTF-8') ?></span></a>
        <?php if ($embed): ?>
        <a class="pc-btn red pc-btn-home" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/dashboard.php?v=' . rawurlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><span class="pc-svgicon pc-i-home" aria-hidden="true"></span><span><?= htmlspecialchars(plugin_schoolmanager_tr('menu_home'), ENT_QUOTES, 'UTF-8') ?></span></a>
        <?php endif; ?>
      </div>
    </aside>
    <main class="pc-main">
      <div class="pc-mainbar"><h2><?= htmlspecialchars($building . ' · ' . $floors[$floor]['label'], ENT_QUOTES, 'UTF-8') ?></h2><div class="pc-hint"><?= htmlspecialchars(plugin_schoolmanager_tr('selector_hint', 'Selecciona un aula desde la lista o el plano'), ENT_QUOTES, 'UTF-8') ?> · v<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="pc-planbox" id="planBox">
        <div class="pc-planbar"><span><?= htmlspecialchars(plugin_schoolmanager_tr('selector_interactive_plan', 'Plano seleccionable'), ENT_QUOTES, 'UTF-8') ?></span><span><?= $floors[$floor]['ready'] ? htmlspecialchars(plugin_schoolmanager_tr('selector_click_room', 'Click en aula'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(plugin_schoolmanager_tr('selector_todo', 'Por hacer'), ENT_QUOTES, 'UTF-8') ?></span></div>
        <?php if ($floors[$floor]['ready'] && $nativePlan && $nativeBbox && $nativeZones): ?>
          <?php
            $pad = max(35, min(120, max((float)$nativeBbox['w'], (float)$nativeBbox['h']) * 0.07));
            $vx = (float)$nativeBbox['x'] - $pad;
            $vy = (float)$nativeBbox['y'] - $pad;
            $vw = (float)$nativeBbox['w'] + ($pad * 2);
            $vh = (float)$nativeBbox['h'] + ($pad * 2);
          ?>
          <div class="pc-svgplan" id="pcSvgPlan" data-native-plan="1">
            <svg class="pc-ed2-svg" id="pcEd2Svg" viewBox="<?= htmlspecialchars($vx . ' ' . $vy . ' ' . $vw . ' ' . $vh, ENT_QUOTES, 'UTF-8') ?>" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Plano <?= htmlspecialchars($building . ' ' . $floor, ENT_QUOTES, 'UTF-8') ?>">
              <?php if ($building === 'ED2' && $floor === 'P3'): ?>
                <image class="pc-ed2-bg" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/maps/planos/ED2/ED2-' . $floor . '-base.png?v=' . PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>" x="<?= htmlspecialchars((string)$nativeBbox['x'], ENT_QUOTES, 'UTF-8') ?>" y="<?= htmlspecialchars((string)$nativeBbox['y'], ENT_QUOTES, 'UTF-8') ?>" width="<?= htmlspecialchars((string)$nativeBbox['w'], ENT_QUOTES, 'UTF-8') ?>" height="<?= htmlspecialchars((string)$nativeBbox['h'], ENT_QUOTES, 'UTF-8') ?>" preserveAspectRatio="none" />
              <?php else: ?>
                <rect class="pc-ed2-shell" x="<?= htmlspecialchars((string)((float)$nativeBbox['x'] - 18), ENT_QUOTES, 'UTF-8') ?>" y="<?= htmlspecialchars((string)((float)$nativeBbox['y'] - 18), ENT_QUOTES, 'UTF-8') ?>" width="<?= htmlspecialchars((string)((float)$nativeBbox['w'] + 36), ENT_QUOTES, 'UTF-8') ?>" height="<?= htmlspecialchars((string)((float)$nativeBbox['h'] + 36), ENT_QUOTES, 'UTF-8') ?>" rx="20" />
                <rect class="pc-ed2-corridor" x="<?= htmlspecialchars((string)((float)$nativeBbox['x'] + (float)$nativeBbox['w'] * 0.10), ENT_QUOTES, 'UTF-8') ?>" y="<?= htmlspecialchars((string)((float)$nativeBbox['y'] + (float)$nativeBbox['h'] * 0.42), ENT_QUOTES, 'UTF-8') ?>" width="<?= htmlspecialchars((string)((float)$nativeBbox['w'] * 0.78), ENT_QUOTES, 'UTF-8') ?>" height="<?= htmlspecialchars((string)(max(38, (float)$nativeBbox['h'] * 0.12)), ENT_QUOTES, 'UTF-8') ?>" rx="18" />
                <path class="pc-ed2-planline" d="M <?= htmlspecialchars((string)((float)$nativeBbox['x'] + 10), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string)((float)$nativeBbox['y'] + (float)$nativeBbox['h'] * .5), ENT_QUOTES, 'UTF-8') ?> H <?= htmlspecialchars((string)((float)$nativeBbox['x'] + (float)$nativeBbox['w'] - 10), ENT_QUOTES, 'UTF-8') ?>" />
              <?php endif; ?>
              <?php foreach ($nativeZones as $z):
                $zx = (float)($z['x'] ?? 0); $zy = (float)($z['y'] ?? 0); $zw = (float)($z['w'] ?? 0); $zh = (float)($z['h'] ?? 0);
                $label = (string)($z['label'] ?? $z['code'] ?? '');
                $font = max(12, min(30, min($zw / max(1, strlen($label) * .55), $zh * .42)));
              ?>
              <g class="pc-ed2-zone" tabindex="0" data-id="<?= htmlspecialchars((string)($z['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-code="<?= htmlspecialchars((string)($z['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                <rect x="<?= htmlspecialchars((string)$zx, ENT_QUOTES, 'UTF-8') ?>" y="<?= htmlspecialchars((string)$zy, ENT_QUOTES, 'UTF-8') ?>" width="<?= htmlspecialchars((string)$zw, ENT_QUOTES, 'UTF-8') ?>" height="<?= htmlspecialchars((string)$zh, ENT_QUOTES, 'UTF-8') ?>" />
                <text x="<?= htmlspecialchars((string)($zx + $zw / 2), ENT_QUOTES, 'UTF-8') ?>" y="<?= htmlspecialchars((string)($zy + $zh / 2), ENT_QUOTES, 'UTF-8') ?>" style="font-size:<?= htmlspecialchars((string)$font, ENT_QUOTES, 'UTF-8') ?>px"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></text>
              </g>
              <?php endforeach; ?>
            </svg>
          </div>
        <?php elseif ($floors[$floor]['ready']): ?>
          <iframe class="pc-frame" id="pcFrame" sandbox="allow-scripts allow-same-origin" src="<?= htmlspecialchars($planUrl, ENT_QUOTES, 'UTF-8') ?>"></iframe>
            <?php if (false && !empty($zoneInfo['zones']) && !empty($zoneInfo['bbox'])): ?>
              <div class="pc-click-overlay" id="pcClickOverlay" aria-hidden="false"></div>
            <?php endif; ?>
        <?php else: ?>
          <div class="pc-under"><div class="pc-under-card"><div class="emoji"><span class="pc-svgicon pc-i-construction" aria-hidden="true"></span></div><h3><?= htmlspecialchars(plugin_schoolmanager_tr('selector_plan_pending', 'Plano por hacer'), ENT_QUOTES, 'UTF-8') ?></h3><p><?= sprintf(htmlspecialchars(plugin_schoolmanager_tr('selector_building_ready_plans', 'El %s está preparado para cargar sus planos cuando los tengas exportados.'), ENT_QUOTES, 'UTF-8'), htmlspecialchars($building, ENT_QUOTES, 'UTF-8')) ?></p></div></div>
        
<?php endif; ?>
        <div class="pc-loading" id="pcLoading"><div><div class="pc-spinner"></div><strong><?= htmlspecialchars(plugin_schoolmanager_tr('selector_loading_plan', 'Cargando plano...'), ENT_QUOTES, 'UTF-8') ?></strong></div></div><div class="pc-plan-error" id="pcPlanError"><div class="pc-plan-error-card"><h3><?= htmlspecialchars(plugin_schoolmanager_tr('selector_failed_load_plan', 'No se ha podido cargar el plano'), ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars(plugin_schoolmanager_tr('selector_plan_timeout_help', 'El plano ha tardado demasiado o el navegador lo ha bloqueado. Puedes seguir seleccionando el aula desde la lista de la izquierda.'), ENT_QUOTES, 'UTF-8') ?></p><button class="pc-btn primary" type="button" id="pcReloadPlan"><?= htmlspecialchars(plugin_schoolmanager_tr('selector_retry_load_plan', 'Reintentar cargar plano'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
      </div>
      <section class="pc-result empty" id="pcResult"><?= htmlspecialchars(plugin_schoolmanager_tr('selector_select_prompt', 'Selecciona un aula desde el plano o desde la lista.'), ENT_QUOTES, 'UTF-8') ?></section>
    </main>
  </div>
</div>
<div class="pc-toast" id="pcToast"><?= htmlspecialchars(plugin_schoolmanager_tr('copied', 'Copiado'), ENT_QUOTES, 'UTF-8') ?></div>

<script>
(function(){
 const PLUGIN_VERSION = <?= json_encode(PLUGIN_SCHOOLMANAGER_VERSION, JSON_UNESCAPED_SLASHES) ?>;
const ROOT = <?= json_encode($root, JSON_UNESCAPED_SLASHES) ?>;
 const BUILDING = <?= json_encode($building) ?>;
 const MODE = <?= json_encode($mode) ?>;
 const DATA = <?= $itemsJson ?: '[]' ?>;
 const PLAN_ZONES = <?= json_encode($zoneInfo['zones'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
 const PLAN_BBOX = <?= json_encode($zoneInfo['bbox'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
 const HAS_NATIVE_PLAN = <?= $nativePlan && $nativeZones ? 'true' : 'false' ?>;
 const CAN_OPEN_NATIVE = <?= $canOpenNative ? 'true' : 'false' ?>;
 const IS_EMBED = <?= $embed ? 'true' : 'false' ?>;
 const I18N = {
   zoneNoLink: <?= json_encode(plugin_schoolmanager_tr('selector_zone_no_link', 'Esta zona no tiene enlace de aula asociado.')) ?>,
   noDescription: <?= json_encode(plugin_schoolmanager_tr('no_description', 'Sin descripción')) ?>,
   markedOnPlan: <?= json_encode(plugin_schoolmanager_tr('marked_on_plan', 'Marcado en el plano: ')) ?>,
   markedOnPlanError: <?= json_encode(plugin_schoolmanager_tr('marked_on_plan_error', 'No se pudo marcar el plano')) ?>,
   planNotAvailable: <?= json_encode(plugin_schoolmanager_tr('plan_not_available', 'Plano no disponible para marcar')) ?>,
   roomDetails: <?= json_encode(plugin_schoolmanager_tr('room_details', 'Detalles del aula')) ?>,
   viewInGlpi: <?= json_encode(plugin_schoolmanager_tr('view_in_glpi', 'Ver en GLPI')) ?>,
   applyLocation: <?= json_encode(plugin_schoolmanager_tr('apply_location', 'Aplicar ubicación')) ?>,
   selectedLocation: <?= json_encode(plugin_schoolmanager_tr('selected_location', 'Ubicación seleccionada')) ?>,
   code: <?= json_encode(plugin_schoolmanager_tr('code', 'Código')) ?>,
   floor: <?= json_encode(plugin_schoolmanager_tr('floor', 'Planta')) ?>,
   selectedRoomActions: <?= json_encode(plugin_schoolmanager_tr('selected_room_actions', 'Acciones del aula seleccionada')) ?>,
   roomSelectedPrefix: <?= json_encode(plugin_schoolmanager_tr('room_selected_prefix', 'Aula seleccionada: ')) ?>,
   idCopiedPrefix: <?= json_encode(plugin_schoolmanager_tr('id_copied_prefix', 'ID copiada: ')) ?>,
   locationSent: <?= json_encode(plugin_schoolmanager_tr('location_sent', 'Ubicación enviada')) ?>,
   noFormConnectedCopied: <?= json_encode(plugin_schoolmanager_tr('no_form_connected_copied', 'No hay formulario conectado. ID copiada.')) ?>,
   roomNotFoundInList: <?= json_encode(plugin_schoolmanager_tr('room_not_found_in_list', 'No se ha encontrado el aula en la lista')) ?>
 };
 const app = document.getElementById('pcSelector'), list=document.getElementById('pcList'), empty=document.getElementById('pcEmpty'), search=document.getElementById('pcSearch'), floorFilter=document.getElementById('pcFloorFilter'), sortSelect=document.getElementById('pcSort'), result=document.getElementById('pcResult'), toast=document.getElementById('pcToast'), frame=document.getElementById('pcFrame'), loading=document.getElementById('pcLoading'), clickOverlay=document.getElementById('pcClickOverlay');
 let selected=null;
 let lastPlanSelectionAt=0;
 function resizeApp(){ if(!app) return; if(IS_EMBED){ app.style.setProperty('--pc-app-h','100vh'); return; } const top=app.getBoundingClientRect().top; const h=Math.max(520, window.innerHeight-top-10); app.style.setProperty('--pc-app-h', h+'px'); }
 window.addEventListener('resize', resizeApp); window.addEventListener('load', resizeApp); setTimeout(resizeApp,80); setTimeout(resizeApp,600);
 function norm(v){return (v||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim()}
 function openUrl(a){return ROOT + '/front/location.form.php?id=' + encodeURIComponent(a.id || '')}
 function detailsUrl(a){return ROOT + '/plugins/schoolmanager/front/classroom_detail.php?id=' + encodeURIComponent(a.id || '') + '&code=' + encodeURIComponent(a.codigo || '') + '&building=' + encodeURIComponent(a.building || '') + '&v=' + encodeURIComponent(<?= json_encode(PLUGIN_SCHOOLMANAGER_VERSION, JSON_UNESCAPED_SLASHES) ?>)}
 function mapUrl(a){return ROOT + '/plugins/schoolmanager/front/selector.php?building=' + encodeURIComponent(a.building) + '&floor=' + encodeURIComponent(a.floor) + '&room=' + encodeURIComponent(a.aula) + '&v=' + encodeURIComponent(<?= json_encode(PLUGIN_SCHOOLMANAGER_VERSION, JSON_UNESCAPED_SLASHES) ?>)}
 function initials(a){const x=(a.aula||'').toString(); if(/^\d/.test(x)||/^S\d/i.test(x)) return x.length>3?x.slice(0,3):x; return x.split(/\s|-/).map(p=>p[0]||'').join('').slice(0,3).toUpperCase()}
 function floorRank(f){return {SOT:0,P0:1,P1:2,P2:3,P3:4}[f] ?? 9}
 function byId(id){ if(!id) return null; return DATA.find(a=>String(a.id)===String(id)) || null; }
 function cleanCode(v){ return (v||'').toString().toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/^E2-/,'ED2-').replace(/^E1-/,'ED1-').replace('DIRECCION','DIR').replace('SECRETARIA','SEC').replace('ORIENTACION','ORI').replace('BIBLIOTECA','BIB').replace('GIMNASIO','GYM').replace(/\s+/g,' ').trim(); }
 function byCodeOrLabel(v){
   const raw=cleanCode(v); if(!raw) return null;
   const variants=[raw, raw.replace(/^ED[12]-/,''), raw.replace(/^ED2-/, 'E2-'), raw.replace(/^ED1-/, 'E1-')];
   for(const x of variants){
     const hit=DATA.find(a=> cleanCode(a.codigo)===cleanCode(x) || cleanCode(a.aula)===cleanCode(x) || cleanCode(a.descripcion)===cleanCode(x) || cleanCode(a.codigo).endsWith('-'+cleanCode(x)) );
     if(hit) return hit;
   }
   return null;
 }
 function hitFromCoords(x,y){
   const px=parseFloat(x), py=parseFloat(y); if(!isFinite(px)||!isFinite(py)||!Array.isArray(PLAN_ZONES)) return null;
   let best=null, bestArea=Infinity;
   PLAN_ZONES.forEach(z=>{
     const zx=Number(z.x||0), zy=Number(z.y||0), zw=Number(z.w||0), zh=Number(z.h||0);
     if(px>=zx && px<=zx+zw && py>=zy && py<=zy+zh){
       const area=Math.max(1,zw*zh); if(area<bestArea){best=z; bestArea=area;}
     }
   });
   if(!best) return null;
   return byId(best.id) || byCodeOrLabel(best.code) || byCodeOrLabel(best.label);
 }
 
 function drawClickOverlay(){
   if(!clickOverlay || !PLAN_BBOX || !Array.isArray(PLAN_ZONES) || !PLAN_ZONES.length) return;
   const rect=clickOverlay.getBoundingClientRect();
   if(!rect.width || !rect.height) return;
   const pad=12;
   const scale=Math.min((rect.width-pad*2)/Math.max(1,Number(PLAN_BBOX.w||1)), (rect.height-pad*2)/Math.max(1,Number(PLAN_BBOX.h||1)));
   const drawW=Number(PLAN_BBOX.w||1)*scale, drawH=Number(PLAN_BBOX.h||1)*scale;
   const offX=(rect.width-drawW)/2 - Number(PLAN_BBOX.x||0)*scale;
   const offY=(rect.height-drawH)/2 - Number(PLAN_BBOX.y||0)*scale;
   clickOverlay.innerHTML='';
   PLAN_ZONES.forEach(z=>{
     const hit=byId(z.id) || byCodeOrLabel(z.code) || byCodeOrLabel(z.label);
     if(!hit) return;
     const b=document.createElement('button');
     b.type='button';
     b.className='pc-click-zone';
     b.dataset.id=String(hit.id||z.id||'');
     b.dataset.code=hit.codigo||z.code||'';
     b.dataset.label=hit.aula||z.label||'';
     b.title=(hit.aula||z.label||'')+' · '+(hit.descripcion||'');
     b.style.left=(offX+Number(z.x||0)*scale)+'px';
     b.style.top=(offY+Number(z.y||0)*scale)+'px';
     b.style.width=(Math.max(18,Number(z.w||0)*scale))+'px';
     b.style.height=(Math.max(18,Number(z.h||0)*scale))+'px';
     b.addEventListener('click', function(ev){ ev.preventDefault(); ev.stopPropagation(); select(hit,true); }, true);
     clickOverlay.appendChild(b);
   });
   markNativePlan();
 }
 window.addEventListener('resize', drawClickOverlay);
 setTimeout(attachIframeLinkInterceptor, 250); setTimeout(attachIframeLinkInterceptor, 900); setTimeout(attachIframeLinkInterceptor, 1800);

 function find(raw){
   const value=(raw||'').toString().trim(); if(!value) return null;
   const id=value.match(/location\.form\.php\?id=(\d+)/i)||value.match(/[?&]id=(\d+)/i)||value.match(/\bID\s*[=: ]\s*(\d+)\b/i);
   if(id){ const h=byId(id[1]); if(h) return h; }
   const code=value.match(/\b(?:ED[12]|E[12])-(?:S\d{1,2}B?|\d{3}[A-Z]?|GYM|GIMNASIO|BIB|BIBLIO|BIBLIOTECA|DIR|DIRECCION|SEC|SECRETARIA|ORI|ORIENTACION|SALA-PROF|SALA\s*PROF(?:ESORES)?)\b/i);
   if(code){ const h=byCodeOrLabel(code[0]); if(h) return h; }
   const tokens=value.match(/\b(?:S\d{1,2}B?|\d{3}[A-Z]?|GYM|GIMNASIO|BIB|BIBLIO|BIBLIOTECA|DIR|DIRECCION|SEC|SECRETARIA|ORI|ORIENTACION|SALA\s*PROF(?:ESORES)?)\b/gi) || [];
   const unique=[...new Set(tokens.map(cleanCode))];
   if(unique.length===1){ return byCodeOrLabel(unique[0]); }
   return null;
 }
 function hrefFromNode(n){
   for(let el=n; el && el.nodeType===1; el=el.parentElement){
     const direct = el.getAttribute('data-pc-real-href') || el.getAttribute('href') || el.getAttribute('xlink:href') || el.getAttribute('data-href') || el.getAttribute('data-url') || '';
     if(direct && direct !== '#') return direct;
   }
   return '';
 }
 function attachIframeLinkInterceptor(){
   if(!frame || frame.dataset.pcLinkBound==='1') return;
   try{
     const doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
     if(!doc || !doc.documentElement) return;
     frame.dataset.pcLinkBound='1';
     const handle=function(ev){
       const href = hrefFromNode(ev.target);
       const hit = find(href);
       if(hit){
         ev.preventDefault(); ev.stopPropagation(); ev.stopImmediatePropagation && ev.stopImmediatePropagation();
         select(hit,true);
         return false;
       }
       return true;
     };
     doc.addEventListener('click', handle, true);
     doc.addEventListener('dblclick', handle, true);
     // Evita que diagrams.net abra una pestaña nativa si dispara window.open(url).
     if(frame.contentWindow){
       frame.contentWindow.open = function(url){ const hit=find(url||''); if(hit){ select(hit,true); } return null; };
     }
   }catch(e){ frame.dataset.pcLinkBound=''; }
 }
 window.addEventListener('message', function(ev){
   const p=ev.data||{}; if(p.type!=='schoolmanager-plan-click') return;
   // v217: usar primero y casi siempre el enlace/ID real del HTML.
   // No se usan coordenadas ni texto cercano como fallback general porque confundia BIBLIO/DIR/SEC y P3.
   const hit = byId(p.id) || find(p.href||'') || byCodeOrLabel(p.code) || byCodeOrLabel(p.label) || find(p.title||'');
   if(hit){ lastPlanSelectionAt=Date.now(); select(hit,true); }
   else { if(Date.now()-lastPlanSelectionAt>900){ showToast(I18N.zoneNoLink); } }
 }, false);
 function filtered(){ const q=norm(search.value), fl=floorFilter.value; let arr=DATA.filter(a=>{const hay=norm([a.aula,a.codigo,a.descripcion,a.planta,a.building].join(' ')); return (!q||hay.includes(q))&&(!fl||a.floor===fl);}); const s=sortSelect.value; arr.sort((a,b)=>{if(s==='name')return (a.aula||'').localeCompare(b.aula||'',undefined,{numeric:true}); if(s==='desc')return (a.descripcion||'').localeCompare(b.descripcion||''); return (floorRank(a.floor)-floorRank(b.floor)) || ((a.is_numbered?1:0)-(b.is_numbered?1:0)) || (a.aula||'').localeCompare(b.aula||'',undefined,{numeric:true});}); return arr; }
 function render(){ const arr=filtered(); list.innerHTML=''; empty.style.display=arr.length?'none':'block'; arr.forEach(a=>{const btn=document.createElement('button'); btn.type='button'; btn.className='pc-item'+(selected&&selected.codigo===a.codigo?' active':''); btn.innerHTML='<div class="pc-avatar">'+esc(initials(a))+'</div><div><div class="pc-room">'+esc(a.aula)+'</div><div class="pc-desc">'+esc(a.descripcion||I18N.noDescription)+'</div></div><div class="pc-code">'+esc(a.codigo)+'</div>'; btn.onclick=()=>select(a,false); btn.ondblclick=()=>{ if(MODE==='select'){ useLocation(a); } else { location.href=CAN_OPEN_NATIVE&&a.id?openUrl(a):detailsUrl(a); } }; list.appendChild(btn); }); }
 function markNativePlan(){
   document.querySelectorAll('.pc-ed2-zone').forEach(z=>{const hit=selected && (z.dataset.code===selected.codigo || String(z.dataset.id||'')===String(selected.id||'')); z.classList.toggle('active', !!hit);});
   document.querySelectorAll('.pc-click-zone').forEach(z=>{const hit=selected && (String(z.dataset.id||'')===String(selected.id||'') || cleanCode(z.dataset.code)===cleanCode(selected.codigo)); z.classList.toggle('active', !!hit);});
 }
 function focusPlan(a){
   if(!a) return;
   if(frame && frame.contentWindow){
     try{
       frame.contentWindow.postMessage({type:'schoolmanager-highlight-room',id:String(a.id||''),code:String(a.codigo||''),room:String(a.aula||'')}, '*');
       frame.scrollIntoView({block:'nearest',inline:'nearest',behavior:'smooth'});
       showToast(I18N.markedOnPlan + (a.aula||a.codigo||''));
     }catch(e){ showToast(I18N.markedOnPlanError); }
   } else {
     showToast(I18N.planNotAvailable);
   }
 }
 function select(a, fromPlan){
   selected=a; render(); markNativePlan(); result.classList.remove('empty');
   const detailsBtn = '<a class="pc-btn primary" data-pc-action="details" href="'+attr(detailsUrl(a))+'"><span class="pc-svgicon pc-i-info" aria-hidden="true"></span><span>'+esc(I18N.roomDetails)+'</span></a>';
   const nativeBtn = a.id ? '<a class="pc-btn glpi" data-pc-action="native" href="'+attr(openUrl(a))+'"><span class="pc-svgicon pc-i-external" aria-hidden="true"></span><span>'+esc(I18N.viewInGlpi)+'</span></a>' : '';
   const selectBtn = MODE==='select' ? '<button class="pc-btn select" data-pc-action="apply" type="button" id="pcUse"><span class="pc-svgicon pc-i-check" aria-hidden="true"></span><span>'+esc(I18N.applyLocation)+'</span></button>' : '';
   const actionsHtml = detailsBtn + nativeBtn + selectBtn;
   result.innerHTML='<div class="pc-room-details"><small>'+esc(I18N.selectedLocation)+'</small><h3>'+esc(a.aula)+' · '+esc(a.descripcion||'')+'</h3><p><span><b>'+esc(I18N.code)+'</b> '+esc(a.codigo)+'</span><span><b>ID GLPI</b> '+esc(a.id||'—')+'</span><span><b>'+esc(I18N.floor)+'</b> '+esc(a.planta)+'</span></p></div><div class="pc-result-actions" aria-label="'+esc(I18N.selectedRoomActions)+'">'+actionsHtml+'</div>';
   // seguridad: si alguna regla antigua intenta vaciar acciones, se reconstruye en el siguiente frame
   requestAnimationFrame(()=>{ const box=result.querySelector('.pc-result-actions'); if(box && !box.children.length){ box.innerHTML=actionsHtml; } });
   const use=document.getElementById('pcUse'); if(use) use.onclick=()=>useLocation(a);
   if(fromPlan){ lastPlanSelectionAt=Date.now(); showToast(I18N.roomSelectedPrefix + a.aula); }
 }
 function copy(v){navigator.clipboard&&navigator.clipboard.writeText(String(v||'')); showToast(I18N.idCopiedPrefix + (v||'—'))}
 function useLocation(a){const payload={type:'schoolmanager:location-selected',id:parseInt(a.id||0,10),name:a.aula,code:a.codigo,building:a.building,floor:a.floor,description:a.descripcion,openUrl:openUrl(a)}; if(window.parent&&window.parent!==window){window.parent.postMessage(payload, window.location.origin); showToast(I18N.locationSent); return;} if(window.opener&&!window.opener.closed){window.opener.postMessage(payload, window.location.origin); showToast(I18N.locationSent); setTimeout(()=>window.close(),450);} else {copy(a.id); showToast(I18N.noFormConnectedCopied)}}
 function showToast(t){toast.textContent=t;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),1900)}
 function esc(s){return (s??'').toString().replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))} function attr(s){return esc(s)}
 search.oninput=render; floorFilter.onchange=render; sortSelect.onchange=render;
 const planError=document.getElementById('pcPlanError'), reloadPlan=document.getElementById('pcReloadPlan');
 if(frame&&loading){
   loading.classList.add('show');
   let loaderKilled=false, loaded=false;
   const hideLoader=()=>{ if(loaderKilled) return; loaderKilled=true; loading.classList.remove('show'); };
   const showPlanError=()=>{ if(loaded) return; hideLoader(); if(planError) planError.classList.add('show'); };
   frame.addEventListener('load',()=>{ loaded=true; setTimeout(hideLoader,220); if(planError) planError.classList.remove('show'); setTimeout(attachIframeLinkInterceptor,80); setTimeout(attachIframeLinkInterceptor,350); setTimeout(attachIframeLinkInterceptor,1000); });
   frame.addEventListener('error',()=>{ loaded=false; setTimeout(showPlanError,220);});
   setTimeout(hideLoader,1800);
   setTimeout(showPlanError,9000);
   if(reloadPlan) reloadPlan.onclick=()=>{ if(planError) planError.classList.remove('show'); loading.classList.add('show'); loaderKilled=false; loaded=false; frame.src=frame.src.replace(/([?&])retry=\d+/, '$1retry=' + Date.now()) + (frame.src.indexOf('?')>-1 ? '&' : '?') + 'retry=' + Date.now(); setTimeout(hideLoader,1800); setTimeout(showPlanError,9000); };
 }
 if(HAS_NATIVE_PLAN){
   const svgPlan=document.getElementById('pcSvgPlan');
   if(svgPlan){
     svgPlan.addEventListener('click', function(ev){
       const z=ev.target.closest && ev.target.closest('.pc-ed2-zone');
       if(!z) return;
       ev.preventDefault(); ev.stopPropagation();
       const hit=find([z.dataset.id?('id='+z.dataset.id):'', z.dataset.code||'', z.dataset.label||''].join(' '));
       if(hit) select(hit,true); else showToast(I18N.roomNotFoundInList);
     }, true);
     svgPlan.addEventListener('keydown', function(ev){
       if(ev.key!=='Enter' && ev.key!==' ') return;
       const z=ev.target.closest && ev.target.closest('.pc-ed2-zone');
       if(!z) return;
       ev.preventDefault();
       const hit=find([z.dataset.id?('id='+z.dataset.id):'', z.dataset.code||'', z.dataset.label||''].join(' '));
       if(hit) select(hit,true);
     }, true);
   }
 }
 render(); markNativePlan();
 document.querySelectorAll('.pc-sel-mobile-btn').forEach(btn => {
   btn.addEventListener('click', function() {
     const view = this.getAttribute('data-sel-view') || 'plan';
     if (app) app.setAttribute('data-mobile-view', view);
     document.querySelectorAll('.pc-sel-mobile-btn').forEach(b => b.classList.toggle('active', b === this));
   });
 });
})();
</script>
<script src="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/js/custom-combobox.js?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"></script>
<?php if (!$embed) { sm_page_footer(); } ?>

