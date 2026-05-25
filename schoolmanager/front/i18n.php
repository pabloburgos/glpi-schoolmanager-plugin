<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/config.php');
header('Content-Type: application/json; charset=UTF-8');
$locale = plugin_schoolmanager_locale();
$map = function_exists('plugin_schoolmanager_translation_map_for_locale') ? plugin_schoolmanager_translation_map_for_locale($locale) : [];
echo json_encode(['locale' => $locale, 'map' => $map], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
