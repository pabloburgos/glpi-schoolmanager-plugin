<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/stock_helpers.php');

$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$canAssets = function_exists('plugin_schoolmanager_can_create_asset') && plugin_schoolmanager_can_create_asset(null);
$canStock = function_exists('plugin_schoolmanager_can_manage_stock') ? plugin_schoolmanager_can_manage_stock() : $canAssets;
if (!$canStock) {
    if (function_exists('plugin_schoolmanager_error_page')) {
        plugin_schoolmanager_error_page('Acceso restringido', 'Tu perfil no tiene permisos para gestionar stock TIC.', 'Pide al equipo administrador que te asigne perfil TIC o permisos de consumibles/cartuchos.');
    }
    Html::redirect($root . '/plugins/schoolmanager/front/formularios.php?v=286');
}

$kind = ($_GET['kind'] ?? 'consumable') === 'cartridge' ? 'cartridge' : 'consumable';
$search = trim((string)($_GET['q'] ?? ''));
$state = in_array(($_GET['state'] ?? 'all'), ['all','ok','low','empty'], true) ? $_GET['state'] : 'all';
$typeId = max(0, (int)($_GET['type_id'] ?? 0));
$msg = trim((string)($_GET['msg'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));
$cfg = smgr_stock_kind_config($kind);
$stockTypes = function_exists('smgr_stock_types') ? smgr_stock_types($kind) : [];
$items = function_exists('smgr_stock_export_rows') ? smgr_stock_export_rows($kind, $search, $state, $typeId) : smgr_stock_items($kind, $search, $state);
if (!function_exists('smgr_stock_export_rows') && $typeId > 0) {
    $typeFk = smgr_stock_type_fk($kind);
    $items = array_values(array_filter($items, static fn($r) => (int)($r[$typeFk] ?? 0) === $typeId));
}
$summary = smgr_stock_summary();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock-schoolmanager-' . $kind . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['tipo', 'id', 'nombre', 'referencia', 'categoria', 'disponibles', 'total_historico', 'minimo', 'estado']);
    foreach ($items as $row) {
        $typeField = smgr_stock_type_fk($kind);
        fputcsv($out, [
            $cfg['label'],
            (int)$row['id'],
            (string)($row['name'] ?? ''),
            (string)($row['ref'] ?? ''),
            smgr_stock_item_type_name($kind, (int)($row[$typeField] ?? 0)),
            (int)$row['_available'],
            (int)$row['_total'],
            (int)$row['_threshold'],
            smgr_stock_status_label((string)$row['_status'])
        ]);
    }
    fclose($out);
    exit;
}

function st_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function st_token() {
    if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
        static $tok = null; if ($tok === null) { $tok = Session::getNewCSRFToken(); } return '<input type="hidden" name="_glpi_csrf_token" value="' . st_h($tok) . '">';
    }
    return '';
}
function st_svg($name) {
    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
        'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73L13 2.27a2 2 0 0 0-2 0L4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>',
        'cart' => '<path d="M6 6h15l-1.5 8h-12z"/><path d="M6 6 5.2 3H2"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',
        'printer' => '<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'filter' => '<path d="M3 5h18l-7 8v5l-4 2v-7z"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'external' => '<path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'chart' => '<path d="M4 19V5"/><path d="M4 19h17"/><path d="M8 16v-5"/><path d="M13 16V8"/><path d="M18 16v-3"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'tag' => '<path d="M20.6 13.2 13.2 20.6a2 2 0 0 1-2.8 0L3 13.2V3h10.2l7.4 7.4a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r=".5"/>',
        'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.38.18.7.48.9.86.2.36.3.75.3 1.14Z"/>',
    ];
    return '<svg class="sti" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? $icons['box']) . '</svg>';
}
function st_pct($available, $threshold) {
    $available = max(0, (int)$available);
    $threshold = max(1, (int)$threshold);
    $scale = max($threshold * 2, $available, 1);
    return max(3, min(100, (int)round(($available / $scale) * 100)));
}
function st_url($base, array $params) { return $base . '?' . http_build_query($params); }

Html::header('Control de stock TIC', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');

$baseUrl = $root . '/plugins/schoolmanager/front/stock_glpi.php';
$nativeUrl = $root . $cfg['native_list'];
$typeFk = smgr_stock_type_fk($kind);
$statusCounts = ['ok'=>0,'low'=>0,'empty'=>0];
foreach (smgr_stock_export_rows($kind, '', 'all', $typeId) as $r) {
    $s = (string)($r['_status'] ?? 'ok');
    if (isset($statusCounts[$s])) { $statusCounts[$s]++; }
}
$openCreateModal = (($_GET['modal'] ?? '') === 'new');
$allCount = count(smgr_stock_export_rows($kind, '', 'all', $typeId));
?>
<style>

html,body{height:auto!important;min-height:100%!important;overflow-y:auto!important}.page,.page-wrapper,.layout-wrapper,.content,.content-wrapper,.main-content{height:auto!important;min-height:0!important;overflow:visible!important}.stp{--navy:#07384d;--teal:#0c6672;--teal2:#0e8790;--red:#b6252b;--gold:#e1a000;--ink:#0b2f40;--muted:#63798b;--line:#dbe9ef;--soft:#f5fafc;--ok:#168052;--shadow:0 12px 30px rgba(7,56,77,.07);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;min-height:100vh;padding:18px 24px 88px;background:linear-gradient(180deg,#f7fbfd 0%,#eef6f8 100%);color:var(--ink)}.stp *{box-sizing:border-box;max-width:100%}.sti{width:18px;height:18px;display:inline-block;vertical-align:-4px;flex:0 0 auto}.stp-wrap{max-width:1480px;margin:0 auto;display:grid;gap:16px}.stp-top{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);padding:16px 18px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center}.stp-brand{display:flex;gap:16px;align-items:center;min-width:0}.stp-logo{width:126px;max-height:66px;object-fit:contain}.stp-kicker{font-size:12px;font-weight:950;letter-spacing:.13em;color:var(--red);text-transform:uppercase}.stp-title h1{margin:2px 0 4px;color:var(--navy);font-size:clamp(34px,3.7vw,50px);line-height:1;letter-spacing:-.04em}.stp-title p{margin:0;color:var(--muted);font-weight:800}.stp-actions,.stp-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.stp-actions{justify-content:flex-end}.stp-btn{border:1px solid var(--line);background:#fff;color:var(--navy)!important;text-decoration:none!important;border-radius:13px;min-height:42px;padding:9px 13px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.15s ease;white-space:nowrap}.stp-btn:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(7,56,77,.10)}.stp-btn.primary{background:linear-gradient(135deg,var(--teal),var(--navy));border-color:var(--navy);color:#fff!important}.stp-btn.red{background:linear-gradient(135deg,#8b1e1e,var(--red));border-color:#8b1e1e;color:#fff!important}.stp-btn.ghost{background:#f9fcfd}.stp-msg{border-radius:15px;padding:12px 15px;font-weight:900;border:1px solid}.stp-msg.ok{background:#eefaf2;color:#12663d;border-color:#bee8ce}.stp-msg.err{background:#fff0f0;color:#a6191f;border-color:#efc0c4}.stp-metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.stp-metric{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 9px 23px rgba(7,56,77,.055);padding:13px 15px;display:flex;gap:12px;align-items:center;min-height:72px}.stp-metric .icon{width:40px;height:40px;border-radius:13px;background:#eef8fb;color:var(--navy);display:grid;place-items:center;flex:0 0 auto}.stp-metric b{font-size:30px;line-height:1;color:var(--navy);letter-spacing:-.03em}.stp-metric span{display:block;color:var(--muted);font-weight:900;font-size:13px}.stp-metric.warn b{color:var(--gold)}.stp-metric.danger b{color:var(--red)}.stp-control{background:#fff;border:1px solid var(--line);border-radius:21px;box-shadow:var(--shadow);padding:16px;display:grid;gap:14px}.stp-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.stp-tab{border:1px solid var(--line);background:#fff;border-radius:17px;padding:13px 15px;color:var(--navy)!important;text-decoration:none!important;display:flex;align-items:center;gap:12px;min-height:66px}.stp-tab strong{display:block;font-size:17px}.stp-tab small{display:block;color:var(--muted);font-weight:800;margin-top:2px}.stp-tab .icon{width:40px;height:40px;border-radius:13px;background:#eef8fb;display:grid;place-items:center;flex:0 0 auto}.stp-tab.active{background:linear-gradient(135deg,var(--teal),var(--navy));border-color:var(--navy);color:#fff!important}.stp-tab.active small{color:#d7f1f5}.stp-tab.active .icon{background:rgba(255,255,255,.18);color:#fff}.stp-filter{display:grid;grid-template-columns:minmax(260px,1.25fr) minmax(220px,.75fr) auto;gap:14px;align-items:end}.stp-field label{display:flex;align-items:center;gap:8px;margin:0 0 7px;font-size:13px;color:var(--navy);font-weight:950}.stp-input,.stp-select{width:100%;height:46px;border:1px solid var(--line);border-radius:12px;background:#fff;color:#27394a;font-weight:800;padding:0 13px}.stp-input:focus,.stp-select:focus{outline:none;border-color:#75b7c3;box-shadow:0 0 0 4px rgba(12,102,114,.12)}.stp-filter-actions{display:flex;gap:9px}.stp-chips{display:flex;gap:9px;flex-wrap:wrap}.stp-chip{border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 12px;font-weight:900;color:var(--navy)!important;text-decoration:none!important;display:inline-flex;gap:8px;align-items:center}.stp-chip.active{background:var(--navy);border-color:var(--navy);color:#fff!important}.stp-content{display:grid;grid-template-columns:1fr;gap:14px;align-items:start}.stp-list{display:grid;gap:12px;min-width:0}.stp-empty{background:#fff;border:1px dashed #b8d5df;border-radius:18px;padding:28px;text-align:center;color:var(--muted);font-weight:900}.stp-item{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 9px 24px rgba(7,56,77,.055);padding:16px 18px;display:grid;grid-template-columns:minmax(250px,1.25fr) minmax(205px,.6fr) minmax(430px,.95fr);gap:18px;align-items:center;transition:.15s ease;overflow:hidden}.stp-item:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(7,56,77,.09)}.stp-item.low{border-color:#efd384}.stp-item.empty{border-color:#f0b8bd}.stp-name h3{margin:0 0 7px;color:var(--navy);font-size:22px;line-height:1.1}.stp-meta{display:flex;gap:7px;flex-wrap:wrap}.stp-pill{border:1px solid var(--line);border-radius:999px;background:#f8fbfd;color:var(--muted);font-size:12px;font-weight:900;padding:5px 9px}.stp-pill.ok{color:#0d6d44;background:#edf9f2;border-color:#c7ead4}.stp-pill.low{color:#855f00;background:#fff7df;border-color:#efd384}.stp-pill.empty{color:#a6191f;background:#fff0f0;border-color:#efc1c6}.stp-stock{display:grid;gap:8px;min-width:0}.stp-stock .big{display:flex;align-items:flex-end;gap:7px}.stp-stock b{font-size:32px;line-height:1;color:var(--navy)}.stp-stock span{color:var(--muted);font-weight:900}.stp-bar{height:9px;background:#edf4f7;border-radius:99px;overflow:hidden;border:1px solid #dcebf0}.stp-bar i{height:100%;width:var(--w);display:block;background:linear-gradient(90deg,var(--teal2),var(--navy));border-radius:99px}.stp-item.low .stp-bar i{background:linear-gradient(90deg,#f5bf23,#d58b00)}.stp-item.empty .stp-bar i{background:var(--red)}.stp-quick{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;align-items:center;min-width:0}.stp-quick form{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin:0;min-width:172px;max-width:205px;flex:0 0 auto}.stp-qty{height:42px;border:1px solid var(--line);border-radius:12px;text-align:center;font-weight:950;color:var(--navy);width:54px;min-width:54px;flex:0 0 54px}.stp-mini{border:0;border-radius:12px;height:42px;min-width:110px;font-weight:950;color:#fff;display:inline-flex;gap:7px;align-items:center;justify-content:center;cursor:pointer;padding:0 12px;white-space:nowrap;flex:0 0 110px}.stp-mini.add{background:linear-gradient(135deg,var(--teal),var(--navy))}.stp-mini.out{background:linear-gradient(135deg,#8b1e1e,var(--red))}.stp-mini:disabled{opacity:.45;cursor:not-allowed}.stp-item-tools{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #eef4f7;padding-top:12px;margin-top:0;flex-wrap:wrap}.stp-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.stp-side-card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 9px 22px rgba(7,56,77,.045);padding:14px 16px}.stp-side-card h3{margin:0 0 8px;font-size:17px;color:var(--navy)}.stp-side-card p{margin:0;color:var(--muted);font-weight:800;line-height:1.45}.stp-side-list{display:grid;gap:6px}.stp-side-list div{display:flex;gap:8px;align-items:flex-start;color:var(--muted);font-weight:800}.stp-dot{width:8px;height:8px;border-radius:99px;background:var(--teal);margin-top:7px;flex:0 0 auto}.stp-modal{position:fixed;inset:0;background:rgba(7,35,50,.46);z-index:9999;display:none;align-items:center;justify-content:center;padding:22px}.stp-modal.open{display:flex}.stp-modal-card{width:min(840px,96vw);max-height:92vh;overflow:auto;background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 26px 70px rgba(0,0,0,.22)}.stp-modal-head{position:sticky;top:0;background:#fff;border-bottom:1px solid var(--line);padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px;z-index:2}.stp-modal-head h2{margin:0;color:var(--navy);font-size:27px;display:flex;gap:10px;align-items:center}.stp-modal-head p{margin:4px 0 0;color:var(--muted);font-weight:800}.stp-close{width:42px;height:42px;border-radius:14px;border:1px solid var(--line);background:#fff;color:var(--navy);display:grid;place-items:center;cursor:pointer}.stp-modal-body{padding:20px;display:grid;gap:16px}.stp-create-hints{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.stp-create-hints div{background:#f7fbfd;border:1px solid var(--line);border-radius:16px;padding:12px;color:var(--muted);font-weight:800}.stp-create-hints b{display:block;color:var(--navy);margin-bottom:3px}.stp-create-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.stp-wide{grid-column:1/-1}.stp-choice{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.stp-kind,.stp-template{border:1px solid var(--line);background:#fff;border-radius:16px;padding:13px;text-align:left;color:var(--navy);font-weight:950;cursor:pointer}.stp-kind.active{background:linear-gradient(135deg,var(--teal),var(--navy));color:#fff;border-color:var(--navy)}.stp-kind small,.stp-template small{display:block;margin-top:4px;color:var(--muted);font-weight:800}.stp-kind.active small{color:#d7f1f5}.stp-templates{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.stp-template{font-size:13px;min-height:74px}.stp-help{font-size:12px;color:var(--muted);font-weight:800;margin-top:6px;line-height:1.35}.stp-modal-foot{position:sticky;bottom:0;background:#fff;border-top:1px solid var(--line);padding:16px 20px;display:flex;justify-content:flex-end;gap:10px}.stp-hidden{display:none!important}@media(max-width:1280px){.stp-item{grid-template-columns:minmax(240px,1fr) minmax(190px,.6fr);}.stp-quick{grid-column:1/-1;justify-content:flex-start}.stp-item-tools{justify-content:flex-start}.stp-side{grid-template-columns:1fr 1fr}}@media(max-width:980px){.stp{padding:16px 14px 80px}.stp-top{grid-template-columns:1fr}.stp-actions{justify-content:flex-start}.stp-logo{width:108px}.stp-metrics{grid-template-columns:repeat(2,1fr)}.stp-filter{grid-template-columns:1fr}.stp-tabs,.stp-create-grid,.stp-choice,.stp-create-hints{grid-template-columns:1fr}.stp-item{grid-template-columns:1fr}.stp-side{grid-template-columns:1fr}.stp-templates{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.stp-metrics{grid-template-columns:1fr}.stp-quick{display:grid;grid-template-columns:1fr}.stp-quick form{max-width:none;width:100%;justify-content:flex-start}.stp-title h1{font-size:34px}.stp-brand{align-items:flex-start}.stp-logo{width:82px}.stp-templates{grid-template-columns:1fr}.stp-filter-actions{flex-wrap:wrap}}


/* HOTFIX NUEVO STOCK PRO: modal mas limpio, interactivo y sin descuadres */
.stp-modal{padding:18px!important;align-items:center!important;justify-content:center!important;backdrop-filter:blur(5px)}
.stp-modal-card{width:min(980px,calc(100vw - 44px))!important;max-height:min(86vh,820px)!important;overflow:hidden!important;border-radius:26px!important;display:grid!important;grid-template-rows:auto 1fr;background:#fff!important;box-shadow:0 28px 80px rgba(7,35,50,.30)!important}.stp-modal-card form{min-height:0;display:grid;grid-template-rows:1fr auto;overflow:hidden}.stp-modal-head{padding:20px 24px!important}.stp-modal-head h2{font-size:30px!important;letter-spacing:-.03em}.stp-modal-head p{font-size:14px!important}.stp-modal-body{padding:18px 24px 22px!important;overflow:auto!important;display:grid!important;grid-template-columns:260px minmax(0,1fr)!important;gap:18px!important;background:linear-gradient(180deg,#fff 0%,#f8fcfd 100%)}.stp-modal-foot{padding:16px 24px!important}.stp-create-rail{display:grid;gap:12px;align-content:start}.stp-step-card{border:1px solid var(--line);background:#fff;border-radius:18px;padding:14px;display:grid;grid-template-columns:38px 1fr;gap:10px;box-shadow:0 8px 18px rgba(7,56,77,.045)}.stp-step-card i{width:38px;height:38px;border-radius:13px;background:#edf8fb;color:var(--navy);display:grid;place-items:center;font-style:normal;font-weight:1000}.stp-step-card.active{border-color:#8ac5d2;background:linear-gradient(180deg,#f4fbfd,#fff)}.stp-step-card b{display:block;color:var(--navy);font-size:14px}.stp-step-card span{display:block;color:var(--muted);font-size:12px;font-weight:800;line-height:1.3;margin-top:2px}.stp-create-main{display:grid;gap:14px;min-width:0}.stp-section{border:1px solid var(--line);background:#fff;border-radius:20px;padding:15px;box-shadow:0 9px 22px rgba(7,56,77,.045)}.stp-section-title{display:flex;align-items:center;gap:8px;margin:0 0 12px;color:var(--navy);font-weight:1000;font-size:17px}.stp-choice{grid-template-columns:repeat(2,minmax(0,1fr))!important}.stp-kind{min-height:86px;border-radius:18px!important;padding:14px!important;display:grid;gap:5px;align-content:center;text-align:left}.stp-kind .sti{width:22px;height:22px}.stp-kind strong{font-size:17px;display:block}.stp-kind small{line-height:1.25}.stp-templates{grid-template-columns:repeat(5,minmax(0,1fr))!important}.stp-template{min-height:82px!important;border-radius:17px!important;padding:12px!important;transition:.15s ease}.stp-template:hover{transform:translateY(-2px);border-color:#8ac5d2;box-shadow:0 10px 20px rgba(7,56,77,.08)}.stp-form-grid{display:grid;grid-template-columns:1.3fr .9fr;gap:12px}.stp-form-grid .stp-field.wide{grid-column:1/-1}.stp-modal .stp-field label{font-size:14px!important;margin-bottom:6px!important}.stp-modal .stp-input,.stp-modal .stp-select{height:48px!important;border-radius:14px!important;font-size:15px}.stp-help{font-size:12px!important}.stp-live-preview{border:1px solid #cfe5ec;background:linear-gradient(135deg,#f6fbfd,#fff);border-radius:18px;padding:14px;display:grid;gap:8px}.stp-live-preview h3{margin:0;color:var(--navy);font-size:20px}.stp-preview-pills{display:flex;gap:7px;flex-wrap:wrap}.stp-preview-pills span{border:1px solid var(--line);background:#fff;border-radius:999px;padding:5px 8px;color:var(--muted);font-size:12px;font-weight:900}.stp-modal-foot .stp-btn{min-height:46px;padding:10px 18px}.stp-modal-foot .stp-btn.primary{min-width:170px}.stp-modal-foot{gap:12px}@media(max-width:980px){.stp-modal-card{width:calc(100vw - 24px)!important;max-height:90vh!important}.stp-modal-body{grid-template-columns:1fr!important}.stp-create-rail{grid-template-columns:repeat(3,1fr)}.stp-step-card{grid-template-columns:1fr;text-align:center}.stp-step-card i{margin:auto}.stp-templates{grid-template-columns:repeat(2,minmax(0,1fr))!important}.stp-form-grid{grid-template-columns:1fr}}@media(max-width:560px){.stp-modal{padding:8px!important}.stp-modal-card{width:calc(100vw - 16px)!important;max-height:94vh!important;border-radius:20px!important}.stp-modal-head{padding:16px!important}.stp-modal-body{padding:14px!important}.stp-create-rail,.stp-choice,.stp-templates{grid-template-columns:1fr!important}.stp-modal-foot{padding:14px!important;display:grid!important;grid-template-columns:1fr}.stp-modal-foot .stp-btn{width:100%}}


/* HOTFIX NUEVO STOCK VISIBLE: seleccion marcada, boton crear fijo y modal mas usable */
.stp-modal.open{display:flex!important;align-items:center!important;justify-content:center!important;background:rgba(6,25,36,.58)!important;backdrop-filter:blur(6px)}
.stp-modal-card{width:min(1040px,calc(100vw - 42px))!important;height:min(86vh,820px)!important;max-height:min(86vh,820px)!important;overflow:hidden!important;display:grid!important;grid-template-rows:auto minmax(0,1fr)!important;border-radius:26px!important;background:#fff!important}
.stp-modal-card form#createStockForm{min-height:0!important;height:100%!important;display:grid!important;grid-template-rows:minmax(0,1fr) auto!important;overflow:hidden!important}
.stp-modal-body{min-height:0!important;overflow:auto!important;overscroll-behavior:contain!important;display:grid!important;grid-template-columns:245px minmax(0,1fr)!important;gap:18px!important;padding:18px 22px!important;background:linear-gradient(180deg,#ffffff 0%,#f5fbfd 100%)!important}
.stp-modal-head{flex:0 0 auto!important;border-bottom:1px solid #dbe9ef!important;background:#fff!important;z-index:6!important}
.stp-modal-foot{position:relative!important;z-index:9!important;flex:0 0 auto!important;width:100%!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:12px!important;padding:16px 22px!important;background:linear-gradient(180deg,#fff,#f7fbfd)!important;border-top:1px solid #dbe9ef!important;box-shadow:0 -12px 30px rgba(7,56,77,.08)!important}
.stp-modal-foot::before{content:'Revisa los datos y pulsa Crear stock';margin-right:auto;color:#63798b;font-weight:900;font-size:13px}
.stp-create-submit{display:inline-flex!important;visibility:visible!important;opacity:1!important;min-width:190px!important;background:linear-gradient(135deg,#0c6672,#07384d)!important;color:#fff!important;border-color:#07384d!important;box-shadow:0 14px 28px rgba(7,56,77,.18)!important}
.stp-create-submit:disabled{opacity:.55!important;cursor:not-allowed!important;filter:grayscale(.2)}
.stp-modal .stp-choice{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:12px!important}
.stp-modal .stp-kind{position:relative!important;min-height:108px!important;border:2px solid #dbe9ef!important;background:#fff!important;color:#07384d!important;border-radius:20px!important;padding:18px 16px 16px!important;box-shadow:0 8px 20px rgba(7,56,77,.04)!important;transition:transform .14s ease, box-shadow .14s ease, border-color .14s ease, background .14s ease!important}
.stp-modal .stp-kind:hover{transform:translateY(-1px)!important;border-color:#8ac5d2!important;box-shadow:0 14px 26px rgba(7,56,77,.09)!important}
.stp-modal .stp-kind.active{background:linear-gradient(135deg,#0c6672 0%,#07384d 100%)!important;color:#fff!important;border-color:#07384d!important;box-shadow:0 18px 34px rgba(7,56,77,.24)!important;outline:4px solid rgba(12,102,114,.16)!important}
.stp-modal .stp-kind.active::after{content:'Seleccionado';position:absolute;right:12px;top:12px;background:#fff;color:#07384d;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:1000;letter-spacing:.02em;box-shadow:0 4px 12px rgba(0,0,0,.12)}
.stp-modal .stp-kind.active small,.stp-modal .stp-kind.active strong{color:#fff!important}.stp-modal .stp-kind.active .sti{color:#fff!important}
.stp-modal .stp-templates{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:10px!important}
.stp-modal .stp-template{position:relative!important;border:2px solid #dbe9ef!important;background:#fff!important;color:#07384d!important;min-height:86px!important;border-radius:18px!important;padding:13px 12px!important}
.stp-modal .stp-template.selected{border-color:#0c6672!important;background:#effbfc!important;box-shadow:0 12px 24px rgba(12,102,114,.14)!important;outline:3px solid rgba(12,102,114,.12)!important}
.stp-modal .stp-template.selected::after{content:'OK';position:absolute;right:9px;top:8px;background:#0c6672;color:#fff;border-radius:999px;padding:4px 7px;font-size:10px;font-weight:1000}
.stp-modal .stp-section{padding:16px!important;border-radius:20px!important;background:#fff!important;border:1px solid #dbe9ef!important;box-shadow:0 9px 20px rgba(7,56,77,.045)!important}
.stp-modal .stp-section-title{font-size:18px!important;margin-bottom:12px!important;color:#07384d!important}
.stp-modal .stp-form-grid{display:grid!important;grid-template-columns:1fr 1fr!important;gap:13px 14px!important}.stp-modal .stp-field.wide{grid-column:1/-1!important}
.stp-modal .stp-input,.stp-modal .stp-select{width:100%!important;height:50px!important;border-radius:14px!important;border:1.5px solid #d7e4eb!important;background:#fff!important;color:#0b2f40!important;font-weight:850!important;padding:0 14px!important;box-shadow:none!important}
.stp-modal .stp-input:focus,.stp-modal .stp-select:focus{border-color:#0c6672!important;outline:4px solid rgba(12,102,114,.13)!important}.stp-modal .stp-input:invalid{border-color:#e56b70!important;outline:none!important}
.stp-modal .stp-live-preview{border:2px solid #cfe5ec!important;background:linear-gradient(145deg,#ffffff,#eef9fb)!important;border-radius:20px!important;padding:16px!important;box-shadow:0 10px 24px rgba(7,56,77,.07)!important}.stp-modal .stp-preview-pills span{background:#fff!important;color:#07384d!important;border-color:#dbe9ef!important}
.stp-step-card.active{border:2px solid #0c6672!important;background:#effbfc!important;box-shadow:0 10px 22px rgba(12,102,114,.12)!important}.stp-step-card.active i{background:#0c6672!important;color:#fff!important}
@media(max-width:980px){.stp-modal-card{height:min(92vh,820px)!important}.stp-modal-body{grid-template-columns:1fr!important}.stp-create-rail{grid-template-columns:repeat(3,1fr)!important}.stp-modal .stp-templates{grid-template-columns:repeat(2,minmax(0,1fr))!important}.stp-modal-foot{display:grid!important;grid-template-columns:1fr 1fr!important}.stp-modal-foot::before{grid-column:1/-1}.stp-create-submit{min-width:0!important;width:100%!important}}
@media(max-width:620px){.stp-modal{padding:8px!important}.stp-modal-card{width:calc(100vw - 16px)!important;height:94vh!important;border-radius:20px!important}.stp-modal-body{padding:14px!important}.stp-create-rail,.stp-modal .stp-choice,.stp-modal .stp-templates,.stp-modal .stp-form-grid{grid-template-columns:1fr!important}.stp-modal-head{padding:15px!important}.stp-modal-head h2{font-size:24px!important}.stp-modal-foot{grid-template-columns:1fr!important;padding:14px!important}.stp-modal-foot .stp-btn{width:100%!important}}

</style>
<div class="stp"><div class="stp-wrap">
    <header class="stp-top">
        <div class="stp-brand"><img class="stp-logo" src="<?= st_h($logoUrl) ?>" alt="School Manager"><div class="stp-title"><div class="stp-kicker">School Manager · IT center</div><h1>Stock TIC</h1><p>Fast control for spare parts and consumables without overloaded screens.</p></div></div>
        <div class="stp-actions"><a class="stp-btn ghost" href="<?= st_h($root) ?>/plugins/schoolmanager/front/panel_tic.php?v=286"><?= st_svg('chart') ?>Panel TIC</a><a class="stp-btn red" href="<?= st_h($root) ?>/plugins/schoolmanager/front/formularios.php?v=286"><?= st_svg('home') ?>Inicio</a><button class="stp-btn primary" type="button" id="openCreateStock"><?= st_svg('plus') ?>Nuevo stock</button></div>
    </header>

    <?php if ($msg !== ''): ?><div class="stp-msg ok"><?= st_h($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="stp-msg err"><?= st_h($err) ?></div><?php endif; ?>

    <section class="stp-metrics" aria-label="Resumen de stock">
        <div class="stp-metric"><span class="icon"><?= st_svg('box') ?></span><div><b><?= (int)$summary['items'] ?></b><span>articulos</span></div></div>
        <div class="stp-metric"><span class="icon"><?= st_svg('cart') ?></span><div><b><?= (int)$summary['available'] ?></b><span>disponibles</span></div></div>
        <div class="stp-metric warn"><span class="icon"><?= st_svg('alert') ?></span><div><b><?= (int)$summary['low'] ?></b><span>stock bajo</span></div></div>
        <div class="stp-metric danger"><span class="icon"><?= st_svg('minus') ?></span><div><b><?= (int)$summary['empty'] ?></b><span>sin stock</span></div></div>
        <div class="stp-metric"><span class="icon"><?= st_svg('filter') ?></span><div><b><?= count($items) ?></b><span>en vista</span></div></div>
    </section>

    <section class="stp-control">
        <div class="stp-tabs">
            <a class="stp-tab <?= $kind === 'consumable' ? 'active' : '' ?>" href="<?= st_h(st_url($baseUrl, ['kind'=>'consumable','v'=>'286'])) ?>"><span class="icon"><?= st_svg('cart') ?></span><span><strong>Consumibles</strong><small>Cables, ratones, teclados, adaptadores...</small></span></a>
            <a class="stp-tab <?= $kind === 'cartridge' ? 'active' : '' ?>" href="<?= st_h(st_url($baseUrl, ['kind'=>'cartridge','v'=>'286'])) ?>"><span class="icon"><?= st_svg('printer') ?></span><span><strong>Cartuchos</strong><small>Toner, tinta y repuestos de impresora.</small></span></a>
        </div>
        <form class="stp-filter" method="get" action="<?= st_h($baseUrl) ?>">
            <input type="hidden" name="kind" value="<?= st_h($kind) ?>"><input type="hidden" name="v" value="286">
            <div class="stp-field"><label><?= st_svg('search') ?>Buscar</label><input class="stp-input" id="stockSearch" type="search" name="q" value="<?= st_h($search) ?>" placeholder="Ej: HDMI, raton, toner, aula..."></div>
            <div class="stp-field"><label><?= st_svg('tag') ?>Categoria</label><select class="stp-select" name="type_id"><option value="0">Todas las categorias</option><?php foreach ($stockTypes as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $typeId===(int)$t['id']?'selected':'' ?>><?= st_h($t['name']) ?></option><?php endforeach; ?></select></div>
            <div class="stp-filter-actions"><button class="stp-btn primary" type="submit"><?= st_svg('filter') ?>Filtrar</button><a class="stp-btn" href="<?= st_h(st_url($baseUrl, ['kind'=>$kind,'v'=>'286'])) ?>">Limpiar</a></div>
        </form>
        <div class="stp-chips">
            <a class="stp-chip <?= $state==='all'?'active':'' ?>" href="<?= st_h(st_url($baseUrl, ['kind'=>$kind,'q'=>$search,'type_id'=>$typeId,'state'=>'all','v'=>'286'])) ?>">Todos <strong><?= $allCount ?></strong></a>
            <a class="stp-chip <?= $state==='ok'?'active':'' ?>" href="<?= st_h(st_url($baseUrl, ['kind'=>$kind,'q'=>$search,'type_id'=>$typeId,'state'=>'ok','v'=>'286'])) ?>">Correctos <strong><?= (int)$statusCounts['ok'] ?></strong></a>
            <a class="stp-chip <?= $state==='low'?'active':'' ?>" href="<?= st_h(st_url($baseUrl, ['kind'=>$kind,'q'=>$search,'type_id'=>$typeId,'state'=>'low','v'=>'286'])) ?>">Bajo <strong><?= (int)$statusCounts['low'] ?></strong></a>
            <a class="stp-chip <?= $state==='empty'?'active':'' ?>" href="<?= st_h(st_url($baseUrl, ['kind'=>$kind,'q'=>$search,'type_id'=>$typeId,'state'=>'empty','v'=>'286'])) ?>">Sin stock <strong><?= (int)$statusCounts['empty'] ?></strong></a>
            <a class="stp-chip" href="<?= st_h(st_url($baseUrl, ['kind'=>$kind,'q'=>$search,'type_id'=>$typeId,'state'=>$state,'export'=>'csv','v'=>'286'])) ?>"><?= st_svg('download') ?>CSV</a>
            <a class="stp-chip" href="<?= st_h($nativeUrl) ?>"><?= st_svg('external') ?>GLPI</a>
        </div>
    </section>

    <main class="stp-content">
        <section class="stp-list" id="stockList">
            <?php if (!$items): ?>
                <div class="stp-empty">No hay articulos para este filtro. Pulsa <strong>Nuevo stock</strong> para crear uno.</div>
            <?php else: foreach ($items as $row):
                $id = (int)($row['id'] ?? 0);
                $name = smgr_stock_item_display_name($kind, $row);
                $ref = trim((string)($row['ref'] ?? ''));
                $typeName = smgr_stock_item_type_name($kind, (int)($row[$typeFk] ?? 0));
                $available = (int)($row['_available'] ?? 0);
                $total = (int)($row['_total'] ?? 0);
                $threshold = (int)($row['_threshold'] ?? 0);
                $status = (string)($row['_status'] ?? 'ok');
                $pct = st_pct($available, max(1, $threshold));
                $nativeForm = $root . $cfg['native_form'] . '?id=' . $id;
                $dataSearch = strtolower($name . ' ' . $ref . ' ' . $typeName . ' ' . smgr_stock_status_label($status));
            ?>
            <article class="stp-item <?= st_h($status) ?>" data-search="<?= st_h($dataSearch) ?>">
                <div class="stp-name"><h3><?= st_h($name) ?></h3><div class="stp-meta"><span class="stp-pill <?= st_h($status) ?>"><?= st_h(smgr_stock_status_label($status)) ?></span><?php if ($typeName !== ''): ?><span class="stp-pill"><?= st_h($typeName) ?></span><?php endif; ?><?php if ($ref !== ''): ?><span class="stp-pill">Ref. <?= st_h($ref) ?></span><?php endif; ?><span class="stp-pill">#<?= $id ?></span></div></div>
                <div class="stp-stock"><div class="big"><b><?= $available ?></b><span>disponibles</span></div><div class="stp-bar"><i style="--w:<?= (int)$pct ?>%"></i></div><span><?= $total ?> historico · minimo <?= $threshold ?></span></div>
                <div class="stp-quick">
                    <form method="post" action="<?= st_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php"><?= st_token() ?><input type="hidden" name="kind" value="<?= st_h($kind) ?>"><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="return" value="list"><input type="hidden" name="note" value="Entrada rapida desde panel de stock"><input class="stp-qty" type="number" name="qty" min="1" max="200" value="1" aria-label="Unidades de entrada"><button class="stp-mini add" name="action" value="entrada" type="submit"><?= st_svg('plus') ?>Entrada</button></form>
                    <form method="post" action="<?= st_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php"><?= st_token() ?><input type="hidden" name="kind" value="<?= st_h($kind) ?>"><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="return" value="list"><input type="hidden" name="note" value="Salida rapida desde panel de stock"><input class="stp-qty" type="number" name="qty" min="1" max="200" value="1" aria-label="Unidades de salida"><button class="stp-mini out" name="action" value="salida" type="submit" <?= $available <= 0 ? 'disabled' : '' ?>><?= st_svg('minus') ?>Salida</button></form>
                </div>
                <div class="stp-item-tools"><a class="stp-btn primary" href="<?= st_h($root) ?>/plugins/schoolmanager/front/stock_item.php?kind=<?= st_h($kind) ?>&id=<?= $id ?>&v=286"><?= st_svg('edit') ?>Ver / editar</a><a class="stp-btn" href="<?= st_h($nativeForm) ?>"><?= st_svg('external') ?>GLPI</a></div>
            </article>
            <?php endforeach; endif; ?>
        </section>
        <aside class="stp-side">
            <div class="stp-side-card"><h3><?= st_svg('settings') ?>Quick actions</h3><p>Input adds new material. Output subtracts delivered units. View/edit opens detailed settings.</p></div>
            <div class="stp-side-card"><h3><?= st_svg('alert') ?>How to use it</h3><div class="stp-side-list"><div><span class="stp-dot"></span><span>Use clear names: USB mice, 2m HDMI cable, HP toner...</span></div><div><span class="stp-dot"></span><span>The minimum value marks the item as low stock.</span></div><div><span class="stp-dot"></span><span>Para cambios grandes usa Ver / editar y ajuste exacto.</span></div></div></div>
        </aside>
    </main>
</div></div>

<div class="stp-modal <?= $openCreateModal ? 'open' : '' ?>" id="createStockModal" aria-hidden="<?= $openCreateModal ? 'false' : 'true' ?>">
    <div class="stp-modal-card" role="dialog" aria-modal="true" aria-labelledby="createStockTitle">
        <div class="stp-modal-head">
            <div>
                <h2 id="createStockTitle"><?= st_svg('plus') ?> Nuevo stock TIC</h2>
                <p>Asistente claro: eliges el tipo, usas plantilla si quieres y creas el material en GLPI.</p>
            </div>
            <button class="stp-close" type="button" id="closeCreateStock" aria-label="Cerrar"><?= st_svg('x') ?></button>
        </div>
        <form method="post" action="<?= st_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php" id="createStockForm">
            <?= st_token() ?>
            <input type="hidden" name="action" value="crear_articulo">
            <input type="hidden" name="return" value="detail">
            <input type="hidden" name="kind" id="newKind" value="<?= st_h($kind) ?>">
            <div class="stp-modal-body">
                <aside class="stp-create-rail">
                    <div class="stp-step-card active"><i>1</i><div><b>Tipo</b><span>Consumible general o cartucho de impresora.</span></div></div>
                    <div class="stp-step-card"><i>2</i><div><b>Datos</b><span>Nombre, categoria y referencia para encontrarlo rapido.</span></div></div>
                    <div class="stp-step-card"><i>3</i><div><b>Stock</b><span>Unidades iniciales y minimo para aviso.</span></div></div>
                    <div class="stp-live-preview">
                        <h3 id="stockPreviewName">Nuevo articulo</h3>
                        <div class="stp-preview-pills">
                            <span id="stockPreviewKind"><?= $kind === 'cartridge' ? 'Cartucho' : 'Consumible' ?></span>
                            <span id="stockPreviewQty">1 unidad inicial</span>
                            <span id="stockPreviewMin">minimo 2</span>
                        </div>
                    </div>
                </aside>
                <main class="stp-create-main">
                    <section class="stp-section">
                        <div class="stp-section-title"><?= st_svg('box') ?> Tipo de stock</div>
                        <div class="stp-choice">
                            <button type="button" class="stp-kind kindChoice <?= $kind==='consumable'?'active':'' ?>" data-kind="consumable" aria-pressed="<?= $kind==='consumable'?'true':'false' ?>"><span><?= st_svg('cart') ?></span><strong>Consumible</strong><small>Cables, ratones, teclados, adaptadores, regletas...</small></button>
                            <button type="button" class="stp-kind kindChoice <?= $kind==='cartridge'?'active':'' ?>" data-kind="cartridge" aria-pressed="<?= $kind==='cartridge'?'true':'false' ?>"><span><?= st_svg('printer') ?></span><strong>Cartucho</strong><small>Toner, tinta, tambor y repuestos de impresora.</small></button>
                        </div>
                    </section>
                    <section class="stp-section">
                        <div class="stp-section-title"><?= st_svg('tag') ?> Plantillas rapidas</div>
                        <div class="stp-templates">
                            <button type="button" class="stp-template" data-name="Cable HDMI" data-ref="HDMI 2m" data-min="5" data-qty="5" data-note="Material para aulas y proyectores">Cable HDMI<small>Aulas / proyectores</small></button>
                            <button type="button" class="stp-template" data-name="Raton USB" data-ref="USB estandar" data-min="8" data-qty="10" data-note="Ratones de repuesto para aulas">Raton USB<small>Periferico</small></button>
                            <button type="button" class="stp-template" data-name="Teclado USB" data-ref="USB estandar" data-min="5" data-qty="6" data-note="Teclados de repuesto para aulas">Teclado USB<small>Periferico</small></button>
                            <button type="button" class="stp-template" data-name="Adaptador USB-C" data-ref="USB-C" data-min="4" data-qty="4" data-note="Adaptadores y conectividad">Adaptador<small>Conectividad</small></button>
                            <button type="button" class="stp-template" data-name="Toner impresora" data-ref="TONER" data-min="2" data-qty="2" data-note="Repuesto de impresora pendiente de asignar">Toner<small>Impresora</small></button>
                        </div>
                    </section>
                    <section class="stp-section">
                        <div class="stp-section-title"><?= st_svg('edit') ?> Datos del articulo</div>
                        <div class="stp-form-grid">
                            <div class="stp-field wide"><label>Nombre del articulo</label><input class="stp-input" name="item_name" id="newName" required placeholder="Ej: Raton USB Logitech"><div class="stp-help">Nombre corto y reconocible. Es lo que veras en el listado.</div></div>
                            <div class="stp-field"><label>Categoria existente</label><select class="stp-select" name="type_id" id="newType"><option value="0">Sin categoria</option><?php foreach ($stockTypes as $t): ?><option value="<?= (int)$t['id'] ?>"><?= st_h($t['name']) ?></option><?php endforeach; ?></select><div class="stp-help">Sirve para separar cables, perifericos, toners...</div></div>
                            <div class="stp-field"><label>O crear categoria nueva</label><input class="stp-input" name="category_new" id="newCategory" placeholder="Ej: Perifericos"><div class="stp-help">Opcional. Si lo rellenas, se crea automaticamente.</div></div>
                            <div class="stp-field"><label>Referencia / modelo</label><input class="stp-input" name="ref" id="newRef" placeholder="Ej: LOGI-M90"><div class="stp-help">Opcional, pero ayuda cuando hay modelos parecidos.</div></div>
                            <div class="stp-field"><label>Nota / ubicacion</label><input class="stp-input" name="comment" id="newNote" placeholder="Ej: Armario TIC"><div class="stp-help">Opcional: armario, aula o comentario interno.</div></div>
                        </div>
                    </section>
                    <section class="stp-section">
                        <div class="stp-section-title"><?= st_svg('chart') ?> Unidades y aviso</div>
                        <div class="stp-form-grid">
                            <div class="stp-field"><label>Unidades iniciales</label><input class="stp-input" type="number" min="0" max="200" name="initial_qty" id="newQty" value="1"><div class="stp-help">Cuantas unidades tienes ahora mismo.</div></div>
                            <div class="stp-field"><label>Minimo para aviso</label><input class="stp-input" type="number" min="0" max="999" name="threshold" id="newMin" value="2"><div class="stp-help">Cuando el stock llegue a este numero saldra como bajo.</div></div>
                        </div>
                    </section>
                </main>
            </div>
            <div class="stp-modal-foot">
                <button type="button" class="stp-btn" id="cancelCreateStock">Cancelar</button>
                <button type="submit" class="stp-btn primary stp-create-submit" id="createStockSubmit"><?= st_svg('plus') ?> Crear stock</button>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
  const modal=document.getElementById('createStockModal');
  const open=document.getElementById('openCreateStock');
  const close=document.getElementById('closeCreateStock');
  const cancel=document.getElementById('cancelCreateStock');
  function setModal(v){ if(!modal)return; modal.classList.toggle('open',v); modal.setAttribute('aria-hidden',v?'false':'true'); document.body.style.overflow=v?'hidden':''; if(v){ setTimeout(()=>document.getElementById('newName')?.focus(),90); } }
  open?.addEventListener('click',()=>setModal(true)); close?.addEventListener('click',()=>setModal(false)); cancel?.addEventListener('click',()=>setModal(false)); modal?.addEventListener('click',e=>{ if(e.target===modal)setModal(false); }); document.addEventListener('keydown',e=>{ if(e.key==='Escape')setModal(false); });
  document.querySelectorAll('.kindChoice').forEach(btn=>btn.addEventListener('click',()=>{ document.querySelectorAll('.kindChoice').forEach(b=>{b.classList.remove('active'); b.setAttribute('aria-pressed','false');}); btn.classList.add('active'); btn.setAttribute('aria-pressed','true'); const input=document.getElementById('newKind'); if(input)input.value=btn.dataset.kind||'consumable'; updateCreatePreview(); }));
  function updateCreatePreview(){ const name=document.getElementById('newName'), qty=document.getElementById('newQty'), min=document.getElementById('newMin'), kind=document.getElementById('newKind'); const pn=document.getElementById('stockPreviewName'), pk=document.getElementById('stockPreviewKind'), pq=document.getElementById('stockPreviewQty'), pm=document.getElementById('stockPreviewMin'); if(pn)pn.textContent=(name?.value||'Nuevo articulo'); if(pk)pk.textContent=(kind?.value==='cartridge'?'Cartucho':'Consumible'); if(pq)pq.textContent=(qty?.value||'0')+' unidad(es) iniciales'; if(pm)pm.textContent='minimo '+(min?.value||'0'); } function updateCreateButton(){ const name=(document.getElementById('newName')?.value||'').trim(); const submit=document.getElementById('createStockSubmit'); if(submit){ submit.disabled=!name; submit.title=name?'Crear este articulo en GLPI':'Pon primero el nombre del articulo'; } } document.querySelectorAll('.stp-template').forEach(btn=>btn.addEventListener('click',()=>{ document.querySelectorAll('.stp-template').forEach(b=>b.classList.remove('selected')); btn.classList.add('selected'); const name=document.getElementById('newName'), ref=document.getElementById('newRef'), min=document.getElementById('newMin'), qty=document.getElementById('newQty'), note=document.getElementById('newNote'); if(name)name.value=btn.dataset.name||''; if(ref)ref.value=btn.dataset.ref||''; if(min)min.value=btn.dataset.min||'2'; if(qty)qty.value=btn.dataset.qty||'1'; if(note)note.value=btn.dataset.note||''; updateCreatePreview(); updateCreateButton(); name?.focus(); })); ['newName','newQty','newMin'].forEach(id=>document.getElementById(id)?.addEventListener('input',()=>{updateCreatePreview(); updateCreateButton();})); updateCreatePreview(); updateCreateButton();
  const search=document.getElementById('stockSearch');
  function applySearch(){ const q=(search?.value||'').toLowerCase().trim(); let shown=0; document.querySelectorAll('#stockList .stp-item').forEach(card=>{ const ok=!q||(card.dataset.search||'').includes(q); card.classList.toggle('stp-hidden',!ok); if(ok)shown++; }); }
  search?.addEventListener('input',applySearch);
  document.querySelectorAll('form[method="post"]').forEach(f=>{ let clicked=null; f.querySelectorAll('button[name="action"]').forEach(btn=>btn.addEventListener('click',()=>{clicked=btn.value;})); f.addEventListener('submit',function(){ if(clicked&&!f.querySelector('input[name="_action"]')){ const h=document.createElement('input'); h.type='hidden'; h.name='_action'; h.value=clicked; f.appendChild(h); } const b=(clicked?Array.from(f.querySelectorAll('button[name="action"]')).find(x=>x.value===clicked):null)||f.querySelector('button[type="submit"]'); if(b){ b.style.opacity=.7; b.style.pointerEvents='none'; b.dataset.original=b.innerHTML; b.innerHTML='Procesando...'; } }); });
})();
</script>
<?php Html::footer(); ?>
