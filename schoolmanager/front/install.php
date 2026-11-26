<?php
if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}
include('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

if (!class_exists('Session') || !Session::getLoginUserID()) {
    Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/index.php');
}
if (!function_exists('plugin_schoolmanager_can_configure') || !plugin_schoolmanager_can_configure()) {
    plugin_schoolmanager_access_denied_page(
        plugin_schoolmanager_tr('access_denied_config', 'Configuración de School Manager restringida'),
        plugin_schoolmanager_tr('access_denied_config_desc', 'Solo los administradores TIC y perfiles autorizados pueden acceder a la instalación de School Manager.')
    );
}

$root = $CFG_GLPI['root_doc'] ?? '';
$cfg = plugin_schoolmanager_config(true);
$isConfigured = plugin_schoolmanager_is_configured();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_wizard_submit'])) {
    $name = 'School Manager';
    $menuTitle = trim((string)($_POST['menu_title'] ?? 'School Manager'));
    if ($menuTitle === '') {
        $menuTitle = 'School Manager';
    }
    $cfg['brand']['title'] = $name;
    $cfg['brand']['menu_title'] = $menuTitle;
    $cfg['brand']['title_es'] = $name;
    $cfg['brand']['title_en'] = $name;
    $cfg['brand']['menu_title_es'] = $menuTitle;
    $cfg['brand']['menu_title_en'] = $menuTitle;
    $cfg['brand']['organization'] = trim((string)($_POST['organization'] ?? 'Centro educativo')) ?: 'Centro educativo';
    $cfg['brand']['show_logo'] = !empty($_POST['show_logo']);

    $locale = (string)($_POST['locale'] ?? 'auto');
    $availableLocales = array_keys(plugin_schoolmanager_available_locales(true));
    $cfg['locale'] = (in_array($locale, $availableLocales, true) || $locale === 'auto') ? $locale : 'auto';

    $moduleKeys = ['tickets', 'classrooms', 'plans', 'assets', 'stock', 'tic_panel', 'tic_assignment_rules', 'versions', 'file_editor'];
    $enabledModules = $_POST['features'] ?? [];
    if (!is_array($enabledModules)) {
        $enabledModules = [];
    }
    $enabledModules = array_map('strval', $enabledModules);
    foreach ($moduleKeys as $k) {
        $cfg['features'][$k] = in_array($k, $enabledModules, true);
    }

    $cfg['schema'] = 6;
    $cfg['setup_complete'] = true;
    $cfg['configured_at'] = date('Y-m-d H:i:s');
    if (isset($_SESSION['glpimenu'])) {
        unset($_SESSION['glpimenu']);
    }

    if (plugin_schoolmanager_save_config($cfg)) {
        plugin_schoolmanager_write_theme_css($cfg);
        plugin_schoolmanager_write_runtime_config_js($cfg);
        $afterAction = (string)($_POST['after_save_target'] ?? 'dashboard');
        if ($afterAction === 'settings') {
            Html::redirect($root . '/plugins/schoolmanager/front/settings.php?saved=general');
        } else {
            Html::redirect($root . '/plugins/schoolmanager/front/dashboard.php?wizard_completed=1');
        }
        exit;
    } else {
        $errors[] = plugin_schoolmanager_tr('save_error', 'No se pudo guardar la configuración. Verifica los permisos de escritura en data/.');
    }
}

$brand = $cfg['brand'] ?? [];
$features = $cfg['features'] ?? [];
$availableLocalesList = plugin_schoolmanager_available_locales(true);
$moduleLabels = [
    'tickets'              => ['title' => plugin_schoolmanager_tr('feature_tickets', 'Incidencias guiadas'), 'desc' => 'Creación paso a paso, seguimiento y avisos para docentes y técnicos.'],
    'classrooms'           => ['title' => plugin_schoolmanager_tr('feature_classrooms', 'Aulas y ubicaciones'), 'desc' => 'Listado de aulas, inventario por ubicación e historial de incidencias.'],
    'plans'                => ['title' => plugin_schoolmanager_tr('feature_plans', 'Planos interactivos'), 'desc' => 'Selección visual de aulas sobre planos por edificio y planta.'],
    'assets'               => ['title' => plugin_schoolmanager_tr('feature_assets', 'Activos guiados'), 'desc' => 'Alta guiada y gestión rápida de ordenadores, monitores, impresoras y red.'],
    'stock'                => ['title' => plugin_schoolmanager_tr('feature_stock', 'Stock TIC'), 'desc' => 'Control de consumibles, cartuchos, umbrales de aviso y entradas/salidas.'],
    'tic_panel'            => ['title' => plugin_schoolmanager_tr('feature_tic_panel', 'Panel TIC'), 'desc' => 'Centro operativo para técnicos con métricas y carga de trabajo.'],
    'tic_assignment_rules' => ['title' => plugin_schoolmanager_tr('feature_tic_rules', 'Reglas de asignación TIC'), 'desc' => 'Asignación automática de técnicos por planta, edificio o categoría.'],
    'versions'             => ['title' => plugin_schoolmanager_tr('feature_versions', 'Historial de versiones'), 'desc' => 'Documentación técnica y registro de cambios del plugin.'],
    'file_editor'          => ['title' => plugin_schoolmanager_tr('feature_file_editor', 'Editor de archivos'), 'desc' => 'Herramienta avanzada para Super-Admin con copias de seguridad.'],
];

sm_page_header([
    'kicker'     => plugin_schoolmanager_tr('wizard_kicker', 'ASISTENTE DE INSTALACIÓN Y ACTUALIZACIÓN'),
    'title'      => plugin_schoolmanager_tr('wizard_page_title', 'Activación de School Manager'),
    'subtitle'   => plugin_schoolmanager_tr('wizard_page_subtitle', 'Verifica los parámetros principales tras instalar o actualizar el plugin y guarda para acceder al panel.'),
    'active_tab' => 'instalacion',
    'show_home'  => $isConfigured,
]);
?>
<style>
.sm-install-wrap {
  width: 100% !important;
  max-width: 100% !important;
  margin: 0 !important;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.sm-install-banner {
  background: #FFFFFF;
  border: 1.5px solid #088E91;
  border-radius: 16px;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  box-shadow: 0 4px 16px rgba(8, 142, 145, 0.08);
  position: relative;
  overflow: hidden;
}
.sm-install-banner::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  width: 5px;
  background: #088E91;
}
.sm-install-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: #CCFBF1;
  color: #0F766E;
  font-size: 11.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}
.sm-install-banner h2 {
  margin: 0 0 4px;
  font-size: 19px;
  font-weight: 800;
  color: #0F2951;
}
.sm-install-banner p {
  margin: 0;
  font-size: 13.5px;
  font-weight: 600;
  color: #475569;
  line-height: 1.45;
}
.sm-install-card {
  background: #FFFFFF;
  border: 1px solid #D7E6EC;
  border-radius: 16px;
  padding: 22px 24px;
  box-shadow: 0 4px 14px rgba(15, 41, 81, 0.04);
}
.sm-install-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #E8F0F4;
}
.sm-install-section-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sm-install-step {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: #0F2951;
  color: #FFFFFF;
  font-size: 12px;
  font-weight: 800;
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
}
.sm-install-section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: #0F2951;
}
.sm-install-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.sm-install-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.sm-install-label {
  font-size: 13px;
  font-weight: 800;
  color: #0F2951;
}
.sm-install-help {
  font-size: 12px;
  font-weight: 600;
  color: #64748B;
}
.sm-install-modules {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.sm-install-module {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 13px 14px;
  border-radius: 12px;
  border: 1px solid #CBD5E1;
  background: #F8FAFC;
  cursor: pointer;
  transition: border-color 0.16s ease, background-color 0.16s ease;
}
.sm-install-module:hover {
  border-color: #088E91;
  background: #FFFFFF;
}
.sm-install-module input {
  width: 17px;
  height: 17px;
  margin-top: 2px;
  flex-shrink: 0;
}
.sm-install-module b {
  display: block;
  font-size: 13.5px;
  font-weight: 800;
  color: #0F2951;
}
.sm-install-module small {
  display: block;
  font-size: 11.5px;
  font-weight: 600;
  color: #64748B;
  margin-top: 2px;
  line-height: 1.35;
}
.sm-install-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 24px;
  background: #FFFFFF;
  border: 1px solid #D7E6EC;
  border-radius: 16px;
  box-shadow: 0 4px 14px rgba(15, 41, 81, 0.04);
  flex-wrap: wrap;
}
.sm-install-actions-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
@media (max-width: 900px) {
  .sm-install-banner { flex-direction: column; align-items: flex-start; }
  .sm-install-grid { grid-template-columns: 1fr; }
  .sm-install-modules { grid-template-columns: 1fr; }
  .sm-install-actions { flex-direction: column; align-items: stretch; }
  .sm-install-actions-right { flex-direction: column; align-items: stretch; }
}
</style>

<div class="sm-install-wrap">
  <?php foreach ($errors as $err): ?>
    <div class="sm-alert error" style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;padding:12px 16px;border-radius:12px;font-weight:700">
      <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endforeach; ?>

  <div class="sm-install-banner">
    <div>
      <span class="sm-install-badge">
        <?= $isConfigured ? '✓ Plugin activo · Revisión rápida' : '🚀 Paso obligatorio tras instalar o actualizar' ?>
      </span>
      <h2><?= htmlspecialchars(plugin_schoolmanager_tr('wizard_banner_title', 'Confirma la configuración inicial para entrar a School Manager'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p><?= htmlspecialchars(plugin_schoolmanager_tr('wizard_banner_desc', 'Esta pantalla de instalación/actualización valida los ajustes esenciales del centro y compila el tema visual. Pulsa "Guardar y entrar a School Manager" para finalizar y abrir el panel principal.'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>

  <form method="post" id="smInstallWizardForm">
    <?php if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')): ?>
      <input type="hidden" name="_glpi_csrf_token" value="<?= htmlspecialchars(Session::getNewCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <input type="hidden" name="install_wizard_submit" value="1">

    <section class="sm-install-card" style="margin-bottom:18px">
      <div class="sm-install-section-head">
        <div class="sm-install-section-left">
          <span class="sm-install-step">1</span>
          <h3 class="sm-install-section-title"><?= htmlspecialchars(plugin_schoolmanager_tr('wizard_step1_title', 'Identidad del Centro e Idioma'), ENT_QUOTES, 'UTF-8') ?></h3>
        </div>
        <span style="font-size:12px;font-weight:700;color:#64748B">Configuración base</span>
      </div>

      <div class="sm-install-grid">
        <label class="sm-install-field">
          <span class="sm-install-label"><?= htmlspecialchars(plugin_schoolmanager_tr('app_name', 'Nombre oficial del plugin'), ENT_QUOTES, 'UTF-8') ?></span>
          <input class="sm-input" type="text" name="title" value="School Manager" readonly>
          <span class="sm-install-help">El nombre del plugin se mantiene siempre como School Manager en todos los idiomas.</span>
        </label>

        <label class="sm-install-field">
          <span class="sm-install-label"><?= htmlspecialchars(plugin_schoolmanager_tr('menu_title', 'Etiqueta en el menú lateral de GLPI'), ENT_QUOTES, 'UTF-8') ?></span>
          <input class="sm-input" type="text" name="menu_title" value="<?= htmlspecialchars((string)($brand['menu_title'] ?? 'School Manager'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="sm-install-help"><?= htmlspecialchars(plugin_schoolmanager_tr('menu_title_help', 'Texto visible en la barra lateral izquierda de GLPI.'), ENT_QUOTES, 'UTF-8') ?></span>
        </label>

        <label class="sm-install-field">
          <span class="sm-install-label"><?= htmlspecialchars(plugin_schoolmanager_tr('organization', 'Nombre del centro educativo / organización'), ENT_QUOTES, 'UTF-8') ?></span>
          <input class="sm-input" type="text" name="organization" value="<?= htmlspecialchars((string)($brand['organization'] ?? 'Centro educativo'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="sm-install-help">Aparece en las cabeceras e informes del centro.</span>
        </label>

        <label class="sm-install-field">
          <span class="sm-install-label"><?= htmlspecialchars(plugin_schoolmanager_tr('plugin_language', 'Idioma predeterminado'), ENT_QUOTES, 'UTF-8') ?></span>
          <select class="sm-select" name="locale">
            <option value="auto" <?= ($cfg['locale'] ?? 'auto') === 'auto' ? 'selected' : '' ?>><?= htmlspecialchars(plugin_schoolmanager_tr('locale_auto', 'Automático (detectar según sesión de GLPI)'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php foreach ($availableLocalesList as $lcode => $linfo): ?>
              <option value="<?= htmlspecialchars($lcode, ENT_QUOTES, 'UTF-8') ?>" <?= ($cfg['locale'] ?? '') === $lcode ? 'selected' : '' ?>>
                <?= htmlspecialchars($linfo['name'] . ' (' . $lcode . ')', ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="sm-install-help"><?= htmlspecialchars(plugin_schoolmanager_tr('builtin_languages_help', 'Idiomas oficiales: English (UK), Español, Français, Deutsch, Italiano, Português.'), ENT_QUOTES, 'UTF-8') ?></span>
        </label>
      </div>

      <div style="margin-top:14px">
        <label class="sm-install-module" style="max-width:480px">
          <input type="checkbox" name="show_logo" value="1" <?= !empty($brand['show_logo']) ? 'checked' : '' ?>>
          <div>
            <b><?= htmlspecialchars(plugin_schoolmanager_tr('show_logo_header', 'Mostrar logotipo institucional en cabeceras'), ENT_QUOTES, 'UTF-8') ?></b>
            <small><?= htmlspecialchars(plugin_schoolmanager_tr('show_logo_help', 'Muestra el escudo del centro o el logo oficial de School Manager.'), ENT_QUOTES, 'UTF-8') ?></small>
          </div>
        </label>
      </div>
    </section>

    <section class="sm-install-card" style="margin-bottom:18px">
      <div class="sm-install-section-head">
        <div class="sm-install-section-left">
          <span class="sm-install-step">2</span>
          <h3 class="sm-install-section-title"><?= htmlspecialchars(plugin_schoolmanager_tr('tab_modules', 'Módulos Activos'), ENT_QUOTES, 'UTF-8') ?></h3>
        </div>
        <span style="font-size:12px;font-weight:700;color:#64748B">Selecciona las funciones habilitadas</span>
      </div>

      <div class="sm-install-modules">
        <?php foreach ($moduleLabels as $mKey => $mMeta): ?>
          <label class="sm-install-module">
            <input type="checkbox" name="features[]" value="<?= htmlspecialchars($mKey, ENT_QUOTES, 'UTF-8') ?>" <?= !empty($features[$mKey]) ? 'checked' : '' ?>>
            <div>
              <b><?= htmlspecialchars($mMeta['title'], ENT_QUOTES, 'UTF-8') ?></b>
              <small><?= htmlspecialchars($mMeta['desc'], ENT_QUOTES, 'UTF-8') ?></small>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="sm-install-actions">
      <div style="font-size:13px;font-weight:600;color:#475569">
        💡 Tras activar el plugin podrás gestionar edificios, aulas, planos e idiomas desde <b>Ajustes avanzados</b>.
      </div>
      <div class="sm-install-actions-right">
        <button type="submit" name="after_save_target" value="settings" class="sm-btn-secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
          <span><?= htmlspecialchars(plugin_schoolmanager_tr('save_and_open_settings', 'Guardar e ir a Ajustes avanzados'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="submit" name="after_save_target" value="dashboard" class="sm-btn-primary">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          <span><?= htmlspecialchars(plugin_schoolmanager_tr('wizard_complete_btn', 'Guardar y entrar a School Manager'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
      </div>
    </div>
  </form>
</div>
<?php sm_page_footer(); ?>
