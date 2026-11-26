<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

$root = $CFG_GLPI['root_doc'] ?? '';
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.2.1';
$isTech = plugin_schoolmanager_is_technician();
[$tickets, $loadError] = smgr_fetch_tickets(500, !$isTech);

$items = [];
foreach ($tickets as $t) {
    $st = (int)($t['status'] ?? 0);
    $p = (int)($t['priority'] ?? 3);
    $msg = '';
    $type = 'info';
    if ($st == 4) {
        $msg = plugin_schoolmanager_tr('notice_wait_desc', 'La solicitud está en espera. Puede que el equipo TIC necesite información o material.');
        $type = 'wait';
    } elseif ($st == 5) {
        $msg = plugin_schoolmanager_tr('notice_done_desc', 'Solicitud resuelta. Revisa la solución y confirma si está correcto.');
        $type = 'done';
    } elseif ($st < 5 && $p >= 4) {
        $msg = plugin_schoolmanager_tr('notice_high_desc', 'Prioridad alta. Conviene revisarla cuanto antes.');
        $type = 'high';
    } elseif ($st < 5) {
        $last = smgr_fetch_last_public_followup((int)$t['id']);
        if ($last) {
            $msg = plugin_schoolmanager_tr('notice_reply_desc', 'Hay una respuesta reciente del equipo TIC.');
            $type = 'reply';
        }
    }
    if ($msg) {
        $t['notice'] = $msg;
        $t['notice_type'] = $type;
        $items[] = $t;
    }
}

sm_page_header([
    'title'      => plugin_schoolmanager_tr('menu_notices', 'Avisos y Alertas'),
    'subtitle'   => $isTech ? plugin_schoolmanager_tr('notices_tech_sub', 'Alertas operativas y seguimiento prioritario de incidencias.') : plugin_schoolmanager_tr('notices_user_sub', 'Actualizaciones importantes y avisos sobre tus solicitudes.'),
    'active_tab' => 'avisos',
]);
?>

<style>
/* Notices Styling */
.av-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.av-item {
  background: #ffffff;
  border: 1px solid #D7E6EC;
  border-radius: 18px;
  padding: 18px 22px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 18px;
  align-items: center;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
}

.av-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(7, 56, 77, 0.08);
  border-color: #075D61;
}

.av-ico {
  width: 50px;
  height: 50px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  transition: transform 0.18s ease;
}

.av-item:hover .av-ico {
  transform: scale(1.05);
}

.av-ico svg {
  width: 24px;
  height: 24px;
  stroke-width: 2.2;
  fill: none;
}

.av-item h2 {
  margin: 0 0 6px;
  color: #07384D;
  font-size: 17px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.av-item p {
  margin: 0 0 6px;
  color: #334155;
  font-weight: 600;
  font-size: 13.5px;
  line-height: 1.4;
}

.av-meta {
  color: #64748b !important;
  font-size: 12.5px !important;
  font-weight: 600 !important;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 !important;
}

/* Status variants */
.av-item.done { border-left: 5px solid #10B981; }
.av-item.done .av-ico { background: #ECFDF5; color: #059669; }
.av-item.done .av-ico svg { stroke: #059669; }

.av-item.high { border-left: 5px solid #EF4444; }
.av-item.high .av-ico { background: #FEF2F2; color: #DC2626; }
.av-item.high .av-ico svg { stroke: #DC2626; }

.av-item.wait { border-left: 5px solid #8B5CF6; }
.av-item.wait .av-ico { background: #F5F3FF; color: #7C3AED; }
.av-item.wait .av-ico svg { stroke: #7C3AED; }

.av-item.reply { border-left: 5px solid #F59E0B; }
.av-item.reply .av-ico { background: #FFFBEB; color: #D97706; }
.av-item.reply .av-ico svg { stroke: #D97706; }

.av-item.info { border-left: 5px solid #075D61; }
.av-item.info .av-ico { background: #E6F4F4; color: #075D61; }
.av-item.info .av-ico svg { stroke: #075D61; }

.av-empty {
  background: #ffffff;
  border: 2px dashed #D7E6EC;
  border-radius: 20px;
  padding: 48px 24px;
  text-align: center;
  color: #64748b;
  font-weight: 700;
  font-size: 15px;
}

@media (max-width: 860px) {
  .av-item {
    grid-template-columns: auto 1fr;
  }
  .av-item .sm-btn {
    grid-column: 1 / -1;
    justify-content: center;
    width: 100%;
  }
}
</style>

<div class="av-container">
  <?php if ($loadError): ?>
    <div class="av-empty" style="border-color:#fca5a5;color:#dc2626">
      <?= htmlspecialchars(plugin_schoolmanager_tr('load_error', 'No se pudieron cargar los avisos:'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <section class="av-list">
    <?php if (!$items && !$loadError): ?>
      <div class="av-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;margin-bottom:10px"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        <p style="margin:0"><?= htmlspecialchars(plugin_schoolmanager_tr('no_notices_pending', 'No hay avisos pendientes en este momento.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    <?php endif; ?>

    <?php foreach ($items as $t):
      $type = $t['notice_type'] ?? 'info';
    ?>
      <article class="av-item <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
        <div class="av-ico">
          <?php if ($type === 'done'): ?>
            <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M4.5 12.5 9.2 17 19.5 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <?php elseif ($type === 'high'): ?>
            <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M12 3 2.8 20h18.4L12 3Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 9v5M12 17h.01" stroke-linecap="round"/></svg>
          <?php elseif ($type === 'wait'): ?>
            <svg viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <?php elseif ($type === 'reply'): ?>
            <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M4 5.5h16v10H8l-4 3v-13Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 9h8M8 12h5" stroke-linecap="round"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7.5h.01" stroke-linecap="round"/></svg>
          <?php endif; ?>
        </div>
        <div>
          <h2>#<?= (int)$t['id'] ?> · <?= htmlspecialchars($t['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
          <p><?= htmlspecialchars($t['notice'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
          <p class="av-meta">
            <span>📍 <?= htmlspecialchars($t['location_name'] ?: plugin_schoolmanager_tr('no_location', 'Sin ubicación'), ENT_QUOTES, 'UTF-8') ?></span>
            <span>·</span>
            <span>🏷️ <?= htmlspecialchars($t['category_name'] ?: plugin_schoolmanager_tr('no_category', 'Sin categoría'), ENT_QUOTES, 'UTF-8') ?></span>
          </p>
        </div>
        <a class="sm-btn sm-btn-primary" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/ticket_detail.php?id=' . (int)$t['id'] . '&v=' . urlencode($version), ENT_QUOTES, 'UTF-8') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <span><?= htmlspecialchars(plugin_schoolmanager_tr('view_details', 'Ver Detalle'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </article>
    <?php endforeach; ?>
  </section>
</div>

<?php
sm_page_footer();
