<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');
require_once(__DIR__ . '/../inc/stock_helpers.php');

global $DB, $CFG_GLPI;
$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$userId = (int) Session::getLoginUserID();
$ticketId = (int)($_GET['id'] ?? ($_POST['ticket_id'] ?? 0));
$message = '';
$messageType = 'ok';
$canAll = function_exists('smgr_can_view_all_tic_tickets') ? smgr_can_view_all_tic_tickets() : (function_exists('smgr_is_super_admin_user') ? smgr_is_super_admin_user() : false);
$canAssign = function_exists('smgr_can_manage_tic_assignments') ? smgr_can_manage_tic_assignments() : (function_exists('smgr_is_super_admin_user') ? smgr_is_super_admin_user() : false);
$canManage = false;
$canReply = false;
$isArchived = false;
$usedMaterialDetail = [];
$usedMaterialRows = [];

Html::header('Detalle de solicitud', $_SERVER['PHP_SELF'], 'helpdesk', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo plugin_schoolmanager_home_button();

function pcd_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function pcd_plain($html) {
    $text = (string)$html;
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
    $text = preg_replace('/<\s*\/p\s*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}
function pcd_parse_description($content) {
    $text = pcd_plain($content);
    $labels = [
        'Categoría GLPI', 'Tipo detectado', 'Detalle técnico', 'Ubicación',
        'ID ubicación GLPI', 'Número de ordenador/equipo', 'Software afectado',
        'Descripción del problema'
    ];
    $data = [];
    foreach ($labels as $i => $label) {
        $next = array_slice($labels, $i + 1);
        $nextPattern = $next ? '(?=' . implode(':|', array_map(fn($l)=>preg_quote($l, '/'), $next)) . ':|$)' : '$';
        if (preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)\s*' . $nextPattern . '/s', $text, $m)) {
            $data[$label] = trim($m[1]);
        }
    }
    if (!$data) { $data['Descripción'] = $text; }
    return $data;
}
function pcd_render_description($content) {
    $data = pcd_parse_description($content);
    $main = $data['Descripción del problema'] ?? ($data['Descripción'] ?? '');
    // En la cabecera ya mostramos categoria y ubicacion. El ID interno no aporta nada al profesor.
    unset(
        $data['Descripción del problema'],
        $data['Descripción'],
        $data['Categoría GLPI'],
        $data['Ubicación'],
        $data['ID ubicación GLPI']
    );
    $html = '<div class="pcd-desc-grid">';
    foreach ($data as $label => $value) {
        if ($value === '') { continue; }
        $displayValue = ($label === 'Activo afectado') ? pcd_link_possible_asset($value) : nl2br(pcd_h($value));
        $html .= '<div class="pcd-desc-item"><small>' . pcd_h($label) . '</small><b>' . $displayValue . '</b></div>';
    }
    $html .= '</div>';
    if ($main !== '') {
        $html .= '<div class="pcd-problem"><small>Descripción del problema</small><p>' . nl2br(pcd_h($main)) . '</p></div>';
    }
    return $html;
}
function pcd_status($status) {
    $map = [
        1 => ['Abierta', 'new', 'La solicitud está registrada y pendiente de revisión.'],
        2 => ['En curso', 'work', 'El equipo TIC ya está trabajando en ella.'],
        3 => ['Planificada', 'work', 'La intervención está programada.'],
        4 => ['En espera', 'wait', 'La solicitud está esperando información, material o disponibilidad.'],
        5 => ['Resuelta', 'done', 'El equipo TIC ha propuesto una solución.'],
        6 => ['Cerrada', 'closed', 'La solicitud está finalizada.'],
    ];
    return $map[(int)$status] ?? ['Estado ' . (int)$status, 'new', 'Estado de GLPI.'];
}
function pcd_priority($priority) {
    $map = [1=>'Muy baja',2=>'Baja',3=>'Media',4=>'Alta',5=>'Muy alta',6=>'Mayor'];
    return $map[(int)$priority] ?? 'Media';
}
function pcd_db() {
    global $DB;
    if (isset($DB) && is_object($DB)) { return $DB; }
    if (isset($GLOBALS['DB']) && is_object($GLOBALS['DB'])) { return $GLOBALS['DB']; }
    if (class_exists('DBConnection') && method_exists('DBConnection', 'getReadConnection')) {
        try { return DBConnection::getReadConnection(); } catch (Throwable $e) { return null; }
    }
    return null;
}
function pcd_csrf_field() { static $tok = null; if (method_exists('Session', 'getNewCSRFToken')) { if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . pcd_h($tok) . '">'; } }

function pcd_icon($name) {
    $icons = [
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        'external' => '<path d="M14 5h5v5"/><path d="M10 14 19 5"/><path d="M19 13v5H5V5h5"/>',
        'ticket' => '<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6z"/><path d="M13 5v14"/>',
        'message' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
        'send' => '<path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4 20-7Z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'arrow' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'tool' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4"/><path d="m15 5 4 4"/>',
        'stock' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>',
    ];
    $body = $icons[$name] ?? $icons['external'];
    return '<svg class="pcd-svg" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

function pcd_user_label($userId) {
    $userId = (int)$userId;
    if ($userId <= 0 || !class_exists('User')) { return 'Usuario'; }
    try {
        $u = new User();
        if ($u->getFromDB($userId)) {
            $name = trim(((string)($u->fields['firstname'] ?? '')) . ' ' . ((string)($u->fields['realname'] ?? '')));
            if ($name === '') { $name = (string)($u->fields['name'] ?? ''); }
            return $name ?: ('Usuario ' . $userId);
        }
    } catch (Throwable $e) {}
    return 'Usuario ' . $userId;
}


function pcd_can_link_objects() {
    if (function_exists('plugin_schoolmanager_is_tic_user') && plugin_schoolmanager_is_tic_user()) { return true; }
    if (function_exists('plugin_schoolmanager_is_admin_tic_user') && plugin_schoolmanager_is_admin_tic_user()) { return true; }
    if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) { return true; }
    return false;
}
function pcd_plugin_detail_url($path, array $params = []) {
    global $root;
    $url = ($root ?? '') . '/plugins/schoolmanager/front/' . ltrim($path, '/');
    if (!isset($params['v'])) { $params['v'] = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : ''; }
    return $url . '?' . http_build_query($params);
}
function pcd_location_detail_link($locationId, $label = '') {
    $locationId = (int)$locationId;
    $label = trim((string)$label);
    if ($label === '') { $label = $locationId > 0 ? ('Ubicación #' . $locationId) : 'Sin ubicación'; }
    if ($locationId <= 0) { return '<b>' . pcd_h($label) . '</b>'; }
    $plugin = pcd_plugin_detail_url('detalle_aula.php', ['id'=>$locationId]);
    $html = '<a class="pcd-inline-link" href="' . pcd_h($plugin) . '">' . pcd_h($label) . '</a>';
    if (pcd_can_link_objects()) {
        global $root;
        $html .= ' <a class="pcd-mini-link" href="' . pcd_h(($root ?? '') . '/front/location.form.php?id=' . $locationId) . '">GLPI</a>';
    }
    return $html;
}
function pcd_asset_native_url($type, $id) {
    global $root;
    $types = function_exists('plugin_schoolmanager_asset_types') ? plugin_schoolmanager_asset_types() : [];
    if (!isset($types[$type]) || empty($types[$type]['native'])) { return ''; }
    return ($root ?? '') . $types[$type]['native'] . '?id=' . (int)$id;
}
function pcd_link_possible_asset($value) {
    $value = trim((string)$value);
    if ($value === '') { return pcd_h($value); }
    if (!pcd_can_link_objects()) { return nl2br(pcd_h($value)); }
    $type = ''; $id = 0;
    if (preg_match('/\b(Computer|Monitor|Printer|NetworkEquipment|Peripheral|Phone|Projector):(\d+)\b/u', $value, $m)) { $type = $m[1]; $id = (int)$m[2]; }
    elseif (preg_match('/\bID\s*(\d+)\b/iu', $value, $m)) {
        $id = (int)$m[1];
        $low = mb_strtolower($value, 'UTF-8');
        if (strpos($low, 'ordenador') !== false || strpos($low, 'computer') !== false) { $type = 'Computer'; }
        elseif (strpos($low, 'monitor') !== false) { $type = 'Monitor'; }
        elseif (strpos($low, 'impresora') !== false || strpos($low, 'printer') !== false) { $type = 'Printer'; }
        elseif (strpos($low, 'red') !== false || strpos($low, 'switch') !== false || strpos($low, 'router') !== false) { $type = 'NetworkEquipment'; }
        elseif (strpos($low, 'perifer') !== false || strpos($low, 'raton') !== false || strpos($low, 'teclado') !== false) { $type = 'Peripheral'; }
        elseif (strpos($low, 'telefono') !== false) { $type = 'Phone'; }
        elseif (strpos($low, 'proyector') !== false) { $type = 'Projector'; }
    }
    $url = ($type !== '' && $id > 0) ? pcd_asset_native_url($type, $id) : '';
    if ($url === '') { return nl2br(pcd_h($value)); }
    return '<a class="pcd-inline-link" href="' . pcd_h($url) . '">' . nl2br(pcd_h(preg_replace('/\s*\[?'.$type.':'.$id.'\]?\s*/', '', $value))) . '</a>';
}
function pcd_render_material_card_items(array $usedRows, array $fallbackItems) {
    $html = '';
    if ($usedRows) {
        foreach ($usedRows as $row) {
            $label = ((int)($row['qty'] ?? 0)) . 'x ' . (string)($row['label'] ?? 'Material');
            $link = pcd_can_link_objects() && !empty($row['url']) ? '<a class="pcd-material-pill linked" href="' . pcd_h($row['url']) . '">' . pcd_icon('stock') . pcd_h($label) . '</a>' : '<span class="pcd-material-pill">' . pcd_icon('stock') . pcd_h($label) . '</span>';
            $extra = '';
            if (!empty($row['dates'][0])) { $extra .= '<small>Salida: ' . pcd_h($row['dates'][0]) . '</small>'; }
            if (!empty($row['technicians']) && (function_exists('smgr_can_manage_tic_assignments') && smgr_can_manage_tic_assignments())) {
                foreach ($row['technicians'] as $uid => $name) {
                    $extra .= '<small>Técnico: <a class="pcd-inline-link" href="' . pcd_h(smgr_stock_technician_summary_url((int)$uid)) . '">' . pcd_h($name) . '</a></small>';
                }
            }
            $html .= '<div class="pcd-material-row">' . $link . $extra . '</div>';
        }
        return $html;
    }
    foreach ($fallbackItems as $mat) { $html .= '<span class="pcd-material-pill">' . pcd_icon('check') . pcd_h($mat) . '</span>'; }
    return $html;
}

function pcd_extract_used_material_from_text($content) {
    $text = pcd_plain($content);
    $items = [];
    if ($text === '') { return $items; }
    if (preg_match_all('/Material utilizado(?:\s+de Control de stock)?\s*:\s*(.*?)(?:\n\s*\n|$)/isu', $text, $matches)) {
        foreach ($matches[1] as $block) {
            foreach (preg_split('/\n+/', trim((string)$block)) as $line) {
                $line = trim((string)$line);
                $line = preg_replace('/^[-•]\s*/u', '', $line);
                if ($line !== '') { $items[] = $line; }
            }
        }
    }
    return array_values(array_unique($items));
}
function pcd_extract_used_material_from_solutions(array $solutions) {
    $items = [];
    foreach ($solutions as $solution) {
        foreach (pcd_extract_used_material_from_text($solution['content'] ?? '') as $item) { $items[] = $item; }
    }
    return array_values(array_unique($items));
}
function pcd_split_solution_material($content) {
    $text = pcd_plain($content);
    $text = str_replace('Material utilizado de Control de stock:', 'Material utilizado:', $text);
    $materials = pcd_extract_used_material_from_text($text);
    $solution = preg_replace('/\n*Material utilizado\s*:\s*.*$/isu', '', $text);
    $solution = trim((string)$solution);
    return [$solution, $materials];
}
function pcd_latest_solution_text(array $solutions) {
    foreach ($solutions as $solution) {
        [$text, $materials] = pcd_split_solution_material($solution['content'] ?? '');
        if ($text !== '') { return $text; }
    }
    return '';
}
function pcd_render_solution_content($content) {
    [$text, $materials] = pcd_split_solution_material($content);
    if ($text === '') { return ''; }
    return nl2br(pcd_h($text));
}

$db = pcd_db();
$error = '';
$ticket = null;
$followups = [];
$solutions = [];
$documents = [];
$requesters = [];
$requesterIds = [];
$assignees = [];
$techs = [];

try {
    if ($ticketId <= 0) { throw new RuntimeException('Solicitud no indicada.'); }
    if (!$db || !method_exists($db, 'request')) { throw new RuntimeException('No se pudo acceder al motor seguro de GLPI.'); }

    $tobj = new Ticket();
    if (!$tobj->getFromDB($ticketId) || !empty($tobj->fields['is_deleted'])) {
        throw new RuntimeException('No se ha encontrado la solicitud.');
    }

    $isRequester = false;
    $tuIterator = $db->request([
        'FROM' => 'glpi_tickets_users',
        'WHERE' => ['tickets_id' => $ticketId],
        'LIMIT' => 50,
    ]);
    foreach ($tuIterator as $tu) {
        if ((int)($tu['type'] ?? 0) === 1) {
            $requesterIds[] = (int)($tu['users_id'] ?? 0);
            if ((int)($tu['users_id'] ?? 0) === $userId) { $isRequester = true; }
        }
    }
    $requesterIds = array_values(array_unique(array_filter($requesterIds)));
    $isTech = function_exists('plugin_schoolmanager_user_mode') && plugin_schoolmanager_user_mode() === 'tecnico';
    if (!$isRequester && !$isTech) {
        throw new RuntimeException('No tienes permiso para ver esta solicitud.');
    }

    $isArchived = ((int)($tobj->fields['status'] ?? 0) >= 5);
    $canManage = !$isArchived && (function_exists('smgr_can_manage_ticket') ? smgr_can_manage_ticket($ticketId, $userId) : false);
    $canReply = !$isArchived && ($canManage || $isRequester);
    $techs = $canAssign && function_exists('smgr_fetch_assignable_technicians') ? smgr_fetch_assignable_technicians() : [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['pc_action'] ?? '');
        if ($isArchived && in_array($action, ['reply_ticket','solve_ticket'], true)) {
            $message = 'Esta incidencia ya está resuelta y archivada. No se pueden añadir respuestas ni volver a resolverla.';
            $messageType = 'error';
        } elseif ($action === 'reply_ticket') {
            $content = trim((string)($_POST['reply_content'] ?? ''));
            if (!$canReply) {
                $message = 'No puedes responder este ticket.';
                $messageType = 'error';
            } else {
                [$ok, $msg] = smgr_add_ticket_followup($ticketId, $content, 0);
                $message = $msg; $messageType = $ok ? 'ok' : 'error';
            }
        } elseif ($action === 'solve_ticket') {
            $solution = trim((string)($_POST['solution_content'] ?? ''));
            if (!$canManage) {
                $message = 'No puedes resolver este ticket. Los tecnicos solo pueden tocar tickets asignados.';
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
                    [$stockOk, $stockMsg, $stockDone] = smgr_stock_consume_for_ticket($stockValue, $qty, $ticketId, 'Resolución de incidencia');
                    if (!$stockOk) { $stockError = $stockMsg; break; }
                    if ($stockMsg !== '') { $usedStock[] = $stockMsg; }
                }
                if ($stockError !== '') {
                    $message = $stockError;
                    $messageType = 'error';
                } else {
                    if ($usedStock) {
                        $solution .= "

Material utilizado:
- " . implode("
- ", $usedStock);
                    }
                    [$ok, $msg] = smgr_solve_ticket($ticketId, $solution);
                    $message = $ok ? ($usedStock ? 'Ticket marcado como resuelto. Material descontado correctamente.' : $msg) : $msg;
                    $messageType = $ok ? 'ok' : 'error';
                }
            }
        } elseif ($action === 'assign_ticket') {
            $techId = (int)($_POST['tech_id'] ?? 0);
            if (!$canAssign) {
                $message = 'Solo Admin TIC o Super-Admin puede asignar tickets.';
                $messageType = 'error';
            } else {
                [$ok, $msg] = smgr_assign_ticket_to_user($ticketId, $techId, true);
                $message = $msg; $messageType = $ok ? 'ok' : 'error';
                $isArchived = ((int)($tobj->fields['status'] ?? 0) >= 5);
                $canManage = !$isArchived && (function_exists('smgr_can_manage_ticket') ? smgr_can_manage_ticket($ticketId, $userId) : $canManage);
                $canReply = !$isArchived && ($canManage || $isRequester);
            }
        }
        $tobj->getFromDB($ticketId);
    }

    $ticket = $tobj->fields;
    $isArchived = ((int)($ticket['status'] ?? 0) >= 5);
    if ($isArchived) { $canManage = false; $canReply = false; }

    $locName = '';
    if (!empty($ticket['locations_id']) && class_exists('Location')) {
        $loc = new Location();
        if ($loc->getFromDB((int)$ticket['locations_id'])) { $locName = smgr_short_location_name($loc->fields['completename'] ?? $loc->fields['name'] ?? ''); }
    }
    $ticket['location_name'] = $locName;

    $catName = '';
    if (!empty($ticket['itilcategories_id']) && class_exists('ITILCategory')) {
        $cat = new ITILCategory();
        if ($cat->getFromDB((int)$ticket['itilcategories_id'])) { $catName = $cat->fields['completename'] ?? $cat->fields['name'] ?? ''; }
    }
    $ticket['category_name'] = $catName;
    $assignees = function_exists('smgr_ticket_assignees') ? smgr_ticket_assignees($ticketId) : [];

    $fuIterator = $db->request([
        'FROM' => 'glpi_itilfollowups',
        'WHERE' => ['itemtype'=>'Ticket', 'items_id'=>$ticketId, 'is_private'=>0],
        'ORDER' => ['date DESC', 'id DESC'],
        'LIMIT' => 50,
    ]);
    foreach ($fuIterator as $fu) { $followups[] = $fu; }

    if (method_exists($db, 'tableExists') && $db->tableExists('glpi_itilsolutions')) {
        $soIterator = $db->request([
            'FROM' => 'glpi_itilsolutions',
            'WHERE' => ['itemtype'=>'Ticket', 'items_id'=>$ticketId],
            'ORDER' => ['id DESC'],
            'LIMIT' => 10,
        ]);
        foreach ($soIterator as $so) { $solutions[] = $so; }
    }
    $usedMaterialRows = function_exists('smgr_stock_used_materials_for_ticket') ? smgr_stock_used_materials_for_ticket($ticketId) : [];
    $usedMaterialDetail = $usedMaterialRows ? [] : pcd_extract_used_material_from_solutions($solutions);
    $latestSolutionText = pcd_latest_solution_text($solutions);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$latestSolutionText = $latestSolutionText ?? '';
[$statusLabel, $statusClass, $statusHelp] = $ticket ? pcd_status($ticket['status'] ?? 0) : ['Error','new',''];
$nativeUrl = $root . '/front/ticket.form.php?id=' . $ticketId;
$listUrl = $root . '/plugins/schoolmanager/front/mis_solicitudes.php?v=234';
$newUrl = $root . '/plugins/schoolmanager/front/nueva_incidencia.php?v=234';
$pcdStockOptions = function_exists('smgr_stock_selectable_items') ? smgr_stock_selectable_items(false) : [];
?>
<style id="pcd-v280-clean">
.pcd{
  --pcd-teal:#07384d;
  --pcd-blue:#0b5f7a;
  --pcd-red:#a82025;
  --pcd-red2:#8f1d1d;
  --pcd-gold:#efa300;
  --pcd-line:#d6e5ed;
  --pcd-muted:#627386;
  --pcd-ink:#102638;
  min-height:calc(100vh - 76px);
  padding:clamp(12px,1.6vw,24px);
  background:linear-gradient(135deg,#f6fafb 0%,#eef6f8 62%,#fffaf0 100%);
  font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
  color:var(--pcd-ink);
}
.pcd *{box-sizing:border-box}
.pcd-wrap{max-width:1510px;margin:0 auto;display:grid;gap:16px}
.pcd-hero{
  position:relative!important;
  overflow:hidden;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:18px;
  background:rgba(255,255,255,.94)!important;
  border:1px solid var(--pcd-line)!important;
  border-radius:26px!important;
  padding:18px 24px!important;
  box-shadow:0 18px 54px rgba(7,56,77,.08)!important;
}
.pcd-hero:before,.pcd-hero:after{display:none!important;content:none!important}
.pcd-brand{display:flex;gap:18px;align-items:center;min-width:0}
.pcd-logo{height:64px;max-width:190px;object-fit:contain;filter:none!important;box-shadow:none!important;background:transparent!important;border:0!important;border-radius:0!important}
.pcd-kicker{font-weight:950;color:var(--pcd-red);letter-spacing:.10em;font-size:13px;text-transform:uppercase}
.pcd h1{margin:0;color:var(--pcd-teal);font-size:clamp(34px,3.6vw,56px);line-height:.98;letter-spacing:-.045em}
.pcd-sub{margin:6px 0 0;color:var(--pcd-muted);font-weight:850;font-size:clamp(14px,1.2vw,18px)}
.pcd-actions{display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.pcd-svg{width:20px;height:20px;min-width:20px;display:block;flex:0 0 20px;transition:transform .22s ease,opacity .22s ease}
.pcd-btn{
  min-height:52px;
  border-radius:18px!important;
  padding:0 20px!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  gap:10px!important;
  font-weight:950!important;
  font-size:16px!important;
  line-height:1!important;
  text-decoration:none!important;
  border:1px solid var(--pcd-line)!important;
  background:#fff!important;
  color:var(--pcd-teal)!important;
  white-space:nowrap!important;
  box-shadow:0 12px 28px rgba(7,56,77,.07)!important;
  transition:transform .22s cubic-bezier(.2,.8,.2,1),box-shadow .22s ease,background .22s ease,border-color .22s ease,color .22s ease!important;
  cursor:pointer;
}
.pcd-btn:hover{transform:translateY(-4px)!important;box-shadow:0 22px 42px rgba(7,56,77,.13)!important;border-color:#bcd4df!important;color:var(--pcd-blue)!important}
.pcd-btn:active{transform:translateY(-1px) scale(.98)!important}
.pcd-btn:hover .pcd-svg{transform:translateY(-1px) scale(1.06)}
.pcd-btn.primary{background:var(--pcd-teal)!important;border-color:var(--pcd-teal)!important;color:#fff!important;box-shadow:0 18px 36px rgba(7,56,77,.18)!important}
.pcd-btn.primary:hover{background:var(--pcd-blue)!important;border-color:var(--pcd-blue)!important;color:#fff!important;box-shadow:0 24px 46px rgba(7,56,77,.24)!important}
.pcd-btn.red,.pcd-btn.gold{background:var(--pcd-red)!important;border-color:var(--pcd-red2)!important;color:#fff!important;box-shadow:0 18px 38px rgba(168,32,37,.20)!important}
.pcd-btn.red:hover,.pcd-btn.gold:hover{background:#b8282d!important;border-color:#9c2020!important;color:#fff!important;box-shadow:0 24px 48px rgba(168,32,37,.27)!important}
.pcd-btn.soft-red{background:#fff8f8!important;border-color:#efc0c4!important;color:var(--pcd-red)!important}
.pcd-btn.soft-red:hover{background:var(--pcd-red)!important;border-color:var(--pcd-red)!important;color:#fff!important;box-shadow:0 22px 42px rgba(168,32,37,.18)!important}
.pcd-msg{border-radius:18px;padding:12px 14px;font-weight:950;border:1px solid var(--pcd-line);background:#effaf8;color:var(--pcd-teal)}
.pcd-msg.error{background:#ffecec;border-color:#ffc7c7;color:#9b1f1f}
.pcd-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:16px}
.pcd-card{background:rgba(255,255,255,.96)!important;border:1px solid var(--pcd-line)!important;border-radius:24px!important;padding:18px!important;box-shadow:0 12px 38px rgba(7,56,77,.06)!important;transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
.pcd-card:hover{border-color:#c8dde6!important;box-shadow:0 18px 46px rgba(7,56,77,.085)!important}
.pcd-title-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.pcd-title{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.pcd-id{background:#eaf3f8;color:var(--pcd-teal);border-radius:999px;padding:8px 12px;font-weight:950}
.pcd-status{border-radius:999px;padding:10px 14px;font-weight:950;border:1px solid var(--pcd-line)}
.pcd-status.new{background:#eaf6ff;color:#0b5a82}.pcd-status.work{background:#fff7dc;color:#806000}.pcd-status.wait{background:#f2eaff;color:#5c3796}.pcd-status.done{background:#e9fbef;color:#176a31}.pcd-status.closed{background:#edf1f4;color:#46545c}
.pcd-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:16px}
.pcd-m{border:1px solid var(--pcd-line);border-radius:17px;background:#f8fbfd;padding:12px 14px}
.pcd-m small{display:block;color:var(--pcd-muted);font-weight:900}.pcd-m b{display:block;color:var(--pcd-ink);margin-top:4px}
.pcd-content{margin-top:16px;border:1px solid var(--pcd-line);border-radius:20px;background:#fbfdff;padding:16px}
.pcd-content h3,.pcd-card h3{margin:0 0 12px;color:var(--pcd-teal);font-size:clamp(20px,1.8vw,26px)}
.pcd-side{display:grid;gap:16px;align-content:start}.pcd-native{display:grid;gap:10px}.pcd-native a{display:flex!important;text-align:center}
.pcd-chip{display:inline-flex;align-items:center;gap:7px;border-radius:999px;background:#effaf8;border:1px solid var(--pcd-line);padding:7px 11px;color:var(--pcd-teal);font-weight:950;font-size:13px}
.pcd-progress{display:grid;gap:9px}.pcd-progress-row{display:flex;align-items:center;gap:10px;color:var(--pcd-muted);font-weight:900}.pcd-dot{width:14px;height:14px;border-radius:999px;background:#dce8e7}.pcd-progress-row.active .pcd-dot{background:var(--pcd-gold)}.pcd-progress-row.done .pcd-dot{background:#2ebf5a}
.pcd-help{background:#fffaf0;border:1px solid #f1cc70;border-radius:18px;padding:13px;color:#806000;font-weight:850}
.pcd-empty{background:#fff;border:1px solid var(--pcd-line);border-radius:22px;padding:28px;text-align:center;color:var(--pcd-muted);font-weight:900}.pcd-error{background:#fff1f1;border-color:#ffcaca;color:#9b1c1c}
.pcd-desc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:6px}.pcd-desc-item{border:1px solid var(--pcd-line);background:linear-gradient(135deg,#f8fbff,#ffffff);border-radius:17px;padding:13px 15px;box-shadow:0 8px 20px rgba(7,56,77,.035)}.pcd-desc-item small,.pcd-problem small{display:block;color:var(--pcd-muted);font-weight:950;margin-bottom:4px}.pcd-desc-item b{display:block;color:var(--pcd-ink);font-weight:900;line-height:1.25}.pcd-problem{margin-top:14px;border:1px solid #ffd0a3;background:linear-gradient(135deg,#fffaf0,#ffffff);border-radius:18px;padding:14px 16px;box-shadow:0 8px 22px rgba(214,161,29,.08)}.pcd-problem p{margin:0;color:#29464f;font-weight:800;line-height:1.45}
.pcd-chat{display:grid;gap:12px}.pcd-bubble{max-width:min(780px,92%);border-radius:20px;padding:14px 16px;box-shadow:0 10px 26px rgba(7,56,77,.07);line-height:1.45;font-weight:780}.pcd-bubble.user{justify-self:start;background:#eef6fb;border:1px solid var(--pcd-line);border-bottom-left-radius:7px}.pcd-bubble.tic{justify-self:end;background:#0b938b;color:#fff;border-bottom-right-radius:7px}.pcd-bubble.solution{justify-self:end;background:#e9fbef;color:#176a31;border:1px solid #bfe9ca}.pcd-bubble small{display:block;opacity:.85;font-weight:950;margin-bottom:6px}.pcd-bubble.tic small{color:#eafffb}
.pcd-form{display:grid;gap:10px}.pcd-form textarea{width:100%;min-height:110px;border:1px solid var(--pcd-line);border-radius:18px;padding:12px 14px;font-weight:850;color:var(--pcd-ink);resize:vertical;background:#fff}.pcd-form select{width:100%;border:1px solid var(--pcd-line);border-radius:18px;padding:11px 13px;font-weight:900;color:var(--pcd-teal);background:#fff}.pcd-form-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.pcd-stock-use{display:grid;gap:10px;margin-top:10px}.pcd-stock-row{display:grid;grid-template-columns:minmax(0,1fr) 86px;gap:8px;align-items:end}.pcd-stock-row select,.pcd-stock-row input{width:100%;border:1px solid var(--pcd-line);border-radius:14px;padding:10px 12px;font-weight:850;background:#fff;color:var(--pcd-teal);min-width:0}.pcd-stock-help{background:#f7fafc;border:1px solid var(--pcd-line);border-radius:16px;padding:10px 12px;color:var(--pcd-muted);font-weight:850}.pcd-stock-help b{color:var(--pcd-teal)}
@media(max-width:980px){.pcd-grid{grid-template-columns:1fr}.pcd-hero{display:grid}.pcd-actions{justify-content:flex-start}.pcd-meta{grid-template-columns:1fr}}
@media(max-width:560px){.pcd{padding:8px}.pcd-brand{align-items:flex-start}.pcd-logo{height:52px;max-width:150px}.pcd-card{padding:13px!important}.pcd h1{font-size:32px}.pcd-actions{display:grid;grid-template-columns:1fr;width:100%}.pcd-btn{width:100%}}

/* v281: botones superiores equilibrados y hover uniforme */
.pcd-actions{
  display:grid!important;
  grid-template-columns:repeat(3, minmax(190px, 1fr))!important;
  gap:12px!important;
  align-items:center!important;
  justify-content:end!important;
  max-width:720px!important;
  width:min(720px,100%)!important;
}
.pcd-actions .pcd-btn{
  width:100%!important;
  min-width:0!important;
  min-height:54px!important;
  padding:0 18px!important;
  border-radius:18px!important;
}
.pcd-actions .pcd-btn span{white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;}
.pcd-actions .pcd-svg{width:19px!important;height:19px!important;min-width:19px!important;flex:0 0 19px!important;}
.pcd-btn{position:relative!important;overflow:hidden!important;will-change:transform!important;}
.pcd-btn::after{content:""!important;position:absolute!important;inset:0!important;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.08) 36%,rgba(255,255,255,.22) 50%,rgba(255,255,255,.08) 64%,transparent 100%)!important;transform:translateX(-145%)!important;transition:transform .42s ease!important;pointer-events:none!important;}
.pcd-btn:hover::after{transform:translateX(145%)!important;}
.pcd-btn span,.pcd-btn .pcd-svg{position:relative!important;z-index:2!important;}
@media(max-width:1280px){.pcd-actions{grid-template-columns:repeat(2,minmax(190px,1fr))!important;max-width:460px!important}.pcd-actions .pcd-btn{min-height:52px!important}}
@media(max-width:980px){.pcd-actions{grid-template-columns:repeat(3,minmax(170px,1fr))!important;max-width:100%!important;justify-content:start!important}}
@media(max-width:680px){.pcd-actions{grid-template-columns:1fr!important}.pcd-actions .pcd-btn{width:100%!important}}



/* v104: resolución clara en tarjetas */
.pcd-resolved-cards{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.55fr);gap:14px;margin-top:16px}
.pcd-resolved-card{border:1px solid var(--pcd-line);border-radius:22px;padding:18px;background:#fff;box-shadow:0 12px 30px rgba(7,56,77,.065)}
.pcd-resolved-card h3{margin:0 0 10px;color:var(--pcd-teal);display:flex;gap:8px;align-items:center;font-size:19px}
.pcd-resolved-card p{margin:0;color:#22384a;font-weight:850;line-height:1.45}
.pcd-resolved-card.solution-card{background:linear-gradient(135deg,#f6fff8,#ffffff);border-color:#b7e5c4}
.pcd-resolved-card.material-card{background:linear-gradient(135deg,#f7fbff,#ffffff);border-color:#bcdce8}
.pcd-material-list{display:flex;gap:8px;flex-wrap:wrap}
.pcd-material-pill{display:inline-flex;align-items:center;gap:7px;border:1px solid #b7e5c4;background:#effaf2;color:#145f36!important;border-radius:999px;padding:8px 11px;font-weight:950;text-decoration:none!important}.pcd-material-pill.linked:hover{transform:translateY(-1px);box-shadow:0 10px 20px rgba(20,95,54,.12)}.pcd-material-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:6px 0}.pcd-material-row small{font-weight:900;color:#5f7480}.pcd-inline-link{color:#0b6570!important;font-weight:950;text-decoration:underline!important;text-underline-offset:3px}.pcd-mini-link{display:inline-flex;margin-left:6px;border:1px solid #cfe1e8;border-radius:999px;padding:3px 7px;color:#07384d!important;text-decoration:none!important;font-size:12px;font-weight:950;background:#fff}
@media(max-width:820px){.pcd-resolved-cards{grid-template-columns:1fr}}

/* Resolver solicitud - stock dinámico */
.pcd-solve-panel{padding:22px!important;background:linear-gradient(180deg,#fff 0%,#f9fcfe 100%)!important}
.pcd-solve-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:12px}
.pcd-solve-head h3{margin:0;color:var(--pcd-teal);font-size:28px;letter-spacing:-.02em}
.pcd-solve-head p{margin:6px 0 0;color:var(--pcd-muted);font-weight:850;line-height:1.35}
.pcd-solve-badge{display:inline-flex;align-items:center;gap:8px;border:1px solid #cfe0e8;background:#eef8fb;color:var(--pcd-teal);border-radius:999px;padding:9px 12px;font-weight:950;white-space:nowrap}
.pcd-field-label{display:block;color:var(--pcd-teal);font-weight:950;margin:2px 0 -2px}
.pcd-solve-form{gap:14px!important}
.pcd-solve-form textarea{min-height:132px!important;border-width:2px!important;border-color:#d6e5ec!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.9)!important}
.pcd-solve-form textarea:focus{outline:none!important;border-color:var(--pcd-teal)!important;box-shadow:0 0 0 4px rgba(7,84,105,.10)!important}
.pcd-stock-use{border:1px solid var(--pcd-line);background:#f7fbfd;border-radius:22px;padding:14px;gap:12px!important}
.pcd-stock-help{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff!important;border-radius:18px!important;padding:14px 16px!important;color:var(--pcd-muted)!important}
.pcd-stock-help b{display:flex;align-items:center;gap:8px;font-size:18px;color:var(--pcd-teal)!important}
.pcd-stock-help span{font-weight:850;text-align:right;max-width:640px}
.pcd-stock-rows{display:grid;gap:10px}
.pcd-stock-row{display:grid!important;grid-template-columns:minmax(0,1fr) 120px 44px!important;gap:10px!important;align-items:end!important;background:#fff;border:1px solid #d9e8ef;border-radius:18px;padding:12px;box-shadow:0 10px 26px rgba(7,56,77,.045);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.pcd-stock-row:hover,.pcd-stock-row.is-filled{border-color:#9ac6d4;box-shadow:0 14px 32px rgba(7,56,77,.08)}
.pcd-stock-row label{display:block;color:var(--pcd-muted);font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.04em;margin:0 0 6px}
.pcd-stock-row select,.pcd-stock-row input{height:48px!important;border-radius:14px!important;border:1px solid #cfdfe8!important;background:#fff!important;font-size:15px!important;font-weight:900!important;color:var(--pcd-ink)!important;padding:0 12px!important}
.pcd-stock-row select:focus,.pcd-stock-row input:focus{outline:none!important;border-color:var(--pcd-teal)!important;box-shadow:0 0 0 4px rgba(7,84,105,.10)!important}
.pcd-stock-remove{height:44px;width:44px;border-radius:14px;border:1px solid #efc0c4;background:#fff8f8;color:var(--pcd-red);font-size:24px;font-weight:950;line-height:1;cursor:pointer;transition:transform .18s ease,background .18s ease,color .18s ease}
.pcd-stock-remove:hover{transform:translateY(-2px);background:var(--pcd-red);color:#fff}
.pcd-stock-row:first-child .pcd-stock-remove{opacity:.45}
.pcd-stock-add{justify-self:flex-start;min-height:44px!important;border-radius:15px!important;padding:0 15px!important;background:#fff!important;color:var(--pcd-teal)!important}
.pcd-solve-actions{position:sticky;bottom:0;background:linear-gradient(180deg,rgba(249,252,254,0),#f9fcfe 35%,#f9fcfe 100%);padding-top:16px;margin-top:2px;z-index:3}
@media (max-width:760px){.pcd-stock-help{display:block}.pcd-stock-help span{text-align:left;display:block;margin-top:6px}.pcd-stock-row{grid-template-columns:1fr!important}.pcd-stock-remove{width:100%}.pcd-solve-actions{position:static}.pcd-solve-actions .pcd-btn{width:100%}}

</style>

<div class="pcd"><div class="pcd-wrap">
  <section class="pcd-hero">
    <div class="pcd-brand"><img class="pcd-logo" src="<?= pcd_h($logoUrl) ?>" alt="Logo"><div><div class="pcd-kicker">GLPI SCHOOL MANAGER</div><h1>Detalle de solicitud</h1><p class="pcd-sub">Vista sencilla con estado, respuestas y solución.</p></div></div>
    <div class="pcd-actions">
      <a class="pcd-btn primary" href="<?= pcd_h($newUrl) ?>"><?= pcd_icon('plus') ?><span>Crear incidencia</span></a>
      <a class="pcd-btn" href="<?= pcd_h($listUrl) ?>"><?= pcd_icon('list') ?><span>Mis solicitudes</span></a>
      <?php if ($ticket): ?><a class="pcd-btn" href="<?= pcd_h($nativeUrl) ?>"><?= pcd_icon('external') ?><span>Vista nativa GLPI</span></a><?php endif; ?>
    </div>
  </section>

  <?php if ($message): ?><div class="pcd-msg <?= pcd_h($messageType) ?>"><?= pcd_h($message) ?></div><?php endif; ?>

  <?php if ($error): ?>
    <div class="pcd-empty pcd-error"><?= pcd_h($error) ?></div>
  <?php else: ?>
  <section class="pcd-grid">
    <main class="pcd-card">
      <div class="pcd-title-row">
        <div class="pcd-title"><span class="pcd-id">#<?= (int)$ticket['id'] ?></span><h2 style="margin:0;color:var(--pcd-teal);font-size:clamp(25px,2.7vw,38px);letter-spacing:-.035em"><?= pcd_h($ticket['name'] ?? 'Solicitud') ?></h2></div>
        <span class="pcd-status <?= pcd_h($statusClass) ?>"><?= pcd_h($statusLabel) ?></span>
      </div>
      <div class="pcd-meta">
        <div class="pcd-m"><small>Creada</small><b><?= pcd_h($ticket['date'] ?? '') ?></b></div>
        <div class="pcd-m"><small>Última actualización</small><b><?= pcd_h($ticket['date_mod'] ?? '') ?></b></div>
        <div class="pcd-m"><small>Categoría</small><b><?= pcd_h($ticket['category_name'] ?: 'Sin categoría') ?></b></div>
        <div class="pcd-m"><small>Ubicación</small><b><?= pcd_location_detail_link((int)($ticket['locations_id'] ?? 0), $ticket['location_name'] ?: 'Sin ubicación') ?></b></div>
        <div class="pcd-m"><small>Prioridad</small><b><?= pcd_h(pcd_priority($ticket['priority'] ?? 3)) ?></b></div>
        <div class="pcd-m"><small>Origen</small><b><?= pcd_h($ticket['requesttypes_id'] ? 'Formulario / Helpdesk' : 'GLPI') ?></b></div>
      </div>
      <div class="pcd-content"><h3>Descripción enviada</h3><?= pcd_render_description($ticket['content'] ?? '') ?></div>
      <?php if ($isArchived): ?>
        <div class="pcd-resolved-cards">
          <article class="pcd-resolved-card solution-card">
            <h3><?= pcd_icon('check') ?> Respuesta / solución</h3>
            <p><?= $latestSolutionText !== '' ? nl2br(pcd_h($latestSolutionText)) : 'La incidencia está resuelta.' ?></p>
          </article>
          <article class="pcd-resolved-card material-card">
            <h3><?= pcd_icon('stock') ?> Material utilizado</h3>
            <?php if (!empty($usedMaterialRows) || !empty($usedMaterialDetail)): ?>
              <div class="pcd-material-list"><?= pcd_render_material_card_items($usedMaterialRows ?? [], $usedMaterialDetail) ?></div>
            <?php else: ?>
              <p>No se ha descontado material para esta resolución.</p>
            <?php endif; ?>
          </article>
        </div>
      <?php endif; ?>
    </main>

    <aside class="pcd-side">
      <div class="pcd-card"><h3>Estado actual</h3><div class="pcd-chip"><?= pcd_icon('check') ?><?= pcd_h($statusLabel) ?></div><p class="pcd-sub" style="margin-top:10px"><?= pcd_h($statusHelp) ?></p>
        <div class="pcd-progress" style="margin-top:12px">
          <?php $st=(int)($ticket['status'] ?? 1); ?>
          <div class="pcd-progress-row <?= $st>=1?'done':'' ?>"><span class="pcd-dot"></span>Abierta</div>
          <div class="pcd-progress-row <?= $st>=2&&$st<5?'active':($st>=5?'done':'') ?>"><span class="pcd-dot"></span>En curso / revisión</div>
          <div class="pcd-progress-row <?= $st==4?'active':'' ?>"><span class="pcd-dot"></span>En espera si falta información</div>
          <div class="pcd-progress-row <?= $st>=5?'done':'' ?>"><span class="pcd-dot"></span>Resuelta o cerrada</div>
        </div>
      </div>
      <div class="pcd-card pcd-native"><h3><?= pcd_icon('tool') ?> Gestión</h3>
        <div class="pcd-help"><b>Técnico asignado:</b><br><?= pcd_h($assignees ? implode(', ', array_map(fn($a)=>$a['name'], $assignees)) : 'Sin asignar') ?></div>
        <?php if ($canAssign && !$techs): ?><div class="pcd-msg error">No hay perfiles o usuarios <b>Técnico TIC</b> disponibles para asignar.</div><?php endif; ?>
        <?php if ($canAssign && !$isArchived): ?><form class="pcd-form" method="post"><?php pcd_csrf_field(); ?><input type="hidden" name="pc_action" value="assign_ticket"><input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>"><select name="tech_id" required><option value="">Técnico TIC...</option><?php foreach ($techs as $u): ?><option value="<?= (int)$u['id'] ?>"><?= pcd_h($u['label']) ?></option><?php endforeach; ?></select><button class="pcd-btn red" type="submit"><?= pcd_icon('user') ?><span>Asignar técnico</span></button></form><?php endif; ?>
        <a class="pcd-btn" href="<?= pcd_h($nativeUrl) ?>"><?= pcd_icon('external') ?><span>Vista nativa GLPI</span></a>
        <a class="pcd-btn" href="<?= pcd_h($listUrl) ?>"><?= pcd_icon('arrow') ?><span>Volver</span></a>
      </div>
    </aside>
  </section>

  <section class="pcd-card"><h3><?= pcd_icon('message') ?> Chat de seguimiento</h3>
    <div class="pcd-chat">
      <article class="pcd-bubble user"><small>Profesor · solicitud inicial</small><?= nl2br(pcd_h(pcd_plain($ticket['content'] ?? ''))) ?></article>
      <?php if (!$followups && !$solutions): ?><div class="pcd-empty">Todavía no hay respuestas públicas del equipo TIC. Cuando respondan aparecerán aquí como conversación.</div><?php endif; ?>
      <?php foreach (array_reverse($followups) as $fu): ?>
        <?php
          $fuUser = (int)($fu['users_id'] ?? 0);
          $fromRequester = in_array($fuUser, $requesterIds, true);
          $bubbleClass = $fromRequester ? 'user' : 'tic';
          $bubbleRole = $fromRequester ? 'Profesor' : 'Equipo TIC';
          $bubbleName = pcd_user_label($fuUser);
        ?>
        <article class="pcd-bubble <?= pcd_h($bubbleClass) ?>"><small><?= pcd_h($bubbleRole) ?> · <?= pcd_h($bubbleName) ?> · <?= pcd_h($fu['date'] ?? '') ?></small><?= nl2br(pcd_h(pcd_plain($fu['content'] ?? ''))) ?></article>
      <?php endforeach; ?>
      <?php foreach (array_reverse($solutions) as $so): ?><article class="pcd-bubble solution"><small><?= pcd_icon('check') ?> Solución propuesta · <?= pcd_h($so['date_creation'] ?? $so['date_mod'] ?? '') ?></small><?= pcd_render_solution_content($so['content'] ?? '') ?></article><?php endforeach; ?>
    </div>
    <?php if ($isArchived): ?>
      <div class="pcd-content pcd-archived"><h3><?= pcd_icon('check') ?> Incidencia ya resuelta</h3><p>Esta incidencia está archivada para el equipo TIC. No se pueden añadir más respuestas, reasignarla ni volver a marcarla como resuelta.</p></div>
    <?php endif; ?>
    <?php if ($canReply): ?>
      <div class="pcd-content"><h3>Añadir mensaje al chat</h3><form class="pcd-form" method="post"><?php pcd_csrf_field(); ?><input type="hidden" name="pc_action" value="reply_ticket"><input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>"><textarea name="reply_content" placeholder="Escribe un mensaje para continuar la conversación..." required></textarea><div class="pcd-form-actions"><button class="pcd-btn primary" type="submit"><?= pcd_icon('send') ?><span>Enviar mensaje</span></button></div></form></div>
    <?php endif; ?>
    <?php if ($canManage): ?>
      <div class="pcd-content pcd-solve-panel">
        <div class="pcd-solve-head">
          <div>
            <h3>Resolver solicitud</h3>
            <p>Primero escribe la respuesta para el profesor y debajo añade el material utilizado si lo hay.</p>
          </div>
          <span class="pcd-solve-badge"><?= pcd_icon('stock') ?> Material opcional</span>
        </div>
        <form class="pcd-form pcd-solve-form" method="post">
          <?php pcd_csrf_field(); ?>
          <input type="hidden" name="pc_action" value="solve_ticket">
          <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
          <label class="pcd-field-label" for="pcd_solution_content">Respuesta / solución para el profesor</label>
          <textarea id="pcd_solution_content" name="solution_content" placeholder="Ej: Se ha sustituido el ratón del aula 003 y se comprueba que el equipo funciona correctamente." required></textarea>
          <div class="pcd-stock-use" data-stock-dynamic="1">
            <div class="pcd-stock-help">
              <b><?= pcd_icon('stock') ?> Material utilizado</b>
              <span>Empieza con una línea. Cuando selecciones un material se abrirá otra nueva para añadir más sin límite.</span>
            </div>
            <div id="pcdStockRows" class="pcd-stock-rows">
              <div class="pcd-stock-row" data-stock-row>
                <div class="pcd-stock-select-wrap">
                  <label>Stock usado</label>
                  <select name="stock_item[]" aria-label="Material usado">
                    <option value="">No descontar material</option>
                    <?php $lastGroup=''; foreach ($pcdStockOptions as $opt): if ($lastGroup !== $opt['group']): if ($lastGroup !== ''): ?></optgroup><?php endif; $lastGroup=$opt['group']; ?><optgroup label="<?= pcd_h($lastGroup) ?>"><?php endif; ?>
                    <option value="<?= pcd_h($opt['value']) ?>"><?= pcd_h($opt['label']) ?></option>
                    <?php endforeach; if ($lastGroup !== ''): ?></optgroup><?php endif; ?>
                  </select>
                </div>
                <div class="pcd-stock-qty-wrap">
                  <label>Cantidad</label>
                  <input type="number" name="stock_qty[]" min="1" max="50" value="1" aria-label="Cantidad">
                </div>
                <button class="pcd-stock-remove" type="button" aria-label="Quitar material">×</button>
              </div>
            </div>
            <button id="pcdAddStockRow" class="pcd-btn pcd-stock-add" type="button"><?= pcd_icon('plus') ?><span>Añadir otro material</span></button>
            <template id="pcdStockRowTemplate">
              <div class="pcd-stock-row" data-stock-row>
                <div class="pcd-stock-select-wrap">
                  <label>Stock usado</label>
                  <select name="stock_item[]" aria-label="Material usado">
                    <option value="">No descontar material</option>
                    <?php $lastGroup=''; foreach ($pcdStockOptions as $opt): if ($lastGroup !== $opt['group']): if ($lastGroup !== ''): ?></optgroup><?php endif; $lastGroup=$opt['group']; ?><optgroup label="<?= pcd_h($lastGroup) ?>"><?php endif; ?>
                    <option value="<?= pcd_h($opt['value']) ?>"><?= pcd_h($opt['label']) ?></option>
                    <?php endforeach; if ($lastGroup !== ''): ?></optgroup><?php endif; ?>
                  </select>
                </div>
                <div class="pcd-stock-qty-wrap">
                  <label>Cantidad</label>
                  <input type="number" name="stock_qty[]" min="1" max="50" value="1" aria-label="Cantidad">
                </div>
                <button class="pcd-stock-remove" type="button" aria-label="Quitar material">×</button>
              </div>
            </template>
          </div>
          <div class="pcd-form-actions pcd-solve-actions">
            <button class="pcd-btn red" type="submit"><?= pcd_icon('check') ?><span>Marcar como resuelto</span></button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div></div>

<script>
(function(){
  const rowsBox = document.getElementById('pcdStockRows');
  const tpl = document.getElementById('pcdStockRowTemplate');
  const addBtn = document.getElementById('pcdAddStockRow');
  if (!rowsBox || !tpl) return;

  function rows(){ return Array.from(rowsBox.querySelectorAll('[data-stock-row]')); }
  function selectedRows(){ return rows().filter(row => (row.querySelector('select') || {}).value); }
  function refresh(){
    const all = rows();
    all.forEach((row, index) => {
      const select = row.querySelector('select');
      row.classList.toggle('is-filled', !!(select && select.value));
      const remove = row.querySelector('.pcd-stock-remove');
      if (remove) remove.style.visibility = all.length <= 1 ? 'hidden' : 'visible';
    });
  }
  function addRow(focus){
    const node = tpl.content.firstElementChild.cloneNode(true);
    rowsBox.appendChild(node);
    bind(node);
    refresh();
    if (focus) {
      const select = node.querySelector('select');
      if (select) select.focus();
    }
    return node;
  }
  function ensureTrailingBlank(){
    const all = rows();
    const last = all[all.length - 1];
    const lastSelect = last ? last.querySelector('select') : null;
    if (lastSelect && lastSelect.value) addRow(false);
    refresh();
  }
  function bind(row){
    const select = row.querySelector('select');
    const qty = row.querySelector('input[type="number"]');
    const remove = row.querySelector('.pcd-stock-remove');
    if (select) select.addEventListener('change', ensureTrailingBlank);
    if (qty) qty.addEventListener('input', function(){ if (parseInt(qty.value || '0', 10) < 1) qty.value = 1; });
    if (remove) remove.addEventListener('click', function(){
      const all = rows();
      if (all.length <= 1) {
        if (select) select.value = '';
        if (qty) qty.value = 1;
      } else {
        row.remove();
      }
      refresh();
    });
  }
  rows().forEach(bind);
  if (addBtn) addBtn.addEventListener('click', function(){ addRow(true); });
  const form = rowsBox.closest('form');
  if (form) form.addEventListener('submit', function(){
    // Evita enviar filas vacias; el PHP igualmente las ignora, pero asi queda mas limpio.
    rows().forEach(function(row){
      const select = row.querySelector('select');
      if (select && !select.value && rows().length > 1) row.remove();
    });
  });
  refresh();
})();
</script>

<?php Html::footer(); ?>
