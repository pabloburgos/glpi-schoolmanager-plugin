<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

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
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.2.1';
$userId = (int) Session::getLoginUserID();

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
    $map = [1 => 'Muy baja', 2 => 'Baja', 3 => 'Media', 4 => 'Alta', 5 => 'Muy alta', 6 => 'Mayor'];
    return $map[(int)$priority] ?? 'Media';
}

function pc_plain($html) {
    $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text));
    return $text;
}

function pc_solution_bits($html) {
    $text = (string)$html;
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
    $text = preg_replace('/<\s*\/p\s*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
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
    if (!isset($DB) || !is_object($DB)) {
        throw new RuntimeException('No hay conexión con la base de datos de GLPI.');
    }

    $where = [
        'glpi_tickets.is_deleted' => 0,
    ];

    $isTech = plugin_schoolmanager_is_technician();
    if (!$isTech) {
        $where['glpi_tickets_users.users_id'] = $userId;
        $where['glpi_tickets_users.type']     = 1; // Requester
    }

    $request = [
        'SELECT'     => [
            'glpi_tickets.id',
            'glpi_tickets.name',
            'glpi_tickets.date',
            'glpi_tickets.date_mod',
            'glpi_tickets.status',
            'glpi_tickets.priority',
            'glpi_tickets.locations_id',
            'glpi_tickets.itilcategories_id',
        ],
        'DISTINCT'   => true,
        'FROM'       => 'glpi_tickets',
        'LEFT JOIN'  => [
            'glpi_tickets_users' => [
                'ON' => [
                    'glpi_tickets_users' => 'tickets_id',
                    'glpi_tickets'       => 'id',
                ],
            ],
        ],
        'WHERE'      => $where,
        'ORDER'      => ['glpi_tickets.date_mod DESC', 'glpi_tickets.id DESC'],
        'LIMIT'      => 300,
    ];

    $ticketIterator = $DB->request($request);
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
                'items_id'   => $id,
                'is_private' => 0,
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

        if (method_exists($DB, 'tableExists') && $DB->tableExists('glpi_itilsolutions')) {
            $solutionIterator = $DB->request([
                'FROM'  => 'glpi_itilsolutions',
                'WHERE' => [
                    'itemtype' => 'Ticket',
                    'items_id' => $id,
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
} catch (Throwable $e) {
    $tickets = [];
    $loadError = $e->getMessage();
}

$total = count($tickets);
$open = 0; $waiting = 0; $solved = 0; $answered = 0;
foreach ($tickets as $t) {
    [$stateLabel, $stateClass, $stateFilter, $stateHint] = pc_ticket_public_state($t);
    if ($stateFilter === 'wait') { $waiting++; }
    if ($stateFilter === 'done') { $solved++; }
    if ($stateFilter === 'answered') { $answered++; }
    if ($stateFilter === 'open') { $open++; }
}

$topActions = '
<a class="sm-btn sm-btn-primary" href="' . pc_h($root) . '/plugins/schoolmanager/front/new_ticket.php?v=' . urlencode($version) . '">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="M12 5v14M5 12h14"/></svg>
  <span>' . pc_h(plugin_schoolmanager_tr('menu_create_ticket', 'Nueva Incidencia')) . '</span>
</a>
<a class="sm-btn sm-btn-secondary" href="' . pc_h($root) . '/front/ticket.php">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
  <span>' . pc_h(plugin_schoolmanager_tr('native_tickets_list', 'Lista Nativa GLPI')) . '</span>
</a>';

sm_page_header([
    'title'      => plugin_schoolmanager_tr('menu_my_requests', 'Mis Solicitudes'),
    'subtitle'   => plugin_schoolmanager_tr('my_requests_desc', 'Consulta el estado en tiempo real, respuestas técnicas y soluciones de tus tickets.'),
    'active_tab' => 'mis_solicitudes',
    'actions'    => $topActions,
]);
?>

<style>
/* Metrics Grid */
.sm-stats-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.sm-stat-box {
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #D7E6EC;
  padding: 18px 20px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
  transition: transform 0.2s ease;
}

.sm-stat-box:hover {
  transform: translateY(-2px);
}

.sm-stat-num {
  font-size: 32px;
  font-weight: 850;
  line-height: 1;
  color: #07384D;
  margin-bottom: 6px;
}

.sm-stat-label {
  font-size: 13px;
  font-weight: 700;
  color: #64748b;
  text-transform: capitalize;
}

/* Tools Bar */
.sm-requests-tools {
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #D7E6EC;
  padding: 14px 18px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.sm-search-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #F4F8FA;
  border: 1.5px solid #D7E6EC;
  border-radius: 12px;
  padding: 8px 14px;
  flex: 1 1 320px;
  transition: border-color 0.2s;
}

.sm-search-bar:focus-within {
  border-color: #075D61;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(7, 93, 97, 0.12);
}

.sm-search-bar svg {
  width: 18px;
  height: 18px;
  stroke: #64748b;
  flex-shrink: 0;
}

.sm-search-bar input {
  border: none;
  background: transparent;
  outline: none;
  width: 100%;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  min-height: auto;
  padding: 0;
}

.sm-filter-pills {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.sm-filter-pill {
  border: 1.5px solid #D7E6EC;
  background: #ffffff;
  border-radius: 12px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
  transition: all 0.18s ease;
}

.sm-filter-pill:hover {
  background: #F4F8FA;
  border-color: #07384D;
  color: #07384D;
}

.sm-filter-pill.active {
  background: linear-gradient(135deg, #07384D 0%, #075D61 100%);
  border-color: transparent;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(7, 93, 97, 0.22);
}

/* Ticket Cards List */
.sm-ticket-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.sm-ticket-card {
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #D7E6EC;
  padding: 20px 22px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 20px;
  align-items: center;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
}

.sm-ticket-card:hover {
  transform: translateY(-2px);
  border-color: #075D61;
  box-shadow: 0 10px 28px rgba(7, 56, 77, 0.08);
}

.sm-ticket-header {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.sm-ticket-id {
  background: #EBF4F7;
  color: #07384D;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12.5px;
  font-weight: 800;
}

.sm-ticket-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: #07384D;
  line-height: 1.25;
}

.sm-ticket-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 750;
}

.sm-ticket-status.new { background: #e0f2fe; color: #0284c7; }
.sm-ticket-status.answered { background: #ccfbf1; color: #0d9488; }
.sm-ticket-status.wait { background: #f3e8ff; color: #9333ea; }
.sm-ticket-status.done { background: #dcfce7; color: #16a34a; }

.sm-ticket-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.sm-ticket-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.sm-meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #F8FAFC;
  border: 1px solid #E2EDF2;
  border-radius: 8px;
  padding: 4px 9px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}

.sm-answer-box {
  background: #F8FAFC;
  border: 1px solid #E2EDF2;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 13px;
  color: #334155;
  line-height: 1.4;
  margin-top: 6px;
}

.sm-answer-box.solution {
  background: #F0FDF4;
  border-color: #BBF7D0;
  color: #166534;
}

.sm-ticket-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.sm-empty-box {
  background: #ffffff;
  border: 2px dashed #D7E6EC;
  border-radius: 20px;
  padding: 48px 24px;
  text-align: center;
  color: #64748b;
  font-weight: 700;
}

@media (max-width: 950px) {
  .sm-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .sm-ticket-card {
    grid-template-columns: 1fr;
  }
  .sm-ticket-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 600px) {
  .sm-stats-grid {
    grid-template-columns: 1fr;
  }
  .sm-requests-tools {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>

<div class="sm-requests-page">
  <!-- Stats Metrics -->
  <section class="sm-stats-grid">
    <div class="sm-stat-box">
      <div class="sm-stat-num"><?= (int)$total ?></div>
      <div class="sm-stat-label"><?= pc_h(plugin_schoolmanager_tr('requests', 'Total Solicitudes')) ?></div>
    </div>
    <div class="sm-stat-box">
      <div class="sm-stat-num" style="color:#0284c7"><?= (int)$open ?></div>
      <div class="sm-stat-label"><?= pc_h(plugin_schoolmanager_tr('open', 'Abiertas')) ?></div>
    </div>
    <div class="sm-stat-box">
      <div class="sm-stat-num" style="color:#0d9488"><?= (int)$answered ?></div>
      <div class="sm-stat-label"><?= pc_h(plugin_schoolmanager_tr('answered', 'Respondidas')) ?></div>
    </div>
    <div class="sm-stat-box">
      <div class="sm-stat-num" style="color:#9333ea"><?= (int)$waiting ?></div>
      <div class="sm-stat-label"><?= pc_h(plugin_schoolmanager_tr('waiting_info', 'Esperando Info')) ?></div>
    </div>
    <div class="sm-stat-box">
      <div class="sm-stat-num" style="color:#16a34a"><?= (int)$solved ?></div>
      <div class="sm-stat-label"><?= pc_h(plugin_schoolmanager_tr('solved', 'Resueltas')) ?></div>
    </div>
  </section>

  <!-- Search & Filters Bar -->
  <section class="sm-requests-tools">
    <label class="sm-search-bar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input id="smSearch" type="search" placeholder="<?= pc_h(plugin_schoolmanager_tr('search_requests_placeholder', 'Buscar por título, aula, categoría, respuesta...')) ?>">
    </label>
    <div class="sm-filter-pills">
      <button type="button" class="sm-filter-pill active" data-filter="all"><?= pc_h(plugin_schoolmanager_tr('all', 'Todas')) ?></button>
      <button type="button" class="sm-filter-pill" data-filter="open"><?= pc_h(plugin_schoolmanager_tr('open', 'Abiertas')) ?></button>
      <button type="button" class="sm-filter-pill" data-filter="answered"><?= pc_h(plugin_schoolmanager_tr('answered', 'Respondidas')) ?></button>
      <button type="button" class="sm-filter-pill" data-filter="wait"><?= pc_h(plugin_schoolmanager_tr('waiting_info', 'Esperando Info')) ?></button>
      <button type="button" class="sm-filter-pill" data-filter="done"><?= pc_h(plugin_schoolmanager_tr('solved', 'Resueltas')) ?></button>
    </div>
  </section>

  <!-- Tickets List -->
  <section class="sm-ticket-list" id="smTicketList">
    <?php if (!empty($loadError)): ?>
      <div class="sm-empty-box" style="border-color:#fca5a5;color:#dc2626">
        <?= pc_h(plugin_schoolmanager_tr('load_error', 'No se pudieron cargar las solicitudes:')) ?> <?= pc_h($loadError) ?>
      </div>
    <?php elseif (!$tickets): ?>
      <div class="sm-empty-box">
        <p style="margin:0 0 12px;font-size:16px"><?= pc_h(plugin_schoolmanager_tr('no_requests_msg', 'Aún no tienes solicitudes registradas.')) ?></p>
        <a class="sm-btn sm-btn-primary" href="<?= pc_h($root) ?>/plugins/schoolmanager/front/new_ticket.php?v=<?= urlencode($version) ?>">
          <?= pc_h(plugin_schoolmanager_tr('menu_create_ticket', 'Crear una Incidencia')) ?>
        </a>
      </div>
    <?php endif; ?>

    <?php foreach ($tickets as $t):
      [$glpiLabel, $glpiClass] = pc_status_label($t['status']);
      [$label, $class, $filter, $hint] = pc_ticket_public_state($t);
      $nativeUrl = $root . '/front/ticket.form.php?id=' . (int)$t['id'];
      $detailUrl = $root . '/plugins/schoolmanager/front/ticket_detail.php?id=' . (int)$t['id'] . '&v=' . urlencode($version);
      $hasPublicAnswer = trim((string)($t['last_answer'] ?? '')) !== '';
      $searchKeywords = strtolower(($t['name'] ?? '') . ' ' . ($t['location_name'] ?? '') . ' ' . ($t['category_name'] ?? '') . ' ' . ($t['last_answer'] ?? '') . ' ' . $label . ' ' . $glpiLabel);
    ?>
      <article class="sm-ticket-card" data-filter="<?= pc_h($filter) ?>" data-search="<?= pc_h($searchKeywords) ?>">
        <div class="sm-ticket-main">
          <div class="sm-ticket-header">
            <span class="sm-ticket-id">#<?= (int)$t['id'] ?></span>
            <h2 class="sm-ticket-title"><?= pc_h($t['name']) ?></h2>
            <span class="sm-ticket-status <?= pc_h($class) ?>">
              <i class="sm-ticket-status-dot"></i>
              <?= pc_h($label) ?>
            </span>
          </div>

          <div class="sm-ticket-meta">
            <span class="sm-meta-pill">📅 <?= pc_h($t['date']) ?></span>
            <span class="sm-meta-pill">⚡ <?= pc_h(pc_priority_label($t['priority'])) ?></span>
            <?php if (!empty($t['location_name'])): ?>
              <span class="sm-meta-pill">📍 <?= pc_h($t['location_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($t['category_name'])): ?>
              <span class="sm-meta-pill">🏷️ <?= pc_h($t['category_name']) ?></span>
            <?php endif; ?>
            <span class="sm-meta-pill">💬 <?= (int)$t['followups_count'] ?> <?= pc_h(plugin_schoolmanager_tr('public_answers', 'respuestas')) ?></span>
          </div>

          <?php if ($filter === 'done' && !empty($t['solution_text'])): ?>
            <div class="sm-answer-box solution">
              <b><?= pc_h(plugin_schoolmanager_tr('solution', 'Solución:')) ?></b> <?= pc_h(mb_strimwidth($t['solution_text'], 0, 240, '...', 'UTF-8')) ?>
            </div>
          <?php elseif ($hasPublicAnswer): ?>
            <div class="sm-answer-box">
              <b><?= pc_h(plugin_schoolmanager_tr('last_reply', 'Última respuesta:')) ?></b> <?= pc_h(mb_strimwidth($t['last_answer'], 0, 240, '...', 'UTF-8')) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="sm-ticket-actions">
          <a class="sm-btn sm-btn-primary" href="<?= pc_h($detailUrl) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
            <span><?= pc_h(plugin_schoolmanager_tr('view_details', 'Ver Detalle')) ?></span>
          </a>
          <a class="sm-btn sm-btn-secondary" href="<?= pc_h($nativeUrl) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M14 5h5v5M10 14 19 5M19 13v5H5V5h5"/></svg>
            <span><?= pc_h(plugin_schoolmanager_tr('native_view', 'GLPI')) ?></span>
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</div>

<script>
(function() {
  const search = document.getElementById('smSearch');
  const filters = Array.from(document.querySelectorAll('.sm-filter-pill'));
  const tickets = Array.from(document.querySelectorAll('.sm-ticket-card'));
  let activeFilter = 'all';

  function applyFilter() {
    const q = (search?.value || '').toLowerCase().trim();
    tickets.forEach(card => {
      const matchFilter = (activeFilter === 'all') || (card.dataset.filter === activeFilter);
      const matchSearch = !q || (card.dataset.search || '').includes(q);
      card.style.display = (matchFilter && matchSearch) ? '' : 'none';
    });
  }

  filters.forEach(btn => {
    btn.addEventListener('click', () => {
      filters.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeFilter = btn.dataset.filter;
      applyFilter();
    });
  });

  if (search) {
    search.addEventListener('input', applyFilter);
  }
})();
</script>

<?php
sm_page_footer();
