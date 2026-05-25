<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
Html::header(plugin_schoolmanager_tr('credits'), $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
$cfg = plugin_schoolmanager_config();
$credits = $cfg['credits'] ?? [];
$authors = htmlspecialchars((string)($credits['authors'] ?? 'Pablo Burgos y Alejandro Galán'), ENT_QUOTES, 'UTF-8');
$license = htmlspecialchars(plugin_schoolmanager_normalize_license_label((string)($credits['license'] ?? 'GPLv3+')), ENT_QUOTES, 'UTF-8');
$url = htmlspecialchars((string)($credits['license_url'] ?? 'https://www.gnu.org/licenses/gpl-3.0.html'), ENT_QUOTES, 'UTF-8');
$appName = htmlspecialchars(plugin_schoolmanager_app_name(), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.0.0'), ENT_QUOTES, 'UTF-8');
$root = $CFG_GLPI['root_doc'] ?? '';
?>
<style>
.schoolmanager-credits{max-width:1240px;margin:18px auto 36px;padding:0 8px;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#07384d}
.smc-card{background:linear-gradient(135deg,#ffffff 0%,#f6fbfd 100%);border:1px solid #d7e6ec;border-radius:30px;padding:32px;box-shadow:0 18px 42px rgba(7,56,77,.08)}
.smc-top{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:22px;align-items:center;margin-bottom:18px}
.smc-kicker{color:#b6252b;font-weight:950;letter-spacing:.14em;text-transform:uppercase;font-size:14px}
.smc-card h1{margin:6px 0 12px;font-size:clamp(34px,5vw,58px);letter-spacing:-.05em;line-height:.95}
.smc-lead{color:#607582;font-weight:850;font-size:18px;line-height:1.5;max-width:880px;margin:0}
.smc-logo{width:112px;height:112px;object-fit:contain;border:1px solid #d7e6ec;border-radius:26px;background:#fff;padding:12px;box-shadow:0 12px 28px rgba(7,56,77,.08)}
.smc-chips{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 24px}
.smc-chip{display:inline-flex;align-items:center;gap:8px;border:1px solid #d7e6ec;background:#fff;border-radius:999px;padding:11px 14px;font-weight:900;color:#07384d}
.smc-chip-dot{width:10px;height:10px;border-radius:50%;background:#1eb980;box-shadow:0 0 0 6px rgba(30,185,128,.14)}
.smc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px}
.smc-box{border:1px solid #d7e6ec;background:#fff;border-radius:24px;padding:22px;box-shadow:0 10px 24px rgba(7,56,77,.05)}
.smc-box .smc-label{display:block;color:#607582;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;margin-bottom:10px}
.smc-box b{display:block;font-size:28px;line-height:1.05;color:#07384d;margin-bottom:8px}
.smc-box p{margin:0;color:#607582;font-weight:800;line-height:1.45}
.smc-box a{font-weight:950;color:#07384d;text-decoration:none}
.smc-box a:hover{text-decoration:underline}
.smc-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
.smc-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:48px;border-radius:16px;padding:0 18px;font-weight:900;text-decoration:none!important;border:1px solid #d7e6ec;color:#07384d;background:#fff;transition:.18s ease}
.smc-btn:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(7,56,77,.10);border-color:#b6252b;color:#07384d}
.smc-btn.primary{background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%);border-color:#8b1e1e;color:#fff!important;box-shadow:0 14px 28px rgba(139,30,30,.18)}
.smc-btn.primary:hover{background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%);color:#fff!important}
@media(max-width:760px){.schoolmanager-credits{padding:0 12px}.smc-card{padding:24px}.smc-top{grid-template-columns:1fr}.smc-logo{width:96px;height:96px}.smc-box b{font-size:24px}}
</style>
<div class="schoolmanager-credits">
  <section class="smc-card">
    <div class="smc-top">
      <div>
        <div class="smc-kicker"><?= $appName ?></div>
        <h1><?= htmlspecialchars(plugin_schoolmanager_tr('credits'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="smc-lead"><?= htmlspecialchars(plugin_schoolmanager_tr('license_summary'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <img class="smc-logo" src="<?= htmlspecialchars(plugin_schoolmanager_logo_url(), ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
    </div>

    <div class="smc-chips">
      <span class="smc-chip"><span class="smc-chip-dot" aria-hidden="true"></span><?= htmlspecialchars(plugin_schoolmanager_tr('glpi_active'), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="smc-chip">School Manager</span>
      <span class="smc-chip">v<?= $version ?></span>
      <span class="smc-chip">SPDX: GPL-3.0-or-later</span>
    </div>

    <div class="smc-grid">
      <article class="smc-box">
        <span class="smc-label"><?= htmlspecialchars(plugin_schoolmanager_tr('authors'), ENT_QUOTES, 'UTF-8') ?></span>
        <b><?= $authors ?></b>
        <p><?= htmlspecialchars(plugin_schoolmanager_tr('credits_by'), ENT_QUOTES, 'UTF-8') ?> <?= $authors ?></p>
      </article>

      <article class="smc-box">
        <span class="smc-label">License</span>
        <b><a href="<?= $url ?>" target="_blank" rel="noopener"><?= $license ?></a></b>
        <p>GNU General Public License v3.0 or later · <a href="<?= $url ?>" target="_blank" rel="noopener">Ver texto completo</a></p>
      </article>
    </div>

    <div class="smc-actions">
      <a class="smc-btn primary" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . rawurlencode((string)(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : time())), ENT_QUOTES, 'UTF-8') ?>">← <?= htmlspecialchars(plugin_schoolmanager_tr('menu_home', 'Inicio'), ENT_QUOTES, 'UTF-8') ?></a>
      <a class="smc-btn" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/instalacion.php?v=' . rawurlencode((string)(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : time())), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(plugin_schoolmanager_tr('menu_config', 'Configuración del plugin'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </section>
</div>
<?php Html::footer(); ?>
