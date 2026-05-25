<?php

function smgr_stock_strip_glpi_prefix($table) {
    $table = trim((string)$table, " `\t\n\r\0\x0B");
    return preg_replace('/^glpi_/i', '', $table);
}

function smgr_stock_glpi_prefix() {
    global $DB;
    foreach (['prefix', 'table_prefix'] as $prop) {
        try {
            if (isset($DB) && isset($DB->$prop) && is_string($DB->$prop) && $DB->$prop !== '') {
                return $DB->$prop;
            }
        } catch (Throwable $e) {}
    }
    return 'glpi_';
}

function smgr_stock_table_candidates($table) {
    $table = trim((string)$table, " `\t\n\r\0\x0B");
    $short = smgr_stock_strip_glpi_prefix($table);
    $prefix = smgr_stock_glpi_prefix();
    $candidates = [$table, $prefix . $short, 'glpi_' . $short, $short];
    return array_values(array_unique(array_filter($candidates, static fn($v) => $v !== '')));
}

function smgr_stock_fetch_assoc($res) {
    global $DB;
    if (!$res) { return false; }
    try { if (isset($DB) && method_exists($DB, 'fetchAssoc')) { return $DB->fetchAssoc($res); } } catch (Throwable $e) {}
    try { if (isset($DB) && method_exists($DB, 'fetch_assoc')) { return $DB->fetch_assoc($res); } } catch (Throwable $e) {}
    try { if (is_object($res) && method_exists($res, 'fetch_assoc')) { return $res->fetch_assoc(); } } catch (Throwable $e) {}
    try { if (is_object($res) && method_exists($res, 'fetch')) { return $res->fetch(); } } catch (Throwable $e) {}
    return false;
}

function smgr_stock_table_exists_direct($table) {
    global $DB;
    $table = trim((string)$table, " `\t\n\r\0\x0B");
    if ($table === '' || !isset($DB)) { return false; }
    $esc = addslashes($table);
    try {
        $res = $DB->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$esc' LIMIT 1");
        if ($res && ($row = smgr_stock_fetch_assoc($res))) { return true; }
    } catch (Throwable $e) {}
    try {
        $res = $DB->query("SHOW TABLES LIKE '$esc'");
        if ($res && ($row = smgr_stock_fetch_assoc($res))) { return true; }
    } catch (Throwable $e) {}
    return false;
}

function smgr_stock_resolve_table($table) {
    global $DB;
    static $cache = [];
    $key = trim((string)$table, " `\t\n\r\0\x0B");
    if ($key === '') { return ''; }
    if (array_key_exists($key, $cache)) { return $cache[$key]; }

    foreach (smgr_stock_table_candidates($key) as $candidate) {
        try {
            if (isset($DB) && method_exists($DB, 'tableExists') && $DB->tableExists($candidate)) {
                return $cache[$key] = $candidate;
            }
        } catch (Throwable $e) {}
        if (smgr_stock_table_exists_direct($candidate)) {
            return $cache[$key] = $candidate;
        }
    }
    return $cache[$key] = '';
}

function smgr_stock_has_table($table) {
    return smgr_stock_resolve_table($table) !== '';
}

function smgr_stock_sql_table($table) {
    $resolved = smgr_stock_resolve_table($table);
    return $resolved !== '' ? $resolved : trim((string)$table, " `\t\n\r\0\x0B");
}

function smgr_stock_field_exists($table, $field) {
    global $DB;
    try {
        if (!isset($DB)) { return false; }
        $field = trim((string)$field, " `\t\n\r\0\x0B");
        if ($field === '') { return false; }
        $resolved = smgr_stock_sql_table($table);
        $candidates = array_values(array_unique(array_filter([$resolved, trim((string)$table, " `\t\n\r\0\x0B"), smgr_stock_strip_glpi_prefix($table)])));

        foreach ($candidates as $candidate) {
            try {
                if (method_exists($DB, 'fieldExists') && $DB->fieldExists($candidate, $field)) { return true; }
            } catch (Throwable $e) {}
            $tableEsc = addslashes($candidate); $fieldEsc = addslashes($field);
            try {
                $res = $DB->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableEsc' AND COLUMN_NAME = '$fieldEsc' LIMIT 1");
                if ($res && ($row = smgr_stock_fetch_assoc($res))) { return true; }
            } catch (Throwable $e) {}
            try {
                $res = $DB->query("SHOW COLUMNS FROM `$candidate` LIKE '$fieldEsc'");
                if ($res && ($row = smgr_stock_fetch_assoc($res))) { return true; }
            } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
    return false;
}

function smgr_stock_safe_now() { return date('Y-m-d H:i:s'); }

function smgr_stock_quote($value) {
    global $DB;
    if (isset($DB) && method_exists($DB, 'escape')) { return "'" . $DB->escape((string)$value) . "'"; }
    return "'" . addslashes((string)$value) . "'";
}

function smgr_stock_empty_date_condition($table, $field) {
    if (!smgr_stock_field_exists($table, $field)) { return ''; }
    return "(`$field` IS NULL OR `$field` = '' OR `$field` = '0000-00-00' OR `$field` = '0000-00-00 00:00:00')";
}

function smgr_stock_not_deleted_condition($table) {
    return smgr_stock_field_exists($table, 'is_deleted') ? '`is_deleted` = 0' : '';
}

function smgr_stock_active_entities() {
    try {
        if (class_exists('Session') && method_exists('Session', 'getActiveEntities')) {
            $entities = Session::getActiveEntities();
            if (is_array($entities) && $entities) { return array_values(array_unique(array_map('intval', $entities))); }
        }
        if (class_exists('Session')) { return [(int)Session::getActiveEntity()]; }
    } catch (Throwable $e) {}
    return [0];
}

function smgr_stock_kind_config($kind) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    if ($kind === 'cartridge') {
        return [
            'kind' => 'cartridge',
            'label' => 'Cartuchos',
            'single' => 'Cartucho',
            'item_table' => 'glpi_cartridgeitems',
            'unit_table' => 'glpi_cartridges',
            'item_fk' => 'cartridgeitems_id',
            'native_list' => '/front/cartridgeitem.php',
            'native_form' => '/front/cartridgeitem.form.php',
            'icon' => 'pc-i-printer',
        ];
    }
    return [
        'kind' => 'consumable',
        'label' => 'Consumibles',
        'single' => 'Consumible',
        'item_table' => 'glpi_consumableitems',
        'unit_table' => 'glpi_consumables',
        'item_fk' => 'consumableitems_id',
        'native_list' => '/front/consumableitem.php',
        'native_form' => '/front/consumableitem.form.php',
        'icon' => 'pc-i-keyboard',
    ];
}

function smgr_stock_item_type_name($kind, $type_id) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $type_id = (int)$type_id;
    if ($type_id <= 0) { return ''; }
    $table = $kind === 'cartridge' ? 'glpi_cartridgeitemtypes' : 'glpi_consumableitemtypes';
    try { return class_exists('Dropdown') ? Dropdown::getDropdownName($table, $type_id) : ''; }
    catch (Throwable $e) { return ''; }
}


function smgr_stock_is_empty_date_value($value) {
    if ($value === null) { return true; }
    $value = trim((string)$value);
    return $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00';
}

function smgr_stock_db_row_to_array($row) {
    if (is_array($row)) { return $row; }
    if ($row instanceof Traversable) { return iterator_to_array($row); }
    if (is_object($row)) { return get_object_vars($row); }
    return [];
}

function smgr_stock_kind_class_names($kind) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    return $kind === 'cartridge'
        ? ['item' => 'CartridgeItem', 'unit' => 'Cartridge']
        : ['item' => 'ConsumableItem', 'unit' => 'Consumable'];
}

function smgr_stock_common_find_rows($class, array $criteria = [], $order = 'name ASC', $limit = 500) {
    $rows = [];
    try {
        if (!class_exists($class)) { return []; }
        $obj = new $class();
        if (!method_exists($obj, 'find')) { return []; }
        $found = $obj->find($criteria, $order, (int)$limit);
        if (is_array($found)) {
            foreach ($found as $id => $row) {
                $row = smgr_stock_db_row_to_array($row);
                if (!isset($row['id']) && is_numeric($id)) { $row['id'] = (int)$id; }
                if ($row) { $rows[] = $row; }
            }
        }
    } catch (Throwable $e) {}
    return $rows;
}

function smgr_stock_db_request_rows($table, array $where = [], $order = 'name ASC', $limit = 500) {
    global $DB;
    if (!isset($DB) || !method_exists($DB, 'request')) { return []; }
    $rows = [];
    $candidates = smgr_stock_table_candidates($table);
    $resolved = smgr_stock_resolve_table($table);
    if ($resolved !== '') { array_unshift($candidates, $resolved); }
    $candidates = array_values(array_unique(array_filter($candidates)));
    foreach ($candidates as $candidate) {
        try {
            $opts = ['FROM' => $candidate, 'LIMIT' => (int)$limit];
            if ($where) { $opts['WHERE'] = $where; }
            if ($order !== '') { $opts['ORDER'] = $order; }
            foreach ($DB->request($opts) as $row) {
                $row = smgr_stock_db_row_to_array($row);
                if ($row) { $rows[] = $row; }
            }
            if ($rows) { return $rows; }
        } catch (Throwable $e) {}
    }
    return $rows;
}

function smgr_stock_raw_rows($table, $whereSql = '', $orderSql = 'name ASC', $limit = 500) {
    global $DB;
    if (!isset($DB)) { return []; }
    $resolved = smgr_stock_sql_table($table);
    if ($resolved === '') { return []; }
    $sql = "SELECT * FROM `$resolved`";
    if (trim($whereSql) !== '') { $sql .= ' WHERE ' . $whereSql; }
    if (trim($orderSql) !== '') { $sql .= ' ORDER BY ' . $orderSql; }
    $sql .= ' LIMIT ' . max(1, (int)$limit);
    $rows = [];
    try {
        $res = $DB->query($sql);
        while ($res && ($row = smgr_stock_fetch_assoc($res))) {
            $rows[] = smgr_stock_db_row_to_array($row);
        }
    } catch (Throwable $e) {}
    return $rows;
}

function smgr_stock_item_source_rows($kind) {
    $cfg = smgr_stock_kind_config($kind);
    $classes = smgr_stock_kind_class_names($cfg['kind']);
    $rows = smgr_stock_common_find_rows($classes['item'], [], 'name ASC', 5000);
    if (!$rows) {
        $rows = smgr_stock_db_request_rows($cfg['item_table'], [], 'name ASC', 5000);
    }
    if (!$rows) {
        $rows = smgr_stock_raw_rows($cfg['item_table'], '', 'name ASC', 5000);
    }
    return $rows;
}

function smgr_stock_unit_source_rows($kind, $item_id, $limit = 10000) {
    $cfg = smgr_stock_kind_config($kind);
    $classes = smgr_stock_kind_class_names($cfg['kind']);
    $fk = $cfg['item_fk'];
    $item_id = (int)$item_id;
    if ($item_id <= 0) { return []; }

    $rows = smgr_stock_common_find_rows($classes['unit'], [$fk => $item_id], 'id ASC', $limit);
    if (!$rows) {
        $rows = smgr_stock_db_request_rows($cfg['unit_table'], [$fk => $item_id], 'id ASC', $limit);
    }
    if (!$rows) {
        $table = smgr_stock_sql_table($cfg['unit_table']);
        if ($table !== '') {
            $rows = smgr_stock_raw_rows($cfg['unit_table'], "`$fk` = " . (int)$item_id, 'id ASC', $limit);
        }
    }
    return $rows;
}

function smgr_stock_row_is_deleted(array $row) {
    return isset($row['is_deleted']) && (int)$row['is_deleted'] !== 0;
}

function smgr_stock_row_is_template(array $row) {
    return isset($row['is_template']) && (int)$row['is_template'] !== 0;
}

function smgr_stock_unit_is_available($kind, array $row) {
    if (smgr_stock_row_is_deleted($row)) { return false; }
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';

    // GLPI marca las unidades usadas con diferentes campos segun version/modulo.
    if (array_key_exists('date_out', $row) && !smgr_stock_is_empty_date_value($row['date_out'])) { return false; }
    if (array_key_exists('date_use', $row) && !smgr_stock_is_empty_date_value($row['date_use'])) { return false; }
    if (array_key_exists('users_id', $row) && (int)$row['users_id'] > 0) { return false; }
    if (array_key_exists('items_id', $row) && (int)$row['items_id'] > 0) { return false; }
    if (array_key_exists('itemtype', $row) && trim((string)$row['itemtype']) !== '') { return false; }

    return true;
}

function smgr_stock_item_matches_search(array $row, $search) {
    $search = trim(mb_strtolower((string)$search));
    if ($search === '') { return true; }
    $hay = [];
    foreach (['name','ref','comment','otherserial','serial'] as $f) {
        if (isset($row[$f])) { $hay[] = (string)$row[$f]; }
    }
    $text = mb_strtolower(implode(' ', $hay));
    return strpos($text, $search) !== false;
}

function smgr_stock_threshold_from_row(array $row) {
    foreach (['alarm_threshold','stock_alert','min_stock','alert_threshold','otherserial'] as $f) {
        if (isset($row[$f]) && is_numeric($row[$f])) { return max(0, (int)$row[$f]); }
    }
    return 0;
}

function smgr_stock_count_available($kind, $item_id) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $item_id = (int)$item_id;
    if ($item_id <= 0) { return 0; }
    $rows = smgr_stock_unit_source_rows($kind, $item_id, 10000);
    $count = 0;
    foreach ($rows as $row) {
        $row = smgr_stock_db_row_to_array($row);
        if (smgr_stock_unit_is_available($kind, $row)) { $count++; }
    }
    return $count;
}



function smgr_stock_count_total_units($kind, $item_id) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $item_id = (int)$item_id;
    if ($item_id <= 0) { return 0; }
    $rows = smgr_stock_unit_source_rows($kind, $item_id, 10000);
    $count = 0;
    foreach ($rows as $row) {
        $row = smgr_stock_db_row_to_array($row);
        if (!smgr_stock_row_is_deleted($row)) { $count++; }
    }
    return $count;
}



function smgr_stock_available_unit_ids($kind, $item_id, $limit) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $item_id = (int)$item_id;
    $limit = max(1, min(200, (int)$limit));
    if ($item_id <= 0) { return []; }
    $rows = smgr_stock_unit_source_rows($kind, $item_id, 10000);
    $ids = [];
    foreach ($rows as $row) {
        $row = smgr_stock_db_row_to_array($row);
        $id = (int)($row['id'] ?? 0);
        if ($id > 0 && smgr_stock_unit_is_available($kind, $row)) {
            $ids[] = $id;
            if (count($ids) >= $limit) { break; }
        }
    }
    return $ids;
}



function smgr_stock_items($kind = 'consumable', $search = '', $state = 'all') {
    $cfg = smgr_stock_kind_config($kind);
    $kind = $cfg['kind'];
    $rows = smgr_stock_item_source_rows($kind);
    $out = [];
    $seen = [];
    foreach ($rows as $row) {
        $row = smgr_stock_db_row_to_array($row);
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) { continue; }
        $seen[$id] = true;
        if (smgr_stock_row_is_deleted($row) || smgr_stock_row_is_template($row)) { continue; }
        if (!smgr_stock_item_matches_search($row, $search)) { continue; }
        $available = smgr_stock_count_available($kind, $id);
        $total = smgr_stock_count_total_units($kind, $id);
        $threshold = smgr_stock_threshold_from_row($row);
        $row['_available'] = $available;
        $row['_total'] = $total;
        $row['_threshold'] = $threshold;
        $row['_status'] = ($available <= 0) ? 'empty' : (($threshold > 0 && $available <= $threshold) ? 'low' : 'ok');
        if ($state === 'low' && !in_array($row['_status'], ['low','empty'], true)) { continue; }
        if ($state === 'empty' && $row['_status'] !== 'empty') { continue; }
        if ($state === 'ok' && $row['_status'] !== 'ok') { continue; }
        $out[] = $row;
    }
    usort($out, static function($a, $b) {
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    return array_slice($out, 0, 500);
}



function smgr_stock_status_label($status) {
    if ($status === 'empty') { return 'Sin stock'; }
    if ($status === 'low') { return 'Stock bajo'; }
    return 'Correcto';
}


function smgr_stock_table_columns($table) {
    global $DB;
    $resolved = smgr_stock_sql_table($table);
    if ($resolved === '' || !isset($DB)) { return []; }
    static $cache = [];
    if (isset($cache[$resolved])) { return $cache[$resolved]; }
    $cols = [];
    try {
        $res = $DB->query("SHOW COLUMNS FROM `$resolved`");
        while ($res && ($row = smgr_stock_fetch_assoc($res))) {
            $field = (string)($row['Field'] ?? ($row['field'] ?? ''));
            if ($field !== '') { $cols[$field] = true; }
        }
    } catch (Throwable $e) {}
    if (!$cols) {
        try {
            $tableEsc = addslashes($resolved);
            $res = $DB->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableEsc'");
            while ($res && ($row = smgr_stock_fetch_assoc($res))) {
                $field = (string)($row['COLUMN_NAME'] ?? ($row['column_name'] ?? ''));
                if ($field !== '') { $cols[$field] = true; }
            }
        } catch (Throwable $e) {}
    }
    return $cache[$resolved] = array_keys($cols);
}

function smgr_stock_col_exists_cached($table, $field) {
    $field = trim((string)$field, " `\t\n\r\0\x0B");
    if ($field === '') { return false; }
    $cols = smgr_stock_table_columns($table);
    if ($cols) { return in_array($field, $cols, true); }
    return smgr_stock_field_exists($table, $field);
}

function smgr_stock_unit_row_by_id($kind, $unit_id) {
    global $DB;
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['unit_table']);
    $unit_id = (int)$unit_id;
    if ($table === '' || $unit_id <= 0 || !isset($DB)) { return []; }
    try {
        $res = $DB->query("SELECT * FROM `$table` WHERE id = " . $unit_id . " LIMIT 1");
        if ($res && ($row = smgr_stock_fetch_assoc($res))) { return smgr_stock_db_row_to_array($row); }
    } catch (Throwable $e) {}
    return [];
}

function smgr_stock_update_unit_row($kind, $unit_id, array $data) {
    global $DB;
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['unit_table']);
    $unit_id = (int)$unit_id;
    if ($table === '' || $unit_id <= 0 || !$data || !isset($DB)) { return false; }
    try {
        if (method_exists($DB, 'update')) {
            $ok = $DB->update($table, $data, ['id' => $unit_id]);
            if ($ok !== false) { return true; }
        }
    } catch (Throwable $e) {}
    return smgr_stock_sql_update($table, $data, 'id = ' . $unit_id);
}

function smgr_stock_delete_unit_row($kind, $unit_id) {
    global $DB;
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['unit_table']);
    $unit_id = (int)$unit_id;
    if ($table === '' || $unit_id <= 0 || !isset($DB)) { return false; }
    try { return (bool)$DB->query("DELETE FROM `$table` WHERE id = " . $unit_id . " LIMIT 1"); }
    catch (Throwable $e) { return false; }
}

function smgr_stock_mark_unit_out($kind, $unit_id, $note = '', array $context = []) {
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['unit_table']);
    $cols = smgr_stock_table_columns($table);
    $has = static function($field) use ($cols, $table) {
        return $cols ? in_array($field, $cols, true) : smgr_stock_field_exists($table, $field);
    };
    $userId = class_exists('Session') ? (int)Session::getLoginUserID() : 0;
    $now = smgr_stock_safe_now();
    $note = trim((string)$note);

    $contextItemtype = trim((string)($context['itemtype'] ?? ''));
    $contextItemsId = (int)($context['items_id'] ?? 0);

    $base = [];
    if ($has('comment') && $note !== '') { $base['comment'] = $note; }
    if ($has('users_id') && $userId > 0) { $base['users_id'] = $userId; }
    if ($has('itemtype')) { $base['itemtype'] = $contextItemtype !== '' ? $contextItemtype : 'User'; }
    if ($has('items_id')) { $base['items_id'] = $contextItemsId > 0 ? $contextItemsId : ($userId > 0 ? $userId : 0); }

    $attempts = [];
    $data = $base;
    if ($has('date_out')) { $data['date_out'] = $now; }
    if ($has('date_use')) { $data['date_use'] = $now; }
    if ($data) { $attempts[] = $data; }

    $data = $base;
    if ($has('date_out')) { $data['date_out'] = $now; }
    if ($data) { $attempts[] = $data; }

    $data = $base;
    if ($has('date_use')) { $data['date_use'] = $now; }
    if ($data) { $attempts[] = $data; }

    $data = [];
    if ($has('is_deleted')) { $data['is_deleted'] = 1; }
    if ($has('comment') && $note !== '') { $data['comment'] = $note; }
    if ($data) { $attempts[] = $data; }

    // Intento especifico para campos habituales aunque la deteccion de columnas de GLPI falle.
    if (!$attempts) {
        $attempts[] = ['date_out' => $now];
        $attempts[] = ['date_use' => $now];
    }

    foreach ($attempts as $data) {
        if (!smgr_stock_update_unit_row($kind, $unit_id, $data)) { continue; }
        $row = smgr_stock_unit_row_by_id($kind, $unit_id);
        if (!$row || !smgr_stock_unit_is_available($kind, $row)) { return true; }
    }

    // Ultimo recurso: eliminamos la unidad para que el disponible baje aunque esta instalacion no permita marcar salida.
    return smgr_stock_delete_unit_row($kind, $unit_id);
}

function smgr_stock_insert_unit($kind, $item_id, $note = '') {
    global $DB;
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['unit_table']);
    $fk = $cfg['item_fk'];
    $item_id = (int)$item_id;
    if ($item_id <= 0 || $table === '' || !smgr_stock_has_table($table)) { return false; }

    $has = static function($field) use ($table) { return smgr_stock_col_exists_cached($table, $field); };
    $data = [$fk => $item_id];
    if ($has('date_in')) { $data['date_in'] = smgr_stock_safe_now(); }
    if ($has('date_out')) { $data['date_out'] = null; }
    if ($has('date_use')) { $data['date_use'] = null; }
    if ($has('entities_id')) { $data['entities_id'] = class_exists('Session') ? (int)Session::getActiveEntity() : 0; }
    if ($has('is_deleted')) { $data['is_deleted'] = 0; }
    if ($has('users_id')) { $data['users_id'] = 0; }
    if ($has('items_id')) { $data['items_id'] = 0; }
    if ($has('itemtype')) { $data['itemtype'] = ''; }
    if ($has('comment') && trim($note) !== '') { $data['comment'] = trim($note); }

    try {
        if (isset($DB) && method_exists($DB, 'insert')) {
            $ok = $DB->insert($table, $data);
            if ($ok !== false) { return true; }
        }
    } catch (Throwable $e) {}
    return smgr_stock_sql_insert($table, $data) > 0;
}

function smgr_stock_remove_units($kind, $item_id, $qty, $note = '', array $context = []) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $qty = max(1, min(200, (int)$qty));
    $item_id = (int)$item_id;
    if ($item_id <= 0) { return 0; }

    $ids = smgr_stock_available_unit_ids($kind, $item_id, $qty);
    if (!$ids) { return 0; }

    $done = 0;
    foreach ($ids as $unit_id) {
        if (smgr_stock_mark_unit_out($kind, (int)$unit_id, $note, $context)) { $done++; }
    }
    return $done;
}

function smgr_stock_add_units($kind, $item_id, $qty, $note = '') {
    $qty = max(1, min(200, (int)$qty)); $done = 0;
    for ($i=0; $i<$qty; $i++) { if (smgr_stock_insert_unit($kind, $item_id, $note)) { $done++; } }
    return $done;
}

function smgr_stock_summary() {
    $cons = smgr_stock_items('consumable', '', 'all');
    $cart = smgr_stock_items('cartridge', '', 'all');
    $all = array_merge($cons, $cart);
    $available = 0; $low = 0; $empty = 0;
    foreach ($all as $row) {
        $available += (int)$row['_available'];
        if ($row['_status'] === 'low') { $low++; }
        if ($row['_status'] === 'empty') { $empty++; }
    }
    return ['items'=>count($all), 'units'=>$available, 'low'=>$low, 'empty'=>$empty, 'consumables'=>count($cons), 'cartridges'=>count($cart)];
}


function smgr_stock_item_display_name($kind, array $row) {
    $name = trim((string)($row['name'] ?? ('Artículo #' . (int)($row['id'] ?? 0))));
    $ref = trim((string)($row['ref'] ?? ''));
    $typeField = ($kind === 'cartridge') ? 'cartridgeitemtypes_id' : 'consumableitemtypes_id';
    $typeName = smgr_stock_item_type_name($kind, (int)($row[$typeField] ?? 0));
    $parts = [];
    if ($typeName !== '') { $parts[] = $typeName; }
    $parts[] = $name !== '' ? $name : ('Artículo #' . (int)($row['id'] ?? 0));
    if ($ref !== '') { $parts[] = 'Ref. ' . $ref; }
    return implode(' · ', $parts);
}

function smgr_stock_selectable_items($include_empty = false) {
    $out = [];
    foreach (['consumable','cartridge'] as $kind) {
        $items = smgr_stock_items($kind, '', 'all');
        foreach ($items as $row) {
            $available = (int)($row['_available'] ?? 0);
            if (!$include_empty && $available <= 0) { continue; }
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) { continue; }
            $cfg = smgr_stock_kind_config($kind);
            $out[] = [
                'kind' => $kind,
                'id' => $id,
                'value' => $kind . ':' . $id,
                'group' => $cfg['label'],
                'single' => $cfg['single'],
                'icon' => $cfg['icon'],
                'label' => $cfg['single'] . ' · ' . smgr_stock_item_display_name($kind, $row) . ' · disponibles ' . $available,
                'plain_label' => smgr_stock_item_display_name($kind, $row),
                'available' => $available,
            ];
        }
    }
    usort($out, static function($a, $b) {
        return [$a['group'], $a['plain_label']] <=> [$b['group'], $b['plain_label']];
    });
    return $out;
}


function smgr_stock_item_url($kind, $itemId) {
    global $CFG_GLPI;
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $itemId = (int)$itemId;
    return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/stock_item.php?kind=' . rawurlencode($kind) . '&id=' . $itemId . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
}

function smgr_stock_ticket_detail_url($ticketId) {
    global $CFG_GLPI;
    return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . (int)$ticketId . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
}

function smgr_stock_technician_summary_url($userId) {
    global $CFG_GLPI;
    return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/tecnico_resumen.php?id=' . (int)$userId . '&v=' . rawurlencode(defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '');
}

function smgr_stock_user_display_name($userId) {
    $userId = (int)$userId;
    if ($userId <= 0 || !class_exists('User')) { return $userId > 0 ? ('Usuario #' . $userId) : ''; }
    try {
        $u = new User();
        if ($u->getFromDB($userId)) {
            $name = trim(((string)($u->fields['firstname'] ?? '')) . ' ' . ((string)($u->fields['realname'] ?? '')));
            if ($name === '') { $name = trim((string)($u->fields['name'] ?? '')); }
            return $name !== '' ? $name : ('Usuario #' . $userId);
        }
    } catch (Throwable $e) {}
    return 'Usuario #' . $userId;
}

function smgr_stock_comment_ticket_id($comment) {
    $comment = (string)$comment;
    if (preg_match('/(?:incidencia|ticket)\s*#(\d+)/iu', $comment, $m)) { return (int)$m[1]; }
    if (preg_match('/solicitud_detalle\.php\?id=(\d+)/iu', $comment, $m)) { return (int)$m[1]; }
    return 0;
}

function smgr_stock_comment_user_id($comment) {
    $comment = (string)$comment;
    if (preg_match('/(?:t[eé]cnico|usuario)\s*[:#]?\s*[^#\n\r]*#(\d+)/iu', $comment, $m)) { return (int)$m[1]; }
    if (preg_match('/tecnico_resumen\.php\?id=(\d+)/iu', $comment, $m)) { return (int)$m[1]; }
    return 0;
}

function smgr_stock_unit_ticket_id(array $unit) {
    if (strcasecmp((string)($unit['itemtype'] ?? ''), 'Ticket') === 0 && (int)($unit['items_id'] ?? 0) > 0) {
        return (int)$unit['items_id'];
    }
    return smgr_stock_comment_ticket_id((string)($unit['comment'] ?? ''));
}

function smgr_stock_unit_technician_id(array $unit) {
    if ((int)($unit['users_id'] ?? 0) > 0) { return (int)$unit['users_id']; }
    return smgr_stock_comment_user_id((string)($unit['comment'] ?? ''));
}

function smgr_stock_unit_out_datetime_value(array $unit) {
    foreach (['date_use','date_out'] as $field) {
        $v = trim((string)($unit[$field] ?? ''));
        if ($v !== '' && $v !== '0000-00-00' && $v !== '0000-00-00 00:00:00') { return $v; }
    }
    $comment = (string)($unit['comment'] ?? '');
    if (preg_match('/Fecha\/hora:\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/u', $comment, $m)) { return $m[1]; }
    return '';
}

function smgr_stock_used_materials_for_ticket($ticketId) {
    $ticketId = (int)$ticketId;
    $out = [];
    if ($ticketId <= 0) { return []; }
    foreach (['consumable','cartridge'] as $kind) {
        foreach (smgr_stock_items($kind, '', 'all') as $item) {
            $itemId = (int)($item['id'] ?? 0);
            if ($itemId <= 0) { continue; }
            $label = smgr_stock_item_display_name($kind, smgr_stock_db_row_to_array($item));
            foreach (smgr_stock_unit_source_rows($kind, $itemId, 10000) as $unit) {
                $unit = smgr_stock_db_row_to_array($unit);
                if (smgr_stock_unit_is_available($kind, $unit)) { continue; }
                if (smgr_stock_unit_ticket_id($unit) !== $ticketId) { continue; }
                $key = $kind . ':' . $itemId;
                if (!isset($out[$key])) {
                    $cfg = smgr_stock_kind_config($kind);
                    $out[$key] = [
                        'kind' => $kind,
                        'item_id' => $itemId,
                        'label' => $label,
                        'single' => $cfg['single'] ?? 'Artículo',
                        'qty' => 0,
                        'dates' => [],
                        'technicians' => [],
                        'url' => smgr_stock_item_url($kind, $itemId),
                    ];
                }
                $out[$key]['qty']++;
                $dt = smgr_stock_unit_out_datetime_value($unit);
                if ($dt !== '') { $out[$key]['dates'][$dt] = true; }
                $uid = smgr_stock_unit_technician_id($unit);
                if ($uid > 0) { $out[$key]['technicians'][$uid] = smgr_stock_user_display_name($uid); }
            }
        }
    }
    foreach ($out as &$row) {
        $row['dates'] = array_keys($row['dates']);
        rsort($row['dates']);
        $row['technicians'] = $row['technicians'];
    }
    unset($row);
    return array_values($out);
}

function smgr_stock_parse_value($value) {
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^(consumable|cartridge):(\d+)$/', $value, $m)) {
        return ['', 0];
    }
    return [$m[1], (int)$m[2]];
}

function smgr_stock_consume_for_ticket($value, $qty, $ticketId, $note = '') {
    [$kind, $itemId] = smgr_stock_parse_value($value);
    $qty = max(1, min(50, (int)$qty));
    $ticketId = (int)$ticketId;
    if ($kind === '' || $itemId <= 0) { return [true, '', 0]; }
    $label = $kind . ' #' . $itemId;
    foreach (smgr_stock_selectable_items(true) as $opt) {
        if ($opt['kind'] === $kind && (int)$opt['id'] === $itemId) { $label = $opt['plain_label']; break; }
    }
    $now = smgr_stock_safe_now();
    $userId = class_exists('Session') ? (int)Session::getLoginUserID() : 0;
    $userLabel = $userId > 0 ? smgr_stock_user_display_name($userId) : 'Equipo TIC';
    $ticketUrl = smgr_stock_ticket_detail_url($ticketId);
    $techUrl = $userId > 0 ? smgr_stock_technician_summary_url($userId) : '';
    $fullNote = trim(
        ($note !== '' ? $note . ' · ' : '') .
        'Usado en incidencia #' . $ticketId . ' · ' .
        'Técnico: ' . $userLabel . ($userId > 0 ? ' #' . $userId : '') . ' · ' .
        'Fecha/hora: ' . $now . ' · ' .
        'incidencia: ' . $ticketUrl .
        ($techUrl !== '' ? ' · perfil tecnico: ' . $techUrl : '')
    );
    $context = ['itemtype' => 'Ticket', 'items_id' => $ticketId];
    $done = smgr_stock_remove_units($kind, $itemId, $qty, $fullNote, $context);
    if ($done <= 0) { return [false, 'No se pudo descontar stock de: ' . $label, 0]; }
    return [true, $qty . 'x ' . $label, $done];
}



function smgr_stock_sql_insert($table, array $data) {
    global $DB;
    $table = smgr_stock_sql_table($table);
    if ($table === '' || !isset($DB) || !$data) { return 0; }
    $fields = [];
    $values = [];
    foreach ($data as $k => $v) {
        $k = trim((string)$k, " `\t\n\r\0\x0B");
        if ($k === '') { continue; }
        $fields[] = '`' . str_replace('`', '', $k) . '`';
        if (is_int($v) || is_float($v)) { $values[] = (string)$v; }
        else { $values[] = smgr_stock_quote($v); }
    }
    if (!$fields) { return 0; }
    try {
        $ok = $DB->query("INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")");
        if (!$ok) { return 0; }
        if (method_exists($DB, 'insertId')) { return (int)$DB->insertId(); }
        if (method_exists($DB, 'insert_id')) { return (int)$DB->insert_id(); }
        $res = $DB->query('SELECT LAST_INSERT_ID() AS id');
        if ($res && ($row = smgr_stock_fetch_assoc($res))) { return (int)($row['id'] ?? 0); }
    } catch (Throwable $e) {}
    return 0;
}

function smgr_stock_sql_update($table, array $data, $whereSql) {
    global $DB;
    $table = smgr_stock_sql_table($table);
    $whereSql = trim((string)$whereSql);
    if ($table === '' || !isset($DB) || !$data || $whereSql === '') { return false; }
    $set = [];
    foreach ($data as $k => $v) {
        $k = trim((string)$k, " `\t\n\r\0\x0B");
        if ($k === '') { continue; }
        $set[] = '`' . str_replace('`', '', $k) . '` = ' . ((is_int($v) || is_float($v)) ? (string)$v : smgr_stock_quote($v));
    }
    if (!$set) { return false; }
    try { return (bool)$DB->query("UPDATE `$table` SET " . implode(', ', $set) . " WHERE " . $whereSql); }
    catch (Throwable $e) { return false; }
}

function smgr_stock_type_table($kind) {
    return ($kind === 'cartridge') ? 'glpi_cartridgeitemtypes' : 'glpi_consumableitemtypes';
}

function smgr_stock_type_fk($kind) {
    return ($kind === 'cartridge') ? 'cartridgeitemtypes_id' : 'consumableitemtypes_id';
}

function smgr_stock_types($kind = 'consumable') {
    global $DB;
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $table = smgr_stock_sql_table(smgr_stock_type_table($kind));
    if (!smgr_stock_has_table($table)) { return []; }
    $where = [];
    if (smgr_stock_field_exists($table, 'is_deleted')) { $where[] = '`is_deleted` = 0'; }
    if (smgr_stock_field_exists($table, 'is_template')) { $where[] = '`is_template` = 0'; }
    $rows = [];
    try {
        $res = $DB->query("SELECT id, name FROM `$table`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY name ASC LIMIT 500");
        while ($res && ($row = smgr_stock_fetch_assoc($res))) {
            $id = (int)($row['id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            if ($id > 0 && $name !== '') { $rows[] = ['id' => $id, 'name' => $name]; }
        }
    } catch (Throwable $e) {}
    return $rows;
}

function smgr_stock_create_type($kind, $name) {
    global $DB;
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $name = trim((string)$name);
    if ($name === '') { return [false, 'Escribe un nombre de categoria.', 0]; }
    $table = smgr_stock_sql_table(smgr_stock_type_table($kind));
    if (!smgr_stock_has_table($table)) { return [false, 'No existe la tabla de categorias de GLPI para este tipo.', 0]; }
    $data = ['name' => $name];
    if (smgr_stock_field_exists($table, 'entities_id')) { $data['entities_id'] = class_exists('Session') ? (int)Session::getActiveEntity() : 0; }
    if (smgr_stock_field_exists($table, 'is_recursive')) { $data['is_recursive'] = 1; }
    if (smgr_stock_field_exists($table, 'is_deleted')) { $data['is_deleted'] = 0; }
    try {
        $newId = 0;
        if (method_exists($DB, 'insert')) {
            $ok = $DB->insert($table, $data);
            if ($ok !== false && method_exists($DB, 'insertId')) { $newId = (int)$DB->insertId(); }
        }
        if ($newId <= 0) { $newId = smgr_stock_sql_insert($table, $data); }
        if ($newId > 0) { return [true, 'Categoria creada: ' . $name, $newId]; }
    } catch (Throwable $e) { return [false, 'No se pudo crear la categoria: ' . $e->getMessage(), 0]; }
    return [false, 'No se pudo crear la categoria.', 0];
}

function smgr_stock_create_item($kind, $name, $typeId = 0, $ref = '', $threshold = 0, $comment = '') {
    global $DB;
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['item_table']);
    $name = trim((string)$name);
    if ($name === '') { return [false, 'Escribe un nombre de articulo.', 0]; }
    if (!smgr_stock_has_table($table)) { return [false, 'No existe la tabla de articulos de GLPI para este tipo.', 0]; }
    $data = ['name' => $name];
    if (smgr_stock_field_exists($table, 'entities_id')) { $data['entities_id'] = class_exists('Session') ? (int)Session::getActiveEntity() : 0; }
    if (smgr_stock_field_exists($table, 'is_recursive')) { $data['is_recursive'] = 1; }
    if (smgr_stock_field_exists($table, 'is_deleted')) { $data['is_deleted'] = 0; }
    $typeFk = smgr_stock_type_fk($kind);
    if ((int)$typeId > 0 && smgr_stock_field_exists($table, $typeFk)) { $data[$typeFk] = (int)$typeId; }
    $ref = trim((string)$ref);
    if ($ref !== '' && smgr_stock_field_exists($table, 'ref')) { $data['ref'] = $ref; }
    $threshold = max(0, (int)$threshold);
    foreach (['alarm_threshold','stock_alert','min_stock','alert_threshold'] as $f) {
        if (smgr_stock_field_exists($table, $f)) { $data[$f] = $threshold; break; }
    }
    $comment = trim((string)$comment);
    if ($comment !== '' && smgr_stock_field_exists($table, 'comment')) { $data['comment'] = $comment; }
    try {
        $newId = 0;
        if (method_exists($DB, 'insert')) {
            $ok = $DB->insert($table, $data);
            if ($ok !== false && method_exists($DB, 'insertId')) { $newId = (int)$DB->insertId(); }
        }
        if ($newId <= 0) { $newId = smgr_stock_sql_insert($table, $data); }
        if ($newId <= 0) {
            $q = smgr_stock_quote($name);
            $res = $DB->query("SELECT id FROM `$table` WHERE name = $q ORDER BY id DESC LIMIT 1");
            if ($res && ($r = smgr_stock_fetch_assoc($res))) { $newId = (int)$r['id']; }
        }
        if ($newId > 0) { return [true, 'Articulo creado: ' . $name, $newId]; }
    } catch (Throwable $e) { return [false, 'No se pudo crear el articulo: ' . $e->getMessage(), 0]; }
    return [false, 'No se pudo crear el articulo.', 0];
}


function smgr_stock_item_row($kind, $item_id) {
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $item_id = (int)$item_id;
    if ($item_id <= 0) { return []; }
    foreach (smgr_stock_item_source_rows($kind) as $row) {
        $row = smgr_stock_db_row_to_array($row);
        if ((int)($row['id'] ?? 0) === $item_id) {
            $row['_available'] = smgr_stock_count_available($kind, $item_id);
            $row['_total'] = smgr_stock_count_total_units($kind, $item_id);
            $row['_threshold'] = smgr_stock_threshold_from_row($row);
            $row['_status'] = ((int)$row['_available'] <= 0) ? 'empty' : (((int)$row['_threshold'] > 0 && (int)$row['_available'] <= (int)$row['_threshold']) ? 'low' : 'ok');
            return $row;
        }
    }
    return [];
}

function smgr_stock_update_item($kind, $item_id, array $input) {
    global $DB;
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $item_id = (int)$item_id;
    if ($item_id <= 0 || !isset($DB)) { return [false, 'Articulo no valido.']; }
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['item_table']);
    if ($table === '' || !smgr_stock_has_table($table)) { return [false, 'No existe la tabla de articulos.']; }

    $data = [];
    $name = trim((string)($input['name'] ?? ''));
    if ($name !== '' && smgr_stock_field_exists($table, 'name')) { $data['name'] = $name; }
    $ref = trim((string)($input['ref'] ?? ''));
    if (smgr_stock_field_exists($table, 'ref')) { $data['ref'] = $ref; }
    $comment = trim((string)($input['comment'] ?? ''));
    if (smgr_stock_field_exists($table, 'comment')) { $data['comment'] = $comment; }
    $typeFk = smgr_stock_type_fk($kind);
    if (smgr_stock_field_exists($table, $typeFk)) { $data[$typeFk] = max(0, (int)($input['type_id'] ?? 0)); }
    $threshold = max(0, (int)($input['threshold'] ?? 0));
    foreach (['alarm_threshold','stock_alert','min_stock','alert_threshold'] as $f) {
        if (smgr_stock_field_exists($table, $f)) { $data[$f] = $threshold; break; }
    }
    if (!$data) { return [false, 'No hay campos editables en esta instalacion.']; }
    try {
        $ok = false;
        if (method_exists($DB, 'update')) {
            $res = $DB->update($table, $data, ['id' => $item_id]);
            $ok = ($res !== false);
        }
        if (!$ok) { $ok = smgr_stock_sql_update($table, $data, 'id = ' . (int)$item_id); }
        return $ok ? [true, 'Articulo actualizado correctamente.'] : [false, 'No se pudo actualizar el articulo.'];
    } catch (Throwable $e) { return [false, 'No se pudo actualizar: ' . $e->getMessage()]; }
}

function smgr_stock_archive_item($kind, $item_id) {
    global $DB;
    $kind = $kind === 'cartridge' ? 'cartridge' : 'consumable';
    $item_id = (int)$item_id;
    $cfg = smgr_stock_kind_config($kind);
    $table = smgr_stock_sql_table($cfg['item_table']);
    if ($item_id <= 0 || $table === '' || !isset($DB)) { return [false, 'Articulo no valido.']; }
    if (!smgr_stock_field_exists($table, 'is_deleted')) { return [false, 'Esta tabla no permite archivar desde el plugin. Usa la vista nativa de GLPI.']; }
    try {
        $DB->query("UPDATE `$table` SET is_deleted = 1 WHERE id = " . $item_id);
        return [true, 'Articulo archivado.'];
    } catch (Throwable $e) { return [false, 'No se pudo archivar: ' . $e->getMessage()]; }
}

function smgr_stock_adjust_units($kind, $item_id, $target, $note = '') {
    $target = max(0, min(999, (int)$target));
    $current = smgr_stock_count_available($kind, $item_id);
    if ($target === $current) { return [true, 'Stock ya estaba ajustado a ' . $target . '. No se han realizado cambios.', 0]; }
    if ($target > $current) {
        $need = $target - $current;
        $done = smgr_stock_add_units($kind, $item_id, $need, $note !== '' ? $note : 'Ajuste de stock desde Gestion School Manager');
        if ($done === $need) { return [true, 'Ajuste realizado. Entrada: ' . $done . ' unidad(es).', $done]; }
        return [false, 'No se pudo completar el ajuste. Entrada realizada: ' . $done . ' de ' . $need . ' unidad(es).', $done];
    }
    $need = $current - $target;
    $done = smgr_stock_remove_units($kind, $item_id, $need, $note !== '' ? $note : 'Ajuste de stock desde Gestion School Manager');
    if ($done === $need) { return [true, 'Ajuste realizado. Salida: ' . $done . ' unidad(es).', $done]; }
    return [false, 'No se pudo completar el ajuste. Salida realizada: ' . $done . ' de ' . $need . ' unidad(es).', $done];
}

function smgr_stock_recent_units($kind, $item_id, $limit = 80) {
    $rows = smgr_stock_unit_source_rows($kind, $item_id, max(1, min(500, (int)$limit)));
    usort($rows, static function($a, $b) {
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    });
    return array_slice($rows, 0, $limit);
}

function smgr_stock_export_rows($kind, $search = '', $state = 'all', $typeId = 0) {
    $items = smgr_stock_items($kind, $search, $state);
    $typeFk = smgr_stock_type_fk($kind);
    $typeId = (int)$typeId;
    if ($typeId > 0) {
        $items = array_values(array_filter($items, static fn($r) => (int)($r[$typeFk] ?? 0) === $typeId));
    }
    return $items;
}
