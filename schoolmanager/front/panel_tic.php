<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');
require_once(__DIR__ . '/../inc/stock_helpers.php');

$userId = (int)Session::getLoginUserID();
$canAll = function_exists('smgr_can_view_all_tic_tickets') ? smgr_can_view_all_tic_tickets() : smgr_is_super_admin_user();
$canAssign = function_exists('smgr_can_manage_tic_assignments') ? smgr_can_manage_tic_assignments() : smgr_is_super_admin_user();
$isAdminTic = function_exists('smgr_user_has_admin_tic_profile') && smgr_user_has_admin_tic_profile($userId);
$isTicTech = function_exists('smgr_user_has_tecnico_tic_profile') && smgr_user_has_tecnico_tic_profile($userId);
$canStock = function_exists('plugin_schoolmanager_can_manage_stock') ? plugin_schoolmanager_can_manage_stock() : ($canAll || $isTicTech);
if (!$canAll && !$isTicTech && plugin_schoolmanager_user_mode() !== 'tecnico') {
    plugin_schoolmanager_access_denied_page('Panel TIC restringido', 'Este panel esta pensado para el equipo TIC.');
}

$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$message = '';
$messageType = 'ok';

function smgr_panel_csrf_field() {
    if (method_exists('Session', 'getNewCSRFToken')) {
        static $tok = null; if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . smgr_h($tok) . '">';
    }
}

function smgr_panel_age_label($date) {
    $ts = strtotime((string)$date);
    if (!$ts) { return 'Sin fecha'; }
    $diff = time() - $ts;
    if ($diff < 3600) { return 'hace ' . max(1, (int)floor($diff / 60)) . ' min'; }
    if ($diff < 86400) { return 'hace ' . (int)floor($diff / 3600) . ' h'; }
    return 'hace ' . (int)floor($diff / 86400) . ' d';
}

function smgr_panel_assignees_label($assignees) {
    if (!$assignees) { return 'Sin asignar'; }
    $names = array_map(static fn($a) => trim((string)($a['name'] ?? '')), $assignees);
    $names = array_values(array_filter($names));
    return $names ? implode(', ', array_slice($names, 0, 3)) : 'Asignado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['pc_action'] ?? '');
    $ticketId = (int)($_POST['ticket_id'] ?? 0);

    if ($action === 'reply_ticket') {
        $content = trim((string)($_POST['reply_content'] ?? ''));
        if (!smgr_can_manage_ticket($ticketId, $userId)) {
            $message = 'No puedes responder este ticket desde el panel. Los tecnicos solo pueden tocar tickets asignados.';
            $messageType = 'error';
        } else {
            [$ok, $msg] = smgr_add_ticket_followup($ticketId, $content, 0);
            $message = $msg;
            $messageType = $ok ? 'ok' : 'error';
        }
    } elseif ($action === 'assign_ticket') {
        $techId = (int)($_POST['tech_id'] ?? 0);
        if (!$canAssign) {
            $message = 'Solo Admin TIC o Super-Admin puede reasignar tickets desde este panel.';
            $messageType = 'error';
        } else {
            [$ok, $msg] = smgr_assign_ticket_to_user($ticketId, $techId, true);
            $message = $msg;
            $messageType = $ok ? 'ok' : 'error';
        }
    } elseif ($action === 'solve_ticket') {
        $solution = trim((string)($_POST['solution_content'] ?? ''));
        $ticketStatus = 0;
        try {
            $tmpTicket = new Ticket();
            if ($tmpTicket->getFromDB($ticketId)) { $ticketStatus = (int)($tmpTicket->fields['status'] ?? 0); }
        } catch (Throwable $e) {}
        if ($ticketStatus >= 5) {
            $message = 'Esta incidencia ya está resuelta y archivada. No se puede volver a resolver ni modificar desde el panel TIC.';
            $messageType = 'error';
        } elseif (!smgr_can_manage_ticket($ticketId, $userId)) {
            $message = 'No puedes resolver este ticket desde el panel. Los tecnicos solo pueden tocar tickets asignados.';
            $messageType = 'error';
        } else {
            $stockValues = $_POST['stock_item'] ?? [];
            $stockQtys = $_POST['stock_qty'] ?? [];
            if (!is_array($stockValues)) { $stockValues = [$stockValues]; }
            if (!is_array($stockQtys)) { $stockQtys = [$stockQtys]; }
            $usedStock = [];
            $stockError = '';
            foreach ($stockValues as $idx => $stockValue) {
                $stockValue = trim((string)$stockValue);
                if ($stockValue === '') { continue; }
                $qty = isset($stockQtys[$idx]) ? (int)$stockQtys[$idx] : 1;
                [$stockOk, $stockMsg] = smgr_stock_consume_for_ticket($stockValue, $qty, $ticketId, 'Resolución de incidencia');
                if (!$stockOk) { $stockError = $stockMsg; break; }
                if ($stockMsg !== '') { $usedStock[] = $stockMsg; }
            }
            if ($stockError !== '') {
                $message = $stockError;
                $messageType = 'error';
            } else {
                if ($usedStock) {
                    $solution .= "\n\nMaterial utilizado:\n- " . implode("\n- ", $usedStock);
                }
                [$ok, $msg] = smgr_solve_ticket($ticketId, $solution);
                $message = $ok ? ($usedStock ? 'Ticket marcado como resuelto. Material descontado correctamente.' : $msg) : $msg;
                $messageType = $ok ? 'ok' : 'error';
            }
        }
    }
}

[$allTickets, $loadError] = smgr_fetch_tickets(1400, false);
$tickets = [];
foreach ($allTickets as $t) {
    $id = (int)($t['id'] ?? 0);
    $assignees = smgr_ticket_assignees($id);
    $t['assignees'] = $assignees;
    $t['assigned_to_me'] = smgr_is_ticket_assigned_to_user($id, $userId);
    $t['can_touch'] = $canAll || !empty($t['assigned_to_me']);
    if ($canAll || !empty($t['assigned_to_me'])) { $tickets[] = $t; }
}
$techs = $canAssign ? smgr_fetch_assignable_technicians() : [];
$stockSummary = function_exists('smgr_stock_summary') ? smgr_stock_summary() : ['items'=>0,'units'=>0,'low'=>0,'empty'=>0,'consumables'=>0,'cartridges'=>0];
$stockOptions = function_exists('smgr_stock_selectable_items') ? array_slice(smgr_stock_selectable_items(true), 0, 7) : [];

$aulasData = require(__DIR__ . '/../inc/aulas_data.php');
$shortLocById = [];
foreach ($aulasData as $aa) {
    if (!empty($aa['id'])) {
        $buildingMeta = function_exists('plugin_schoolmanager_building') ? plugin_schoolmanager_building((string)($aa['building'] ?? '')) : null;
        $buildingLabel = is_array($buildingMeta) && function_exists('plugin_schoolmanager_label') ? plugin_schoolmanager_label($buildingMeta, 'name', (string)($aa['building'] ?? '')) : (string)($aa['building'] ?? '');
        $shortLocById[(int)$aa['id']] = trim($buildingLabel . ' · ' . ($aa['aula'] ?? ''), ' ·');
    }
}

$now = time();
$stats = ['total'=>0,'open'=>0,'wait'=>0,'solved'=>0,'high'=>0,'today'=>0,'assigned'=>0,'unassigned'=>0,'mine'=>0,'old'=>0];
$byCat = [];
$byLoc = [];
$assigned = [];
$critical = [];
$recent = [];
$unassigned = [];
$old = [];
$wait = [];
$solvedTickets = [];
$openTickets = [];

foreach ($tickets as $t) {
    $stats['total']++;
    $st = (int)($t['status'] ?? 0);
    $p = (int)($t['priority'] ?? 3);
    $id = (int)($t['id'] ?? 0);
    $isOpen = $st < 5;
    if ($isOpen) { $stats['open']++; $openTickets[] = $t; }
    if (!empty($t['assigned_to_me']) && $isOpen) { $stats['mine']++; $assigned[] = $t; }
    if (!$t['assignees'] && $isOpen) { $stats['unassigned']++; $unassigned[] = $t; }
    if ($st == 4) { $stats['wait']++; $wait[] = $t; }
    if ($st >= 5) { $stats['solved']++; $solvedTickets[] = $t; }
    if ($isOpen && $p >= 4) { $stats['high']++; $critical[] = $t; }
    $day = substr((string)($t['date'] ?? ''), 0, 10);
    if ($day === date('Y-m-d')) { $stats['today']++; }
    $created = strtotime((string)($t['date'] ?? ''));
    if ($isOpen && $created && ($now - $created) > 172800) { $stats['old']++; $old[] = $t; }
    $cat = trim((string)($t['category_name'] ?? '')) ?: 'Sin categoria';
    $locId = (int)($t['locations_id'] ?? 0);
    $loc = $shortLocById[$locId] ?? smgr_short_location_name($t['location_name'] ?: 'Sin ubicacion', 'Sin ubicacion');
    if ($isOpen) {
        $byCat[$cat] = ($byCat[$cat] ?? 0) + 1;
        $byLoc[$loc] = ($byLoc[$loc] ?? 0) + 1;
    }
    $recent[] = $t;
}
arsort($byCat);
arsort($byLoc);
$openTickets = array_slice($openTickets, 0, 120);
$assigned = array_slice($assigned, 0, 80);
$critical = array_slice($critical, 0, 60);
$recent = array_slice($recent, 0, 80);
$unassigned = array_slice($unassigned, 0, 80);
$old = array_slice($old, 0, 50);
$wait = array_slice($wait, 0, 50);
$solvedTickets = array_slice($solvedTickets, 0, 80);
$mainTickets = $canAll ? $openTickets : $assigned;

Html::header('Centro de control TIC', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');

if (!function_exists('smgr_tic_icon')) {
    function smgr_tic_icon($name, $label = '') {
        $icons = [
            'home' => '<path d="M3.8 10.6 12 4.3l8.2 6.3"/><path d="M6.8 9.8v9.7h10.4V9.8"/><path d="M10 19.5v-5a2 2 0 0 1 4 0v5"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'shield' => '<path d="M12 3.8 19 7v5.2c0 4.4-2.8 7.4-7 8.8-4.2-1.4-7-4.4-7-8.8V7l7-3.2Z"/><path d="M9.5 12.2 11.3 14l3.5-4"/>',
            'ticket' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5V10a2 2 0 0 0 0 4v2.5a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5V14a2 2 0 0 0 0-4V7.5Z"/><path d="M13 5v14"/>',
            'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/>',
            'userplus' => '<path d="M15 19a5 5 0 0 0-10 0"/><circle cx="10" cy="8" r="3"/><path d="M19 8v6M16 11h6"/>',
            'fire' => '<path d="M12 22a7 7 0 0 0 7-7c0-5-7-9-7-13 0 4-7 8-7 13a7 7 0 0 0 7 7Z"/><path d="M12 22a3 3 0 0 0 3-3c0-2-3-4-3-6 0 2-3 4-3 6a3 3 0 0 0 3 3Z"/>',
            'chart' => '<path d="M4 19V5M4 19h16"/><path d="M8 16v-5M12 16V8M16 16v-8"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'alert' => '<path d="M12 4 21 20H3L12 4Z"/><path d="M12 9v5M12 17h.01"/>',
            'list' => '<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
            'bolt' => '<path d="M13 2 4 14h7l-1 8 10-13h-7l1-7Z"/>',
            'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6c.36-.13.7-.34 1-.6.3-.3.4-.7.4-1.1V3a2 2 0 1 1 4 0v.09c0 .4.1.8.4 1.1.3.26.64.47 1 .6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.13.36.34.7.6 1 .3.3.7.4 1.1.4H21a2 2 0 1 1 0 4h-.09c-.4 0-.8.1-1.1.4-.26.3-.47.64-.6 1Z"/>',
            'box' => '<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'send' => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/>',
            'refresh' => '<path d="M21 12a9 9 0 0 1-15.5 6.2"/><path d="M3 12A9 9 0 0 1 18.5 5.8"/><path d="M3 19v-5h5M21 5v5h-5"/>',
            'map' => '<path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15M15 6v15"/>',
            'cpu' => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3"/>',
        ];
        $body = $icons[$name] ?? $icons['ticket'];
        $aria = $label !== '' ? ' role="img" aria-label="' . smgr_h($label) . '"' : ' aria-hidden="true"';
        return '<svg class="tic-ico"' . $aria . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
    }
}
?>
<style id="v310-panel-tic-command">
.ticdesk{--ink:#062f43;--blue:#07384d;--blue2:#075d75;--cyan:#0c7891;--red:#a92025;--gold:#eda400;--ok:#138355;--muted:#647887;--line:#d6e5ea;--card:#ffffff;--soft:#f2f8fa;min-height:calc(100vh - 76px);padding:clamp(12px,1.4vw,24px);background:radial-gradient(circle at 16% 10%,rgba(12,120,145,.13),transparent 28%),radial-gradient(circle at 88% 18%,rgba(237,164,0,.14),transparent 30%),linear-gradient(135deg,#f7fbfc 0%,#eef7f6 65%,#fff8e8 100%);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
.ticdesk *{box-sizing:border-box}.ticdesk a{text-decoration:none!important}.ticdesk .wrap{max-width:1780px;margin:0 auto;display:grid;gap:16px}.tic-ico{width:20px;height:20px;min-width:20px;display:inline-block;vertical-align:-.18em;flex:0 0 20px;overflow:visible;stroke:currentColor!important;fill:none!important}.ticdesk .hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;background:linear-gradient(135deg,#fff 0%,#f9fcfd 72%,#fff8ea 100%);border:1px solid var(--line);border-radius:30px;padding:22px 24px;box-shadow:0 18px 50px rgba(7,56,77,.08)}.ticdesk .hero:after{content:"";position:absolute;right:-80px;top:-80px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(12,120,145,.13),transparent 68%)}.ticdesk .brand{display:flex;align-items:center;gap:18px;min-width:0;position:relative;z-index:1}.ticdesk .logo{width:176px;height:82px;object-fit:contain;mix-blend-mode:multiply}.ticdesk .k{font-weight:1000;color:var(--red);letter-spacing:.14em;font-size:12px;text-transform:uppercase}.ticdesk h1{margin:2px 0;color:var(--blue);font-size:clamp(36px,4vw,72px);line-height:.9;letter-spacing:-.06em}.ticdesk .sub{margin:8px 0 0;color:var(--muted);font-size:17px;font-weight:850}.ticdesk .actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;position:relative;z-index:1}.ticdesk .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:48px;border-radius:17px;border:1px solid var(--line);background:#fff;color:var(--blue)!important;font-weight:1000;padding:0 16px;box-shadow:0 10px 22px rgba(7,56,77,.055);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}.ticdesk .btn:hover{transform:translateY(-2px);box-shadow:0 16px 30px rgba(7,56,77,.11);border-color:#b8d3da}.ticdesk .btn.primary{background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff!important;border-color:var(--blue)}.ticdesk .btn.red{background:linear-gradient(135deg,var(--red),#c23338);color:#fff!important;border-color:var(--red)}.ticdesk .btn.gold{background:#fff7df;border-color:#f0d181;color:#805500!important}.ticdesk .btn.small{min-height:39px;padding:0 12px;border-radius:14px;font-size:13px}.ticdesk .mode{display:inline-flex;gap:9px;align-items:center;min-height:48px;border-radius:17px;background:#edf8f6;border:1px solid #c5e4df;color:var(--blue);font-weight:1000;padding:0 16px}.ticdesk .alerts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ticdesk .alertTile{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:16px;display:flex;align-items:center;gap:13px;box-shadow:0 13px 30px rgba(7,56,77,.06)}.ticdesk .alertTile b{font-size:34px;line-height:1;color:var(--blue);letter-spacing:-.05em}.ticdesk .alertTile span{display:block;color:var(--muted);font-weight:950}.ticdesk .alertTile .bubble{width:46px;height:46px;border-radius:16px;display:grid;place-items:center;background:#eef8fb;color:var(--blue)}.ticdesk .alertTile.danger{border-color:#f1c2c5;background:#fff7f7}.ticdesk .alertTile.danger b,.ticdesk .alertTile.danger .bubble{color:var(--red)}.ticdesk .alertTile.warn{border-color:#f0d68d;background:#fffaf0}.ticdesk .alertTile.warn b,.ticdesk .alertTile.warn .bubble{color:#a86e00}.ticdesk .alertTile.ok{border-color:#cde8da;background:#f7fcf9}.ticdesk .alertTile.ok b,.ticdesk .alertTile.ok .bubble{color:var(--ok)}.ticdesk .msg{border-radius:19px;border:1px solid #cce5d8;background:#f4fbf7;color:#16603b;padding:13px 15px;font-weight:950;display:flex;gap:10px;align-items:center}.ticdesk .msg.error{border-color:#f1c2c5;background:#fff5f5;color:var(--red)}.ticdesk .cockpit{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:16px}.ticdesk .panel{background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:26px;padding:18px;box-shadow:0 14px 34px rgba(7,56,77,.065)}.ticdesk .panelHead{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}.ticdesk h2{margin:0;color:var(--blue);font-size:28px;letter-spacing:-.035em;display:flex;align-items:center;gap:10px}.ticdesk .hint{color:var(--muted);font-weight:850;margin-top:3px}.ticdesk .tools{display:flex;gap:9px;flex-wrap:wrap}.ticdesk .search{min-width:min(520px,100%);flex:1;display:flex;align-items:center;gap:10px;border:1px solid var(--line);background:#f9fcfd;border-radius:18px;padding:0 14px;height:50px}.ticdesk .search input{border:0;background:transparent;outline:0;width:100%;height:100%;font-weight:900;color:var(--ink);font-size:15px}.ticdesk .chips{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 15px}.ticdesk .chip{border:1px solid var(--line);background:#fff;color:var(--blue);min-height:42px;border-radius:999px;padding:0 14px;font-weight:1000;display:inline-flex;gap:8px;align-items:center;cursor:pointer}.ticdesk .chip.active{background:var(--red);border-color:var(--red);color:#fff}.ticdesk .chip b{background:rgba(255,255,255,.25);border-radius:999px;padding:2px 8px}.ticdesk .ticketList{display:grid;gap:11px;max-height:770px;overflow:auto;padding-right:4px}.ticdesk .ticket{border:1px solid var(--line);background:linear-gradient(135deg,#fff,#f8fcfd);border-radius:22px;padding:15px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease}.ticdesk .ticket:hover{transform:translateY(-2px);border-color:#a9d0da;box-shadow:0 16px 32px rgba(7,56,77,.09)}.ticdesk .ticketMain{min-width:0}.ticdesk .ticketTitle{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.ticdesk .ticketTitle strong{font-size:18px;color:var(--blue);line-height:1.15}.ticdesk .idtag{background:#edf8fb;border:1px solid #c7e2e9;color:var(--blue);border-radius:999px;padding:4px 9px;font-weight:1000}.ticdesk .meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}.ticdesk .pill{display:inline-flex;align-items:center;gap:6px;min-height:28px;border-radius:999px;border:1px solid var(--line);background:#fff;color:#506a7a;font-size:12px;font-weight:950;padding:0 9px}.ticdesk .pill.high,.ticdesk .pill.veryhigh{border-color:#f1c2c5;background:#fff3f3;color:var(--red)}.ticdesk .pill.medium{border-color:#f0d68d;background:#fffaf0;color:#8a6100}.ticdesk .pill.status{border-color:#c7e2e9;background:#eff9fb;color:var(--blue2)}.ticdesk .ticketActions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;max-width:430px}.ticdesk .assignForm{display:flex;gap:7px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.ticdesk select,.ticdesk textarea,.ticdesk input[type="number"]{border:1px solid var(--line);background:#fff;border-radius:14px;color:var(--ink);font-weight:900;min-height:39px;padding:0 10px}.ticdesk .selectTech{max-width:190px}.ticdesk .empty{border:1px dashed #c8dce3;background:#f9fcfd;border-radius:18px;padding:24px;text-align:center;color:var(--muted);font-weight:950}.ticdesk .hidden{display:none!important}.ticdesk .side{display:grid;gap:16px;align-content:start}.ticdesk .quickGrid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ticdesk .quick{border:1px solid var(--line);background:#fff;border-radius:19px;padding:13px;display:flex;gap:10px;align-items:center;color:var(--blue)!important;font-weight:1000}.ticdesk .quick .bubble{width:40px;height:40px;border-radius:14px;background:#eef8fb;display:grid;place-items:center;color:var(--blue)}.ticdesk .bars{display:grid;gap:10px}.ticdesk .barRow{display:grid;grid-template-columns:minmax(0,1fr) 42px;gap:9px;align-items:center}.ticdesk .barName{font-weight:950;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ticdesk .barTrack{grid-column:1 / -1;height:10px;border-radius:99px;background:#edf3f5;overflow:hidden}.ticdesk .barFill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--blue2),var(--cyan))}.ticdesk .stockLine{display:flex;justify-content:space-between;align-items:center;border:1px solid var(--line);border-radius:16px;background:#fff;padding:10px 12px;gap:10px}.ticdesk .stockLine b{color:var(--blue)}.ticdesk .stockLine small{color:var(--muted);font-weight:900}.ticdesk .modal{position:fixed;inset:0;background:rgba(7,56,77,.44);backdrop-filter:blur(4px);z-index:9999;display:none;place-items:center;padding:18px}.ticdesk .modal.show{display:grid}.ticdesk .dialog{width:min(760px,100%);background:#fff;border:1px solid var(--line);border-radius:26px;padding:18px;box-shadow:0 24px 90px rgba(0,0,0,.22)}.ticdesk .dialog h2{font-size:30px;margin-bottom:12px}.ticdesk .dialog textarea{width:100%;min-height:150px;padding:12px 14px;resize:vertical}.ticdesk .dialogActions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin-top:12px}.ticdesk .stockPick{display:grid;grid-template-columns:minmax(0,1fr) 88px;gap:8px;margin-top:8px}.ticdesk .stockPick select{width:100%}.ticdesk .stockPick input{width:88px}.ticdesk .noResults{display:none}.ticdesk.no-matches .noResults{display:block}@media(max-width:1320px){.ticdesk .cockpit{grid-template-columns:1fr}.ticdesk .side{grid-template-columns:repeat(2,minmax(0,1fr))}.ticdesk .alerts{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:860px){.ticdesk .hero{grid-template-columns:1fr}.ticdesk .actions{justify-content:flex-start}.ticdesk .brand{align-items:flex-start}.ticdesk .logo{width:132px;height:62px}.ticdesk .ticket{grid-template-columns:1fr}.ticdesk .ticketActions{justify-content:flex-start;max-width:none}.ticdesk .selectTech{max-width:100%;width:100%}.ticdesk .side{grid-template-columns:1fr}.ticdesk .panelHead{display:block}.ticdesk .tools{margin-top:10px}.ticdesk .search{min-width:0}.ticdesk .quickGrid{grid-template-columns:1fr}}@media(max-width:560px){.ticdesk{padding:8px}.ticdesk .hero,.ticdesk .panel{border-radius:20px;padding:14px}.ticdesk .brand{display:grid}.ticdesk h1{font-size:42px}.ticdesk .alerts{grid-template-columns:1fr}.ticdesk .actions{display:grid;grid-template-columns:1fr}.ticdesk .btn,.ticdesk .mode{width:100%}.ticdesk .chips{display:grid;grid-template-columns:1fr 1fr}.ticdesk .chip{justify-content:center}.ticdesk .ticketActions,.ticdesk .assignForm{display:grid;grid-template-columns:1fr;width:100%}}
</style>
<div class="ticdesk" id="ticdesk"><div class="wrap">
<section class="hero">
  <div class="brand"><img class="logo" src="<?= smgr_h($logoUrl) ?>" alt="Logo"><div><div class="k">GLPI SCHOOL MANAGER</div><h1>Centro de control TIC</h1><p class="sub"><?= $canAll ? 'Puesto de mando para incidencias, asignaciones, reglas y material TIC.' : 'Tu puesto de trabajo: tickets asignados, material y prioridades.' ?></p></div></div>
  <div class="actions"><span class="mode"><?= smgr_tic_icon('shield') ?><span><?= $isAdminTic ? 'Admin TIC' : ($canAll ? 'Super-Admin' : 'Técnico TIC') ?></span></span><?php if ($canAssign): ?><a class="btn" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/asignaciones_tic.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= smgr_tic_icon('gear') ?><span>Reglas TIC</span></a><?php endif; ?><?php if ($canStock): ?><a class="btn" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/stock_glpi.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= smgr_tic_icon('box') ?><span>Stock TIC</span></a><?php endif; ?><a class="btn primary" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/nueva_incidencia.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= smgr_tic_icon('plus') ?><span>Nueva incidencia</span></a><a class="btn red" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/formularios.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= smgr_tic_icon('home') ?><span>Inicio</span></a></div>
</section>

<?php if ($message): ?><div class="msg <?= $messageType === 'error' ? 'error' : '' ?>"><?= smgr_tic_icon($messageType === 'error' ? 'alert' : 'check') ?><span><?= smgr_h($message) ?></span></div><?php endif; ?>
<?php if ($loadError): ?><div class="msg error"><?= smgr_tic_icon('alert') ?><span>No se pudo cargar el panel: <?= smgr_h($loadError) ?></span></div><?php endif; ?>
<?php if ($canAssign && !$techs): ?><div class="msg error"><?= smgr_tic_icon('alert') ?><span>No se detectan técnicos TIC asignables. Revisa que exista un perfil con nombre tipo <b>Técnico TIC</b>, <b>Soporte TIC</b> o un usuario activo relacionado con TIC.</span></div><?php endif; ?>

<section class="alerts">
  <div class="alertTile"><span class="bubble"><?= smgr_tic_icon('ticket') ?></span><div><b><?= (int)$stats['open'] ?></b><span>tickets abiertos</span></div></div>
  <div class="alertTile danger"><span class="bubble"><?= smgr_tic_icon('userplus') ?></span><div><b><?= (int)$stats['unassigned'] ?></b><span>sin técnico</span></div></div>
  <div class="alertTile warn"><span class="bubble"><?= smgr_tic_icon('fire') ?></span><div><b><?= (int)$stats['high'] ?></b><span>prioridad alta</span></div></div>
  <div class="alertTile ok"><span class="bubble"><?= smgr_tic_icon('box') ?></span><div><b><?= (int)$stockSummary['units'] ?></b><span>unidades en stock</span></div></div>
</section>

<section class="cockpit">
  <main class="panel">
    <div class="panelHead"><div><h2><?= smgr_tic_icon('cpu') ?> Mesa TIC</h2><div class="hint">Filtra, asigna y gestiona incidencias desde una vista rápida.</div></div><div class="tools"><label class="search"><?= smgr_tic_icon('search') ?><input id="ticSearch" type="search" placeholder="Buscar por aula, título, categoría, técnico, prioridad..."></label><button class="btn small" type="button" id="ticCompact"><?= smgr_tic_icon('list') ?>Compacto</button></div></div>
    <div class="chips" role="tablist">
      <button class="chip active" type="button" data-tab="main"><?= $canAll ? 'Abiertos' : 'Mis tickets' ?> <b><?= count($mainTickets) ?></b></button>
      <?php if ($canAssign): ?><button class="chip" type="button" data-tab="unassigned">Sin asignar <b><?= count($unassigned) ?></b></button><?php endif; ?>
      <button class="chip" type="button" data-tab="critical">Críticos <b><?= count($critical) ?></b></button>
      <button class="chip" type="button" data-tab="old">+48h <b><?= count($old) ?></b></button>
      <button class="chip" type="button" data-tab="wait">En espera <b><?= count($wait) ?></b></button>
      <button class="chip" type="button" data-tab="solved">Resueltos <b><?= count($solvedTickets) ?></b></button>
    </div>
    <div class="ticketList" id="ticketList">
      <div class="empty noResults">No hay tickets que coincidan con la búsqueda.</div>
<?php
$groups = ['main' => $mainTickets, 'unassigned' => $unassigned, 'critical' => $critical, 'old' => $old, 'wait' => $wait, 'solved' => $solvedTickets];
foreach ($groups as $group => $arr):
    if ($group === 'unassigned' && !$canAll) { continue; }
    if (!$arr && $group === 'main'): ?><div class="empty" data-group="<?= smgr_h($group) ?>">No hay tickets en esta vista.</div><?php endif;
    foreach ($arr as $t):
        $id = (int)($t['id'] ?? 0);
        [$statusLabel, $statusClass] = smgr_status_label($t['status'] ?? 0);
        $priorityClass = smgr_priority_class($t['priority'] ?? 3);
        $priorityLabel = smgr_priority_label($t['priority'] ?? 3);
        $assignLabel = smgr_panel_assignees_label($t['assignees'] ?? []);
        $locId = (int)($t['locations_id'] ?? 0);
        $loc = $shortLocById[$locId] ?? smgr_short_location_name($t['location_name'] ?: 'Sin ubicacion', 'Sin ubicacion');
        $searchText = strtolower(trim(($t['name'] ?? '') . ' ' . ($t['category_name'] ?? '') . ' ' . ($t['location_name'] ?? '') . ' ' . $loc . ' ' . $statusLabel . ' ' . $priorityLabel . ' ' . $assignLabel));
        $isClosedTicket = ((int)($t['status'] ?? 0) >= 5);
        $isVisible = $group === 'main';
?>
      <article class="ticket <?= $isVisible ? '' : 'hidden' ?>" data-group="<?= smgr_h($group) ?>" data-search="<?= smgr_h($searchText) ?>">
        <div class="ticketMain"><div class="ticketTitle"><span class="idtag">#<?= $id ?></span><strong><?= smgr_h($t['name'] ?: 'Incidencia sin título') ?></strong></div><div class="meta"><span class="pill status"><?= smgr_h($statusLabel) ?></span><span class="pill <?= smgr_h($priorityClass) ?>"><?= smgr_h($priorityLabel) ?></span><span class="pill"><?= smgr_tic_icon('clock') ?><?= smgr_h(smgr_panel_age_label($t['date'] ?? '')) ?></span><span class="pill"><?= smgr_tic_icon('map') ?><?= smgr_h($loc) ?></span><span class="pill"><?= smgr_tic_icon('userplus') ?><?= smgr_h($assignLabel) ?></span><span class="pill"><?= smgr_h($t['category_name'] ?: 'Sin categoría') ?></span></div></div>
        <div class="ticketActions"><a class="btn small primary" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/solicitud_detalle.php?id=<?= $id ?>&v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= smgr_tic_icon('eye') ?><span><?= $isClosedTicket ? 'Ver detalle' : 'Gestionar' ?></span></a><?php if ($canAssign && !$isClosedTicket): ?><form method="post" class="assignForm"><?php smgr_panel_csrf_field(); ?><input type="hidden" name="pc_action" value="assign_ticket"><input type="hidden" name="ticket_id" value="<?= $id ?>"><select class="selectTech" name="tech_id" required><option value="">Técnico TIC...</option><?php foreach ($techs as $u): ?><option value="<?= (int)$u['id'] ?>"><?= smgr_h($u['label']) ?></option><?php endforeach; ?></select><button class="btn small gold" type="submit"><?= smgr_tic_icon('userplus') ?><span>Asignar</span></button></form><?php endif; ?></div>
      </article>
<?php endforeach; endforeach; ?>
    </div>
  </main>

  <aside class="side">
    <section class="panel"><h2><?= smgr_tic_icon('bolt') ?> Acciones rápidas</h2><div class="hint">Atajos útiles para trabajar como equipo TIC.</div><div class="quickGrid" style="margin-top:12px"><a class="quick" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/asignaciones_tic.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><span class="bubble"><?= smgr_tic_icon('gear') ?></span><span>Reglas<br>TIC</span></a><a class="quick" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/stock_glpi.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><span class="bubble"><?= smgr_tic_icon('box') ?></span><span>Stock<br>TIC</span></a><a class="quick" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/gestion_activos.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><span class="bubble"><?= smgr_tic_icon('cpu') ?></span><span>Activos<br>GLPI</span></a><a class="quick" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/selector.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><span class="bubble"><?= smgr_tic_icon('map') ?></span><span>Plano<br>aulas</span></a></div></section>
    <section class="panel"><h2><?= smgr_tic_icon('box') ?> Estado del stock</h2><div class="bars" style="margin-top:12px"><div class="stockLine"><span><b><?= (int)$stockSummary['items'] ?></b><small> artículos</small></span><span><b><?= (int)$stockSummary['units'] ?></b><small> unidades</small></span></div><div class="stockLine"><span><b><?= (int)$stockSummary['low'] ?></b><small> bajo</small></span><span><b><?= (int)$stockSummary['empty'] ?></b><small> sin stock</small></span></div><?php if (!$stockOptions): ?><div class="empty">No hay stock detectado todavía en la vista TIC.</div><?php endif; ?><?php foreach ($stockOptions as $opt): ?><div class="stockLine"><span><b><?= smgr_h($opt['plain_label']) ?></b><br><small><?= smgr_h($opt['group']) ?></small></span><span><b><?= (int)$opt['available'] ?></b></span></div><?php endforeach; ?></div></section>
    <section class="panel"><h2><?= smgr_tic_icon('chart') ?> Hot zones</h2><div class="bars" style="margin-top:12px"><?php $topLoc = array_slice($byLoc, 0, 7, true); $maxLoc = $topLoc ? max($topLoc) : 1; if (!$topLoc): ?><div class="empty">Sin incidencias abiertas por aula.</div><?php endif; ?><?php foreach ($topLoc as $loc => $n): ?><div class="barRow"><span class="barName"><?= smgr_h($loc) ?></span><b><?= (int)$n ?></b><div class="barTrack"><div class="barFill" style="width:<?= max(8, min(100, (int)round(($n / max(1, $maxLoc)) * 100))) ?>%"></div></div></div><?php endforeach; ?></div></section>
    <section class="panel"><h2><?= smgr_tic_icon('filter') ?> Active categories</h2><div class="bars" style="margin-top:12px"><?php $topCat = array_slice($byCat, 0, 7, true); $maxCat = $topCat ? max($topCat) : 1; if (!$topCat): ?><div class="empty">No active categories.</div><?php endif; ?><?php foreach ($topCat as $cat => $n): ?><div class="barRow"><span class="barName"><?= smgr_h($cat) ?></span><b><?= (int)$n ?></b><div class="barTrack"><div class="barFill" style="width:<?= max(8, min(100, (int)round(($n / max(1, $maxCat)) * 100))) ?>%"></div></div></div><?php endforeach; ?></div></section>
  </aside>
</section>
</div></div>
<script>
(function(){
  const root=document.getElementById('ticdesk');
  const chips=[...document.querySelectorAll('.ticdesk .chip')];
  const cards=[...document.querySelectorAll('.ticdesk .ticket')];
  const search=document.getElementById('ticSearch');
  const compact=document.getElementById('ticCompact');
  let active='main';
  function apply(){
    const q=(search?.value||'').trim().toLowerCase();
    let shown=0;
    cards.forEach(card=>{
      const group=card.dataset.group||'';
      const text=card.dataset.search||'';
      const ok=group===active && (!q || text.includes(q));
      card.classList.toggle('hidden',!ok);
      if(ok) shown++;
    });
    root.classList.toggle('no-matches',shown===0);
  }
  chips.forEach(chip=>chip.addEventListener('click',()=>{chips.forEach(c=>c.classList.remove('active'));chip.classList.add('active');active=chip.dataset.tab||'main';apply();}));
  if(search){search.addEventListener('input',apply);}
  if(compact){compact.addEventListener('click',()=>{root.classList.toggle('compact');document.querySelectorAll('.ticdesk .meta').forEach(m=>m.classList.toggle('hidden'));});}
  apply();
})();
</script>
<?php Html::footer(); ?>
