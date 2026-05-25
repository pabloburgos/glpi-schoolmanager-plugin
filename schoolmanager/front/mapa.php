<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

$root = $CFG_GLPI['root_doc'] ?? '';
$query = [];
foreach (['building','floor','room','mode','embed'] as $key) {
    if (isset($_GET[$key])) { $query[$key] = $_GET[$key]; }
}
$query['v'] = PLUGIN_SCHOOLMANAGER_VERSION;
$url = $root . '/plugins/schoolmanager/front/selector.php' . ($query ? ('?' . http_build_query($query)) : '');
Html::redirect($url);

