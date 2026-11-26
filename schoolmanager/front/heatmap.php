<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');
$root = $CFG_GLPI['root_doc'] ?? '';
sm_page_header([
    'title'       => 'Mapa de calor retirado',
    'subtitle'    => 'Puedes consultar las incidencias desde el Panel TIC, Mis solicitudes y los planos.',
    'active_tab'  => 'panel_tic',
]);
?>
<div class="sm-card" style="max-width:780px;margin:20px auto;text-align:center;padding:32px">
  <h2 style="margin:0 0 10px;color:var(--sm-primary,#0F2951);font-size:24px;font-weight:900">Mapa de calor retirado</h2>
  <p style="margin:0 auto 20px;color:var(--sm-muted,#526580);font-weight:650;line-height:1.5">Se ha quitado esta vista para mantener el plugin limpio y funcional. Puedes seguir viendo incidencias desde el Panel TIC, Mis solicitudes y el plano normal.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a class="sm-btn-primary" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/it_panel.php">Abrir Panel TIC</a>
    <a class="sm-btn-secondary" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/selector.php">Abrir plano de clases</a>
  </div>
</div>
<?php sm_page_footer(); ?>
