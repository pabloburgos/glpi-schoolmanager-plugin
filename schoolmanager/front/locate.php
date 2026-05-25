<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/config.php');

$query = trim((string)($_GET['q'] ?? $_GET['name'] ?? $_GET['code'] ?? ''));
if ($query === '') {
    plugin_schoolmanager_error_page('Ubicación no encontrada', 'No se ha indicado ninguna ubicación.', 'Vuelve al plano o a la lista de aulas para seleccionar una ubicación.');
}

function schoolmanager_locate_normalize(string $value): string {
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = mb_strtoupper(trim($value), 'UTF-8');
    $value = strtr($value, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
    return preg_replace('/[^A-Z0-9]+/', '', $value) ?: '';
}

function schoolmanager_go_location_or_detail(string $code, int $id, string $building = '', string $floor = '', string $room = ''): void {
    global $CFG_GLPI;
    $root = $CFG_GLPI['root_doc'] ?? '';
    if ($id > 0 && function_exists('plugin_schoolmanager_can_open_native_locations') && plugin_schoolmanager_can_open_native_locations()) {
        Html::redirect($root . '/front/location.form.php?id=' . $id);
        exit;
    }
    $params = [
        'id' => $id,
        'code' => $code,
        'building' => $building,
        'floor' => $floor,
        'room' => $room,
        'v' => defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.0.0',
    ];
    Html::redirect($root . '/plugins/schoolmanager/front/detalle_aula.php?' . http_build_query($params));
    exit;
}

$building = preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string)($_GET['building'] ?? plugin_schoolmanager_default_building_code())));
$rooms = [];
try { $tmp = require(__DIR__ . '/../inc/aulas_data.php'); if (is_array($tmp)) { $rooms = $tmp; } } catch (Throwable $e) { $rooms = []; }

$needle = schoolmanager_locate_normalize($query);
foreach ($rooms as $room) {
    $roomBuilding = strtoupper((string)($room['building'] ?? ''));
    $roomCode = (string)($room['codigo'] ?? '');
    $roomName = (string)($room['aula'] ?? '');
    $roomDesc = (string)($room['descripcion'] ?? '');
    $roomId = (int)($room['id'] ?? 0);
    $candidates = [$roomCode, $roomName, $roomDesc, $roomCode . ' ' . $roomName . ' ' . $roomDesc];
    foreach ($candidates as $candidate) {
        if ($needle !== '' && schoolmanager_locate_normalize((string)$candidate) === $needle) {
            schoolmanager_go_location_or_detail($roomCode, $roomId, $roomBuilding, (string)($room['floor'] ?? ''), $roomName);
        }
    }
}

// Second pass: partial match, useful for searches like "101" or "library".
foreach ($rooms as $room) {
    $hay = schoolmanager_locate_normalize((string)($room['codigo'] ?? '') . ' ' . (string)($room['aula'] ?? '') . ' ' . (string)($room['descripcion'] ?? ''));
    if ($needle !== '' && str_contains($hay, $needle)) {
        schoolmanager_go_location_or_detail((string)($room['codigo'] ?? ''), (int)($room['id'] ?? 0), (string)($room['building'] ?? ''), (string)($room['floor'] ?? ''), (string)($room['aula'] ?? ''));
    }
}

plugin_schoolmanager_error_page('Ubicación no encontrada', 'No se ha encontrado una ubicación configurada para "' . $query . '".', 'Revisa la configuración de aulas o añade el ID de ubicación GLPI correspondiente.');
