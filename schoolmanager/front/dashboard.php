<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/config.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');

$root = $CFG_GLPI['root_doc'] ?? '';
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.1.0';
$mode = plugin_schoolmanager_user_mode();
$canTickets = plugin_schoolmanager_can_create_ticket();
$canSuper = plugin_schoolmanager_is_superadmin();
$isAdminTic = function_exists('plugin_schoolmanager_is_admin_tic_user') && plugin_schoolmanager_is_admin_tic_user();
$isTicTech = function_exists('smgr_user_has_tecnico_tic_profile') && smgr_user_has_tecnico_tic_profile((int)Session::getLoginUserID());
$canTicAssign = function_exists('smgr_can_manage_tic_assignments') ? smgr_can_manage_tic_assignments() : ($canSuper || $isAdminTic);
$canAssets = plugin_schoolmanager_can_create_asset(null);
$canStock = function_exists('plugin_schoolmanager_can_manage_stock') ? plugin_schoolmanager_can_manage_stock() : $canAssets;
$isTech = plugin_schoolmanager_is_technician();
$cfgTheme = function_exists('plugin_schoolmanager_config') ? (plugin_schoolmanager_config()['theme'] ?? []) : [];
$density = ($cfgTheme['density'] ?? 'comfortable') === 'compact' ? 'compact' : 'comfortable';

function smhome_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function smhome_url($path) {
    global $root, $version;
    $sep = strpos($path, '?') === false ? '?' : '&';
    return $root . $path . $sep . 'v=' . rawurlencode($version);
} 

function smhome_icon($name) {
    $icons = [
        'ticket' => '<path d="M5 7.5h14v3a2 2 0 0 0 0 4v3H5v-3a2 2 0 0 0 0-4v-3Z"/><path d="M9 8v8M15 8v8"/>',
        'check'  => '<path d="M4.5 12.5 9.2 17 19.5 7"/>',
        'bell'   => '<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"/><path d="M10 20a2.4 2.4 0 0 0 4 0"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'tools'  => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4.8 16.2a2 2 0 0 0 2.8 2.8l4.5-4.5a4 4 0 0 0 5.5-5.2l-2.7 2.7-2.2-2.2 2.7-2.7Z"/>',
        'stock'  => '<rect x="5" y="4" width="14" height="6" rx="2"/><rect x="5" y="14" width="14" height="6" rx="2"/><path d="M8 7h.01M8 17h.01M12 10v4M9 12h6"/>',
        'map'    => '<path d="M9 18 3.5 20V6l5.5-2 6 2 5.5-2v14l-5.5 2-6-2Z"/><path d="M9 4v14M15 6v14"/>',
        'list'   => '<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'chart'  => '<path d="M4 19V5"/><path d="M8 17V9M13 17V5M18 17v-6"/><path d="M3 19h18"/>',
        'note'   => '<path d="M6 3h9l3 3v15H6V3Z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
        'clock'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    ];
    $body = $icons[$name] ?? $icons['note'];
    return '<svg class="sm-card-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function smhome_card($title, $text, $url, $icon, $tag, $main = false) {
    echo '<a class="sm-item-card ' . ($main ? 'is-featured' : '') . '" href="' . smhome_h(smhome_url($url)) . '">';
    echo '  <span class="sm-item-ico">' . smhome_icon($icon) . '</span>';
    echo '  <div class="sm-item-info">';
    echo '    <div class="sm-item-top">';
    echo '      <h4>' . smhome_h($title) . '</h4>';
    echo '      <em class="sm-item-tag">' . smhome_h($tag) . '</em>';
    echo '    </div>';
    echo '    <p>' . smhome_h($text) . '</p>';
    echo '  </div>';
    echo '</a>';
}

$sections = [];
if (plugin_schoolmanager_feature_enabled('tickets') && $canTickets) {
    $sections[plugin_schoolmanager_tr('support_tickets', 'Soporte e Incidencias')] = [
        [plugin_schoolmanager_tr('menu_create_ticket', 'Nueva Incidencia'), plugin_schoolmanager_tr('home_create_ticket_desc', 'Formulario guiado adaptado al centro educativo.'), '/plugins/schoolmanager/front/new_ticket.php', 'ticket', plugin_schoolmanager_tr('support', 'Soporte'), true],
        [plugin_schoolmanager_tr('menu_my_requests', 'Mis Solicitudes'), plugin_schoolmanager_tr('home_my_requests_desc', 'Consulta el estado, respuestas y soluciones de tus tickets.'), '/plugins/schoolmanager/front/my_requests.php', 'check', plugin_schoolmanager_tr('tracking', 'Seguimiento'), false],
        [plugin_schoolmanager_tr('menu_notices', 'Avisos'), plugin_schoolmanager_tr('home_notices_desc', 'Alertas, respuestas recibidas y tickets en espera.'), '/plugins/schoolmanager/front/notifications.php', 'bell', plugin_schoolmanager_tr('notices', 'Avisos'), false],
    ];
}

$inventory = [];
if (plugin_schoolmanager_feature_enabled('assets') && $canAssets) {
    $inventory[] = [plugin_schoolmanager_tr('menu_create_asset', 'Nuevo Activo'), plugin_schoolmanager_tr('home_asset_create_desc', 'Alta guiada de ordenadores, impresoras y periféricos.'), '/plugins/schoolmanager/front/new_asset.php', 'plus', plugin_schoolmanager_tr('create', 'Alta'), true];
    $inventory[] = [plugin_schoolmanager_tr('menu_assets', 'Inventario'), plugin_schoolmanager_tr('home_assets_desc', 'Gestión, búsqueda y edición de activos del centro.'), '/plugins/schoolmanager/front/asset_management.php', 'tools', plugin_schoolmanager_tr('edit', 'Gestión'), true];
}
if (plugin_schoolmanager_feature_enabled('stock') && $canStock) {
    $inventory[] = [plugin_schoolmanager_tr('menu_stock', 'Stock TIC'), plugin_schoolmanager_tr('home_stock_desc', 'Control de consumibles, cartuchos y componentes.'), '/plugins/schoolmanager/front/stock.php', 'stock', plugin_schoolmanager_tr('stock', 'Stock'), true];
}
if ($inventory) {
    $sections[plugin_schoolmanager_tr('inventory_assets', 'Inventario y Equipamiento')] = $inventory;
}

$loc = [];
if (plugin_schoolmanager_feature_enabled('plans')) {
    $loc[] = [plugin_schoolmanager_tr('menu_plan', 'Planos'), plugin_schoolmanager_tr('home_plan_desc', 'Visualización de plantas y ubicación interactiva de aulas.'), '/plugins/schoolmanager/front/selector.php', 'map', plugin_schoolmanager_tr('plans', 'Mapas'), true];
}
if (plugin_schoolmanager_feature_enabled('classrooms')) {
    $loc[] = [plugin_schoolmanager_tr('menu_classrooms', 'Aulas'), plugin_schoolmanager_tr('home_classrooms_desc', 'Directorio de aulas, plantas y dependencias del centro.'), '/plugins/schoolmanager/front/classrooms.php', 'list', plugin_schoolmanager_tr('classrooms_label', 'Aulas'), false];
}
if ($loc) {
    $sections[plugin_schoolmanager_tr('locations_rooms', 'Espacios y Aulas')] = $loc;
}

$tic = [];
if (plugin_schoolmanager_feature_enabled('tic_panel') && ($canAssets || $canStock || $isTicTech || $canSuper)) {
    $tic[] = [plugin_schoolmanager_tr('menu_tic_panel', 'Panel TIC'), plugin_schoolmanager_tr('home_tic_desc', 'Centro operativo de tickets, asignaciones y material.'), '/plugins/schoolmanager/front/it_panel.php', 'chart', plugin_schoolmanager_tr('technical', 'Técnico'), true];
}
if (plugin_schoolmanager_feature_enabled('tic_assignment_rules') && $canTicAssign) {
    $tic[] = [plugin_schoolmanager_tr('menu_tic_rules', 'Reglas TIC'), plugin_schoolmanager_tr('home_rules_desc', 'Asignación automática de técnicos por aula o edificio.'), '/plugins/schoolmanager/front/it_assignments.php', 'tools', plugin_schoolmanager_tr('it_admin', 'Admin TIC'), false];
}
if ($tic) {
    $sections[plugin_schoolmanager_tr('it_panel', 'Operaciones TIC')] = $tic;
}

$settings = [];
if ($canSuper) {
    $settings[] = [plugin_schoolmanager_tr('menu_config', 'Ajustes'), plugin_schoolmanager_tr('home_settings_desc', 'Configuración general, módulos, colores e idiomas.'), '/plugins/schoolmanager/front/settings.php', 'tools', plugin_schoolmanager_tr('settings', 'Ajustes'), true];
    if (plugin_schoolmanager_feature_enabled('file_editor')) {
        $settings[] = [plugin_schoolmanager_tr('menu_files', 'Archivos'), plugin_schoolmanager_tr('home_files_desc', 'Editor interno de plantillas y configuraciones.'), '/plugins/schoolmanager/front/file_editor.php', 'note', plugin_schoolmanager_tr('advanced', 'Avanzado'), false];
    }
}
if (plugin_schoolmanager_feature_enabled('versions')) {
    $settings[] = [plugin_schoolmanager_tr('menu_versions', 'Versiones'), plugin_schoolmanager_tr('home_versions_desc', 'Documentación y notas de actualización.'), '/plugins/schoolmanager/front/versions.php', 'clock', plugin_schoolmanager_tr('docs', 'Docs'), false];
}
$settings[] = [plugin_schoolmanager_tr('credits', 'Créditos'), plugin_schoolmanager_tr('home_credits_desc', 'Información de autoría, contacto y licencia.'), '/plugins/schoolmanager/front/credits.php', 'note', plugin_schoolmanager_tr('credits', 'Créditos'), false];
if ($settings) {
    $sections[plugin_schoolmanager_tr('plugin_settings', 'Configuración del Sistema')] = $settings;
}

// Render Universal Header
sm_page_header([
    'title'       => plugin_schoolmanager_app_name(),
    'subtitle'    => $isTech ? plugin_schoolmanager_tr('home_subtitle_tech', 'Panel técnico unificado para soporte, inventario, aulas y administración.') : plugin_schoolmanager_tr('home_subtitle_teacher', 'Panel docente para crear incidencias y consultar aulas del centro.'),
    'active_tab'  => 'home',
    'show_home'   => false,
]);
?>

<style>
/* Dashboard Styles & Density Modes */
.sm-home-container {
  display: grid;
  gap: 22px;
}

.sm-home-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 24px;
  align-items: start;
}

/* Sections & Panels */
.sm-home-panel {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sm-home-section {
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid #D7E6EC;
  padding: 22px 24px;
  box-shadow: 0 4px 16px rgba(11, 34, 48, 0.04);
}

.sm-home-section-title {
  margin: 0 0 16px 0;
  font-size: 19px;
  font-weight: 800;
  color: #07384D;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 2px solid #F4F8FA;
  padding-bottom: 10px;
}

/* Card System */
.sm-home-grid {
  display: grid;
  gap: 14px;
}

.sm-item-card {
  display: grid;
  grid-template-columns: 52px 1fr;
  gap: 16px;
  align-items: center;
  background: #ffffff;
  border: 1.5px solid #E2EDF2;
  border-radius: 16px;
  padding: 16px;
  text-decoration: none !important;
  color: #1e293b !important;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 8px rgba(7, 56, 77, 0.03);
  position: relative;
}

.sm-item-card:hover {
  transform: translateY(-2px);
  border-color: #075D61;
  box-shadow: 0 8px 24px rgba(7, 93, 97, 0.12);
  text-decoration: none !important;
}

.sm-item-card.is-featured {
  background: linear-gradient(135deg, #ffffff 0%, #fbfdfd 100%);
  border-color: #D3E6EE;
}

.sm-item-ico {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: #F0F6F9;
  display: grid;
  place-items: center;
  color: #07384D;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.sm-item-card:hover .sm-item-ico {
  background: linear-gradient(135deg, #07384D 0%, #075D61 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(7, 93, 97, 0.25);
}

.sm-card-svg {
  width: 24px;
  height: 24px;
}

.sm-item-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.sm-item-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sm-item-top h4 {
  margin: 0;
  font-size: 16px;
  font-weight: 750;
  color: #07384D;
  line-height: 1.25;
}

.sm-item-tag {
  font-style: normal;
  font-size: 11px;
  font-weight: 750;
  padding: 3px 8px;
  border-radius: 999px;
  background: #EFF6F9;
  color: #075D61;
  border: 1px solid #D7E6EC;
  white-space: nowrap;
}

.sm-item-info p {
  margin: 0;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  line-height: 1.35;
}

/* Quick Access Sidebar */
.sm-quick-aside {
  background: #ffffff;
  border-radius: 20px;
  border: 1px solid #D7E6EC;
  padding: 22px 20px;
  box-shadow: 0 4px 16px rgba(11, 34, 48, 0.04);
  position: sticky;
  top: 20px;
}

.sm-quick-title {
  margin: 0 0 16px 0;
  font-size: 18px;
  font-weight: 800;
  color: #07384D;
  border-bottom: 2px solid #F4F8FA;
  padding-bottom: 10px;
}

.sm-quick-notice {
  background: #FEF9EE;
  border: 1px solid #F6E2B3;
  border-radius: 14px;
  padding: 12px 14px;
  color: #8C6207;
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.4;
  margin-bottom: 16px;
}

.sm-quick-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sm-quick-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 12px;
  background: #F8FAFC;
  border: 1.5px solid #E2EDF2;
  color: #07384D !important;
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none !important;
  transition: all 0.18s ease;
}

.sm-quick-btn-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sm-quick-btn-left svg {
  width: 18px;
  height: 18px;
  stroke: #075D61;
}

.sm-quick-btn-arrow {
  color: #94a3b8;
  font-size: 16px;
  font-weight: 800;
  transition: transform 0.18s ease;
}

.sm-quick-btn:hover {
  background: #ffffff;
  border-color: #075D61;
  color: #075D61 !important;
  transform: translateX(3px);
  box-shadow: 0 4px 12px rgba(7, 93, 97, 0.10);
}

.sm-quick-btn:hover .sm-quick-btn-arrow {
  transform: translateX(3px);
  color: #075D61;
}

/* ==========================================================================
   DENSITY MODES: COMFORTABLE vs COMPACT
   ========================================================================== */

/* Comfortable Mode (Default) */
.sm-home-container[data-layout="comfortable"] .sm-home-grid {
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

/* Compact Mode */
.sm-home-container[data-layout="compact"] .sm-home-layout {
  grid-template-columns: minmax(0, 1fr) 290px;
  gap: 16px;
}

.sm-home-container[data-layout="compact"] .sm-home-section {
  padding: 16px 18px;
}

.sm-home-container[data-layout="compact"] .sm-home-section-title {
  font-size: 16px;
  margin-bottom: 12px;
  padding-bottom: 6px;
}

.sm-home-container[data-layout="compact"] .sm-home-grid {
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 10px;
}

.sm-home-container[data-layout="compact"] .sm-item-card {
  grid-template-columns: 42px 1fr;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 13px;
}

.sm-home-container[data-layout="compact"] .sm-item-ico {
  width: 42px;
  height: 42px;
  border-radius: 11px;
}

.sm-home-container[data-layout="compact"] .sm-card-svg {
  width: 20px;
  height: 20px;
}

.sm-home-container[data-layout="compact"] .sm-item-top h4 {
  font-size: 14.5px;
}

.sm-home-container[data-layout="compact"] .sm-item-tag {
  font-size: 10px;
  padding: 2px 6px;
}

.sm-home-container[data-layout="compact"] .sm-item-info p {
  font-size: 12px;
  line-height: 1.25;
}

.sm-home-container[data-layout="compact"] .sm-quick-aside {
  padding: 16px 14px;
}

.sm-home-container[data-layout="compact"] .sm-quick-title {
  font-size: 16px;
  margin-bottom: 12px;
  padding-bottom: 6px;
}

.sm-home-container[data-layout="compact"] .sm-quick-btn {
  padding: 9px 11px;
  font-size: 12.5px;
}

/* Responsive Breakpoints */
@media (max-width: 1100px) {
  .sm-home-layout {
    grid-template-columns: 1fr;
  }
  .sm-quick-aside {
    position: static;
  }
}

@media (max-width: 650px) {
  .sm-home-grid,
  .sm-home-container[data-layout="comfortable"] .sm-home-grid,
  .sm-home-container[data-layout="compact"] .sm-home-grid {
    grid-template-columns: 1fr;
  }
  .sm-home-section {
    padding: 16px;
  }
}
</style>

<div class="sm-home-container" id="smHomeRoot" data-layout="<?= smhome_h($density) ?>">
  <div class="sm-home-layout">
    <!-- Main Sections Area -->
    <main class="sm-home-panel">
      <?php foreach ($sections as $sectionTitle => $cards):
        if (empty($cards)) { continue; }
      ?>
        <section class="sm-home-section">
          <div class="sm-home-section-title">
            <span><?= smhome_h($sectionTitle) ?></span>
          </div>
          <div class="sm-home-grid">
            <?php foreach ($cards as $cardData):
              smhome_card($cardData[0], $cardData[1], $cardData[2], $cardData[3], $cardData[4], $cardData[5]);
            endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </main>

    <!-- Quick Access Lateral Sidebar -->
    <aside class="sm-quick-aside">
      <div class="sm-quick-title">
        <?= smhome_h(plugin_schoolmanager_tr('quick_access', 'Accesos Rápidos')) ?>
      </div>

      <?php if (!$isTech): ?>
        <div class="sm-quick-notice">
          <?= smhome_h(plugin_schoolmanager_feature_enabled('plans')
            ? plugin_schoolmanager_tr('teacher_notice', 'Este perfil permite crear incidencias y consultar aulas/planos. La edición de inventario está restringida.')
            : plugin_schoolmanager_tr('teacher_notice_no_plans', 'Este perfil permite crear incidencias y consultar el listado de aulas. La edición de inventario está restringida.')) ?>
        </div>
      <?php endif; ?>

      <div class="sm-quick-list">
        <?php if (plugin_schoolmanager_feature_enabled('tickets') && $canTickets): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/new_ticket.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('ticket') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_create_ticket', 'Nueva Incidencia')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>

        <?php if (plugin_schoolmanager_feature_enabled('tickets') && $canTickets): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/my_requests.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('check') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_my_requests', 'Mis Solicitudes')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>

        <?php if (plugin_schoolmanager_feature_enabled('stock') && $canStock): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/stock.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('stock') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_stock', 'Stock TIC')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>

        <?php if (plugin_schoolmanager_feature_enabled('assets') && $canAssets): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/asset_management.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('tools') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_assets', 'Inventario')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>

        <?php if (plugin_schoolmanager_feature_enabled('classrooms')): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/classrooms.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('list') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_classrooms', 'Lista de Aulas')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>

        <?php if (plugin_schoolmanager_feature_enabled('plans')): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/selector.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('map') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_plan', 'Planos de Planta')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>

        <?php if ($canSuper): ?>
          <a class="sm-quick-btn" href="<?= smhome_h(smhome_url('/plugins/schoolmanager/front/settings.php')) ?>">
            <div class="sm-quick-btn-left">
              <?= smhome_icon('tools') ?>
              <span><?= smhome_h(plugin_schoolmanager_tr('menu_config', 'Ajustes del Plugin')) ?></span>
            </div>
            <span class="sm-quick-btn-arrow">›</span>
          </a>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<?php
sm_page_footer();
