<?php

define('PLUGIN_SCHOOLMANAGER_VERSION', '1.0.0');
define('PLUGIN_SCHOOLMANAGER_LICENSE', 'GPLv3+');
define('PLUGIN_SCHOOLMANAGER_LICENSE_SPDX', 'GPL-3.0-or-later');

require_once(__DIR__ . '/inc/permissions.php');
require_once(__DIR__ . '/inc/config.php');


function plugin_schoolmanager_is_profesor_blocked_native_request() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH) ?: '';
    $path = rtrim($path, '/');

    // Nunca bloquear paginas del propio plugin ni rutas tecnicas.
    if (strpos($path, '/plugins/schoolmanager/') !== false) { return false; }
    if (preg_match('#/(login|logout)\.php$#i', $path)) { return false; }
    if (strpos($path, '/ajax/') !== false) { return false; }

    // Permitir cuenta propia y listado/estado de solicitudes.
    $allowed = [
        '/front/preference.php',
        '/front/user.form.php',
        '/front/ticket.php',
        '/front/ticket.form.php',
    ];
    foreach ($allowed as $ok) {
        if (stripos($path, $ok) !== false) { return false; }
    }

    // Bloquear portal/catalogo nativo y configuraciones que confunden al profesor.
    if (preg_match('#/(Helpdesk|ServiceCatalog)$#i', $path)) { return true; }
    $blocked = [
        '/front/helpdesk.public.php',
        '/front/knowbaseitem.php',
        '/front/dropdown.php',
        '/front/location.form.php',
        '/front/location.php',
        '/front/itilcategory.php',
        '/front/pendingreason.php',
        '/front/config.form.php',
        '/front/rule.php',
        '/front/profile.php',
        '/front/entity.php',
        '/front/plugin.php',
        '/front/setup.php',
        '/front/crontask.php',
        '/front/notification.php',
        '/front/requesttype.php',
    ];
    foreach ($blocked as $bad) {
        if (stripos($path, $bad) !== false) { return true; }
    }
    return false;
}

function plugin_schoolmanager_redirect_profesor_home_if_needed() {
    global $CFG_GLPI;

    if (headers_sent()) { return; }
    if (!class_exists('Session') || !Session::getLoginUserID()) { return; }
    if (isset($_GET['schoolmanager_noredirect'])) { return; }

    $mode = function_exists('plugin_schoolmanager_user_mode') ? plugin_schoolmanager_user_mode() : 'profesor';
    if ($mode !== 'profesor') { return; }
    if (!plugin_schoolmanager_is_profesor_blocked_native_request()) { return; }

    $root = $CFG_GLPI['root_doc'] ?? '';
    $target = $root . '/plugins/schoolmanager/front/error.php?title=' . rawurlencode('Acceso restringido') . '&message=' . rawurlencode('Tu perfil no tiene permisos para abrir esta zona de GLPI. Usa la página de Gestión School Manager para trabajar con incidencias, aulas y solicitudes.') . '&v=225';

    header('Location: ' . $target, true, 302);
    exit;
}

function plugin_init_schoolmanager() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['schoolmanager'] = true;
    $PLUGIN_HOOKS['config_page']['schoolmanager'] = 'front/instalacion.php';

    // Redireccion servidor-side: en interfaz simplificada /Helpdesk no siempre carga JS del plugin.
    plugin_schoolmanager_redirect_profesor_home_if_needed();
    if (function_exists('plugin_schoolmanager_require_initial_setup')) { plugin_schoolmanager_require_initial_setup(); }
    if (function_exists('plugin_schoolmanager_guard_current_feature')) { plugin_schoolmanager_guard_current_feature(); }

    // Carga global de recursos del plugin.
    // Incluye un redireccionador suave para perfiles profesor/self-service.
    $schoolmanager_theme_ver = @filemtime(__DIR__ . '/css/generated-theme.css') ?: time();
    $schoolmanager_config_ver = @filemtime(__DIR__ . '/js/generated-config.js') ?: time();
    $schoolmanager_js = [
        'js/generated-config.js?v=' . $schoolmanager_config_ver,
        'js/location-selector-integration.js',
        'js/profesor-home-redirect.js',
        'js/profesor-catalog-block.js',
        'js/custom-combobox.js',
        'js/schoolmanager-page-transition.js',
        'js/schoolmanager-i18n.js',
    ];
    $PLUGIN_HOOKS['add_javascript']['schoolmanager'] = $schoolmanager_js;
    $schoolmanager_theme = function_exists('plugin_schoolmanager_theme_values') ? plugin_schoolmanager_theme_values() : ['palette'=>'teal-red'];
    $schoolmanager_theme_file = 'css/themes/' . preg_replace('/[^a-z0-9_-]/i', '', (string)($schoolmanager_theme['palette'] ?? 'teal-red')) . '.css';
    $schoolmanager_css = ['css/location-selector-integration.css', 'css/gestion-schoolmanager-theme.css'];
    if (is_file(__DIR__ . '/' . $schoolmanager_theme_file)) { $schoolmanager_css[] = $schoolmanager_theme_file . '?v=' . (@filemtime(__DIR__ . '/' . $schoolmanager_theme_file) ?: $schoolmanager_theme_ver); }
    $schoolmanager_css[] = 'css/generated-theme.css?v=' . $schoolmanager_theme_ver;
    $PLUGIN_HOOKS['add_css']['schoolmanager'] = $schoolmanager_css;
    if (class_exists('Glpi\Plugin\Hooks')) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['schoolmanager'] = $schoolmanager_js;
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_CSS]['schoolmanager'] = $schoolmanager_css;
    }

    Plugin::registerClass('PluginSchoolmanagerMapa');

    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS['menu_toadd']['schoolmanager'] = [
            'tools'    => 'PluginSchoolmanagerMapa',
            'helpdesk' => 'PluginSchoolmanagerMapa',
        ];
    }
}

function plugin_version_schoolmanager() {
    return [
        'name'           => function_exists('plugin_schoolmanager_app_name') ? plugin_schoolmanager_app_name() : 'School Manager',
        'version'        => PLUGIN_SCHOOLMANAGER_VERSION,
        'author'         => 'Pablo Burgos y Alejandro Galán',
        'license'        => PLUGIN_SCHOOLMANAGER_LICENSE,
        'homepage'       => 'https://github.com/pabloburgos/glpi-schoolmanager-plugin',
        'icon'           => 'icon.png',
        'config_page'    => 'front/instalacion.php',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0',
                'max' => '11.99',
            ],
        ],
    ];
}

function plugin_schoolmanager_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '10.0', 'lt')) {
        echo 'Este plugin requiere GLPI >= 10.0';
        return false;
    }
    return true;
}

function plugin_schoolmanager_check_config($verbose = false) { return true; }
function plugin_schoolmanager_install() {
    if (function_exists('plugin_schoolmanager_force_normalize_persist')) {
        plugin_schoolmanager_force_normalize_persist();
    }
    if (function_exists('plugin_schoolmanager_write_theme_css')) {
        plugin_schoolmanager_write_theme_css();
    }
    if (function_exists('plugin_schoolmanager_write_runtime_config_js')) {
        plugin_schoolmanager_write_runtime_config_js();
    }
    return true;
}
function plugin_schoolmanager_uninstall() { return true; }
