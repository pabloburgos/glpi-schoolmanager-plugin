<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');

if (!plugin_schoolmanager_can_update_asset(null)) {
    plugin_schoolmanager_access_denied_page('Gestión de activos restringida', 'Tu perfil no tiene permisos para modificar inventario.');
}

global $CFG_GLPI, $DB;
$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$allTypes = plugin_schoolmanager_asset_types();
$types = [];
foreach ($allTypes as $type => $info) {
    $tableOk = isset($DB) && method_exists($DB, 'tableExists') ? $DB->tableExists($info['table']) : true;
    if ($tableOk && plugin_schoolmanager_can_update_asset($type)) { $types[$type] = $info; }
}
if (!$types) { $types = array_intersect_key($allTypes, ['Computer'=>true, 'Monitor'=>true, 'Printer'=>true, 'NetworkEquipment'=>true, 'Peripheral'=>true]); }

$itemtype = plugin_schoolmanager_req('itemtype', 'Computer');
if (!isset($types[$itemtype])) { $itemtype = array_key_first($types); }
$q = plugin_schoolmanager_req('q');
$location = (int)plugin_schoolmanager_req('location', 0);
$onlyEmpty = plugin_schoolmanager_req('empty_location') === '1';
$sort = plugin_schoolmanager_req('sort', 'name');
if (!in_array($sort, ['name','location','id'], true)) { $sort = 'name'; }
$current = $types[$itemtype];
$locations = plugin_schoolmanager_location_rows_for_select();
$loadError = '';
$rows = [];

try {
    if (!isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists($current['table'])) {
        throw new RuntimeException('La tabla de este tipo de activo no existe en GLPI.');
    }
    $where = [];
    if (method_exists($DB, 'fieldExists') && $DB->fieldExists($current['table'], 'is_deleted')) { $where['is_deleted'] = 0; }
    if (method_exists($DB, 'fieldExists') && $DB->fieldExists($current['table'], 'entities_id')) {
        $entities = plugin_schoolmanager_safe_entities_where();
        if ($entities) { $where['entities_id'] = $entities; }
    }
    if ($location > 0) { $where['locations_id'] = $location; }
    if ($onlyEmpty) { $where['locations_id'] = 0; }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = ['OR' => [
            ['name' => ['LIKE', $like]],
            ['serial' => ['LIKE', $like]],
            ['otherserial' => ['LIKE', $like]],
            ['comment' => ['LIKE', $like]],
        ]];
    }
    $orderby = ['name ASC', 'id DESC'];
    if ($sort === 'location') { $orderby = ['locations_id ASC', 'name ASC', 'id ASC']; }
    if ($sort === 'id') { $orderby = ['id DESC']; }
    $it = $DB->request([
        'FROM' => $current['table'],
        'WHERE' => $where,
        'ORDERBY' => $orderby,
        'LIMIT' => 120,
    ]);
    foreach ($it as $row) { $rows[] = $row; }
} catch (Throwable $e) {
    $rows = [];
    $loadError = $e->getMessage();
}

if ($sort === 'location' && $rows) {
    usort($rows, static function($a, $b) {
        $la = plugin_schoolmanager_asset_location_label($a['locations_id'] ?? 0);
        $lb = plugin_schoolmanager_asset_location_label($b['locations_id'] ?? 0);
        $c = strnatcasecmp($la, $lb);
        if ($c !== 0) { return $c; }
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
}

Html::header('Gestión de activos', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
?>
<style id="gestion-schoolmanager-global-override"><?php @readfile(__DIR__ . '/../css/gestion-schoolmanager-theme.css'); ?></style>
<style>
.gsm-assets{--primary:#06384a;--primary2:#0b5265;--teal:#0f8f86;--gold:#d6a11d;--red:#b6252b;--red-dark:#8f1a22;--ink:#082f3f;--muted:#607386;--line:#d7e3e9;--soft:#f5f8fb;--cream:#fbf6ee;min-height:calc(100vh - 80px);padding:clamp(10px,1.4vw,22px);background:linear-gradient(180deg,#f7fafc 0%,#f2f6f9 100%);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
.gsm-assets *{box-sizing:border-box}
.gsm-wrap{max-width:1560px;margin:0 auto;display:grid;gap:16px}
.gsm-hero{position:relative;overflow:hidden;display:flex;justify-content:space-between;gap:22px;align-items:center;background:linear-gradient(135deg,#fbfcfd 0%,#ffffff 62%,#f6f9fb 100%);border:1px solid var(--line);border-radius:26px;padding:22px 24px;box-shadow:0 16px 42px rgba(7,56,77,.08)}
.gsm-hero:before{content:"";position:absolute;left:0;top:0;bottom:0;width:8px;background:linear-gradient(180deg,var(--gold),var(--red))}
.gsm-brand{position:relative;z-index:1;display:flex;align-items:center;gap:18px;min-width:0;flex:1 1 auto}
.gsm-logo{height:68px;max-width:210px;object-fit:contain;background:#fff;border:1px solid #e4ecef;border-radius:20px;padding:8px;box-shadow:0 10px 24px rgba(7,56,77,.08)}
.gsm-kicker{font-weight:900;color:var(--red);letter-spacing:.10em;text-transform:uppercase;font-size:12px;margin-bottom:2px}
.gsm-hero h1{margin:2px 0 0;font-size:clamp(34px,4vw,60px);line-height:.96;color:var(--primary)}
.gsm-hero p{margin:8px 0 0;color:var(--muted);font-weight:800;max-width:820px}
.gsm-hero-actions{position:relative;z-index:1;display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;align-items:center;flex:0 0 auto}
.gsm-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--line);border-radius:14px;padding:11px 16px;font-weight:900;text-decoration:none!important;cursor:pointer;white-space:nowrap;min-height:46px;transition:.16s ease}
.gsm-btn.primary{background:var(--primary);color:#fff!important;border-color:var(--primary)}
.gsm-btn.soft{background:#fff;color:var(--primary)!important}
.gsm-btn.gold{background:var(--red);color:#fff!important;border-color:var(--red)}
.gsm-btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(7,56,77,.08)}
.gsm-sections{display:grid;grid-template-columns:290px minmax(0,1fr);gap:16px;align-items:start}
.gsm-panel{background:#fff;border:1px solid var(--line);border-radius:24px;padding:18px;box-shadow:0 10px 30px rgba(7,56,77,.06)}
.gsm-panel h2{margin:0 0 8px;color:var(--primary);font-size:24px;line-height:1.12;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.gsm-panel small{color:var(--muted);font-weight:800;display:block;line-height:1.45}
.gsm-type-list{display:grid;gap:10px;margin-top:16px}
.gsm-type{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:18px;padding:13px 14px;text-decoration:none!important;color:var(--primary)!important;font-weight:900;background:#fff;transition:.14s ease;min-height:74px}
.gsm-type:hover{transform:translateX(3px);border-color:#bfcfd8;box-shadow:0 12px 28px rgba(7,56,77,.08)}
.gsm-type.active{background:linear-gradient(135deg,var(--primary),var(--primary2));color:#fff!important;border-color:var(--primary)}
.gsm-type b{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:14px;background:#f4f6f8;color:var(--red);border:1px solid #e5eaee}
.gsm-type.active b{background:#fff;color:var(--red);border-color:#fff}
.gsm-type span:last-child{line-height:1.2}
.gsm-filter{display:grid;grid-template-columns:minmax(240px,1.45fr) minmax(180px,.95fr) minmax(160px,.8fr) minmax(135px,.7fr) auto auto;gap:12px;align-items:end;margin:16px 0 14px}
.gsm-field{min-width:0}
.gsm-field span{display:block;font-weight:900;color:var(--primary);margin:0 0 6px}
.gsm-input,.gsm-select{width:100%;border:1px solid var(--line);border-radius:14px;padding:12px 14px;font-weight:800;background:#fff;color:var(--ink);min-height:48px}
.gsm-input::placeholder{color:#7a8c96}
.gsm-check{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:14px;padding:12px 14px;background:#fff;font-weight:900;color:var(--primary);min-height:48px}
.gsm-check input{flex:0 0 auto}
.gsm-results-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:4px 0 12px;flex-wrap:wrap}
.gsm-results-head h2{margin:0;color:var(--primary);font-size:22px}
.gsm-count{background:#fff6f6;border:1px solid #ebc7c9;border-radius:999px;padding:8px 12px;font-weight:900;color:var(--red-dark)}
.gsm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.gsm-group{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:4px 0 0;padding:12px 14px;border-radius:18px;background:#fff7f7;border:1px solid #ebc7c9;font-weight:900;color:var(--red-dark)}
.gsm-group .left{display:flex;align-items:center;gap:10px;min-width:0}
.gsm-group b{display:grid;place-items:center;min-width:34px;height:34px;border-radius:12px;background:#fff;color:var(--red);border:1px solid #ebc7c9}
.gsm-card{display:grid;gap:10px;border:1px solid var(--line);border-radius:22px;padding:16px;background:#fff;text-decoration:none!important;color:var(--ink)!important;transition:.16s ease;min-width:0}
.gsm-card:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(7,56,77,.1);border-color:#c5d3da}
.gsm-card-top{display:flex;gap:12px;align-items:flex-start;min-width:0}
.gsm-asset-icon{display:grid;place-items:center;flex:0 0 50px;width:50px;height:50px;border-radius:16px;background:#f6f7f8;color:var(--red);font-size:24px;border:1px solid #e7ecef}
.gsm-card-title{min-width:0;flex:1 1 auto}
.gsm-card h3{margin:0;color:var(--primary);font-size:20px;line-height:1.12;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gsm-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:6px}
.gsm-chip{display:inline-flex;align-items:center;gap:5px;border:1px solid var(--line);border-radius:999px;background:#f8fafb;padding:5px 9px;font-weight:850;color:var(--primary2);font-size:12px;line-height:1.2}
.gsm-chip.warn{background:#fff8ea;border-color:#edd59c;color:#7c5700}
.gsm-chip.loc{background:#fff7f7;border-color:#ebc7c9;color:var(--red-dark)}
.gsm-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.gsm-mini-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:9px 12px;font-weight:900;text-decoration:none!important;min-height:40px}
.gsm-mini-btn.edit{background:var(--primary);color:#fff!important}
.gsm-mini-btn.native{background:#fff;border:1px solid var(--line);color:var(--primary)!important}
.gsm-empty{border:1px dashed var(--line);border-radius:20px;padding:28px;text-align:center;background:#fff;color:var(--muted);font-weight:900}
.gsm-tools{display:grid;gap:10px;margin-top:14px}
.gsm-tool{border:1px solid var(--line);border-radius:16px;padding:12px 14px;background:#fff;color:var(--primary);font-weight:900;text-decoration:none!important;line-height:1.35;transition:.14s ease}
.gsm-tool:hover{border-color:#c5d3da;background:#fafcfd;box-shadow:0 10px 24px rgba(7,56,77,.06)}
@media(max-width:1360px){.gsm-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.gsm-filter{grid-template-columns:minmax(220px,1.2fr) minmax(170px,1fr) minmax(150px,.8fr) minmax(135px,.7fr) auto auto}}
@media(max-width:1120px){.gsm-hero{align-items:flex-start;flex-direction:column}.gsm-hero-actions{justify-content:flex-start;width:100%}.gsm-sections{grid-template-columns:1fr}.gsm-type-list{grid-template-columns:repeat(2,minmax(0,1fr))}.gsm-filter{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:820px){.gsm-assets{padding:10px}.gsm-hero{padding:18px}.gsm-brand{align-items:flex-start}.gsm-logo{height:56px;max-width:170px}.gsm-grid,.gsm-type-list{grid-template-columns:1fr}.gsm-filter{grid-template-columns:1fr}.gsm-group{align-items:flex-start}.gsm-card h3{white-space:normal;overflow:visible;text-overflow:clip}.gsm-card-actions,.gsm-hero-actions{display:grid;grid-template-columns:1fr}.gsm-btn,.gsm-mini-btn{width:100%}.gsm-panel{padding:16px}}
@media(max-width:520px){.gsm-hero h1{font-size:42px}.gsm-panel h2{font-size:22px}.gsm-card-top{align-items:flex-start}.gsm-meta{gap:6px}.gsm-group{padding:12px}.gsm-group .left{width:100%}}
</style>

<?php
function gsm_icon_svg($name, $extraClass = '') {
    $extraClass = trim((string)$extraClass);
    $cls = 'gsm-inline-icon' . ($extraClass !== '' ? ' ' . $extraClass : '');
    $attrs = 'class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"';
    $icons = [
        'home' => '<path d="M3.75 10.5 12 4.25l8.25 6.25"/><path d="M6.75 9.75v9.5h10.5v-9.5"/><path d="M10 19.25V14.5a2 2 0 0 1 4 0v4.75"/>',
        'list' => '<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'map' => '<path d="M9 18 3.5 20.5v-15L9 3l6 2.5 5.5-2.5v15L15 20.5 9 18Z"/><path d="M9 3v15M15 5.5v15"/>',
        'location' => '<path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.4"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/>',
        'reset' => '<path d="M4 7h10a6 6 0 1 1-5.6 8.2"/><path d="M4 7l3-3M4 7l3 3"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5Z"/>',
        'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/>',
        'external' => '<path d="M14 5h5v5"/><path d="M10 14 19 5"/><path d="M19 13v5H5V4h5"/>',
        'computer' => '<rect x="3" y="4" width="12.8" height="9.4" rx="1.8"/><path d="M7.2 17.7h4.4M9.4 13.4v4.3"/><rect x="17.2" y="6.2" width="3.8" height="10.8" rx="1.2"/><path d="M18.5 14.2h1.2M5.3 20h13.4"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="12" rx="2.4"/><path d="M8 21h8M12 16v5"/>',
        'printer' => '<path d="M7 8V4h10v4"/><rect x="6" y="14" width="12" height="6" rx="1.6"/><path d="M6 17H4.8A1.8 1.8 0 0 1 3 15.2V10a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5.2a1.8 1.8 0 0 1-1.8 1.8H18"/><path d="M17 11h.01"/>',
        'network' => '<rect x="4" y="10.5" width="16" height="7.5" rx="2.2"/><path d="M7.5 14.2h.01M10.5 14.2h.01M13.5 14.2h.01M16.5 14.2h.01"/><path d="M8 7.8a6.2 6.2 0 0 1 8 0"/><path d="M10.2 9.8a3 3 0 0 1 3.6 0"/>',
        'keyboard' => '<rect x="3" y="6" width="18" height="12" rx="2.2"/><path d="M7 10h.01M11 10h.01M15 10h.01M17 14H7"/>',
    ];
    $body = $icons[$name] ?? $icons['computer'];
    return '<svg ' . $attrs . '>' . $body . '</svg>';
}

function gsm_asset_icon_html($type, $fallbackClass, $extraClass = '') {
    $map = [
        'Computer' => 'computer',
        'Monitor' => 'monitor',
        'Printer' => 'printer',
        'NetworkEquipment' => 'network',
        'Peripheral' => 'keyboard',
        'Projector' => 'monitor',
    ];
    return gsm_icon_svg($map[$type] ?? 'computer', $extraClass);
}
?>

<style id="v278-gestion-activos-clean">
/* v278 limpio: estilos finales antes del HTML, sin bloques antiguos por debajo */
.gsm-assets{background:linear-gradient(180deg,#f7fbfc 0%,#f2f6f8 100%)!important;overflow-x:hidden!important;}
.gsm-assets .gsm-wrap{gap:14px!important;max-width:1540px!important;width:100%!important;}
.gsm-assets .gsm-hero{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;align-items:center!important;gap:22px!important;padding:22px 28px!important;border-radius:24px!important;background:linear-gradient(135deg,#fff 0%,#fbfdfe 78%,#fff8ea 100%)!important;border:1px solid #d9e7ed!important;box-shadow:0 12px 30px rgba(7,56,77,.06)!important;overflow:hidden!important;}
.gsm-assets .gsm-hero:before,.gsm-assets .gsm-hero:after{display:none!important;content:none!important;}
.gsm-assets .gsm-brand{display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;align-items:center!important;gap:22px!important;min-width:0!important;}
.gsm-assets .gsm-logo{height:76px!important;max-width:200px!important;object-fit:contain!important;background:transparent!important;border:0!important;box-shadow:none!important;filter:none!important;padding:0!important;margin:0!important;}
.gsm-assets .gsm-kicker{margin:0 0 4px!important;font-size:12px!important;letter-spacing:.12em!important;line-height:1.1!important;color:#b6252b!important;-webkit-text-fill-color:#b6252b!important;font-weight:950!important;}
.gsm-assets .gsm-hero h1{margin:0 0 6px!important;font-size:clamp(40px,4vw,58px)!important;line-height:.94!important;letter-spacing:-.052em!important;color:#07384d!important;max-width:720px!important;text-shadow:none!important;}
.gsm-assets .gsm-hero p{margin:0!important;max-width:620px!important;color:#18394a!important;font-size:15px!important;line-height:1.28!important;font-weight:850!important;}
.gsm-assets .smn-actions-v265{display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:12px!important;flex-wrap:nowrap!important;min-width:0!important;position:relative!important;z-index:20!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn{height:48px!important;min-height:48px!important;padding:0 18px!important;border-radius:15px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;font-size:15.5px!important;font-weight:950!important;line-height:1!important;letter-spacing:-.01em!important;text-decoration:none!important;white-space:nowrap!important;border:1px solid transparent!important;box-shadow:none!important;position:relative!important;overflow:hidden!important;transform:none!important;transition:transform .18s cubic-bezier(.2,.8,.2,1),box-shadow .18s ease,background-color .18s ease,border-color .18s ease,color .18s ease!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn::before{display:none!important;content:none!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn::after{content:""!important;position:absolute!important;inset:0!important;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.09) 36%,rgba(255,255,255,.22) 50%,rgba(255,255,255,.09) 64%,transparent 100%)!important;transform:translateX(-145%)!important;transition:transform .42s ease!important;pointer-events:none!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn:hover{transform:translateY(-3px)!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn:hover::after{transform:translateX(145%)!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn:active{transform:translateY(-1px) scale(.985)!important;}
.gsm-assets .smn-actions-v265 .smn-action-btn span{position:relative!important;z-index:2!important;color:inherit!important;line-height:1!important;}
.gsm-assets .smn-actions-v265 .smn-action-svg,.gsm-assets .smn-actions-v265 svg.gsm-inline-icon{display:block!important;width:18px!important;height:18px!important;min-width:18px!important;flex:0 0 18px!important;color:currentColor!important;stroke:currentColor!important;fill:none!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0!important;opacity:1!important;visibility:visible!important;overflow:visible!important;position:relative!important;z-index:2!important;stroke-width:2.35!important;stroke-linecap:round!important;stroke-linejoin:round!important;transform:none!important;}
.gsm-assets .smn-action-home{background:#a92323!important;color:#fff!important;border-color:#951e1e!important;box-shadow:0 12px 26px rgba(169,35,35,.20)!important;}
.gsm-assets .smn-action-home:hover{background:#bb2b2b!important;border-color:#a92323!important;box-shadow:0 18px 34px rgba(169,35,35,.28)!important;color:#fff!important;}
.gsm-assets .smn-action-list{background:#fff!important;color:#a92323!important;border-color:#efc4c7!important;box-shadow:0 9px 20px rgba(169,35,35,.07)!important;}
.gsm-assets .smn-action-list:hover{background:#a92323!important;color:#fff!important;border-color:#a92323!important;box-shadow:0 16px 30px rgba(169,35,35,.18)!important;}
.gsm-assets .smn-action-primary{background:#07384d!important;color:#fff!important;border-color:#07384d!important;box-shadow:0 12px 26px rgba(7,56,77,.17)!important;}
.gsm-assets .smn-action-primary:hover{background:#0a5268!important;border-color:#0a5268!important;box-shadow:0 18px 34px rgba(7,56,77,.24)!important;color:#fff!important;}
.gsm-assets .gsm-sections{grid-template-columns:260px minmax(0,1fr)!important;gap:14px!important;align-items:start!important;}
.gsm-assets .gsm-panel{border-radius:22px!important;padding:16px!important;box-shadow:0 9px 24px rgba(7,56,77,.052)!important;overflow:hidden!important;min-width:0!important;}
.gsm-assets .gsm-panel h2{font-size:23px!important;margin-bottom:8px!important;}
.gsm-assets .gsm-type-list{grid-template-columns:1fr!important;gap:10px!important;}
.gsm-assets .gsm-type{min-height:64px!important;padding:11px 12px!important;border-radius:16px!important;gap:11px!important;}
.gsm-assets .gsm-type b{width:48px!important;height:48px!important;flex:0 0 48px!important;border-radius:15px!important;background:linear-gradient(135deg,#f8fbfa,#fff8e6)!important;color:#07384d!important;border-color:#d7e6ec!important;box-shadow:0 8px 18px rgba(7,56,77,.06)!important;}
.gsm-assets .gsm-type.active{background:linear-gradient(135deg,#b6252b,#951923)!important;border-color:#b6252b!important;color:#fff!important;}
.gsm-assets .gsm-type.active b{background:#fff!important;color:#b6252b!important;border-color:#fff!important;}
.gsm-assets .gsm-type b .gsm-inline-icon{width:28px!important;height:28px!important;min-width:28px!important;display:block!important;stroke:currentColor!important;fill:none!important;}
.gsm-assets .gsm-asset-icon{background:linear-gradient(135deg,#07384d,#0f8f86)!important;color:#fff!important;border:0!important;box-shadow:0 10px 24px rgba(7,56,77,.14)!important;}
.gsm-assets .gsm-asset-icon .gsm-inline-icon{width:30px!important;height:30px!important;min-width:30px!important;stroke:currentColor!important;}
.gsm-assets .gsm-tool{display:flex!important;align-items:center!important;gap:10px!important;}
.gsm-assets .gsm-tool .gsm-inline-icon{width:20px!important;height:20px!important;min-width:20px!important;color:#07384d!important;stroke:currentColor!important;}
.gsm-assets .gsm-filter{gap:10px!important;margin-top:14px!important;}
.gsm-assets .gsm-input,.gsm-assets .gsm-select,.gsm-assets .gsm-check{min-height:46px!important;border-radius:13px!important;}
.gsm-assets .gsm-filter .gsm-btn{height:48px!important;min-height:48px!important;border-radius:14px!important;}
.gsm-assets .gsm-card{border-color:#d7e3ea!important;box-shadow:0 8px 22px rgba(7,56,77,.045)!important;outline:none!important;}
.gsm-assets .gsm-card:hover,.gsm-assets .gsm-card:focus,.gsm-assets .gsm-card:focus-within{border-color:#c6d7df!important;box-shadow:0 14px 30px rgba(7,56,77,.08)!important;}
.gsm-assets .gsm-card:before,.gsm-assets .gsm-card:after,.gsm-assets .gsm-type:before,.gsm-assets .gsm-type:after{display:none!important;content:none!important;}
@media(max-width:1220px){.gsm-assets .gsm-hero{grid-template-columns:1fr!important;gap:18px!important}.gsm-assets .smn-actions-v265{justify-content:flex-start!important;flex-wrap:wrap!important;width:100%!important}.gsm-assets .gsm-sections{grid-template-columns:1fr!important}.gsm-assets .gsm-type-list{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
@media(max-width:900px){.gsm-assets .gsm-brand{grid-template-columns:1fr!important;gap:12px!important}.gsm-assets .gsm-logo{height:68px!important;max-width:175px!important}.gsm-assets .gsm-hero h1{font-size:clamp(38px,11vw,50px)!important}.gsm-assets .gsm-type-list{grid-template-columns:1fr!important}}
@media(max-width:680px){.gsm-assets .gsm-hero{padding:18px!important;border-radius:20px!important}.gsm-assets .smn-actions-v265{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}.gsm-assets .smn-actions-v265 .smn-action-btn{width:100%!important;height:48px!important;min-height:48px!important}.gsm-assets .gsm-grid,.gsm-assets .gsm-filter{grid-template-columns:1fr!important}.gsm-assets .gsm-btn,.gsm-assets .gsm-mini-btn{width:100%!important}}
</style>


<style id="v281-gestion-activos-icons-fix">
/* v281: evita iconos gigantes y compacta la cabecera de gestión */
.gsm-assets svg.gsm-inline-icon{
  width:22px!important;
  height:22px!important;
  min-width:22px!important;
  max-width:34px!important;
  max-height:34px!important;
  display:inline-block!important;
  flex:0 0 auto!important;
  stroke:currentColor!important;
  fill:none!important;
  overflow:visible!important;
  vertical-align:-.16em!important;
}
.gsm-assets .gsm-panel h2{
  display:flex!important;
  align-items:center!important;
  gap:10px!important;
  line-height:1.1!important;
}
.gsm-assets .gsm-panel h2 > svg.gsm-inline-icon{
  width:25px!important;
  height:25px!important;
  min-width:25px!important;
  max-width:25px!important;
  max-height:25px!important;
  color:#07384d!important;
  stroke-width:2.35!important;
}
.gsm-assets .gsm-chip svg.gsm-inline-icon{
  width:14px!important;
  height:14px!important;
  min-width:14px!important;
  max-width:14px!important;
  max-height:14px!important;
}
.gsm-assets .gsm-btn svg.gsm-inline-icon,
.gsm-assets .gsm-mini-btn svg.gsm-inline-icon{
  width:17px!important;
  height:17px!important;
  min-width:17px!important;
  max-width:17px!important;
  max-height:17px!important;
}
.gsm-assets .gsm-type b svg.gsm-inline-icon{
  width:28px!important;
  height:28px!important;
  min-width:28px!important;
  max-width:28px!important;
  max-height:28px!important;
}
.gsm-assets .gsm-asset-icon svg.gsm-inline-icon{
  width:30px!important;
  height:30px!important;
  min-width:30px!important;
  max-width:30px!important;
  max-height:30px!important;
}
.gsm-assets .smn-actions-v265{
  display:grid!important;
  grid-template-columns:repeat(3,minmax(150px,1fr))!important;
  gap:12px!important;
  width:min(650px,100%)!important;
}
.gsm-assets .smn-actions-v265 .smn-action-btn{
  width:100%!important;
  height:50px!important;
  min-height:50px!important;
  border-radius:16px!important;
  padding:0 17px!important;
}
.gsm-assets .smn-actions-v265 .smn-action-svg,
.gsm-assets .smn-actions-v265 svg.gsm-inline-icon{
  width:18px!important;
  height:18px!important;
  min-width:18px!important;
  max-width:18px!important;
  max-height:18px!important;
}
@media(max-width:1220px){.gsm-assets .smn-actions-v265{grid-template-columns:repeat(3,minmax(140px,1fr))!important;width:100%!important}}
@media(max-width:760px){.gsm-assets .smn-actions-v265{grid-template-columns:1fr!important}.gsm-assets .gsm-panel h2{font-size:24px!important}}
</style>

<div class="gsm-assets"><div class="gsm-wrap">
  <section class="gsm-hero">
    <div class="gsm-brand"><img class="gsm-logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo"><div><div class="gsm-kicker">GLPI SCHOOL MANAGER · INVENTARIO</div><h1>Gestión de activos</h1><p>Inventario ordenado por tipo, aula y ficha nativa de GLPI.</p></div></div>
    <div class="gsm-hero-actions smn-actions-v265">
      <a class="smn-action-btn smn-action-home" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/formularios.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>" aria-label="Inicio"><?= gsm_icon_svg('home', 'smn-action-svg') ?><span>Inicio</span></a>
      <a class="smn-action-btn smn-action-list" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/aulas.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>" aria-label="Lista de aulas"><?= gsm_icon_svg('list', 'smn-action-svg') ?><span>Lista de aulas</span></a>
      <a class="smn-action-btn smn-action-primary" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/nuevo_activo.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>" aria-label="Alta guiada"><?= gsm_icon_svg('plus', 'smn-action-svg') ?><span>Alta guiada</span></a>
    </div>
  </section>
  <section class="gsm-sections">
    <aside class="gsm-panel"><h2>Tipo de activo</h2><small>Elige qué parte del inventario quieres modificar.</small><div class="gsm-type-list">
      <?php foreach ($types as $type => $info): ?>
        <a class="gsm-type <?= $type === $itemtype ? 'active' : '' ?>" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/gestion_activos.php?itemtype=' . urlencode($type) . '&sort=' . urlencode($sort) . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><b><?= gsm_asset_icon_html($type, $info['icon']) ?></b><span><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></span></a>
      <?php endforeach; ?>
    </div><div class="gsm-tools"><a class="gsm-tool" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/selector.php?building=<?= urlencode(plugin_schoolmanager_default_building_code()) ?>&floor=<?= urlencode(plugin_schoolmanager_default_floor_code(plugin_schoolmanager_default_building_code())) ?>&mode=normal&v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= gsm_icon_svg('map') ?> Abrir plano de clases</a><a class="gsm-tool" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/aulas.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= gsm_icon_svg('list') ?> Lista de aulas</a><a class="gsm-tool" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/front/location.php"><?= gsm_icon_svg('location') ?> Ubicaciones nativas</a></div></aside>
    <main class="gsm-panel"><h2><?= gsm_asset_icon_html($itemtype, $current['icon']) ?> <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></h2><small>Filtra por nombre, inventario, número de serie, comentario o aula.</small>
      <form method="get" class="gsm-filter"><input type="hidden" name="itemtype" value="<?= htmlspecialchars($itemtype, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="v" value="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>"><label class="gsm-field"><span>Buscar</span><input class="gsm-input" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre, serie, inventario, comentario..."></label><label class="gsm-field"><span>Aula</span><select class="gsm-select" name="location"><option value="0">Todas las aulas</option><?php foreach ($locations as $lid => $loc): ?><option value="<?= (int)$lid ?>" <?= $location === (int)$lid ? 'selected' : '' ?>><?= htmlspecialchars($loc['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label class="gsm-field"><span>Ordenar</span><select class="gsm-select" name="sort"><option value="name" <?= $sort==='name'?'selected':'' ?>>Por nombre</option><option value="location" <?= $sort==='location'?'selected':'' ?>>Por aula</option><option value="id" <?= $sort==='id'?'selected':'' ?>>Más recientes</option></select></label><label class="gsm-check"><input type="checkbox" name="empty_location" value="1" <?= $onlyEmpty ? 'checked' : '' ?>> Sin ubicación</label><button class="gsm-btn primary" type="submit"><?= gsm_icon_svg('search') ?><span>Buscar</span></button><a class="gsm-btn soft" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/gestion_activos.php?itemtype=' . urlencode($itemtype) . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><?= gsm_icon_svg('reset') ?><span>Limpiar</span></a></form>
      <div class="gsm-results-head"><h2>Resultados</h2><span class="gsm-count"><?= count($rows) ?> encontrados</span></div>
      <?php if (!empty($loadError)): ?><div class="gsm-empty">No se pudieron cargar los activos: <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php elseif (!$rows): ?><div class="gsm-empty">No hay resultados con estos filtros. Prueba a limpiar la búsqueda o cambiar de tipo de activo.</div><?php else: ?><div class="gsm-grid">
        <?php $lastLoc = null; foreach ($rows as $row): $id=(int)$row['id']; $title=plugin_schoolmanager_asset_clean_title($itemtype,$row); $locId=(int)($row['locations_id'] ?? 0); $loc=plugin_schoolmanager_asset_location_label($locId); $serial=trim((string)($row['serial'] ?? '')); $inv=trim((string)($row['otherserial'] ?? '')); if ($sort === 'location' && $lastLoc !== $loc): $lastLoc = $loc; ?><div class="gsm-group"><div class="left"><b><?= gsm_icon_svg('location') ?></b><span><?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?></span></div><?php if ($locId > 0): ?><a class="gsm-mini-btn native" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/detalle_aula.php?id=' . $locId . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><?= gsm_icon_svg('eye') ?><span>Detalles del aula</span></a><?php endif; ?></div><?php endif; ?>
          <article class="gsm-card"><div class="gsm-card-top"><span class="gsm-asset-icon"><?= gsm_asset_icon_html($itemtype, $current['icon']) ?></span><div class="gsm-card-title"><h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3><div class="gsm-meta"><span class="gsm-chip">ID <?= $id ?></span><span class="gsm-chip loc <?= ($loc === 'Sin ubicación') ? 'warn' : '' ?>"><?= gsm_icon_svg('location') ?> <?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?></span><?php if ($inv): ?><span class="gsm-chip">Inv. <?= htmlspecialchars($inv, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?><?php if ($serial): ?><span class="gsm-chip">S/N <?= htmlspecialchars($serial, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div></div></div><div class="gsm-card-actions"><a class="gsm-mini-btn edit" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/editar_activo.php?itemtype=' . urlencode($itemtype) . '&id=' . $id . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><?= gsm_icon_svg('edit') ?><span>Modificar</span></a><a class="gsm-mini-btn native" href="<?= htmlspecialchars($root . $current['native'] . '?id=' . $id, ENT_QUOTES, 'UTF-8') ?>"><?= gsm_icon_svg('external') ?><span>Vista nativa</span></a><?php if ($locId > 0): ?><a class="gsm-mini-btn native" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/detalle_aula.php?id=' . $locId . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><?= gsm_icon_svg('location') ?><span>Aula</span></a><?php endif; ?></div></article>
        <?php endforeach; ?>
      </div><?php endif; ?>
    </main>
  </section>
</div></div>


<?php Html::footer(); ?>




