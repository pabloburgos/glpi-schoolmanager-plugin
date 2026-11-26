<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');
$cfg = plugin_schoolmanager_config();
$credits = $cfg['credits'] ?? [];
$authors = htmlspecialchars((string)($credits['authors'] ?? 'Pablo Burgos y Alejandro Galán'), ENT_QUOTES, 'UTF-8');
$license = htmlspecialchars(plugin_schoolmanager_normalize_license_label((string)($credits['license'] ?? 'GPLv3+')), ENT_QUOTES, 'UTF-8');
$url = htmlspecialchars((string)($credits['license_url'] ?? 'https://www.gnu.org/licenses/gpl-3.0.html'), ENT_QUOTES, 'UTF-8');
$appName = htmlspecialchars(plugin_schoolmanager_app_name(), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.1.0'), ENT_QUOTES, 'UTF-8');
$root = $CFG_GLPI['root_doc'] ?? '';
$headerActions = '<a class="sm-btn-secondary" href="' . htmlspecialchars($root . '/plugins/schoolmanager/front/settings.php', ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(plugin_schoolmanager_tr('menu_config', 'Configuración del plugin'), ENT_QUOTES, 'UTF-8') . '</a>';
sm_page_header([
    'title'       => plugin_schoolmanager_tr('credits', 'Créditos y Licencia'),
    'subtitle'    => plugin_schoolmanager_tr('license_summary', 'Información de autoría y licencia oficial del plugin.'),
    'active_tab'  => 'configuracion',
    'actions'     => $headerActions,
]);
?>
<style>
.schoolmanager-credits{max-width:1240px;margin:0 auto 36px;padding:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#0F2951}
.smc-card{background:linear-gradient(135deg,#ffffff 0%,#f6fbfd 100%);border:1px solid #D4DFED;border-radius:24px;padding:32px;box-shadow:0 18px 42px rgba(15,41,81,.08)}
.smc-chips{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 24px}
.smc-chip{display:inline-flex;align-items:center;gap:8px;border:1px solid #D4DFED;background:#fff;border-radius:999px;padding:10px 14px;font-weight:900;color:#0F2951}
.smc-chip-dot{width:10px;height:10px;border-radius:50%;background:#1eb980;box-shadow:0 0 0 6px rgba(30,185,128,.14)}
.smc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px}
.smc-box{border:1px solid #D4DFED;background:#fff;border-radius:20px;padding:22px;box-shadow:0 10px 24px rgba(15,41,81,.05)}
.smc-box .smc-label{display:block;color:#526580;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;margin-bottom:10px}
.smc-box b{display:block;font-size:28px;line-height:1.05;color:#0F2951;margin-bottom:8px}
.smc-box p{margin:0;color:#526580;font-weight:800;line-height:1.45}
.smc-box a{font-weight:900;color:#088E91;text-decoration:none}
.smc-box a:hover{text-decoration:underline}
@media(max-width:760px){.smc-card{padding:24px}.smc-box b{font-size:24px}}
</style>
<div class="schoolmanager-credits">
  <section class="smc-card">
    <div class="smc-chips">
      <span class="smc-chip"><span class="smc-chip-dot" aria-hidden="true"></span><?= htmlspecialchars(plugin_schoolmanager_tr('glpi_active'), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="smc-chip">School Manager</span>
      <span class="smc-chip">v<?= $version ?></span>
      <span class="smc-chip">SPDX: GPL-3.0-or-later</span>
    </div>

    <div class="smc-grid">
      <article class="smc-box">
        <span class="smc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('authors', 'Autores'), ENT_QUOTES, 'UTF-8') ?></span>
        <b>Pablo Burgos</b>
        <p><?= htmlspecialchars(plugin_schoolmanager_tr('pro_contact', 'Contacto profesional'), ENT_QUOTES, 'UTF-8') ?>: <a href="mailto:pabloburgostena@faltax.app" style="color:var(--sm-primary,#0F2951);font-weight:900;text-decoration:underline">pabloburgostena@faltax.app</a></p>
        <p style="margin-top:6px;font-size:13px;color:#526580"><?= htmlspecialchars(plugin_schoolmanager_tr('coauthor', 'Coautor'), ENT_QUOTES, 'UTF-8') ?>: Alejandro Galán</p>
      </article>

      <article class="smc-box">
        <span class="smc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('license', 'Licencia'), ENT_QUOTES, 'UTF-8') ?></span>
        <b><a href="<?= $url ?>" target="_blank" rel="noopener"><?= $license ?></a></b>
        <p>GNU General Public License v3.0 or later · <a href="<?= $url ?>" target="_blank" rel="noopener"><?= htmlspecialchars(plugin_schoolmanager_tr('view_full_text', 'Ver texto completo'), ENT_QUOTES, 'UTF-8') ?></a></p>
      </article>
    </div>
  </section>
</div>
<?php sm_page_footer(); ?>
