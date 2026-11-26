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
        $menu['page']  = '/plugins/schoolmanager/front/dashboard.php';
        $menu['icon']  = 'ti ti-map-2';

        $menu['options']['dashboard']['title'] = plugin_schoolmanager_tr('menu_home');
        $menu['options']['dashboard']['page']  = '/plugins/schoolmanager/front/dashboard.php';
        $menu['options']['dashboard']['icon']  = 'ti ti-home-cog';

        if ((function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) || !function_exists('plugin_schoolmanager_is_configured') || !plugin_schoolmanager_is_configured()) {
            $isCfg = function_exists('plugin_schoolmanager_is_configured') && plugin_schoolmanager_is_configured();
            $menu['options']['settings']['title'] = $isCfg ? plugin_schoolmanager_tr('menu_config') : plugin_schoolmanager_tr('menu_initial_setup');
            $menu['options']['settings']['page']  = $isCfg ? '/plugins/schoolmanager/front/settings.php' : '/plugins/schoolmanager/front/install.php';
            $menu['options']['settings']['icon']  = 'ti ti-adjustments-cog';
        }

        if (plugin_schoolmanager_feature_enabled('tickets') && plugin_schoolmanager_can_create_ticket()) {
            $menu['options']['new_ticket']['title'] = plugin_schoolmanager_tr('menu_create_ticket');
            $menu['options']['new_ticket']['page']  = '/plugins/schoolmanager/front/new_ticket.php';
            $menu['options']['new_ticket']['icon']  = 'ti ti-ticket';

            $menu['options']['my_requests']['title'] = plugin_schoolmanager_tr('menu_my_requests');
            $menu['options']['my_requests']['page']  = '/plugins/schoolmanager/front/my_requests.php';
            $menu['options']['my_requests']['icon']  = 'ti ti-message-check';

            $menu['options']['notifications']['title'] = plugin_schoolmanager_tr('menu_notices');
            $menu['options']['notifications']['page']  = '/plugins/schoolmanager/front/notifications.php';
            $menu['options']['notifications']['icon']  = 'ti ti-bell';
        }

        if (plugin_schoolmanager_feature_enabled('assets') && plugin_schoolmanager_can_create_asset(null)) {
            $menu['options']['it_panel']['title'] = plugin_schoolmanager_tr('menu_tic_panel');
            $menu['options']['it_panel']['page']  = '/plugins/schoolmanager/front/it_panel.php';
            $menu['options']['it_panel']['icon']  = 'ti ti-dashboard';

            $menu['options']['new_asset']['title'] = plugin_schoolmanager_tr('menu_create_asset');
            $menu['options']['new_asset']['page']  = '/plugins/schoolmanager/front/new_asset.php';
            $menu['options']['new_asset']['icon']  = 'ti ti-devices-plus';

            $menu['options']['asset_management']['title'] = plugin_schoolmanager_tr('menu_assets');
            $menu['options']['asset_management']['page']  = '/plugins/schoolmanager/front/asset_management.php';
            $menu['options']['asset_management']['icon']  = 'ti ti-adjustments-cog';
        }

        if (plugin_schoolmanager_feature_enabled('stock') && function_exists('plugin_schoolmanager_can_manage_stock') && plugin_schoolmanager_can_manage_stock()) {
            $menu['options']['stock']['title'] = plugin_schoolmanager_tr('menu_stock');
            $menu['options']['stock']['page']  = '/plugins/schoolmanager/front/stock.php';
            $menu['options']['stock']['icon']  = 'ti ti-package';
        }

        if (plugin_schoolmanager_feature_enabled('tic_assignment_rules') && function_exists('smgr_can_manage_tic_assignments') && smgr_can_manage_tic_assignments()) {
            $menu['options']['it_assignments']['title'] = plugin_schoolmanager_tr('menu_tic_rules');
            $menu['options']['it_assignments']['page']  = '/plugins/schoolmanager/front/it_assignments.php';
            $menu['options']['it_assignments']['icon']  = 'ti ti-users-group';
        }

        if (plugin_schoolmanager_feature_enabled('file_editor') && function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) {
            $menu['options']['file_editor']['title'] = plugin_schoolmanager_tr('menu_files');
            $menu['options']['file_editor']['page']  = '/plugins/schoolmanager/front/file_editor.php';
            $menu['options']['file_editor']['icon']  = 'ti ti-file-pencil';
        }

        if (plugin_schoolmanager_feature_enabled('plans')) {
            $menu['options']['map']['title'] = plugin_schoolmanager_tr('menu_plan');
            $menu['options']['map']['page']  = '/plugins/schoolmanager/front/selector.php';
            $menu['options']['map']['icon']  = 'ti ti-map-pin';
        }

        if (plugin_schoolmanager_feature_enabled('versions')) {
            $menu['options']['versions']['title'] = plugin_schoolmanager_tr('menu_versions');
            $menu['options']['versions']['page']  = '/plugins/schoolmanager/front/versions.php';
            $menu['options']['versions']['icon']  = 'ti ti-versions';
        }

        if (plugin_schoolmanager_feature_enabled('classrooms')) {
            $menu['options']['classrooms']['title'] = plugin_schoolmanager_tr('menu_classrooms');
            $menu['options']['classrooms']['page']  = '/plugins/schoolmanager/front/classrooms.php';
            $menu['options']['classrooms']['icon']  = 'ti ti-list-details';
        }

        return $menu;
    }
}
