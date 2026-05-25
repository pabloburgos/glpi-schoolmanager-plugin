<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');

global $DB, $CFG_GLPI;
if (!isset($DB) || !is_object($DB)) {
    $DB = $GLOBALS['DB'] ?? null;
}
if ((!isset($DB) || !is_object($DB)) && class_exists('DBConnection') && method_exists('DBConnection', 'getReadConnection')) {
    try {
        $DB = DBConnection::getReadConnection();
    } catch (Throwable $e) {
        $DB = null;
    }
}

$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$userId = (int) Session::getLoginUserID();

Html::header('Mis solicitudes', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');

function pc_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pc_status_label($status) {
    $map = [
        1 => ['Abierta', 'new'],
        2 => ['En curso', 'work'],
        3 => ['Planificada', 'work'],
        4 => ['En espera', 'wait'],
        5 => ['Resuelto', 'done'],
        6 => ['Cerrado', 'closed'],
    ];
    return $map[(int)$status] ?? ['Estado ' . (int)$status, 'new'];
}

function pc_priority_label($priority) {
    $map = [1=>'Muy baja',2=>'Baja',3=>'Media',4=>'Alta',5=>'Muy alta',6=>'Mayor'];
    return $map[(int)$priority] ?? 'Media';
}

function pc_plain($html) {
    $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text));
    return $text;
}
function pc_solution_bits($html) {
    $text = (string)$html;
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "
", $text);
    $text = preg_replace('/<\s*\/p\s*>/i', "
", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["
", "
"], "
", $text);
    $text = str_replace('Material utilizado de Control de stock:', 'Material utilizado:', $text);
    $materials = [];
    if (preg_match('/Material utilizado\s*:\s*(.*)$/isu', $text, $m)) {
        foreach (preg_split('/\n+/', trim($m[1])) as $line) {
            $line = trim(preg_replace('/^[-•]\s*/u', '', (string)$line));
            if ($line !== '') { $materials[] = $line; }
        }
    }
    $solution = trim((string)preg_replace('/\n*Material utilizado\s*:\s*.*$/isu', '', $text));
    $solution = preg_replace('/\s+/', ' ', $solution);
    return [$solution, array_values(array_unique($materials))];
}

function pc_ticket_public_state($ticket) {
    $status = (int)($ticket['status'] ?? 0);
    $hasPublicAnswer = trim((string)($ticket['last_answer'] ?? '')) !== '' || (int)($ticket['followups_count'] ?? 0) > 0;

    if ($status >= 5) {
        return ['Resuelta', 'done', 'done', 'Ticket finalizado o con solución publicada'];
    }
    if ($status === 4) {
        return ['Esperando información', 'wait', 'wait', 'El equipo TIC necesita más datos para continuar'];
    }
    if ($hasPublicAnswer) {
        return ['Respondida', 'answered', 'answered', 'Ya hay una respuesta pública del equipo TIC'];
    }
    return ['Abierta', 'new', 'open', 'Pendiente de primera respuesta pública'];
}


$tickets = [];
$loadError = '';
try {
    if (!isset($DB) || !is_object($DB) || !method_exists($DB, 'request')) {
        throw new RuntimeException('No se pudo acceder al motor de consultas seguro de GLPI.');
    }

    // GLPI bloquea las consultas SQL directas en algunos contextos de plugin.
    // Por eso usamos $DB->request(), que es el iterador oficial/seguro de GLPI.
    $ticketIds = [];
    $tuIterator = $DB->request([
        'SELECT' => ['tickets_id'],
        'FROM'   => 'glpi_tickets_users',
        'WHERE'  => [
            'users_id' => $userId,
            'type'     => 1, // solicitante
        ],
        'ORDER'  => ['tickets_id DESC'],
        'LIMIT'  => 200,
    ]);

    foreach ($tuIterator as $tuRow) {
        $tid = (int)($tuRow['tickets_id'] ?? 0);
        if ($tid > 0) { $ticketIds[$tid] = $tid; }
    }

    if ($ticketIds) {
        $ticketIterator = $DB->request([
            'FROM'  => 'glpi_tickets',
            'WHERE' => [
                'id'         => array_values($ticketIds),
                'is_deleted' => 0,
            ],
            'ORDER' => ['date_mod DESC', 'id DESC'],
            'LIMIT' => 80,
        ]);

        $locNames = [];
        $catNames = [];

        foreach ($ticketIterator as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }

            $locationsId = (int)($row['locations_id'] ?? 0);
            $categoryId  = (int)($row['itilcategories_id'] ?? 0);

            $row['location_name'] = '';
            if ($locationsId > 0) {
                if (!array_key_exists($locationsId, $locNames)) {
                    $locNames[$locationsId] = '';
                    if (class_exists('Location')) {
                        $loc = new Location();
                        if ($loc->getFromDB($locationsId)) {
                            $locNames[$locationsId] = $loc->fields['name'] ?? '';
                        }
                    }
                }
                $row['location_name'] = $locNames[$locationsId];
            }

            $row['category_name'] = '';
            if ($categoryId > 0) {
                if (!array_key_exists($categoryId, $catNames)) {
                    $catNames[$categoryId] = '';
                    if (class_exists('ITILCategory')) {
                        $cat = new ITILCategory();
                        if ($cat->getFromDB($categoryId)) {
                            $catNames[$categoryId] = $cat->fields['completename'] ?? ($cat->fields['name'] ?? '');
                        }
                    }
                }
                $row['category_name'] = $catNames[$categoryId];
            }

            $row['last_answer'] = '';
            $row['answer_date'] = '';
            $row['solution_text'] = '';
            $row['used_material'] = [];
            $row['followups_count'] = 0;

            $followups = [];
            $followIterator = $DB->request([
                'FROM'  => 'glpi_itilfollowups',
                'WHERE' => [
                    'itemtype'   => 'Ticket',
                    'items_id'    => $id,
                    'is_private'  => 0,
                ],
                'ORDER' => ['date DESC', 'id DESC'],
                'LIMIT' => 30,
            ]);
            foreach ($followIterator as $fu) {
                $followups[] = $fu;
            }
            $row['followups_count'] = count($followups);
            if ($followups) {
                $row['last_answer'] = pc_plain($followups[0]['content'] ?? '');
                $row['answer_date'] = $followups[0]['date'] ?? '';
            }

            // En tickets resueltos se muestra siempre la solución real, no el último mensaje del chat.
            if (method_exists($DB, 'tableExists') && $DB->tableExists('glpi_itilsolutions')) {
                $solutionIterator = $DB->request([
                    'FROM'  => 'glpi_itilsolutions',
                    'WHERE' => [
                        'itemtype' => 'Ticket',
                        'items_id'  => $id,
                    ],
                    'ORDER' => ['id DESC'],
                    'LIMIT' => 1,
                ]);
                foreach ($solutionIterator as $so) {
                    [$solutionText, $usedMaterial] = pc_solution_bits($so['content'] ?? '');
                    $row['solution_text'] = $solutionText;
                    $row['used_material'] = $usedMaterial;
                    if ((int)($row['status'] ?? 0) >= 5 || $row['last_answer'] === '') {
                        $row['last_answer'] = $solutionText;
                    }
                    break;
                }
            }

            $tickets[] = $row;
        }
    }
} catch (Throwable $e) {
    $tickets = [];
    $loadError = $e->getMessage();
}

$total = count($tickets);
$open = 0; $waiting = 0; $solved = 0; $answered = 0;
foreach ($tickets as $t) {
    [$stateLabel,$stateClass,$stateFilter,$stateHint] = pc_ticket_public_state($t);
    if ($stateFilter === 'wait') { $waiting++; }
    if ($stateFilter === 'done') { $solved++; }
    if ($stateFilter === 'answered') { $answered++; }
    if ($stateFilter === 'open') { $open++; }
}
?>
<style>
.pc-requests{--teal:#0b4f6c;--teal2:#07384d;--gold:#e53935;--line:#d9e7ef;--muted:#627386;--ink:#102638;min-height:calc(100vh - 76px);padding:clamp(10px,1.3vw,20px);background:radial-gradient(circle at top right,rgba(11,79,108,.14),transparent 34%),linear-gradient(135deg,#f6f9fc,#eef6fb);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
.pc-wrap{max-width:1500px;margin:0 auto;display:grid;gap:14px}.pc-hero{display:flex;align-items:center;justify-content:space-between;gap:14px;background:linear-gradient(120deg,#fff,#effffc);border:1px solid var(--line);border-radius:26px;padding:16px 20px;box-shadow:0 18px 60px rgba(7,56,77,.09)}.pc-brand{display:flex;align-items:center;gap:16px}.pc-logo{height:62px;max-width:210px;object-fit:contain}.pc-kicker{font-weight:950;color:var(--teal);letter-spacing:.1em;font-size:13px}.pc-hero h1{margin:1px 0 0;font-size:clamp(30px,3.8vw,50px);line-height:.98;color:var(--teal2)}.pc-hero p{margin:3px 0 0;color:var(--muted);font-weight:850}.pc-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.pc-btn{border-radius:999px;padding:11px 15px;font-weight:950;text-decoration:none!important;border:1px solid var(--line);background:#fff;color:var(--teal2)!important;white-space:nowrap}.pc-btn.primary{background:var(--teal);color:#fff!important;border-color:var(--teal)}
.pc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.pc-stat{background:#fff;border:1px solid var(--line);border-radius:20px;padding:14px;box-shadow:0 10px 34px rgba(7,56,77,.06)}.pc-stat b{display:block;font-size:30px;color:var(--teal2);line-height:1}.pc-stat span{display:block;margin-top:4px;color:var(--muted);font-weight:900}.pc-tools{display:grid;grid-template-columns:1fr auto;gap:10px;background:#fff;border:1px solid var(--line);border-radius:22px;padding:12px}.pc-search{display:flex;align-items:center;gap:9px;border:1px solid var(--line);background:#f8fffd;border-radius:16px;padding:10px 12px}.pc-search input{border:0;background:transparent;outline:0;font-weight:850;font-size:16px;width:100%;color:var(--ink)}.pc-filters{display:flex;gap:8px;flex-wrap:wrap}.pc-filter{border:1px solid var(--line);background:#fff;border-radius:14px;padding:10px 12px;color:var(--teal2);font-weight:950;cursor:pointer}.pc-filter.active{background:var(--teal);color:#fff;border-color:var(--teal)}
.pc-list{display:grid;gap:10px}.pc-ticket{background:#fff;border:1px solid var(--line);border-radius:22px;padding:14px;box-shadow:0 10px 34px rgba(7,56,77,.06);display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center}.pc-ticket:hover{border-color:var(--teal)}.pc-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.pc-title h2{margin:0;color:var(--teal2);font-size:22px}.pc-id{background:#e8f7f5;border-radius:999px;padding:5px 9px;color:var(--teal2);font-weight:950}.pc-meta{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.pc-pill{border:1px solid var(--line);border-radius:999px;background:#f8fffd;padding:6px 9px;color:var(--muted);font-weight:850;font-size:13px}.pc-status{border-radius:999px;padding:7px 10px;font-weight:950;font-size:13px}.pc-status.new{background:#eaf6ff;color:#0b5a82}.pc-status.work{background:#fff7dc;color:#806000}.pc-status.wait{background:#f2eaff;color:#5c3796}.pc-status.done{background:#e9fbef;color:#176a31}.pc-status.closed{background:#edf1f4;color:#46545c}.pc-answer{margin:8px 0 0;padding:10px 12px;border-radius:16px;background:#f8fffd;border:1px dashed var(--line);color:#3f5964;font-weight:800;line-height:1.35}.pc-empty{background:#fff;border:1px solid var(--line);border-radius:22px;padding:28px;text-align:center;color:var(--muted);font-weight:900}.pc-ticket-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.pc-mini{border:1px solid var(--line);border-radius:14px;padding:10px 12px;text-decoration:none!important;font-weight:950;color:var(--teal2)!important;background:#fff}.pc-mini.primary{background:var(--teal);color:#fff!important;border-color:var(--teal)}
@media(max-width:900px){.pc-hero,.pc-ticket,.pc-tools{grid-template-columns:1fr;display:grid}.pc-actions,.pc-ticket-actions{justify-content:flex-start}.pc-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.pc-requests{padding:8px}.pc-brand{align-items:flex-start}.pc-logo{height:48px}.pc-stats{grid-template-columns:1fr}.pc-ticket{padding:12px}.pc-title h2{font-size:19px}}

/* v245: limpieza visual y botones mejorados */
.pc-requests{background:linear-gradient(135deg,#f6f9fc 0%,#eef5f8 100%)!important;}
.pc-requests .pc-hero{
  background:#fffdfa!important;
  border-radius:30px!important;
  box-shadow:0 16px 42px rgba(7,56,77,.08)!important;
}
.pc-requests .pc-brand{gap:18px!important;align-items:center!important;}
.pc-requests .pc-brand:before,
.pc-requests .pc-stat:before{display:none!important;content:none!important;}
.pc-requests .pc-logo{
  height:96px!important;
  max-width:240px!important;
  object-fit:contain!important;
  mix-blend-mode:multiply!important;
  filter:saturate(1.03) contrast(1.02)!important;
}
.pc-requests .pc-kicker{color:#5b7384!important;letter-spacing:.11em!important;}
.pc-requests .pc-hero p{max-width:630px!important;line-height:1.28!important;}
.pc-requests .pc-stats{gap:16px!important;}
.pc-requests .pc-stat{
  border-radius:24px!important;
  background:#fff!important;
  box-shadow:0 12px 32px rgba(7,56,77,.07)!important;
}
.pc-requests .pc-stat:hover{transform:translateY(-3px)!important;}
.pc-requests .pc-btn,
.pc-requests .pc-mini{
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  gap:10px!important;
  position:relative!important;
  overflow:hidden!important;
  transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, border-color .22s ease, background-color .22s ease, color .22s ease!important;
}
.pc-requests .pc-btn::before,
.pc-requests .pc-mini::before,
.pc-requests .pc-filter::before{
  content:"";
  position:absolute;
  inset:0;
  background:linear-gradient(120deg,transparent 0%, rgba(255,255,255,.04) 30%, rgba(255,255,255,.18) 48%, rgba(255,255,255,.04) 66%, transparent 100%);
  transform:translateX(-135%);
  transition:transform .55s ease;
  pointer-events:none;
}
.pc-requests .pc-btn:hover,
.pc-requests .pc-mini:hover,
.pc-requests .pc-filter:hover{transform:translateY(-4px)!important;}
.pc-requests .pc-btn:hover::before,
.pc-requests .pc-mini:hover::before,
.pc-requests .pc-filter:hover::before{transform:translateX(135%);}
.pc-requests .pc-btn svg,
.pc-requests .pc-mini svg{width:19px!important;height:19px!important;stroke:currentColor!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home{
  background:linear-gradient(135deg,#8f1d1d 0%, #a82323 54%, #b62d2d 100%)!important;
  border-color:#972121!important;
  color:#fff!important;
  box-shadow:0 18px 38px rgba(165,31,36,.22)!important;
}
.pc-requests .pc-btn-home:hover{box-shadow:0 24px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
.pc-requests .pc-btn.primary{
  background:linear-gradient(135deg,#0a4964 0%,#0b5f7a 100%)!important;
  border-color:#0a526a!important;
  color:#fff!important;
  box-shadow:0 18px 38px rgba(7,56,77,.18)!important;
}
.pc-requests .pc-btn.primary:hover{background:linear-gradient(135deg,#0b5f7a 0%, #117a80 100%)!important;box-shadow:0 24px 46px rgba(7,56,77,.24)!important;}
.pc-requests .pc-btn-native,
.pc-requests .pc-mini{
  background:#fff!important;
  border-color:#cfe0e8!important;
  color:#11384b!important;
  box-shadow:0 10px 26px rgba(7,56,77,.07)!important;
}
.pc-requests .pc-btn-native:hover,
.pc-requests .pc-mini:hover{border-color:#a9c2cf!important;background:#f8fbfd!important;box-shadow:0 18px 38px rgba(7,56,77,.12)!important;}
.pc-requests .pc-mini.primary{
  background:linear-gradient(135deg,#0a4964 0%,#0b5f7a 100%)!important;
  border-color:#0a526a!important;
  color:#fff!important;
}
.pc-requests .pc-mini.primary:hover{background:linear-gradient(135deg,#0b5f7a 0%, #117a80 100%)!important;}
.pc-requests .pc-mini span,
.pc-requests .pc-btn span{position:relative;z-index:1;}
.pc-requests .pc-ticket-actions{gap:10px!important;}
.pc-requests .pc-tools{box-shadow:0 16px 36px rgba(7,56,77,.07)!important;}
.pc-requests .pc-filter{position:relative;overflow:hidden;box-shadow:0 10px 24px rgba(7,56,77,.06)!important;}
.pc-requests .pc-filter.active{background:linear-gradient(135deg,#8f1d1d 0%, #b72e2e 100%)!important;border-color:#972121!important;box-shadow:0 18px 38px rgba(165,31,36,.20)!important;}
@media(max-width:900px){
  .pc-requests .pc-logo{height:82px!important;max-width:200px!important;}
}
@media(max-width:560px){
  .pc-requests .pc-logo{height:68px!important;max-width:170px!important;}
}


/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>

<style id="pc-requests-v230-clean">
.pc-requests{background:linear-gradient(135deg,#f6f9fc 0%,#f8fbfd 56%,#fffaf0 100%)!important;}
.pc-requests .pc-hero{position:relative!important;overflow:hidden!important;background:linear-gradient(135deg,#ffffff 0%,#f8fbfd 68%,#fffaf0 100%)!important;border-radius:28px!important;box-shadow:0 16px 46px rgba(7,56,77,.07)!important;}
.pc-requests .pc-hero:before,.pc-requests .pc-hero:after{display:none!important;content:none!important;}
.pc-requests .pc-brand{min-width:0!important}.pc-requests .pc-kicker{color:#607684!important}.pc-requests .pc-hero h1{letter-spacing:-.045em!important}.pc-requests .pc-actions .pc-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:44px!important}.pc-requests .pc-btn.primary{box-shadow:0 12px 28px rgba(7,56,77,.16)!important}.pc-requests .pc-tools,.pc-requests .pc-stat,.pc-requests .pc-ticket{background:rgba(255,255,255,.96)!important}.pc-requests .pc-stat{border-radius:22px!important}.pc-requests .pc-ticket{border-radius:24px!important}.pc-requests .pc-answer{background:#f8fbfd!important}.pc-requests .pc-logo{background:#fff!important;border-radius:20px!important;padding:6px!important;border:1px solid #d9e7ef!important}
@media(max-width:900px){.pc-requests .pc-hero{display:grid!important;grid-template-columns:1fr!important}.pc-requests .pc-actions{justify-content:flex-start!important}.pc-requests .pc-actions .pc-btn{flex:1 1 180px!important}}

/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>
<style id="gestion-schoolmanager-global-override"><?php @readfile(__DIR__ . '/../css/gestion-schoolmanager-theme.css'); ?>
/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>

<style id="pc-requests-v244-polish">
.pc-requests{
  --pc-red:#a51f24;
  --pc-red-dark:#86171c;
  --pc-navy:#07384d;
  --pc-teal:#0b5f7a;
  --pc-soft:#f7fbfd;
  --pc-cream:#fff8ec;
  --pc-shadow:0 18px 46px rgba(7,56,77,.11);
  background:
    radial-gradient(circle at 10% 0%,rgba(239,163,0,.18),transparent 32%),
    radial-gradient(circle at 92% 6%,rgba(165,31,36,.10),transparent 30%),
    linear-gradient(135deg,#f6f9fc 0%,#f9fcfd 52%,#fff8ec 100%)!important;
}
.pc-requests .pc-wrap{gap:18px!important;}
.pc-requests .pc-hero{
  align-items:center!important;
  background:linear-gradient(135deg,rgba(255,255,255,.98) 0%,rgba(249,252,253,.98) 62%,rgba(255,248,236,.98) 100%)!important;
  border:1px solid rgba(207,224,232,.95)!important;
  box-shadow:var(--pc-shadow)!important;
  isolation:isolate!important;
  padding:20px 24px!important;
}
.pc-requests .pc-hero:after{
  content:""!important;
  position:absolute!important;
  right:-60px!important;
  top:-90px!important;
  width:260px!important;
  height:260px!important;
  border-radius:999px!important;
  background:radial-gradient(circle,rgba(165,31,36,.12),transparent 66%)!important;
  display:block!important;
  z-index:-1!important;
}
.pc-requests .pc-brand{gap:18px!important;position:relative!important;}
.pc-requests .pc-brand:before{
  content:""!important;
  width:5px!important;
  align-self:stretch!important;
  min-height:74px!important;
  border-radius:999px!important;
  background:linear-gradient(180deg,var(--pc-red),#efa300,var(--pc-teal))!important;
  box-shadow:0 12px 26px rgba(165,31,36,.14)!important;
  order:0!important;
}
.pc-requests .pc-logo{
  order:1!important;
  height:76px!important;
  max-width:158px!important;
  width:auto!important;
  background:transparent!important;
  border:0!important;
  padding:0!important;
  border-radius:0!important;
  box-shadow:none!important;
  filter:drop-shadow(0 10px 16px rgba(7,56,77,.10))!important;
  transition:transform .22s cubic-bezier(.2,.8,.2,1),filter .22s ease!important;
}
.pc-requests .pc-brand:hover .pc-logo{transform:translateY(-2px) scale(1.015)!important;filter:drop-shadow(0 14px 18px rgba(7,56,77,.13))!important;}
.pc-requests .pc-brand>div{order:2!important;}
.pc-requests .pc-kicker{color:#607684!important;font-size:14px!important;}
.pc-requests .pc-hero h1{color:var(--pc-navy)!important;text-wrap:balance!important;}
.pc-requests .pc-hero p{font-size:16px!important;color:#5f7281!important;}
.pc-requests .pc-actions{align-items:center!important;gap:12px!important;}
.pc-requests .pc-btn,
.pc-requests .pc-mini,
.pc-requests .pc-filter{
  position:relative!important;
  overflow:hidden!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  gap:9px!important;
  min-height:48px!important;
  border-radius:18px!important;
  font-weight:950!important;
  letter-spacing:-.01em!important;
  transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s ease,background .2s ease,border-color .2s ease,color .2s ease!important;
}
.pc-requests .pc-btn{padding:12px 18px!important;}
.pc-requests .pc-mini{padding:11px 14px!important;}
.pc-requests .pc-btn:before,
.pc-requests .pc-mini:before,
.pc-requests .pc-filter:before{
  content:""!important;
  position:absolute!important;
  inset:0!important;
  background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.10) 30%,rgba(255,255,255,.34) 48%,rgba(255,255,255,.10) 66%,transparent 100%)!important;
  transform:translateX(-140%)!important;
  transition:transform .55s ease!important;
  pointer-events:none!important;
}
.pc-requests .pc-btn:hover:before,
.pc-requests .pc-mini:hover:before,
.pc-requests .pc-filter:hover:before{transform:translateX(140%)!important;}
.pc-requests .pc-btn:hover,
.pc-requests .pc-mini:hover,
.pc-requests .pc-filter:hover{transform:translateY(-3px)!important;text-decoration:none!important;}
.pc-requests .pc-btn:active,
.pc-requests .pc-mini:active,
.pc-requests .pc-filter:active{transform:translateY(-1px) scale(.99)!important;}
.pc-requests .pc-btn.primary,
.pc-requests .pc-mini.primary{
  background:linear-gradient(135deg,var(--pc-navy),var(--pc-teal))!important;
  border-color:#06364a!important;
  color:#fff!important;
  box-shadow:0 16px 34px rgba(7,56,77,.20)!important;
}
.pc-requests .pc-btn.primary:hover,
.pc-requests .pc-mini.primary:hover{
  background:linear-gradient(135deg,#08445d,#0d6b88)!important;
  box-shadow:0 22px 42px rgba(7,56,77,.26)!important;
}
.pc-requests .pc-btn-home{
  background:linear-gradient(135deg,var(--pc-red-dark),var(--pc-red))!important;
  border-color:#7d171b!important;
  color:#fff!important;
  box-shadow:0 16px 34px rgba(165,31,36,.20)!important;
}
.pc-requests .pc-btn-home:hover{
  background:linear-gradient(135deg,#7b1519,#bb2730)!important;
  box-shadow:0 22px 42px rgba(165,31,36,.28)!important;
}
.pc-requests .pc-btn-native,
.pc-requests .pc-mini:not(.primary){
  background:rgba(255,255,255,.92)!important;
  border-color:#cfe0e8!important;
  color:var(--pc-navy)!important;
  box-shadow:0 10px 28px rgba(7,56,77,.06)!important;
}
.pc-requests .pc-btn-native:hover,
.pc-requests .pc-mini:not(.primary):hover{
  border-color:rgba(165,31,36,.42)!important;
  color:var(--pc-red-dark)!important;
  box-shadow:0 18px 34px rgba(7,56,77,.12)!important;
}
.pc-requests .pc-btn-ico,
.pc-requests .pc-mini-ico{
  width:18px!important;
  height:18px!important;
  display:inline-block!important;
  flex:0 0 18px!important;
  background:currentColor!important;
  opacity:.95!important;
}
.pc-requests .pc-home-icon{clip-path:polygon(50% 0,100% 42%,84% 42%,84% 100%,60% 100%,60% 64%,40% 64%,40% 100%,16% 100%,16% 42%,0 42%)!important;}
.pc-requests .pc-plus-icon{clip-path:polygon(42% 0,58% 0,58% 42%,100% 42%,100% 58%,58% 58%,58% 100%,42% 100%,42% 58%,0 58%,0 42%,42% 42%)!important;}
.pc-requests .pc-list-icon{clip-path:polygon(0 10%,18% 10%,18% 26%,0 26%,0 10%,28% 12%,100% 12%,100% 24%,28% 24%,28% 12%,0 42%,18% 42%,18% 58%,0 58%,0 42%,28% 44%,100% 44%,100% 56%,28% 56%,28% 44%,0 74%,18% 74%,18% 90%,0 90%,0 74%,28% 76%,100% 76%,100% 88%,28% 88%,28% 76%)!important;}
.pc-requests .pc-eye-icon{clip-path:ellipse(49% 32% at 50% 50%)!important;position:relative!important;}
.pc-requests .pc-open-icon{clip-path:polygon(8% 18%,62% 18%,62% 32%,28% 32%,28% 72%,68% 72%,68% 48%,82% 48%,82% 86%,8% 86%,8% 18%,58% 8%,92% 8%,92% 42%,78% 42%,78% 31%,50% 59%,41% 50%,69% 22%,58% 22%)!important;}
.pc-requests .pc-stats{gap:14px!important;}
.pc-requests .pc-stat{
  position:relative!important;
  overflow:hidden!important;
  border-radius:24px!important;
  padding:18px 18px!important;
  box-shadow:0 14px 34px rgba(7,56,77,.075)!important;
  transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s ease,border-color .2s ease!important;
}
.pc-requests .pc-stat:before{
  content:""!important;
  position:absolute!important;
  inset:auto 18px 0 18px!important;
  height:4px!important;
  border-radius:999px 999px 0 0!important;
  background:linear-gradient(90deg,var(--pc-red),#efa300,var(--pc-teal))!important;
  opacity:.72!important;
}
.pc-requests .pc-stat:hover{transform:translateY(-3px)!important;border-color:#c7dce6!important;box-shadow:0 20px 44px rgba(7,56,77,.12)!important;}
.pc-requests .pc-stat b{font-size:34px!important;color:var(--pc-teal)!important;}
.pc-requests .pc-tools{
  padding:14px!important;
  border-radius:26px!important;
  box-shadow:0 16px 36px rgba(7,56,77,.07)!important;
}
.pc-requests .pc-search{
  background:linear-gradient(135deg,#fff,#f8fbfd)!important;
  border-radius:20px!important;
  min-height:54px!important;
  transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease!important;
}
.pc-requests .pc-search:focus-within{
  border-color:rgba(11,95,122,.55)!important;
  box-shadow:0 0 0 5px rgba(11,95,122,.10),0 16px 34px rgba(7,56,77,.09)!important;
  transform:translateY(-1px)!important;
}
.pc-requests .pc-filter{min-height:54px!important;background:#fff!important;color:var(--pc-navy)!important;box-shadow:0 8px 22px rgba(7,56,77,.05)!important;}
.pc-requests .pc-filter.active,
.pc-requests .pc-filter.active:hover{
  background:linear-gradient(135deg,var(--pc-red-dark),var(--pc-red))!important;
  color:#fff!important;
  border-color:#7d171b!important;
  box-shadow:0 16px 34px rgba(165,31,36,.22)!important;
}
.pc-requests .pc-filter:not(.active):hover{border-color:rgba(165,31,36,.38)!important;color:var(--pc-red-dark)!important;box-shadow:0 14px 30px rgba(7,56,77,.10)!important;}
.pc-requests .pc-ticket{
  border-radius:26px!important;
  padding:18px!important;
  border-color:#d6e7ef!important;
  box-shadow:0 14px 36px rgba(7,56,77,.075)!important;
  transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s ease,border-color .2s ease!important;
}
.pc-requests .pc-ticket:hover{
  transform:translateY(-4px)!important;
  border-color:rgba(11,95,122,.42)!important;
  box-shadow:0 24px 54px rgba(7,56,77,.14)!important;
}
.pc-requests .pc-id{background:linear-gradient(135deg,#e9fbf7,#fff8ec)!important;color:var(--pc-navy)!important;border:1px solid #d9e7ef!important;}
.pc-requests .pc-title h2{letter-spacing:-.025em!important;}
.pc-requests .pc-pill{background:linear-gradient(135deg,#fff,#f8fbfd)!important;border-color:#d7e7ef!important;color:#29495b!important;}
.pc-requests .pc-answer{background:linear-gradient(135deg,#fbfdfe,#f7fbfd)!important;border-color:#cfe0e8!important;color:#29495b!important;}
.pc-requests .pc-status{box-shadow:inset 0 0 0 1px rgba(255,255,255,.42)!important;}
.pc-requests .pc-empty{box-shadow:0 14px 36px rgba(7,56,77,.075)!important;}
@media(max-width:900px){
  .pc-requests .pc-brand{display:grid!important;grid-template-columns:auto 1fr!important;gap:12px!important;}
  .pc-requests .pc-brand:before{grid-row:1/3!important;min-height:auto!important;}
  .pc-requests .pc-logo{height:64px!important;max-width:142px!important;}
  .pc-requests .pc-brand>div{grid-column:2!important;}
  .pc-requests .pc-actions{display:grid!important;grid-template-columns:1fr!important;width:100%!important;}
  .pc-requests .pc-btn{width:100%!important;}
}
@media(max-width:560px){
  .pc-requests .pc-hero{padding:16px!important;border-radius:22px!important;}
  .pc-requests .pc-brand{grid-template-columns:1fr!important;}
  .pc-requests .pc-brand:before{display:none!important;}
  .pc-requests .pc-logo{height:58px!important;max-width:138px!important;}
  .pc-requests .pc-brand>div{grid-column:auto!important;}
  .pc-requests .pc-tools{grid-template-columns:1fr!important;}
  .pc-requests .pc-filters{display:grid!important;grid-template-columns:1fr 1fr!important;}
  .pc-requests .pc-filter{width:100%!important;}
}

/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>

<style id="pc-requests-v245-final">
.pc-requests{background:linear-gradient(135deg,#f6f9fc 0%,#eef5f8 56%,#f7fafb 100%)!important;}
.pc-requests .pc-hero{background:#fffdfa!important;border-radius:30px!important;box-shadow:0 16px 42px rgba(7,56,77,.08)!important;}
.pc-requests .pc-hero:before,.pc-requests .pc-hero:after,.pc-requests .pc-brand:before,.pc-requests .pc-stat:before{display:none!important;content:none!important;}
.pc-requests .pc-logo{height:96px!important;max-width:240px!important;object-fit:contain!important;background:transparent!important;border:0!important;padding:0!important;border-radius:0!important;box-shadow:none!important;mix-blend-mode:multiply!important;filter:saturate(1.03) contrast(1.02)!important;}
.pc-requests .pc-stats{gap:16px!important;}
.pc-requests .pc-stat{background:#fff!important;border-radius:24px!important;box-shadow:0 12px 32px rgba(7,56,77,.07)!important;}
.pc-requests .pc-stat:hover{transform:translateY(-3px)!important;}
.pc-requests .pc-btn,.pc-requests .pc-mini{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;position:relative!important;overflow:hidden!important;transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, border-color .22s ease, background-color .22s ease, color .22s ease!important;}
.pc-requests .pc-btn::before,.pc-requests .pc-mini::before,.pc-requests .pc-filter::before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent 0%, rgba(255,255,255,.05) 30%, rgba(255,255,255,.16) 48%, rgba(255,255,255,.05) 66%, transparent 100%);transform:translateX(-135%);transition:transform .55s ease;pointer-events:none;}
.pc-requests .pc-btn:hover,.pc-requests .pc-mini:hover,.pc-requests .pc-filter:hover{transform:translateY(-4px)!important;}
.pc-requests .pc-btn:hover::before,.pc-requests .pc-mini:hover::before,.pc-requests .pc-filter:hover::before{transform:translateX(135%);}
.pc-requests .pc-btn svg,.pc-requests .pc-mini svg{width:19px!important;height:19px!important;stroke:currentColor!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;flex:0 0 auto!important;position:relative;z-index:1;}
.pc-requests .pc-btn span,.pc-requests .pc-mini span{position:relative;z-index:1;}
.pc-requests .pc-btn-home{background:linear-gradient(135deg,#8f1d1d 0%,#a82323 54%,#b62d2d 100%)!important;border-color:#972121!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home:hover{background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;box-shadow:0 24px 46px rgba(165,31,36,.28)!important;}
.pc-requests .pc-btn.primary,.pc-requests .pc-mini.primary{background:linear-gradient(135deg,#0a4964 0%,#0b5f7a 100%)!important;border-color:#0a526a!important;color:#fff!important;box-shadow:0 18px 38px rgba(7,56,77,.18)!important;}
.pc-requests .pc-btn.primary:hover,.pc-requests .pc-mini.primary:hover{background:linear-gradient(135deg,#0b5f7a 0%,#117a80 100%)!important;box-shadow:0 24px 46px rgba(7,56,77,.24)!important;}
.pc-requests .pc-btn-native,.pc-requests .pc-mini{background:#fff!important;border-color:#cfe0e8!important;color:#11384b!important;box-shadow:0 10px 26px rgba(7,56,77,.07)!important;}
.pc-requests .pc-btn-native:hover,.pc-requests .pc-mini:hover{background:#f8fbfd!important;border-color:#a9c2cf!important;box-shadow:0 18px 38px rgba(7,56,77,.12)!important;}
.pc-requests .pc-ticket-actions{gap:10px!important;}
.pc-requests .pc-tools{box-shadow:0 16px 36px rgba(7,56,77,.07)!important;}
.pc-requests .pc-filter{position:relative!important;overflow:hidden!important;box-shadow:0 10px 24px rgba(7,56,77,.06)!important;}
.pc-requests .pc-filter.active{background:linear-gradient(135deg,#8f1d1d 0%,#b72e2e 100%)!important;border-color:#972121!important;box-shadow:0 18px 38px rgba(165,31,36,.20)!important;}
@media(max-width:900px){.pc-requests .pc-logo{height:82px!important;max-width:200px!important;}}
@media(max-width:560px){.pc-requests .pc-logo{height:68px!important;max-width:170px!important;}}

/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>

<style id="pc-requests-v247-final">
.pc-requests .pc-brand,
.pc-requests .pc-logo{cursor:default!important;}
.pc-requests .pc-brand:hover .pc-logo,
.pc-requests .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-logo{
  height:92px!important;
  max-width:236px!important;
  object-fit:contain!important;
  background:transparent!important;
  border:0!important;
  border-radius:0!important;
  padding:0!important;
  box-shadow:none!important;
  filter:none!important;
  mix-blend-mode:normal!important;
  transition:none!important;
}
.pc-requests .pc-btn-home svg.pc-icon-home-round{
  width:20px!important;
  height:20px!important;
  stroke:currentColor!important;
  stroke-width:2.2!important;
  stroke-linecap:round!important;
  stroke-linejoin:round!important;
  fill:none!important;
  transform:none!important;
  flex:0 0 auto!important;
}
.pc-requests .pc-btn-home{
  gap:10px!important;
  min-height:56px!important;
  padding-inline:24px!important;
}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;}
(max-width:900px){.pc-requests .pc-logo{height:78px!important;max-width:202px!important;}}
(max-width:560px){.pc-requests .pc-logo{height:64px!important;max-width:168px!important;}}

/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>

<style id="pc-requests-v248-states">
.pc-requests .pc-stats{grid-template-columns:repeat(5,minmax(0,1fr))!important;}
.pc-requests .pc-ticket{position:relative!important;overflow:hidden!important;border-width:2px!important;}
.pc-requests .pc-ticket:before{content:""!important;position:absolute!important;inset:0 auto 0 0!important;width:8px!important;background:#d6e7ef!important;display:block!important;}
.pc-requests .pc-ticket.state-new{border-color:#bddded!important;background:linear-gradient(135deg,#ffffff 0%,#f5fbff 100%)!important;}
.pc-requests .pc-ticket.state-answered{border-color:#9fcfd4!important;background:linear-gradient(135deg,#ffffff 0%,#f1fbfc 100%)!important;}
.pc-requests .pc-ticket.state-wait{border-color:#dfc4ff!important;background:linear-gradient(135deg,#ffffff 0%,#fbf6ff 100%)!important;}
.pc-requests .pc-ticket.state-done{border-color:#b9e6c5!important;background:linear-gradient(135deg,#ffffff 0%,#f2fff6 100%)!important;}
.pc-requests .pc-ticket.state-closed{border-color:#cbd5dc!important;background:linear-gradient(135deg,#ffffff 0%,#f5f7f9 100%)!important;}
.pc-requests .pc-ticket.state-new:before{background:#1377a2!important;}
.pc-requests .pc-ticket.state-answered:before{background:#0f8d98!important;}
.pc-requests .pc-ticket.state-wait:before{background:#7b4cc2!important;}
.pc-requests .pc-ticket.state-done:before{background:#1f9d4d!important;}
.pc-requests .pc-title{align-items:center!important;gap:12px!important;}
.pc-requests .pc-status{display:inline-flex!important;align-items:center!important;gap:8px!important;padding:10px 14px!important;border-radius:999px!important;font-size:15px!important;text-transform:uppercase!important;letter-spacing:.035em!important;box-shadow:0 10px 24px rgba(7,56,77,.08)!important;}
.pc-requests .pc-status-dot{width:10px!important;height:10px!important;border-radius:999px!important;background:currentColor!important;box-shadow:0 0 0 5px rgba(255,255,255,.45)!important;}
.pc-requests .pc-status.new{background:#dff2ff!important;color:#075a82!important;border:1px solid #9fd1ea!important;}
.pc-requests .pc-status.answered{background:#dff9fb!important;color:#075f66!important;border:1px solid #94d7dc!important;}
.pc-requests .pc-status.wait{background:#f0e4ff!important;color:#583293!important;border:1px solid #d4b8ff!important;}
.pc-requests .pc-status.done{background:#e3faeb!important;color:#126632!important;border:1px solid #a9dfb8!important;}
.pc-requests .pc-state-caption{font-weight:950!important;color:#526a79!important;background:#f7fbfd!important;border:1px dashed #cfe0e8!important;border-radius:999px!important;padding:8px 11px!important;}
.pc-requests .pc-pill-answers{background:#fff4e6!important;border-color:#f0d2a4!important;color:#704b08!important;}
.pc-requests .pc-answer{display:grid!important;grid-template-columns:auto 1fr!important;gap:10px!important;align-items:start!important;font-size:16px!important;padding:14px 16px!important;border-width:2px!important;border-style:solid!important;border-radius:18px!important;color:#15384a!important;}
.pc-requests .pc-answer-tag{display:inline-flex!important;align-items:center!important;justify-content:center!important;border-radius:999px!important;padding:7px 10px!important;font-size:12px!important;font-weight:1000!important;letter-spacing:.035em!important;text-transform:uppercase!important;white-space:nowrap!important;}
.pc-requests .pc-answer.empty{background:#f8fbfd!important;border-color:#cfe0e8!important;color:#385767!important;}
.pc-requests .pc-answer.empty .pc-answer-tag{background:#edf4f8!important;color:#4c6574!important;}
.pc-requests .pc-answer.answered{background:#edfdfd!important;border-color:#a7dce0!important;}
.pc-requests .pc-answer.answered .pc-answer-tag{background:#0f8d98!important;color:#fff!important;}
.pc-requests .pc-answer.solution{background:#f0fff4!important;border-color:#a9dfb8!important;}
.pc-requests .pc-answer.solution .pc-answer-tag{background:#1f9d4d!important;color:#fff!important;}
.pc-requests .pc-resolved-cards{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(240px,.55fr)!important;gap:10px!important;margin-top:10px!important}.pc-requests .pc-resolved-card{border:2px solid #a9dfb8!important;background:#f0fff4!important;border-radius:18px!important;padding:13px 15px!important;color:#15384a!important}.pc-requests .pc-resolved-card.material{border-color:#b9dce8!important;background:#f5fbff!important}.pc-requests .pc-resolved-card b{display:block!important;color:#07384d!important;margin-bottom:6px!important}.pc-requests .pc-material-pills{display:flex!important;flex-wrap:wrap!important;gap:7px!important}.pc-requests .pc-material-pill{border:1px solid #b7e5c4!important;background:#effaf2!important;color:#145f36!important;border-radius:999px!important;padding:6px 9px!important;font-weight:950!important;font-size:13px!important}@media(max-width:760px){.pc-requests .pc-resolved-cards{grid-template-columns:1fr!important}}
.pc-requests .pc-filter[data-filter="answered"].active{background:linear-gradient(135deg,#087983,#0f9aa5)!important;border-color:#087983!important;box-shadow:0 18px 38px rgba(15,141,152,.20)!important;}
@media(max-width:1100px){.pc-requests .pc-stats{grid-template-columns:repeat(3,minmax(0,1fr))!important;}}
@media(max-width:900px){.pc-requests .pc-stats{grid-template-columns:repeat(2,minmax(0,1fr))!important;}.pc-requests .pc-answer{grid-template-columns:1fr!important;}.pc-requests .pc-answer-tag{width:max-content!important;}}
@media(max-width:560px){.pc-requests .pc-stats{grid-template-columns:1fr!important;}.pc-requests .pc-status{font-size:13px!important;padding:8px 10px!important;}.pc-requests .pc-state-caption{width:100%!important;border-radius:14px!important;}.pc-requests .pc-filters{grid-template-columns:1fr!important;}.pc-requests .pc-answer-tag{white-space:normal!important;width:100%!important;}}

/* v249: tarjetas por estado sin barra lateral, logo robusto e inicio unificado */
.pc-requests .pc-ticket::before{display:none!important;content:none!important;}
.pc-requests .pc-ticket{border-width:1px!important;box-shadow:0 14px 34px rgba(7,56,77,.08)!important;}
.pc-requests .pc-ticket.state-new{background:#eef8fc!important;border-color:#a5d2e6!important;}
.pc-requests .pc-ticket.state-answered{background:#edfafa!important;border-color:#9ad7d9!important;}
.pc-requests .pc-ticket.state-wait{background:#f7f1ff!important;border-color:#ccb3ef!important;}
.pc-requests .pc-ticket.state-done{background:#eefaf1!important;border-color:#a2d9b1!important;}
.pc-requests .pc-ticket.state-closed{background:#f3f6f8!important;border-color:#c7d1d8!important;}
.pc-requests .pc-brand .pc-logo{display:block!important;height:116px!important;width:auto!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-requests .pc-brand .pc-logo:hover{transform:none!important;box-shadow:none!important;filter:none!important;}
.pc-requests .pc-btn-home{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;background:linear-gradient(135deg,#8f1d1d 0%,#aa2424 100%)!important;border:1px solid #922020!important;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)!important;}
.pc-requests .pc-btn-home .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;flex:0 0 auto!important;}
.pc-requests .pc-btn-home .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-requests .pc-btn-home span:last-child{line-height:1!important;color:#fff!important;font-weight:950!important;}
.pc-requests .pc-btn-home:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(165,31,36,.28)!important;background:linear-gradient(135deg,#a32323 0%,#bc3131 100%)!important;}
@media(max-width:560px){.pc-requests .pc-brand .pc-logo{height:92px!important;}.pc-requests .pc-btn-home{width:100%!important;}}

</style>


<style id="schoolmanager-v251-home-buttons">
/* v251: Inicio rojo y sin circulo en el icono */
.av .av-btn.home,
.pc-requests .pc-btn-home,
.pc-form .pc-head-actions .pc-header-home-clean{
  background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%)!important;
  border:1px solid #7c1b1b!important;
  color:#fff!important;
  box-shadow:0 18px 38px rgba(139,30,30,.24)!important;
}
.av .av-btn.home:hover,
.pc-requests .pc-btn-home:hover,
.pc-form .pc-head-actions .pc-header-home-clean:hover{
  background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%)!important;
  transform:translateY(-4px)!important;
  box-shadow:0 26px 46px rgba(139,30,30,.30)!important;
}
.pc-requests .pc-btn-home .pc-home-badge,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  width:auto!important;
  height:auto!important;
  min-width:0!important;
  min-height:0!important;
  padding:0!important;
  margin:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
  flex:0 0 auto!important;
  position:relative!important;
  z-index:2!important;
}
.pc-requests .pc-btn-home .pc-home-badge svg,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge svg{
  display:block!important;
  width:19px!important;
  height:19px!important;
  stroke:#fff!important;
  stroke-width:2.25!important;
  stroke-linecap:round!important;
  stroke-linejoin:round!important;
  fill:none!important;
}
.pc-requests .pc-btn-home span:last-child,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-label,
.pc-form .pc-head-actions .pc-header-home-clean span:last-child{
  color:#fff!important;
  font-weight:950!important;
  line-height:1!important;
  position:relative!important;
  z-index:2!important;
}
.pc-requests .pc-btn-home,
.pc-form .pc-head-actions .pc-header-home-clean{
  gap:10px!important;
  min-height:52px!important;
  padding:0 24px!important;
  border-radius:999px!important;
}
@media(max-width:760px){
  .pc-form .pc-head-actions .pc-header-home-clean{width:auto!important;min-width:130px!important;}
}

/* v252: botones menos redondos, rectangulares con bordes redondeados */
.pc-requests .pc-btn-home,
.pc-requests .pc-btn-primary,
.pc-requests .pc-btn-secondary,
.pc-requests .pc-btn-detail,
.pc-requests .pc-btn-native,
.pc-requests .pc-card-actions .pc-btn,
.pc-requests .pc-hero-actions .pc-btn,
.pc-form .pc-head-actions .pc-header-home-clean,
.pc-form .pc-btn-location,
.pc-form .pc-btn-cancel,
.pc-form .pc-btn-create,
.pc-alerts .pc-btn-back,
.pc-alerts .pc-btn-detail{border-radius:18px!important;}

.pc-requests .pc-btn-home,
.pc-form .pc-head-actions .pc-header-home-clean,
.pc-form .pc-btn-location,
.pc-form .pc-btn-cancel,
.pc-form .pc-btn-create,
.pc-alerts .pc-btn-back,
.pc-alerts .pc-btn-detail{padding-left:22px!important;padding-right:22px!important;}

.pc-requests .pc-btn-home .pc-home-badge,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{border-radius:10px!important;width:24px!important;height:24px!important;background:rgba(255,255,255,.12)!important;border:0!important;box-shadow:none!important;}

.pc-requests .pc-btn-home .pc-home-badge svg,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge svg{width:15px!important;height:15px!important;}

.pc-requests .pc-btn-home:hover,
.pc-form .pc-head-actions .pc-header-home-clean:hover,
.pc-form .pc-btn-location:hover,
.pc-form .pc-btn-cancel:hover,
.pc-form .pc-btn-create:hover,
.pc-alerts .pc-btn-back:hover,
.pc-alerts .pc-btn-detail:hover{transform:translateY(-4px)!important;}

@media(max-width:760px){
  .pc-requests .pc-btn-home,
  .pc-form .pc-head-actions .pc-header-home-clean,
  .pc-form .pc-btn-location,
  .pc-form .pc-btn-cancel,
  .pc-form .pc-btn-create,
  .pc-alerts .pc-btn-back,
  .pc-alerts .pc-btn-detail{border-radius:16px!important;}
}

</style>

<style id="pc-local-icons-hotfix">
/* Hotfix iconos locales: evita cuadrados cuando no carga ninguna fuente de iconos */
.pc-svgicon,.gsm-svgicon{display:inline-block!important;width:20px!important;height:20px!important;min-width:20px!important;flex:0 0 auto!important;background:transparent!important;color:currentColor!important;border:0!important;box-shadow:none!important;text-indent:0!important;overflow:visible!important;-webkit-mask:none!important;mask:none!important;line-height:1!important;vertical-align:middle!important;}
.pc-svgicon:before,.gsm-svgicon:before{content:""!important;display:block!important;width:100%!important;height:100%!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;}
.pc-i-home,.gsm-i-home{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M3.8%2010.6%2012%204.3l8.2%206.3%22%2F%3E%3Cpath%20d%3D%22M6.8%209.8v9.7h10.4V9.8%22%2F%3E%3Cpath%20d%3D%22M10%2019.5v-5a2%202%200%200%201%204%200v5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-plus,.gsm-i-plus{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%205v14M5%2012h14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-list,.gsm-i-list{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M8%206h12M8%2012h12M8%2018h12%22%2F%3E%3Cpath%20d%3D%22M4%206h.01M4%2012h.01M4%2018h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-search,.gsm-i-search{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Ccircle%20cx%3D%2211%22%20cy%3D%2211%22%20r%3D%227%22%2F%3E%3Cpath%20d%3D%22m20%2020-3.5-3.5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-eye,.gsm-i-eye{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M2.8%2012s3.5-6%209.2-6%209.2%206%209.2%206-3.5%206-9.2%206-9.2-6-9.2-6Z%22%2F%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2212%22%20r%3D%222.8%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-external,.gsm-i-external{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M14%205h5v5%22%2F%3E%3Cpath%20d%3D%22M10%2014%2019%205%22%2F%3E%3Cpath%20d%3D%22M19%2013v5H5V5h5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-info,.gsm-i-info{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2212%22%20r%3D%229%22%2F%3E%3Cpath%20d%3D%22M12%2010v6%22%2F%3E%3Cpath%20d%3D%22M12%207.5h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-check,.gsm-i-check{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4.5%2012.5%209.2%2017%2019.5%207%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-alert,.gsm-i-alert{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%203%202.8%2020h18.4L12%203Z%22%2F%3E%3Cpath%20d%3D%22M12%209v5M12%2017h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-warning,.gsm-i-warning{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%203%202.8%2020h18.4L12%203Z%22%2F%3E%3Cpath%20d%3D%22M12%209v5M12%2017h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-clock,.gsm-i-clock{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2212%22%20r%3D%228.5%22%2F%3E%3Cpath%20d%3D%22M12%207.5V12l3%202%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-message,.gsm-i-message{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%205.5h16v10H8l-4%203v-13Z%22%2F%3E%3Cpath%20d%3D%22M8%209h8M8%2012h5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-location,.gsm-i-location{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%2021s7-5.2%207-11a7%207%200%201%200-14%200c0%205.8%207%2011%207%2011Z%22%2F%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2210%22%20r%3D%222.5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-pin,.gsm-i-pin{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%2021s7-5.2%207-11a7%207%200%201%200-14%200c0%205.8%207%2011%207%2011Z%22%2F%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2210%22%20r%3D%222.5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-map,.gsm-i-map{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M9%2018%203.5%2020V6L9%204l6%202%205.5-2v14L15%2020l-6-2Z%22%2F%3E%3Cpath%20d%3D%22M9%204v14M15%206v14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-ticket,.gsm-i-ticket{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%207.5A2.5%202.5%200%200%201%206.5%205h11A2.5%202.5%200%200%201%2020%207.5V10a2%202%200%200%200%200%204v2.5a2.5%202.5%200%200%201-2.5%202.5h-11A2.5%202.5%200%200%201%204%2016.5V14a2%202%200%200%200%200-4V7.5Z%22%2F%3E%3Cpath%20d%3D%22M13%205v14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-box,.gsm-i-box{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%207.5%2012%203l8%204.5v9L12%2021l-8-4.5v-9Z%22%2F%3E%3Cpath%20d%3D%22M4%207.5%2012%2012l8-4.5%22%2F%3E%3Cpath%20d%3D%22M12%2012v9%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-keyboard,.gsm-i-keyboard{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%223%22%20y%3D%226%22%20width%3D%2218%22%20height%3D%2212%22%20rx%3D%222.2%22%2F%3E%3Cpath%20d%3D%22M7%2010h.01M10%2010h.01M13%2010h.01M16%2010h.01M7%2014h10%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-printer,.gsm-i-printer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M7%208V4h10v4%22%2F%3E%3Crect%20x%3D%227%22%20y%3D%2214%22%20width%3D%2210%22%20height%3D%226%22%20rx%3D%221.2%22%2F%3E%3Cpath%20d%3D%22M7%2018H5a2%202%200%200%201-2-2v-5a3%203%200%200%201%203-3h12a3%203%200%200%201%203%203v5a2%202%200%200%201-2%202h-2%22%2F%3E%3Cpath%20d%3D%22M17%2011h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-filter,.gsm-i-filter{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%205h16l-6.5%207.5V19l-3%201v-7.5L4%205Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-refresh,.gsm-i-refresh{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M20%206v5h-5%22%2F%3E%3Cpath%20d%3D%22M4%2018v-5h5%22%2F%3E%3Cpath%20d%3D%22M18.5%209A7%207%200%200%200%206.2%206.2%22%2F%3E%3Cpath%20d%3D%22M5.5%2015A7%207%200%200%200%2017.8%2017.8%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-minus,.gsm-i-minus{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M5%2012h14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-computer,.gsm-i-computer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%223.5%22%20y%3D%224.5%22%20width%3D%2217%22%20height%3D%2211.2%22%20rx%3D%222.2%22%2F%3E%3Cpath%20d%3D%22M8.3%2020h7.4M12%2015.7V20%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-monitor,.gsm-i-monitor{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%223.5%22%20y%3D%224.5%22%20width%3D%2217%22%20height%3D%2211.2%22%20rx%3D%222.2%22%2F%3E%3Cpath%20d%3D%22M8.3%2020h7.4M12%2015.7V20%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-network,.gsm-i-network{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%225%22%20y%3D%2213%22%20width%3D%2214%22%20height%3D%226%22%20rx%3D%222%22%2F%3E%3Cpath%20d%3D%22M8%2016h.01M11%2016h.01M14%2016h.01%22%2F%3E%3Cpath%20d%3D%22M8%209a6%206%200%200%201%208%200%22%2F%3E%3Cpath%20d%3D%22M10.3%2011.2a3%203%200%200%201%203.4%200%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-phone,.gsm-i-phone{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M8%203h8a1.5%201.5%200%200%201%201.5%201.5v15A1.5%201.5%200%200%201%2016%2021H8a1.5%201.5%200%200%201-1.5-1.5v-15A1.5%201.5%200%200%201%208%203Z%22%2F%3E%3Cpath%20d%3D%22M11%2018h2%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-tools,.gsm-i-tools{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M14.7%206.3a4%204%200%200%200-5.4%205.4L4.8%2016.2a2%202%200%200%200%202.8%202.8l4.5-4.5a4%204%200%200%200%205.5-5.2l-2.7%202.7-2.2-2.2%202.7-2.7Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-note,.gsm-i-note{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M6%203h9l3%203v15H6V3Z%22%2F%3E%3Cpath%20d%3D%22M14%203v4h4%22%2F%3E%3Cpath%20d%3D%22M9%2012h6M9%2016h6%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-lock,.gsm-i-lock{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%225%22%20y%3D%2210%22%20width%3D%2214%22%20height%3D%2210%22%20rx%3D%222%22%2F%3E%3Cpath%20d%3D%22M8%2010V7a4%204%200%200%201%208%200v3%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-bell,.gsm-i-bell{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M18%209a6%206%200%200%200-12%200c0%207-3%207-3%207h18s-3%200-3-7%22%2F%3E%3Cpath%20d%3D%22M10%2020a2.4%202.4%200%200%200%204%200%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-chart,.gsm-i-chart{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%2019V5%22%2F%3E%3Cpath%20d%3D%22M8%2017V9M13%2017V5M18%2017v-6%22%2F%3E%3Cpath%20d%3D%22M3%2019h18%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-fire,.gsm-i-fire{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%2022c4%200%207-2.7%207-6.8%200-3.4-2.4-5.9-4.3-7.8-.3%202.7-1.6%204.1-3.1%205.2.1-3.4-1.1-6.5-3.5-8.6.2%204.2-3.1%206.1-3.1%2011.1C5%2019.3%208%2022%2012%2022Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-puzzle,.gsm-i-puzzle{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M8%203h5v4h3a2%202%200%201%201%200%204h-3v3h-4v3a2%202%200%201%201-4%200v-3H3V9h5V3Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-construction,.gsm-i-construction{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M3%2021h18%22%2F%3E%3Cpath%20d%3D%22M5%2021V8l7-4%207%204v13%22%2F%3E%3Cpath%20d%3D%22M9%2021v-6h6v6%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-arrow-left,.gsm-i-arrow-left{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M19%2012H5%22%2F%3E%3Cpath%20d%3D%22m12%205-7%207%207%207%22%2F%3E%3C%2Fsvg%3E")!important;}
</style>
<div class="pc-requests"><div class="pc-wrap">
  <section class="pc-hero">
    <div class="pc-brand"><img class="pc-logo" src="<?= pc_h($logoUrl) ?>" alt="Logo del centro" onerror="this.onerror=null;this.src='<?= pc_h($root) ?>/plugins/schoolmanager/logo.svg?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>';"><div><div class="pc-kicker">GLPI SCHOOL MANAGER</div><h1>Mis solicitudes</h1><p>Consulta el estado, respuestas y resolución de tus incidencias.</p></div></div>
    <div class="pc-actions">
      <a class="pc-btn pc-btn-home" href="<?= pc_h($root) ?>/plugins/schoolmanager/front/formularios.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>" aria-label="Inicio"><span class="pc-svgicon pc-i-home" aria-hidden="true"></span><span>Inicio</span></a>
      <a class="pc-btn primary" href="<?= pc_h($root) ?>/plugins/schoolmanager/front/nueva_incidencia.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><span class="pc-svgicon pc-i-plus" aria-hidden="true"></span><span>Crear incidencia</span></a>
      <a class="pc-btn pc-btn-native" href="<?= pc_h($root) ?>/front/ticket.php"><span class="pc-svgicon pc-i-list" aria-hidden="true"></span><span>Lista nativa GLPI</span></a>
    </div>
  </section>
  <section class="pc-stats"><div class="pc-stat"><b><?= $total ?></b><span>solicitudes</span></div><div class="pc-stat"><b><?= $open ?></b><span>abiertas</span></div><div class="pc-stat"><b><?= $answered ?></b><span>respondidas</span></div><div class="pc-stat"><b><?= $waiting ?></b><span>esperando info</span></div><div class="pc-stat"><b><?= $solved ?></b><span>resueltas</span></div></section>
  <section class="pc-tools"><label class="pc-search"><span class="pc-svgicon pc-i-search" aria-hidden="true"></span><input id="pcSearch" type="search" placeholder="Buscar por título, aula, categoría, respuesta..."></label><div class="pc-filters"><button class="pc-filter active" data-filter="all">Todas</button><button class="pc-filter" data-filter="open">Abiertas</button><button class="pc-filter" data-filter="answered">Respondidas</button><button class="pc-filter" data-filter="wait">Esperando info</button><button class="pc-filter" data-filter="done">Resueltas</button></div></section>
  <section class="pc-list" id="pcList">
    <?php if (!empty($loadError ?? '')): ?><div class="pc-empty">No se pudieron cargar las solicitudes: <?= pc_h($loadError) ?></div><?php endif; ?>
    <?php if (!$tickets && empty($loadError ?? '')): ?><div class="pc-empty">Aún no tienes solicitudes. Puedes crear una incidencia guiada desde Gestion School Manager.</div><?php endif; ?>
    <?php foreach ($tickets as $t): [$glpiLabel,$glpiClass]=pc_status_label($t['status']); [$label,$class,$filter,$hint]=pc_ticket_public_state($t); $native=$root . '/front/ticket.form.php?id=' . (int)$t['id']; $detail=$root . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . (int)$t['id'] . '&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION); $hasPublicAnswer=trim((string)($t['last_answer'] ?? '')) !== ''; $answerClass=$hasPublicAnswer?($filter==='done'?'solution':'answered'):'empty'; $answerLabel=$hasPublicAnswer?($filter==='done'?'Solución / cierre':'Respuesta TIC'):'Sin respuesta todavía'; $search=strtolower(($t['name']??'').' '.($t['location_name']??'').' '.($t['category_name']??'').' '.($t['last_answer']??'').' '.implode(' ', (array)($t['used_material'] ?? [])).' '.$label.' '.$glpiLabel); ?>
      <article class="pc-ticket state-<?= pc_h($class) ?>" data-filter="<?= pc_h($filter) ?>" data-search="<?= pc_h($search) ?>">
        <div><div class="pc-title"><span class="pc-id">#<?= (int)$t['id'] ?></span><h2><?= pc_h($t['name']) ?></h2><span class="pc-status <?= pc_h($class) ?>"><span class="pc-status-dot"></span><?= pc_h($label) ?></span><span class="pc-state-caption"><?= pc_h($hint) ?></span></div>
          <div class="pc-meta"><span class="pc-pill">Fecha: <?= pc_h($t['date']) ?></span><span class="pc-pill">Actualizado: <?= pc_h($t['date_mod']) ?></span><span class="pc-pill">Prioridad: <?= pc_h(pc_priority_label($t['priority'])) ?></span><?php if ($t['location_name']): ?><span class="pc-pill">Ubicación: <?= pc_h($t['location_name']) ?></span><?php endif; ?><?php if ($t['category_name']): ?><span class="pc-pill">Categoría: <?= pc_h($t['category_name']) ?></span><?php endif; ?><span class="pc-pill pc-pill-answers">Respuestas públicas: <?= (int)$t['followups_count'] ?></span><span class="pc-pill">Estado GLPI: <?= pc_h($glpiLabel) ?></span></div>
          <?php if ($filter === 'done'): ?>
            <div class="pc-resolved-cards">
              <div class="pc-resolved-card"><b>Respuesta / solución</b><span><?= !empty($t['solution_text']) ? pc_h(mb_strimwidth($t['solution_text'],0,260,'...','UTF-8')) : 'Incidencia resuelta.' ?></span></div>
              <div class="pc-resolved-card material"><b>Material utilizado</b><?php if (!empty($t['used_material'])): ?><div class="pc-material-pills"><?php foreach ((array)$t['used_material'] as $mat): ?><span class="pc-material-pill"><?= pc_h($mat) ?></span><?php endforeach; ?></div><?php else: ?><span>No se ha usado material.</span><?php endif; ?></div>
            </div>
          <?php else: ?>
            <div class="pc-answer <?= pc_h($answerClass) ?>"><span class="pc-answer-tag"><?= pc_h($answerLabel) ?></span><span><?= $t['last_answer'] ? pc_h(mb_strimwidth($t['last_answer'],0,260,'...','UTF-8')) : 'Todavía no hay respuesta pública. Cuando el equipo TIC responda aparecerá aquí.' ?></span></div>
          <?php endif; ?>
        </div>
        <div class="pc-ticket-actions"><a class="pc-mini primary" href="<?= pc_h($detail) ?>"><span class="pc-svgicon pc-i-eye" aria-hidden="true"></span><span>Ver detalle</span></a><a class="pc-mini" href="<?= pc_h($native) ?>"><span class="pc-svgicon pc-i-external" aria-hidden="true"></span><span>Vista nativa</span></a></div>
      </article>
    <?php endforeach; ?>
  </section>
</div></div>
<script>
(function(){
  const search = document.getElementById('pcSearch');
  const filters = Array.from(document.querySelectorAll('.pc-filter'));
  const tickets = Array.from(document.querySelectorAll('.pc-ticket'));
  let active = 'all';
  function apply(){
    const q = (search?.value || '').toLowerCase().trim();
    tickets.forEach(t => {
      const okFilter = active === 'all' || t.dataset.filter === active;
      const okSearch = !q || (t.dataset.search || '').includes(q);
      t.style.display = okFilter && okSearch ? '' : 'none';
    });
  }
  filters.forEach(btn => btn.addEventListener('click', () => { filters.forEach(b=>b.classList.remove('active')); btn.classList.add('active'); active=btn.dataset.filter; apply(); }));
  if (search) search.addEventListener('input', apply);
})();
</script>

<style id="v255-mis-solicitudes-iconos-unificados">
.pc-requests .pc-btn-home .pc-svgicon,.pc-requests .pc-actions .pc-btn .pc-svgicon,.pc-requests .pc-mini .pc-svgicon{width:18px!important;height:18px!important;background:transparent!important;color:currentColor!important;margin:0!important;}
.pc-requests .pc-btn-home .pc-svgicon{background:transparent!important;color:#fff!important;}
.pc-requests .pc-actions .pc-btn{border-radius:18px!important;}
</style>


<?php Html::footer(); ?>


<style id="v254-home-icon-direct">
.pc-btn-home .pc-home-badge,.av-btn.home .pc-home-badge{display:none!important}
.pc-btn-home i,.av-btn.home i{display:inline-block!important;color:#fff!important;font-size:18px!important;line-height:1!important;background:transparent!important;border:0!important;box-shadow:none!important}
.pc-btn-home,.av-btn.home{border-radius:18px!important;background:linear-gradient(135deg,#8f1d1d 0%,#b72e2e 100%)!important;border-color:#922020!important}
</style>




<style id="v258-iconos-finales">
/* v258: icon system final - no solid squares */
.pc-svgicon,.gsm-svgicon{
  display:inline-block!important;width:20px!important;height:20px!important;min-width:20px!important;
  background:transparent!important;color:currentColor!important;border:0!important;box-shadow:none!important;
  -webkit-mask:none!important;mask:none!important;overflow:visible!important;text-indent:0!important;line-height:1!important;
  flex:0 0 auto!important;position:relative!important;vertical-align:middle!important;
}
.pc-svgicon:before,.gsm-svgicon:before{
  content:""!important;display:block!important;width:100%!important;height:100%!important;
  background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;
}
.av .av-btn .pc-svgicon,.av .av-btn.home .pc-svgicon,.av .av-btn.primary .pc-svgicon,
.pc-form .pc-btn .pc-svgicon,.pc-form #pcOpenSelector .pc-svgicon,.pc-form .pc-btn-create .pc-svgicon,.pc-form .pc-btn-cancel .pc-svgicon,.pc-form .pc-btn-back .pc-svgicon,
.pc-home .pc-action .pc-svgicon,.pc-home .pc-asset-icon .pc-svgicon,.pc-home .pc-ico.pc-svgicon{
  background:transparent!important;-webkit-mask:none!important;mask:none!important;color:currentColor!important;
}
.av .av-btn .pc-svgicon:before,.av .av-btn.home .pc-svgicon:before,.av .av-btn.primary .pc-svgicon:before,
.pc-form .pc-btn .pc-svgicon:before,.pc-form #pcOpenSelector .pc-svgicon:before,.pc-form .pc-btn-create .pc-svgicon:before,.pc-form .pc-btn-cancel .pc-svgicon:before,.pc-form .pc-btn-back .pc-svgicon:before,
.pc-home .pc-action .pc-svgicon:before,.pc-home .pc-asset-icon .pc-svgicon:before,.pc-home .pc-ico.pc-svgicon:before{
  display:block!important;content:""!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;
}
.av .av-btn.home .pc-svgicon,.pc-form .pc-btn-home .pc-svgicon,.pc-form .pc-btn-create .pc-svgicon,.pc-form #pcOpenSelector .pc-svgicon,.av .av-btn.primary .pc-svgicon{color:#fff!important;}
.pc-btn-svg{width:20px;height:20px;display:block;stroke:currentColor;stroke-width:2.35;stroke-linecap:round;stroke-linejoin:round;fill:none;flex:0 0 auto;position:relative;z-index:1;}
</style>

