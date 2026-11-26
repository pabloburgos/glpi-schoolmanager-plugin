<?php
/**
 * School Manager - Universal Shared Layout Helper
 * Provides a unified glassmorphism header, role badges, integrated home button, and footer across all plugin pages.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/ui_helpers.php';

function sm_page_header(array $params = []): void {
    global $CFG_GLPI;

    $title = (string)($params['title'] ?? plugin_schoolmanager_app_name());
    $subtitle = (string)($params['subtitle'] ?? '');
    $kicker = (string)($params['kicker'] ?? 'GLPI School Manager');
    $activeTab = (string)($params['active_tab'] ?? '');
    $customActions = (string)($params['actions'] ?? '');
    $showHomeButton = $params['show_home'] ?? ($activeTab !== 'home');
    $containerClass = (string)($params['container_class'] ?? '');
    $helpdeskMenu = (bool)($params['helpdesk_menu'] ?? false);

    $root = $CFG_GLPI['root_doc'] ?? '';
    $version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.1.0';
    $logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');

    // Permissions & User mode
    $canSuper = plugin_schoolmanager_is_superadmin();
    $isAdminTic = function_exists('plugin_schoolmanager_is_admin_tic_user') && plugin_schoolmanager_is_admin_tic_user();
    $isTech = plugin_schoolmanager_is_technician();

    $modeText = $canSuper
        ? plugin_schoolmanager_tr('mode_superadmin', 'Modo Super-Admin')
        : ($isAdminTic
            ? plugin_schoolmanager_tr('mode_it_admin', 'Modo Admin TIC')
            : ($isTech
                ? plugin_schoolmanager_tr('mode_it_technician', 'Modo Técnico TIC')
                : plugin_schoolmanager_tr('mode_teacher', 'Modo Profesor')));

    $modeClass = $canSuper ? 'mode-superadmin' : ($isAdminTic ? 'mode-admintic' : ($isTech ? 'mode-tech' : 'mode-teacher'));

    // Output GLPI Header
    $menuType = $helpdeskMenu ? 'helpdesk' : 'tools';
    Html::header($title, $_SERVER['PHP_SELF'], $menuType, 'PluginSchoolmanagerMapa');
    $cssVer = (string)(@filemtime(__DIR__ . '/../css/gestion-schoolmanager-theme.css') ?: $version);
    $genVer = (string)(@filemtime(__DIR__ . '/../css/generated-theme.css') ?: $version);
    $tealVer = (string)(@filemtime(__DIR__ . '/../css/themes/teal-red.css') ?: $version);
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/css/gestion-schoolmanager-theme.css?v=' . rawurlencode($cssVer), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/css/themes/teal-red.css?v=' . rawurlencode($tealVer), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/css/generated-theme.css?v=' . rawurlencode($genVer), ENT_QUOTES, 'UTF-8') ?>">
    <style>
      .sm-hero-logo, .pc-hero-logo, .sm-install-logo, .sm-universal-hero img.sm-hero-logo {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        filter: none !important;
        -webkit-filter: none !important;
        padding: 0 !important;
        outline: none !important;
        border-radius: 0 !important;
      }
      @media (max-width: 900px) {
        .sm-universal-hero .sm-hero-subtitle,
        .sm-universal-hero p.sm-hero-subtitle,
        .sm-hero-subtitle {
          display: none !important;
        }
      }
    </style>
    <div class="sm-page-shell schoolmanager-configured <?= htmlspecialchars($containerClass, ENT_QUOTES, 'UTF-8') ?>">
      <!-- Universal Institutional Glassmorphism Header -->
      <header class="sm-universal-hero">
        <div class="sm-hero-brand">
          <img class="sm-hero-logo" style="background:transparent!important;border:none!important;box-shadow:none!important;filter:none!important;-webkit-filter:none!important;padding:0!important;outline:none!important;border-radius:0!important;" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($root . '/plugins/schoolmanager/logo.svg?v=' . urlencode($version), ENT_QUOTES, 'UTF-8') ?>';">
          <div class="sm-hero-titles">
            <div class="sm-hero-kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></div>
            <h1 class="sm-hero-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($subtitle !== ''): ?>
              <p class="sm-hero-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="sm-hero-actions-bar">
          <div class="sm-status-pills">
            <span class="sm-role-pill <?= htmlspecialchars($modeClass, ENT_QUOTES, 'UTF-8') ?>">
              <i class="sm-role-dot"></i>
              <?= htmlspecialchars($modeText, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="sm-version-pill">v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></span>
          </div>

          <div class="sm-actions-slot">
            <?= $customActions ?>
            <?php if ($showHomeButton): ?>
              <a class="sm-btn-home" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/dashboard.php?v=' . urlencode($version), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(plugin_schoolmanager_tr('menu_home', 'Inicio'), ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M3 10.5 12 3l9 7.5"/>
                  <path d="M5 9v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/>
                  <path d="M9 21v-7a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v7"/>
                </svg>
                <span><?= htmlspecialchars(plugin_schoolmanager_tr('menu_home', 'Inicio'), ENT_QUOTES, 'UTF-8') ?></span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <!-- Main Body Container (Secondary navbar removed per /grill-me decision) -->
      <main class="sm-page-body">
    <?php
}

function sm_page_footer(): void {
    global $CFG_GLPI;
    $root = $CFG_GLPI['root_doc'] ?? '';
    $comboVer = (string)(@filemtime(__DIR__ . '/../js/custom-combobox.js') ?: '2.1');
    ?>
      </main>
    </div>
    <script src="<?= htmlspecialchars($root . '/plugins/schoolmanager/js/custom-combobox.js?v=' . rawurlencode($comboVer), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php
    Html::footer();
}
