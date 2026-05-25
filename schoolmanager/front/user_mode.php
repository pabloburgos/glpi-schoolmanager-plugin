<?php
include('../../../inc/includes.php');
header('Content-Type: application/json; charset=utf-8');
if (!Session::getLoginUserID()) {
    echo json_encode(['logged' => false]);
    exit;
}
require_once(__DIR__ . '/../inc/permissions.php');
$mode = plugin_schoolmanager_user_mode();
echo json_encode([
    'logged'      => true,
    'mode'        => $mode,
    'canTickets'  => plugin_schoolmanager_can_create_ticket(),
    'canAssets'   => plugin_schoolmanager_can_create_asset(null),
    'target'      => ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/formularios.php?v=139',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

