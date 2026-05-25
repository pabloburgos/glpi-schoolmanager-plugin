<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
$root = $CFG_GLPI['root_doc'] ?? '';
Html::header('Mapa de calor retirado', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo plugin_schoolmanager_home_button();
?>
<style>
.pc-disabled{min-height:calc(100vh - 90px);display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,#f6f9fc,#eef6fb);font-family:Inter,system-ui,-apple-system,'Segoe UI',sans-serif;color:#073c44}.pc-card{max-width:780px;background:white;border:1px solid #d9e7ef;border-radius:28px;padding:28px;box-shadow:0 18px 54px rgba(7,56,77,.10);text-align:center}.pc-card h1{margin:0 0 8px;color:#07384d;font-size:clamp(30px,4vw,48px)}.pc-card p{margin:0 auto 18px;color:#617781;font-weight:850;line-height:1.45}.pc-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}.pc-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:12px 18px;border:1px solid #d9e7ef;background:#fff;color:#07384d!important;text-decoration:none!important;font-weight:950}.pc-btn.primary{background:#0b4f6c;color:white!important;border-color:#0b4f6c}
</style>
<style id="gestion-schoolmanager-global-override"><?php @readfile(__DIR__ . '/../css/gestion-schoolmanager-theme.css'); ?></style>

<div class="pc-disabled"><div class="pc-card"><h1>Mapa de calor retirado</h1><p>Se ha quitado esta vista para mantener el plugin limpio y funcional. Puedes seguir viendo incidencias desde el Panel TIC, Mis solicitudes y el plano normal.</p><div class="pc-actions"><a class="pc-btn primary" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/panel_tic.php?v=234">Abrir Panel TIC</a><a class="pc-btn" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/selector.php?v=234">Abrir plano de clases</a><a class="pc-btn" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/formularios.php?v=234">Volver al inicio</a></div></div></div>

<?php Html::footer(); ?>
