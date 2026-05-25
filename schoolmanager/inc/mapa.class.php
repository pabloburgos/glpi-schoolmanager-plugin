<?php

require_once(__DIR__ . '/permissions.php');
require_once(__DIR__ . '/stats_helpers.php');
require_once(__DIR__ . '/config.php');

class PluginSchoolmanagerMapa extends CommonGLPI
{
    public static function getTypeName($nb = 0) {
        return function_exists('plugin_schoolmanager_menu_name') ? plugin_schoolmanager_menu_name() : plugin_schoolmanager_app_name();
    }

    public static function getMenuName() {
        return function_exists('plugin_schoolmanager_menu_name') ? plugin_schoolmanager_menu_name() : plugin_schoolmanager_app_name();
    }

    public static function getMenuContent() {
        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page']  = '/plugins/schoolmanager/front/formularios.php';
        $menu['icon']  = 'ti ti-map-2';

        $menu['options']['formularios']['title'] = plugin_schoolmanager_tr('menu_home');
        $menu['options']['formularios']['page']  = '/plugins/schoolmanager/front/formularios.php';
        $menu['options']['formularios']['icon']  = 'ti ti-home-cog';

        if ((function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) || !function_exists('plugin_schoolmanager_is_configured') || !plugin_schoolmanager_is_configured()) {
            $menu['options']['instalacion']['title'] = plugin_schoolmanager_is_configured() ? plugin_schoolmanager_tr('menu_config') : plugin_schoolmanager_tr('menu_initial_setup');
            $menu['options']['instalacion']['page']  = '/plugins/schoolmanager/front/instalacion.php';
            $menu['options']['instalacion']['icon']  = 'ti ti-adjustments-cog';
        }

        if (plugin_schoolmanager_feature_enabled('tickets') && plugin_schoolmanager_can_create_ticket()) {
            $menu['options']['nueva_incidencia']['title'] = plugin_schoolmanager_tr('menu_create_ticket');
            $menu['options']['nueva_incidencia']['page']  = '/plugins/schoolmanager/front/nueva_incidencia.php';
            $menu['options']['nueva_incidencia']['icon']  = 'ti ti-ticket';

            $menu['options']['mis_solicitudes']['title'] = plugin_schoolmanager_tr('menu_my_requests');
            $menu['options']['mis_solicitudes']['page']  = '/plugins/schoolmanager/front/mis_solicitudes.php';
            $menu['options']['mis_solicitudes']['icon']  = 'ti ti-message-check';

            $menu['options']['avisos']['title'] = plugin_schoolmanager_tr('menu_notices');
            $menu['options']['avisos']['page']  = '/plugins/schoolmanager/front/avisos.php';
            $menu['options']['avisos']['icon']  = 'ti ti-bell';
        }

        if (plugin_schoolmanager_feature_enabled('assets') && plugin_schoolmanager_can_create_asset(null)) {
            $menu['options']['panel_tic']['title'] = plugin_schoolmanager_tr('menu_tic_panel');
            $menu['options']['panel_tic']['page']  = '/plugins/schoolmanager/front/panel_tic.php';
            $menu['options']['panel_tic']['icon']  = 'ti ti-dashboard';


            $menu['options']['nuevo_activo']['title'] = plugin_schoolmanager_tr('menu_create_asset');
            $menu['options']['nuevo_activo']['page']  = '/plugins/schoolmanager/front/nuevo_activo.php';
            $menu['options']['nuevo_activo']['icon']  = 'ti ti-devices-plus';

            $menu['options']['gestion_activos']['title'] = plugin_schoolmanager_tr('menu_assets');
            $menu['options']['gestion_activos']['page']  = '/plugins/schoolmanager/front/gestion_activos.php';
            $menu['options']['gestion_activos']['icon']  = 'ti ti-adjustments-cog';
        }

        if (plugin_schoolmanager_feature_enabled('stock') && function_exists('plugin_schoolmanager_can_manage_stock') && plugin_schoolmanager_can_manage_stock()) {
            $menu['options']['stock_glpi']['title'] = plugin_schoolmanager_tr('menu_stock');
            $menu['options']['stock_glpi']['page']  = '/plugins/schoolmanager/front/stock_glpi.php';
            $menu['options']['stock_glpi']['icon']  = 'ti ti-package';
        }

        if (plugin_schoolmanager_feature_enabled('tic_assignment_rules') && function_exists('smgr_can_manage_tic_assignments') && smgr_can_manage_tic_assignments()) {
            $menu['options']['asignaciones_tic']['title'] = plugin_schoolmanager_tr('menu_tic_rules');
            $menu['options']['asignaciones_tic']['page']  = '/plugins/schoolmanager/front/asignaciones_tic.php';
            $menu['options']['asignaciones_tic']['icon']  = 'ti ti-users-group';
        }

        if (plugin_schoolmanager_feature_enabled('file_editor') && function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) {
            $menu['options']['archivos']['title'] = plugin_schoolmanager_tr('menu_files');
            $menu['options']['archivos']['page']  = '/plugins/schoolmanager/front/archivos.php';
            $menu['options']['archivos']['icon']  = 'ti ti-file-pencil';
        }

        // Unificamos el antiguo plano y el selector: ahora la vista buena es el Plano de clases.
        if (plugin_schoolmanager_feature_enabled('plans')) {
            $menu['options']['mapa']['title'] = plugin_schoolmanager_tr('menu_plan');
            $menu['options']['mapa']['page']  = '/plugins/schoolmanager/front/selector.php';
            $menu['options']['mapa']['icon']  = 'ti ti-map-pin';
        }

        if (plugin_schoolmanager_feature_enabled('versions')) {
            $menu['options']['versiones']['title'] = plugin_schoolmanager_tr('menu_versions');
            $menu['options']['versiones']['page']  = '/plugins/schoolmanager/front/versiones.php';
            $menu['options']['versiones']['icon']  = 'ti ti-versions';
        }

        if (plugin_schoolmanager_feature_enabled('classrooms')) {
            $menu['options']['aulas']['title'] = plugin_schoolmanager_tr('menu_classrooms');
            $menu['options']['aulas']['page']  = '/plugins/schoolmanager/front/aulas.php';
            $menu['options']['aulas']['icon']  = 'ti ti-list-details';
        }

        return $menu;
    }
}
