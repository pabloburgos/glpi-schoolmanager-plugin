<?php

function plugin_schoolmanager_asset_types() {
    return [
        'Computer' => ['label'=>'Ordenador', 'plural'=>'Ordenadores', 'icon'=>'pc-i-computer', 'table'=>'glpi_computers', 'native'=>'/front/computer.form.php', 'list'=>'/front/computer.php', 'type_field'=>'computertypes_id', 'type_class'=>'ComputerType', 'model_field'=>'computermodels_id', 'model_class'=>'ComputerModel'],
        'Monitor' => ['label'=>'Monitor', 'plural'=>'Monitores', 'icon'=>'pc-i-monitor', 'table'=>'glpi_monitors', 'native'=>'/front/monitor.form.php', 'list'=>'/front/monitor.php', 'type_field'=>'monitortypes_id', 'type_class'=>'MonitorType', 'model_field'=>'monitormodels_id', 'model_class'=>'MonitorModel'],
        'Printer' => ['label'=>'Impresora', 'plural'=>'Impresoras', 'icon'=>'pc-i-printer', 'table'=>'glpi_printers', 'native'=>'/front/printer.form.php', 'list'=>'/front/printer.php', 'type_field'=>'printertypes_id', 'type_class'=>'PrinterType', 'model_field'=>'printermodels_id', 'model_class'=>'PrinterModel'],
        'NetworkEquipment' => ['label'=>'Dispositivo de red', 'plural'=>'Red', 'icon'=>'pc-i-network', 'table'=>'glpi_networkequipments', 'native'=>'/front/networkequipment.form.php', 'list'=>'/front/networkequipment.php', 'type_field'=>'networkequipmenttypes_id', 'type_class'=>'NetworkEquipmentType', 'model_field'=>'networkequipmentmodels_id', 'model_class'=>'NetworkEquipmentModel'],
        'Peripheral' => ['label'=>'Periférico', 'plural'=>'Periféricos', 'icon'=>'pc-i-keyboard', 'table'=>'glpi_peripherals', 'native'=>'/front/peripheral.form.php', 'list'=>'/front/peripheral.php', 'type_field'=>'peripheraltypes_id', 'type_class'=>'PeripheralType', 'model_field'=>'peripheralmodels_id', 'model_class'=>'PeripheralModel'],
        'Projector' => ['label'=>'Proyector', 'plural'=>'Proyectores', 'icon'=>'pc-i-monitor', 'table'=>'glpi_projectors', 'native'=>'/front/projector.form.php', 'list'=>'/front/projector.php'],
    ];
}

function plugin_schoolmanager_req($key, $default = '') {
    return isset($_REQUEST[$key]) ? trim((string) $_REQUEST[$key]) : $default;
}

function plugin_schoolmanager_post($key, $default = '') {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function plugin_schoolmanager_can_update_asset($itemtype = null) {
    if (class_exists('Session') && Session::getLoginUserID()) {
        if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) { return true; }
        if (function_exists('plugin_schoolmanager_is_super_admin_v176') && plugin_schoolmanager_is_super_admin_v176()) { return true; }
        if (function_exists('plugin_schoolmanager_is_tic_user') && plugin_schoolmanager_is_tic_user()) { return true; }
    }
    $types = plugin_schoolmanager_asset_types();
    if ($itemtype !== null && !isset($types[$itemtype])) { return false; }
    $targets = $itemtype ? [$itemtype] : array_keys($types);
    $update = defined('UPDATE') ? UPDATE : 2;
    foreach ($targets as $type) {
        if (class_exists($type)) {
            try {
                if (method_exists($type, 'canUpdate') && call_user_func([$type, 'canUpdate'])) { return true; }
            } catch (Throwable $e) {}
        }
        $right = strtolower($type);
        if ($type === 'NetworkEquipment') { $right = 'networking'; }
        if ($type === 'Projector') { $right = 'projector'; }
        if (function_exists('plugin_schoolmanager_safe_have_right') && plugin_schoolmanager_safe_have_right($right, $update)) { return true; }
        if (function_exists('plugin_schoolmanager_can_create_asset') && plugin_schoolmanager_can_create_asset($type)) { return true; }
    }
    return false;
}

function plugin_schoolmanager_short_location($full) {
    $full = trim((string)$full);
    if ($full === '' || $full === '&nbsp;') { return 'Sin ubicación'; }
    $plain = html_entity_decode(strip_tags($full), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $plain))));
    if (!$parts) { return $plain; }
    $building = '';
    $room = end($parts);
    foreach ($parts as $part) {
        if (preg_match('/Edificio\s*([12])/iu', $part, $m)) { $building = 'Edificio ' . $m[1]; }
    }
    if ($building !== '') { return $building . ' · ' . $room; }
    return $room;
}

function plugin_schoolmanager_dropdown_name($table, $id) {
    $id = (int)$id;
    if ($id <= 0 || !class_exists('Dropdown')) { return ''; }
    try { return Dropdown::getDropdownName($table, $id); }
    catch (Throwable $e) { return ''; }
}

function plugin_schoolmanager_asset_location_label($locations_id) {
    return plugin_schoolmanager_short_location(plugin_schoolmanager_dropdown_name('glpi_locations', (int)$locations_id));
}

function plugin_schoolmanager_safe_entities_where() {
    $entities = [];
    if (class_exists('Session') && method_exists('Session', 'getActiveEntities')) {
        try { $entities = Session::getActiveEntities(); } catch (Throwable $e) { $entities = []; }
    }
    if (!is_array($entities) || !$entities) {
        $entities = [Session::getActiveEntity()];
    }
    $entities = array_values(array_unique(array_map('intval', $entities)));
    return $entities;
}

function plugin_schoolmanager_get_asset_row($itemtype, $id) {
    global $DB;
    $types = plugin_schoolmanager_asset_types();
    if (!isset($types[$itemtype])) { return null; }
    $id = (int)$id;
    if ($id <= 0) { return null; }
    if (!isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists($types[$itemtype]['table'])) { return null; }
    $it = $DB->request([
        'FROM' => $types[$itemtype]['table'],
        'WHERE' => ['id' => $id, 'is_deleted' => 0],
        'LIMIT' => 1,
    ]);
    foreach ($it as $row) { return $row; }
    return null;
}

function plugin_schoolmanager_asset_clean_title($itemtype, $row) {
    $types = plugin_schoolmanager_asset_types();
    $name = trim((string)($row['name'] ?? ''));
    if ($name === '') { $name = ($types[$itemtype]['label'] ?? 'Activo') . ' #' . (int)($row['id'] ?? 0); }
    return $name;
}

function plugin_schoolmanager_location_rows_for_select() {
    $aulas = [];
    $dataFile = __DIR__ . '/aulas_data.php';
    if (is_file($dataFile)) {
        $data = require($dataFile);
        if (is_array($data)) {
            foreach ($data as $a) {
                $id = (int)($a['id'] ?? 0);
                if ($id <= 0) { continue; }
                $name = trim((string)($a['aula'] ?? ''));
                $desc = trim((string)($a['descripcion'] ?? ''));
                $aulas[$id] = [
                    'id' => $id,
                    'label' => ($name !== '' ? $name : ('Ubicación ' . $id)) . ($desc !== '' ? ' · ' . $desc : ''),
                    'building' => (string)($a['building'] ?? ''),
                    'floor' => (string)($a['floor'] ?? ''),
                ];
            }
        }
    }
    uasort($aulas, static fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
    return $aulas;
}



function plugin_schoolmanager_pc_friendly_name($name) {
    $name = trim((string)$name);
    if ($name === '') { return ''; }
    if (preg_match('/(?:^|[^A-Z0-9])(?:PC|P)[-_\s]*0*(\d{1,3})\s*$/i', $name, $m)) {
        return 'PC ' . (int)$m[1];
    }
    if (preg_match('/\b(\d{3})P0*(\d{1,3})\b/i', $name, $m)) {
        return 'PC ' . (int)$m[2];
    }
    if (preg_match('/\bPC[-_\s]*0*(\d{1,3})\b/i', $name, $m)) {
        return 'PC ' . (int)$m[1];
    }
    return $name;
}


function plugin_schoolmanager_pc_ref_from_text($text) {
    $text = trim((string)$text);
    if ($text === '') { return null; }
    if (preg_match('/\b(\d{3}P0*\d{1,3})\b/i', $text, $m)) {
        $code = strtoupper($m[1]);
        $friendly = plugin_schoolmanager_pc_friendly_name($code);
        return ['code' => $code, 'short' => ($friendly !== '' ? $friendly : $code) . ' (' . $code . ')'];
    }
    if (preg_match('/\b(?:PC|P)[-_\s]*0*(\d{1,3})\b/i', $text, $m)) {
        $n = (int)$m[1];
        return ['code' => 'PC' . $n, 'short' => 'PC ' . $n];
    }
    return null;
}

function plugin_schoolmanager_find_computer_by_reference($ref, $location_id = 0) {
    global $DB;
    if (!is_array($ref) || empty($ref['code']) || !isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists('glpi_computers')) { return null; }
    $code = (string)$ref['code'];
    $like = '%' . addcslashes($code, "_%\\") . '%';
    try {
        $where = ['is_deleted' => 0];
        if ((int)$location_id > 0) { $where['locations_id'] = (int)$location_id; }
        $it = $DB->request([
            'FROM' => 'glpi_computers',
            'WHERE' => $where + ['OR' => [
                ['name' => ['LIKE', $like]],
                ['serial' => ['LIKE', $like]],
                ['otherserial' => ['LIKE', $like]],
                ['contact' => ['LIKE', $like]],
                ['contact_num' => ['LIKE', $like]],
            ]],
            'LIMIT' => 1,
        ]);
        foreach ($it as $row) {
            $full = plugin_schoolmanager_asset_clean_title('Computer', $row);
            $friendly = plugin_schoolmanager_pc_friendly_name($full);
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => $full,
                'short' => ($friendly !== '' && $friendly !== $full ? $friendly . ' (' . $full . ')' : ($ref['short'] ?? $full)),
                'source' => 'reference',
            ];
        }
    } catch (Throwable $e) {}
    return [
        'id' => 0,
        'name' => $code,
        'short' => (string)($ref['short'] ?? $code),
        'source' => 'alternative_user',
    ];
}

function plugin_schoolmanager_connected_computer_for_monitor($monitor_id, $monitor_row = null) {
    global $DB;
    $monitor_id = (int)$monitor_id;
    if ($monitor_id <= 0 || !isset($DB) || !method_exists($DB, 'tableExists')) { return null; }

    // 1) Relacion real GLPI monitor -> ordenador, si existe.
    try {
        if ($DB->tableExists('glpi_items_monitors')) {
            $it = $DB->request([
                'FROM' => 'glpi_items_monitors',
                'WHERE' => ['monitors_id' => $monitor_id, 'itemtype' => 'Computer'],
                'LIMIT' => 1,
            ]);
            foreach ($it as $link) {
                $computer_id = (int)($link['items_id'] ?? 0);
                if ($computer_id <= 0) { continue; }
                $row = plugin_schoolmanager_get_asset_row('Computer', $computer_id);
                if (!$row) { continue; }
                $full = plugin_schoolmanager_asset_clean_title('Computer', $row);
                $friendly = plugin_schoolmanager_pc_friendly_name($full);
                return [
                    'id' => $computer_id,
                    'name' => $full,
                    'short' => ($friendly !== '' && $friendly !== $full ? $friendly . ' (' . $full . ')' : $full),
                    'source' => 'glpi_link',
                ];
            }
        }
    } catch (Throwable $e) {}

    // 2) Fallback practico del centro: GLPI guarda a veces el PC en "Nombre de usuario alternativo".
    //    En BD suele estar en contact/contact_num. Ejemplo: dam@205P02 -> PC 2 (205P02).
    try {
        if (!is_array($monitor_row) && $DB->tableExists('glpi_monitors')) {
            $it = $DB->request(['FROM' => 'glpi_monitors', 'WHERE' => ['id' => $monitor_id], 'LIMIT' => 1]);
            foreach ($it as $r) { $monitor_row = $r; break; }
        }
        if (is_array($monitor_row)) {
            $fields = ['contact', 'contact_num', 'name', 'serial', 'otherserial', 'comment'];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $monitor_row)) { continue; }
                $ref = plugin_schoolmanager_pc_ref_from_text((string)$monitor_row[$field]);
                if ($ref) {
                    return plugin_schoolmanager_find_computer_by_reference($ref, (int)($monitor_row['locations_id'] ?? 0));
                }
            }
        }
    } catch (Throwable $e) {}

    return null;
}

function plugin_schoolmanager_asset_display_title($itemtype, $row) {
    $full = plugin_schoolmanager_asset_clean_title($itemtype, $row);
    if ($itemtype === 'Computer') {
        $friendly = plugin_schoolmanager_pc_friendly_name($full);
        if ($friendly !== '' && $friendly !== $full) { return $friendly . ' (' . $full . ')'; }
    }
    if ($itemtype === 'Monitor') {
        $conn = plugin_schoolmanager_connected_computer_for_monitor((int)($row['id'] ?? 0), $row);
        if ($conn && !empty($conn['short'])) { return $full . ' · conectado a ' . $conn['short']; }
        return $full . ' · ordenador no vinculado';
    }
    return $full;
}

function plugin_schoolmanager_asset_counts_by_location($location_id) {
    global $DB;
    $location_id = (int)$location_id;
    $types = plugin_schoolmanager_asset_types();
    $counts = [];
    foreach ($types as $type => $info) {
        if ($location_id <= 0 || !isset($info['table'])) {
            $counts[$type] = 0;
            continue;
        }
        try {
            if (!isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists($info['table'])) {
                $counts[$type] = 0;
                continue;
            }
            $criteria = ['locations_id' => $location_id];
            if (method_exists($DB, 'fieldExists') && $DB->fieldExists($info['table'], 'is_deleted')) {
                $criteria['is_deleted'] = 0;
            }
            $it = $DB->request([
                'SELECT' => ['COUNT' => 'id AS c'],
                'FROM' => $info['table'],
                'WHERE' => $criteria,
            ]);
            $n = 0;
            foreach ($it as $row) { $n = (int)($row['c'] ?? 0); break; }
            $counts[$type] = $n;
        } catch (Throwable $e) {
            $counts[$type] = 0;
        }
    }
    return $counts;
}

function plugin_schoolmanager_assets_by_location($location_id, $limit_per_type = 8) {
    global $DB;
    $location_id = (int)$location_id;
    $out = [];
    if ($location_id <= 0) { return $out; }
    foreach (plugin_schoolmanager_asset_types() as $type => $info) {
        try {
            if (!isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists($info['table'])) { continue; }
            $criteria = ['locations_id' => $location_id];
            if (method_exists($DB, 'fieldExists') && $DB->fieldExists($info['table'], 'is_deleted')) { $criteria['is_deleted'] = 0; }
            $it = $DB->request([
                'FROM' => $info['table'],
                'WHERE' => $criteria,
                'ORDERBY' => ['name ASC', 'id ASC'],
                'LIMIT' => max(1, (int)$limit_per_type),
            ]);
            foreach ($it as $row) {
                $fullName = plugin_schoolmanager_asset_clean_title($type, $row);
                $displayName = function_exists('plugin_schoolmanager_asset_display_title') ? plugin_schoolmanager_asset_display_title($type, $row) : $fullName;
                $connected = null;
                if ($type === 'Monitor' && function_exists('plugin_schoolmanager_connected_computer_for_monitor')) {
                    $connected = plugin_schoolmanager_connected_computer_for_monitor((int)($row['id'] ?? 0), $row);
                }
                $out[] = [
                    'type' => $type,
                    'label' => $info['label'],
                    'icon' => $info['icon'],
                    'id' => (int)($row['id'] ?? 0),
                    'name' => $fullName,
                    'display_name' => $displayName,
                    'connected_computer' => $connected,
                    'serial' => trim((string)($row['serial'] ?? '')),
                    'inventory' => trim((string)($row['otherserial'] ?? '')),
                    'native' => ($info['native'] ?? ''),
                ];
            }
        } catch (Throwable $e) {}
    }
    return $out;
}

