<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');
require_once(__DIR__ . '/../inc/stock_helpers.php');

$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$canAssets = function_exists('plugin_schoolmanager_can_create_asset') && plugin_schoolmanager_can_create_asset(null);
$canStock = function_exists('plugin_schoolmanager_can_manage_stock') ? plugin_schoolmanager_can_manage_stock() : $canAssets;
$canViewTechSummary = function_exists('smgr_can_manage_tic_assignments') ? smgr_can_manage_tic_assignments() : (function_exists('plugin_schoolmanager_is_admin_tic_user') && plugin_schoolmanager_is_admin_tic_user());
if (!$canStock) { Html::redirect($root . '/plugins/schoolmanager/front/formularios.php?v=286'); }

$kind = ($_GET['kind'] ?? 'consumable') === 'cartridge' ? 'cartridge' : 'consumable';
$id = (int)($_GET['id'] ?? 0);
$cfg = smgr_stock_kind_config($kind);
$item = smgr_stock_item_row($kind, $id);
$msg = trim((string)($_GET['msg'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));
$stockTypes = function_exists('smgr_stock_types') ? smgr_stock_types($kind) : [];
$units = $item ? smgr_stock_recent_units($kind, $id, 120) : [];

function pcsi_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pcsi_token() {
    if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
        static $tok = null; if ($tok === null) { $tok = Session::getNewCSRFToken(); } return '<input type="hidden" name="_glpi_csrf_token" value="' . pcsi_h($tok) . '">';
    }
    return '';
}
function pcsi_svg($name) {
    $icons = [
        'back' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73L13 2.27a2 2 0 0 0-2 0L4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>',
        'external' => '<path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/>',
        'chart' => '<path d="M4 19V5"/><path d="M4 19h17"/><path d="M8 16v-5"/><path d="M13 16V8"/><path d="M18 16v-3"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
    ];
    return '<svg class="sti" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? $icons['box']) . '</svg>';
}
function pcsi_pct($available, $threshold) {
    $scale = max(max(1,(int)$threshold) * 2, (int)$available, 1);
    return max(3, min(100, (int)round(((int)$available / $scale) * 100)));
}

function pcsi_render_comment($comment) {
    global $root;
    $comment = (string)$comment;
    if ($comment === '') { return ''; }
    $safe = pcsi_h($comment);
    $safe = preg_replace_callback('/\b(?:incidencia|ticket)\s*#(\d+)\b/i', function($m) use ($root) {
        $id = (int)$m[1];
        $label = 'incidencia #' . $id;
        $url = ($root ?? '') . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . $id . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
        return '<a class="sid-ticket-link" href="' . pcsi_h($url) . '">' . pcsi_h($label) . '</a>';
    }, $safe);
    $safe = preg_replace_callback('#/plugins/schoolmanager/front/solicitud_detalle\.php\?id=(\d+)#', function($m) use ($root) {
        $id = (int)$m[1];
        $url = ($root ?? '') . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . $id . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
        return '<a class="sid-ticket-link" href="' . pcsi_h($url) . '">abrir incidencia #' . $id . '</a>';
    }, $safe);
    return $safe;
}


function pcsi_user_display($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) { return ''; }
    if (function_exists('smgr_stock_user_display_name')) { return smgr_stock_user_display_name($userId); }
    if (class_exists('User')) {
        try { $u = new User(); if ($u->getFromDB($userId)) { $n = trim(((string)($u->fields['firstname'] ?? '')) . ' ' . ((string)($u->fields['realname'] ?? ''))); return $n !== '' ? $n : (string)($u->fields['name'] ?? ('Usuario #' . $userId)); } } catch (Throwable $e) {}
    }
    return 'Usuario #' . $userId;
}
function pcsi_tech_summary_url($userId) {
    global $root;
    return ($root ?? '') . '/plugins/schoolmanager/front/tecnico_resumen.php?id=' . (int)$userId . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
}
function pcsi_render_technician_cell(array $u, $canViewTechSummary) {
    $uid = function_exists('smgr_stock_unit_technician_id') ? smgr_stock_unit_technician_id($u) : (int)($u['users_id'] ?? 0);
    if ($uid <= 0) { return '<span class="sid-muted">Sin técnico</span>'; }
    $label = pcsi_user_display($uid);
    if ($canViewTechSummary) {
        return '<a class="sid-ticket-link" href="' . pcsi_h(pcsi_tech_summary_url($uid)) . '">' . pcsi_svg('user') . ' ' . pcsi_h($label) . '</a>';
    }
    return pcsi_h($label);
}
function pcsi_render_assigned_cell(array $u) {
    global $root;
    if (function_exists('smgr_stock_unit_ticket_id')) {
        $tid = smgr_stock_unit_ticket_id($u);
        if ($tid > 0) {
            $url = ($root ?? '') . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . $tid . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
            return '<a class="sid-ticket-link" href="' . pcsi_h($url) . '">Incidencia #' . (int)$tid . '</a>';
        }
    }
    return pcsi_h(pcsi_unit_assigned_label($u));
}

function pcsi_unit_out_datetime(array $u) {
    $use = trim((string)($u['date_use'] ?? ''));
    $out = trim((string)($u['date_out'] ?? ''));
    if ($use !== '' && $use !== '0000-00-00' && $use !== '0000-00-00 00:00:00') { return $use; }
    if ($out !== '' && $out !== '0000-00-00' && $out !== '0000-00-00 00:00:00') {
        $comment = (string)($u['comment'] ?? '');
        if (preg_match('/Fecha\/hora:\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/u', $comment, $m)) { return $m[1]; }
        return $out;
    }
    $comment = (string)($u['comment'] ?? '');
    if (preg_match('/Fecha\/hora:\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/u', $comment, $m)) { return $m[1]; }
    return '';
}

function pcsi_unit_assigned_label(array $u) {
    if (strcasecmp((string)($u['itemtype'] ?? ''), 'Ticket') === 0 && (int)($u['items_id'] ?? 0) > 0) {
        return 'Incidencia #' . (int)$u['items_id'];
    }
    if (!empty($u['itemtype']) || !empty($u['items_id'])) {
        return trim((string)($u['itemtype'] ?? 'Item') . ' #' . (int)($u['items_id'] ?? 0));
    }
    if (!empty($u['users_id'])) { return 'Usuario #' . (int)$u['users_id']; }
    return '';
}

function pcsi_render_unit_history(array $u) {
    global $root;
    $html = pcsi_render_comment((string)($u['comment'] ?? ''));
    if (strcasecmp((string)($u['itemtype'] ?? ''), 'Ticket') === 0 && (int)($u['items_id'] ?? 0) > 0) {
        $id = (int)$u['items_id'];
        $url = ($root ?? '') . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . $id . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
        $link = '<a class="sid-ticket-link" href="' . pcsi_h($url) . '">Usado en incidencia #' . $id . '</a>';
        if (strpos($html, 'incidencia #' . $id) === false) { $html = $link . ($html !== '' ? '<br>' . $html : ''); }
    }
    if ($html === '') { return '<span class="sid-muted">Sin historial</span>'; }
    return $html;
}

Html::header('Ficha de stock TIC', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo plugin_schoolmanager_home_button();
?>
<style>
html,body{height:auto!important;min-height:100%!important;overflow-y:auto!important}.page,.page-wrapper,.layout-wrapper,.content,.content-wrapper,.main-content{height:auto!important;min-height:0!important;overflow:visible!important}.sid{--navy:#07384d;--teal:#0c6672;--red:#b6252b;--gold:#e1a000;--ink:#0b2f40;--muted:#63798b;--line:#dbe9ef;--soft:#f5fafc;--ok:#168052;--shadow:0 14px 38px rgba(7,56,77,.08);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;min-height:100vh;padding:22px 28px 90px;background:linear-gradient(180deg,#f7fbfd 0%,#eef6f8 100%);color:var(--ink)}.sid *{box-sizing:border-box}.sti{width:18px;height:18px;display:inline-block;vertical-align:-4px;flex:0 0 auto}.sid-wrap{max-width:1420px;margin:0 auto;display:grid;gap:18px}.sid-top{background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:18px 20px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center}.sid-brand{display:flex;gap:18px;align-items:center;min-width:0}.sid-logo{width:130px;max-height:70px;object-fit:contain}.sid-kicker{font-size:12px;font-weight:950;letter-spacing:.14em;color:var(--red);text-transform:uppercase}.sid h1{margin:2px 0 4px;color:var(--navy);font-size:clamp(30px,4vw,50px);line-height:1;letter-spacing:-.035em}.sid p{margin:0;color:var(--muted);font-weight:800}.sid-actions,.sid-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.sid-actions{justify-content:flex-end}.sid-btn,.sid-mini{border:1px solid var(--line);background:#fff;color:var(--navy)!important;text-decoration:none!important;border-radius:14px;min-height:44px;padding:10px 14px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.15s ease;white-space:nowrap}.sid-btn:hover,.sid-mini:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(7,56,77,.10)}.sid-btn.primary,.sid-mini.add{background:linear-gradient(135deg,var(--teal),var(--navy));border-color:var(--navy);color:#fff!important}.sid-btn.red,.sid-mini.out{background:linear-gradient(135deg,#8b1e1e,var(--red));border-color:#8b1e1e;color:#fff!important}.sid-msg{border-radius:16px;padding:13px 16px;font-weight:900;border:1px solid}.sid-msg.ok{background:#eefaf2;color:#12663d;border-color:#bee8ce}.sid-msg.err{background:#fff0f0;color:#a6191f;border-color:#efc0c4}.sid-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.sid-metric{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 10px 26px rgba(7,56,77,.055);padding:14px 16px;min-height:72px}.sid-metric b{font-size:32px;color:var(--navy);line-height:1}.sid-metric span{display:block;color:var(--muted);font-weight:900;font-size:13px}.sid-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}.sid-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);padding:18px}.sid-card h2{margin:0 0 14px;color:var(--navy);display:flex;gap:8px;align-items:center}.sid-form{display:grid;gap:13px}.sid-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.sid-field label{display:block;margin:0 0 7px;font-size:13px;color:var(--navy);font-weight:950}.sid-input,.sid-select,.sid-textarea{width:100%;border:1px solid var(--line);border-radius:12px;background:#fff;color:#27394a;font-weight:800;padding:0 13px}.sid-input,.sid-select{height:44px}.sid-textarea{min-height:96px;padding-top:11px;resize:vertical}.sid-input:focus,.sid-select:focus,.sid-textarea:focus{outline:none;border-color:#75b7c3;box-shadow:0 0 0 4px rgba(12,102,114,.12)}.sid-help{font-size:12px;color:var(--muted);font-weight:800;margin-top:5px}.sid-stockbar{height:10px;border-radius:99px;background:#edf4f7;border:1px solid #dcebf0;overflow:hidden}.sid-stockbar i{display:block;height:100%;width:var(--w);background:linear-gradient(90deg,#0e8790,var(--navy));border-radius:99px}.sid-chip{border:1px solid var(--line);border-radius:999px;background:#f8fbfd;color:var(--muted);font-size:12px;font-weight:900;padding:5px 9px;display:inline-flex}.sid-chip.ok{color:#0d6d44;background:#edf9f2;border-color:#c7ead4}.sid-chip.low{color:#855f00;background:#fff7df;border-color:#efd384}.sid-chip.empty{color:#a6191f;background:#fff0f0;border-color:#efc1c6}.sid-side{display:grid;gap:12px}.sid-move{display:grid;gap:12px}.sid-move form{display:grid;gap:11px}.sid-move-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sid-mini{width:100%;border:0}.sid-table-wrap{overflow:auto}.sid-table{width:100%;border-collapse:separate;border-spacing:0 8px}.sid-table th{font-size:12px;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.05em}.sid-table td{background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:10px;font-weight:800;color:#314556}.sid-table td:first-child{border-left:1px solid var(--line);border-radius:12px 0 0 12px}.sid-table td:last-child{border-right:1px solid var(--line);border-radius:0 12px 12px 0}.sid-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.sid-toolbar button{border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--navy);padding:8px 11px;font-weight:900;cursor:pointer}.sid-empty{border:1px dashed #b8d5df;border-radius:18px;padding:24px;color:var(--muted);font-weight:900;text-align:center}@media(max-width:1050px){.sid-grid{grid-template-columns:1fr}.sid-metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:760px){.sid{padding:16px 14px 80px}.sid-top{grid-template-columns:1fr}.sid-actions{justify-content:flex-start}.sid-logo{width:90px}.sid-metrics,.sid-two,.sid-move-actions{grid-template-columns:1fr}.sid h1{font-size:32px}}
.sid-ticket-link{color:var(--teal)!important;font-weight:950;text-decoration:underline!important;text-underline-offset:3px;display:inline-flex;align-items:center;gap:6px}.sid-ticket-link .sti{width:15px;height:15px}.sid-muted{color:var(--muted);font-weight:850}</style>
<?php if (!$item): ?>
<div class="sid"><div class="sid-wrap"><div class="sid-msg err">Articulo no encontrado.</div><a class="sid-btn" href="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/stock_glpi.php?kind=<?= pcsi_h($kind) ?>&v=286"><?= pcsi_svg('back') ?>Volver al stock</a></div></div>
<?php else:
$row = smgr_stock_db_row_to_array($item);
$name = smgr_stock_item_display_name($kind, $row);
$available = smgr_stock_count_available($kind, $id);
$total = smgr_stock_count_total_units($kind, $id);
$threshold = smgr_stock_threshold_from_row($row);
$status = $available <= 0 ? 'empty' : (($threshold > 0 && $available <= $threshold) ? 'low' : 'ok');
$typeFk = smgr_stock_type_fk($kind);
$typeId = (int)($row[$typeFk] ?? 0);
$ref = (string)($row['ref'] ?? '');
$comment = (string)($row['comment'] ?? '');
$pct = pcsi_pct($available, max(1,$threshold));
$nativeForm = $root . $cfg['native_form'] . '?id=' . $id;
?>
<div class="sid"><div class="sid-wrap">
    <header class="sid-top"><div class="sid-brand"><img class="sid-logo" src="<?= pcsi_h($logoUrl) ?>" alt="School Manager"><div><div class="sid-kicker">Stock TIC · ficha</div><h1><?= pcsi_h($name) ?></h1><p>Editar datos, sumar, sacar o ajustar unidades.</p></div></div><div class="sid-actions"><a class="sid-btn" href="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/stock_glpi.php?kind=<?= pcsi_h($kind) ?>&v=286"><?= pcsi_svg('back') ?>Volver</a><a class="sid-btn" href="<?= pcsi_h($nativeForm) ?>"><?= pcsi_svg('external') ?>GLPI</a><a class="sid-btn red" href="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/formularios.php?v=286"><?= pcsi_svg('home') ?>Inicio</a></div></header>
    <?php if ($msg !== ''): ?><div class="sid-msg ok"><?= pcsi_h($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="sid-msg err"><?= pcsi_h($err) ?></div><?php endif; ?>
    <section class="sid-metrics"><div class="sid-metric"><b><?= $available ?></b><span>disponibles</span></div><div class="sid-metric"><b><?= max(0,$total-$available) ?></b><span>usadas</span></div><div class="sid-metric"><b><?= $total ?></b><span>historico</span></div><div class="sid-metric"><b><?= $threshold ?></b><span>minimo aviso</span></div></section>
    <section class="sid-grid">
        <main class="sid-card"><h2><?= pcsi_svg('edit') ?>Datos del articulo</h2><div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:14px"><span class="sid-chip <?= pcsi_h($status) ?>"><?= pcsi_h(smgr_stock_status_label($status)) ?></span><div style="flex:1"><div class="sid-stockbar"><i style="--w:<?= (int)$pct ?>%"></i></div></div></div><form class="sid-form" method="post" action="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php"><?= pcsi_token() ?><input type="hidden" name="kind" value="<?= pcsi_h($kind) ?>"><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="return" value="detail"><div class="sid-field"><label>Nombre</label><input class="sid-input" name="name" value="<?= pcsi_h($name) ?>"><div class="sid-help">Nombre claro para el equipo TIC.</div></div><div class="sid-two"><div class="sid-field"><label>Categoria</label><select class="sid-select" name="type_id"><option value="0">No category</option><?php foreach ($stockTypes as $tp): ?><option value="<?= (int)$tp['id'] ?>" <?= $typeId===(int)$tp['id']?'selected':'' ?>><?= pcsi_h($tp['name']) ?></option><?php endforeach; ?></select></div><div class="sid-field"><label>Referencia / modelo</label><input class="sid-input" name="ref" value="<?= pcsi_h($ref) ?>"></div></div><div class="sid-field"><label>Minimum warning level</label><input class="sid-input" type="number" min="0" max="999" name="threshold" value="<?= $threshold ?>"></div><div class="sid-field"><label>Notes internas</label><textarea class="sid-textarea" name="comment"><?= pcsi_h($comment) ?></textarea></div><button class="sid-btn primary" name="action" value="actualizar_articulo" type="submit"><?= pcsi_svg('save') ?>Guardar cambios</button></form></main>
        <aside class="sid-side"><div class="sid-card"><h2><?= pcsi_svg('plus') ?>Movimientos</h2><p style="margin-bottom:12px">Input adds units. Output subtracts units. Exact adjustment sets the final number.</p><form method="post" action="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php"><?= pcsi_token() ?><input type="hidden" name="kind" value="<?= pcsi_h($kind) ?>"><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="return" value="detail"><div class="sid-two"><div class="sid-field"><label>Quantity</label><input class="sid-input" type="number" min="1" max="200" name="qty" value="1"></div><div class="sid-field"><label>Note</label><input class="sid-input" name="note" placeholder="Example: classroom 003"></div></div><div class="sid-move-actions"><button class="sid-mini add" name="action" value="entrada" type="submit"><?= pcsi_svg('plus') ?>Input</button><button class="sid-mini out" name="action" value="salida" type="submit" <?= $available<=0?'disabled':'' ?>><?= pcsi_svg('minus') ?>Output</button></div></form><hr style="border:0;border-top:1px solid var(--line);margin:14px 0"><form class="sid-form" method="post" action="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php"><?= pcsi_token() ?><input type="hidden" name="kind" value="<?= pcsi_h($kind) ?>"><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="return" value="detail"><div class="sid-field"><label>Set exact available stock</label><input class="sid-input" type="number" min="0" max="999" name="target_qty" value="<?= $available ?>"></div><div class="sid-field"><label>Reason</label><input class="sid-input" name="note" placeholder="Inventory reviewed"></div><button class="sid-btn primary" name="action" value="ajustar" type="submit"><?= pcsi_svg('chart') ?>Apply adjustment</button></form></div><form class="sid-card" method="post" action="<?= pcsi_h($root) ?>/plugins/schoolmanager/front/stock_movimiento.php" onsubmit="return confirm('Archive este articulo?');"><?= pcsi_token() ?><input type="hidden" name="kind" value="<?= pcsi_h($kind) ?>"><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="return" value="list"><h2><?= pcsi_svg('alert') ?>Safe zone</h2><p style="margin-bottom:12px">Archive hides the item without modifying the rest of GLPI.</p><button class="sid-btn red" name="action" value="archivar" type="submit"><?= pcsi_svg('trash') ?>Archive</button></form></aside>
    </section>
    <section class="sid-card"><h2><?= pcsi_svg('box') ?>Unidades</h2><div class="sid-toolbar"><button type="button" data-unit="all">Todas</button><button type="button" data-unit="available">Disponibles</button><button type="button" data-unit="used">Usadas</button></div><?php if (!$units): ?><div class="sid-empty">Todavia no hay unidades registradas.</div><?php else: ?><div class="sid-table-wrap"><table class="sid-table"><thead><tr><th>ID</th><th>Estado</th><th>Input</th><th>Output/Uso exacta</th><th>Asignado</th><?php if ($canViewTechSummary): ?><th>Técnico</th><?php endif; ?><th>Historial / incidencia</th></tr></thead><tbody><?php foreach ($units as $u): $u=smgr_stock_db_row_to_array($u); $av=smgr_stock_unit_is_available($kind,$u); $out=pcsi_unit_out_datetime($u); ?><tr data-unit-state="<?= $av?'available':'used' ?>"><td>#<?= (int)($u['id'] ?? 0) ?></td><td><span class="sid-chip <?= $av?'ok':'empty' ?>"><?= $av?'Disponible':'Usada' ?></span></td><td><?= pcsi_h((string)($u['date_in'] ?? '')) ?></td><td><?= pcsi_h($out) ?></td><td><?= pcsi_render_assigned_cell($u) ?></td><?php if ($canViewTechSummary): ?><td><?= pcsi_render_technician_cell($u, $canViewTechSummary) ?></td><?php endif; ?><td><?= pcsi_render_unit_history($u) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div></div>
<script>
(function(){document.querySelectorAll('[data-unit]').forEach(b=>b.addEventListener('click',function(){const f=this.dataset.unit;document.querySelectorAll('[data-unit-state]').forEach(r=>{r.style.display=(f==='all'||r.dataset.unitState===f)?'':'none';});}));document.querySelectorAll('form[method="post"]').forEach(f=>{let clicked=null;f.querySelectorAll('button[name="action"]').forEach(btn=>btn.addEventListener('click',()=>{clicked=btn.value;}));f.addEventListener('submit',function(){if(clicked&&!f.querySelector('input[name="_action"]')){const h=document.createElement('input');h.type='hidden';h.name='_action';h.value=clicked;f.appendChild(h);}const b=(clicked?Array.from(f.querySelectorAll('button[name="action"]')).find(x=>x.value===clicked):null)||f.querySelector('button[type="submit"]');if(b){b.style.opacity=.7;b.style.pointerEvents='none';b.innerHTML='Procesando...';}});});})();
</script>
<?php endif; Html::footer(); ?>
