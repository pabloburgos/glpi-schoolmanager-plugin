<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/assets_helpers.php');
header('Content-Type: application/json; charset=UTF-8');
$locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$out = ['ok' => false, 'assets' => []];
try {
    if ($locationId > 0 && function_exists('plugin_schoolmanager_assets_by_location')) {
        $assets = plugin_schoolmanager_assets_by_location($locationId, 80);
        foreach ($assets as $a) {
            $out['assets'][] = [
                'type' => (string)($a['type'] ?? ''),
                'label' => (string)($a['label'] ?? 'Activo'),
                'id' => (int)($a['id'] ?? 0),
                'name' => (string)($a['name'] ?? ''),
                'display_name' => (string)($a['display_name'] ?? $a['name'] ?? ''),
                'serial' => (string)($a['serial'] ?? ''),
                'inventory' => (string)($a['inventory'] ?? ''),
                'connected_computer' => $a['connected_computer'] ?? null,
            ];
        }
        $out['ok'] = true;
    }
} catch (Throwable $e) {
    $out['error'] = 'No se pudieron leer los activos del aula.';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

