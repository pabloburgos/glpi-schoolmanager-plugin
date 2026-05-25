<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/assets_helpers.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');

$aulasAll = require(__DIR__ . '/../inc/aulas_data.php');
$root = $CFG_GLPI['root_doc'] ?? '';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg');
$message = '';
$messageType = 'info';
function pc_post($key, $default = '') { return isset($_REQUEST[$key]) ? trim((string)$_REQUEST[$key]) : $default; }
function pc_old($key, $default = '') { return htmlspecialchars(pc_post($key, $default), ENT_QUOTES, 'UTF-8'); }
function pc_selected($key, $value, $default = false) {
    $posted = pc_post($key, null);
    if ($posted === null || $posted === '') { return $default ? ' selected' : ''; }
    return ((string)$posted === (string)$value) ? ' selected' : '';
}


function pc_first_request_int(array $keys, $default = 0) {
    foreach ($keys as $key) {
        if (isset($_REQUEST[$key]) && trim((string)$_REQUEST[$key]) !== '') {
            return (int)$_REQUEST[$key];
        }
    }
    return (int)$default;
}

function pc_location_label_by_id($id) {
    $id = (int)$id;
    if ($id <= 0) { return ''; }
    if (function_exists('plugin_schoolmanager_asset_location_label')) {
        try {
            $label = plugin_schoolmanager_asset_location_label($id);
            if ($label !== '' && $label !== 'Sin ubicación') { return $label; }
        } catch (Throwable $e) {}
    }
    if (class_exists('Dropdown')) {
        try {
            $name = Dropdown::getDropdownName('glpi_locations', $id);
            if (function_exists('plugin_schoolmanager_short_location')) { return plugin_schoolmanager_short_location($name); }
            return trim((string)$name);
        } catch (Throwable $e) {}
    }
    return '';
}

function pc_asset_label_for_ticket($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === '__none__') { return ['', '', 0]; }
    if (!preg_match('/^([A-Za-z_\\]+):(\\d+)$/', $value, $m)) { return ['', '', 0]; }
    $type = $m[1];
    $id = (int)$m[2];
    if ($id <= 0 || !function_exists('plugin_schoolmanager_asset_types')) { return ['', '', 0]; }
    $types = plugin_schoolmanager_asset_types();
    if (!isset($types[$type])) { return ['', '', 0]; }
    $label = $types[$type]['label'] ?? $type;
    $name = $label . ' #' . $id;
    try {
        if (function_exists('plugin_schoolmanager_get_asset_row')) {
            $row = plugin_schoolmanager_get_asset_row($type, $id);
            if ($row) {
                $title = function_exists('plugin_schoolmanager_asset_display_title') ? plugin_schoolmanager_asset_display_title($type, $row) : (function_exists('plugin_schoolmanager_asset_clean_title') ? plugin_schoolmanager_asset_clean_title($type, $row) : trim((string)($row['name'] ?? '')));
                if ($title !== '') { $name = $label . ' · ' . $title . ' (ID ' . $id . ')'; }
            }
        }
    } catch (Throwable $e) {}
    return [$name, $type, $id];
}

function pc_link_asset_to_ticket($ticketId, $type, $assetId) {
    $ticketId = (int)$ticketId; $assetId = (int)$assetId; $type = trim((string)$type);
    if ($ticketId <= 0 || $assetId <= 0 || $type === '') { return; }
    try {
        if (class_exists('Item_Ticket')) {
            $link = new Item_Ticket();
            $link->add(['tickets_id' => $ticketId, 'itemtype' => $type, 'items_id' => $assetId]);
            return;
        }
    } catch (Throwable $e) {}
    try {
        global $DB;
        if (isset($DB) && method_exists($DB, 'tableExists') && $DB->tableExists('glpi_items_tickets')) {
            if (method_exists($DB, 'insert')) {
                $DB->insert('glpi_items_tickets', ['tickets_id' => $ticketId, 'itemtype' => $type, 'items_id' => $assetId]);
            }
        }
    } catch (Throwable $e) {}
}


function pc_csrf_token_field() {
    if (method_exists('Session', 'getNewCSRFToken')) {
        static $tok = null; if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . htmlspecialchars($tok, ENT_QUOTES, 'UTF-8') . '">';
    }
}


function pc_category_icon($name) {
    $t = mb_strtolower((string)$name, 'UTF-8');
    if (preg_match('/hardware|ordenador|port[aá]til|chromebook|monitor|impresora|teclado|rat[oó]n|proyector|pizarra/', $t)) { return 'pc-i-computer'; }
    if (preg_match('/software|sistema|aplicaci[oó]n|ofim[aá]tica|navegador|antivirus|licencia/', $t)) { return 'pc-i-puzzle'; }
    if (preg_match('/red|comunicaciones|internet|wifi|switch|router|dns|dhcp|servidor/', $t)) { return 'pc-i-network'; }
    if (preg_match('/cuenta|acceso|usuario|contrase[nñ]a|microsoft|permiso/', $t)) { return 'pc-i-lock'; }
    if (preg_match('/solicitud|reserva|pr[eé]stamo|compra|alta|cambio/', $t)) { return 'pc-i-note'; }
    if (preg_match('/mantenimiento|preventivo|revisi[oó]n|actualizaci[oó]n|limpieza|backup/', $t)) { return 'pc-i-tools'; }
    return 'pc-i-pin';
}

function pc_label_from_kind($kind) {
    $labels = [
        'computer' => 'Ordenador',
        'software' => 'Software en ordenador',
        'network' => 'Internet / red',
        'projector' => 'Proyector o pantalla',
        'printer' => 'Impresora',
        'whiteboard' => 'Pizarra digital',
        'audio' => 'Audio',
        'account' => 'Usuario / contraseña',
        'other' => 'Otro',
    ];
    return $labels[$kind] ?? 'Otro';
}

function pc_kind_from_text($text) {
    $t = mb_strtolower((string)$text, 'UTF-8');
    if (preg_match('/ordenador|comput|equipo|pc|port[aá]til|windows/', $t)) { return 'computer'; }
    if (preg_match('/software|program|aplicaci[oó]n|licencia|office|chrome|moodle/', $t)) { return 'software'; }
    if (preg_match('/red|internet|wifi|wi-fi|conexi[oó]n|cable/', $t)) { return 'network'; }
    if (preg_match('/proyector|pantalla|hdmi|vga|imagen/', $t)) { return 'projector'; }
    if (preg_match('/impresora|impresi[oó]n|t[oó]ner|tinta/', $t)) { return 'printer'; }
    if (preg_match('/pizarra|digital|t[aá]ctil/', $t)) { return 'whiteboard'; }
    if (preg_match('/audio|sonido|altavoz|micr[oó]fono/', $t)) { return 'audio'; }
    if (preg_match('/usuario|contrase[nñ]a|cuenta|correo|login|sesi[oó]n/', $t)) { return 'account'; }
    return 'other';
}


function pc_asset_types_from_category_text($text) {
    $t = mb_strtolower((string)$text, 'UTF-8');
    if (preg_match('/monitor|pantalla de ordenador/u', $t)) { return ['Monitor']; }
    if (preg_match('/ordenador|comput|equipo|\bpc\b|port[aá]til|chromebook|windows/u', $t)) { return ['Computer']; }
    if (preg_match('/impresora|impresi[oó]n|t[oó]ner|tinta|esc[aá]ner/u', $t)) { return ['Printer']; }
    if (preg_match('/red|internet|wifi|wi-fi|conexi[oó]n|cable|switch|router|punto de red/u', $t)) { return ['NetworkEquipment']; }
    if (preg_match('/proyector/u', $t)) { return ['Projector']; }
    if (preg_match('/teclado|rat[oó]n|perif[eé]rico|hdmi|cable|adaptador/u', $t)) { return ['Peripheral']; }
    if (preg_match('/tel[eé]fono/u', $t)) { return ['Phone']; }
    return [];
}

function pc_asset_types_attr($text) {
    return implode(',', pc_asset_types_from_category_text($text));
}

function pc_db_field_exists_safe($table, $field) {
    global $DB;
    if (!isset($DB)) { return false; }
    try {
        if (method_exists($DB, 'fieldExists')) { return (bool)$DB->fieldExists($table, $field); }
        $tableEsc = addslashes($table); $fieldEsc = addslashes($field);
        $res = $DB->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableEsc' AND COLUMN_NAME = '$fieldEsc' LIMIT 1");
        return $res && method_exists($DB, 'numrows') ? $DB->numrows($res) > 0 : (bool)$res;
    } catch (Throwable $e) { return false; }
}

function pc_load_itil_categories() {
    global $DB;
    $table = 'glpi_itilcategories';
    // Fallback basado en las categorias que el usuario tiene creadas en GLPI.
    // Solo se usa si no se puede leer la BBDD.
    $fallback = [
        ['id'=>1,'name'=>'Hardware','complete'=>'Hardware','kind'=>'computer','children'=>[
            ['id'=>7,'name'=>'Ordenador','complete'=>'Hardware > Ordenador','kind'=>'computer','children'=>[]],
            ['id'=>8,'name'=>'Portatil','complete'=>'Hardware > Portatil','kind'=>'computer','children'=>[]],
            ['id'=>9,'name'=>'Chromebook','complete'=>'Hardware > Chromebook','kind'=>'computer','children'=>[]],
            ['id'=>10,'name'=>'Proyector','complete'=>'Hardware > Proyector','kind'=>'projector','children'=>[]],
            ['id'=>11,'name'=>'Impresora','complete'=>'Hardware > Impresora','kind'=>'printer','children'=>[]],
            ['id'=>12,'name'=>'Monitor','complete'=>'Hardware > Monitor','kind'=>'monitor','children'=>[]],
            ['id'=>13,'name'=>'Pizarra digital','complete'=>'Hardware > Pizarra digital','kind'=>'whiteboard','children'=>[]],
            ['id'=>14,'name'=>'Altavoces / sonido','complete'=>'Hardware > Altavoces / sonido','kind'=>'audio','children'=>[]],
            ['id'=>15,'name'=>'Teclado','complete'=>'Hardware > Teclado','kind'=>'computer','children'=>[]],
            ['id'=>16,'name'=>'Raton','complete'=>'Hardware > Raton','kind'=>'computer','children'=>[]],
            ['id'=>17,'name'=>'Escaner','complete'=>'Hardware > Escaner','kind'=>'printer','children'=>[]],
            ['id'=>18,'name'=>'Plastificadora','complete'=>'Hardware > Plastificadora','kind'=>'other','children'=>[]],
        ]],
        ['id'=>2,'name'=>'Software','complete'=>'Software','kind'=>'software','children'=>[
            ['id'=>19,'name'=>'Sistema operativo','complete'=>'Software > Sistema operativo','kind'=>'software','children'=>[]],
            ['id'=>20,'name'=>'Aplicacion educativa','complete'=>'Software > Aplicacion educativa','kind'=>'software','children'=>[]],
            ['id'=>21,'name'=>'Ofimatica','complete'=>'Software > Ofimatica','kind'=>'software','children'=>[]],
            ['id'=>22,'name'=>'Navegador','complete'=>'Software > Navegador','kind'=>'software','children'=>[]],
            ['id'=>23,'name'=>'Antivirus / seguridad','complete'=>'Software > Antivirus / seguridad','kind'=>'software','children'=>[]],
            ['id'=>24,'name'=>'Instalacion de software','complete'=>'Software > Instalacion de software','kind'=>'software','children'=>[]],
            ['id'=>25,'name'=>'Licencias','complete'=>'Software > Licencias','kind'=>'software','children'=>[]],
        ]],
        ['id'=>3,'name'=>'Red y comunicaciones','complete'=>'Red y comunicaciones','kind'=>'network','children'=>[
            ['id'=>26,'name'=>'Internet','complete'=>'Red y comunicaciones > Internet','kind'=>'network','children'=>[]],
            ['id'=>27,'name'=>'WiFi','complete'=>'Red y comunicaciones > WiFi','kind'=>'network','children'=>[]],
            ['id'=>28,'name'=>'Punto de red','complete'=>'Red y comunicaciones > Punto de red','kind'=>'network','children'=>[]],
            ['id'=>29,'name'=>'Switch','complete'=>'Red y comunicaciones > Switch','kind'=>'network','children'=>[]],
            ['id'=>30,'name'=>'Router','complete'=>'Red y comunicaciones > Router','kind'=>'network','children'=>[]],
            ['id'=>31,'name'=>'Punto de acceso WiFi','complete'=>'Red y comunicaciones > Punto de acceso WiFi','kind'=>'network','children'=>[]],
            ['id'=>32,'name'=>'Servidor','complete'=>'Red y comunicaciones > Servidor','kind'=>'network','children'=>[]],
            ['id'=>33,'name'=>'DNS / IP / DHCP','complete'=>'Red y comunicaciones > DNS / IP / DHCP','kind'=>'network','children'=>[]],
        ]],
        ['id'=>4,'name'=>'Cuentas y accesos','complete'=>'Cuentas y accesos','kind'=>'account','children'=>[
            ['id'=>34,'name'=>'Contrasena','complete'=>'Cuentas y accesos > Contrasena','kind'=>'account','children'=>[]],
            ['id'=>35,'name'=>'Cuenta bloqueada','complete'=>'Cuentas y accesos > Cuenta bloqueada','kind'=>'account','children'=>[]],
            ['id'=>36,'name'=>'Alta de usuario','complete'=>'Cuentas y accesos > Alta de usuario','kind'=>'account','children'=>[]],
            ['id'=>37,'name'=>'Baja de usuario','complete'=>'Cuentas y accesos > Baja de usuario','kind'=>'account','children'=>[]],
            ['id'=>38,'name'=>'Permisos','complete'=>'Cuentas y accesos > Permisos','kind'=>'account','children'=>[]],
            ['id'=>39,'name'=>'Microsoft / Cuenta institucional','complete'=>'Cuentas y accesos > Microsoft / Cuenta institucional','kind'=>'account','children'=>[]],
            ['id'=>40,'name'=>'Acceso a plataforma educativa','complete'=>'Cuentas y accesos > Acceso a plataforma educativa','kind'=>'account','children'=>[]],
        ]],
        ['id'=>5,'name'=>'Solicitudes','complete'=>'Solicitudes','kind'=>'request','children'=>[
            ['id'=>41,'name'=>'Prestamo de equipo','complete'=>'Solicitudes > Prestamo de equipo','kind'=>'request','children'=>[]],
            ['id'=>42,'name'=>'Instalacion de software','complete'=>'Solicitudes > Instalacion de software','kind'=>'request','children'=>[]],
            ['id'=>43,'name'=>'Alta de equipo','complete'=>'Solicitudes > Alta de equipo','kind'=>'request','children'=>[]],
            ['id'=>44,'name'=>'Cambio de aula','complete'=>'Solicitudes > Cambio de aula','kind'=>'request','children'=>[]],
            ['id'=>45,'name'=>'Reserva de material','complete'=>'Solicitudes > Reserva de material','kind'=>'request','children'=>[]],
            ['id'=>46,'name'=>'Compra o sustitucion','complete'=>'Solicitudes > Compra o sustitucion','kind'=>'request','children'=>[]],
        ]],
        ['id'=>6,'name'=>'Mantenimiento preventivo','complete'=>'Mantenimiento preventivo','kind'=>'maintenance','children'=>[
            ['id'=>47,'name'=>'Revision de aula','complete'=>'Mantenimiento preventivo > Revision de aula','kind'=>'maintenance','children'=>[]],
            ['id'=>48,'name'=>'Revision de red','complete'=>'Mantenimiento preventivo > Revision de red','kind'=>'maintenance','children'=>[]],
            ['id'=>49,'name'=>'Revision de impresoras','complete'=>'Mantenimiento preventivo > Revision de impresoras','kind'=>'maintenance','children'=>[]],
            ['id'=>50,'name'=>'Actualizaciones','complete'=>'Mantenimiento preventivo > Actualizaciones','kind'=>'maintenance','children'=>[]],
            ['id'=>51,'name'=>'Limpieza de equipos','complete'=>'Mantenimiento preventivo > Limpieza de equipos','kind'=>'maintenance','children'=>[]],
            ['id'=>52,'name'=>'Comprobacion de backups','complete'=>'Mantenimiento preventivo > Comprobacion de backups','kind'=>'maintenance','children'=>[]],
        ]],
    ];
    if (!isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists($table)) { return $fallback; }

    // Cargamos las categorías reales de GLPI desde BBDD. No filtramos por is_incident
    // ni is_helpdeskvisible porque muchas veces esas marcas no están en los padres y
    // eso dejaba el selector vacío o impedía elegir categorías hijas.
    $fields = ['id','name','itilcategories_id'];
    foreach (['completename','level','is_active','is_deleted'] as $f) {
        if (pc_db_field_exists_safe($table, $f)) { $fields[] = $f; }
    }
    $where = [];
    if (in_array('is_deleted', $fields, true)) { $where[] = 'is_deleted = 0'; }
    if (in_array('is_active', $fields, true)) { $where[] = 'is_active = 1'; }
    $sql = 'SELECT ' . implode(',', $fields) . ' FROM ' . $table;
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= in_array('completename', $fields, true) ? ' ORDER BY completename ASC' : ' ORDER BY name ASC';

    $rows = [];
    try {
        $res = $DB->query($sql);
        if (!$res) { return $fallback; }
        while ($row = $DB->fetchAssoc($res)) {
            $id = (int)$row['id'];
            $name = trim((string)$row['name']);
            if ($id <= 0 || $name === '') { continue; }
            $complete = isset($row['completename']) && trim((string)$row['completename']) !== '' ? trim((string)$row['completename']) : $name;
            $rows[$id] = [
                'id' => $id,
                'name' => $name,
                'complete' => $complete,
                'parent_id' => (int)($row['itilcategories_id'] ?? 0),
                'level' => (int)($row['level'] ?? 1),
                'kind' => pc_kind_from_text($complete . ' ' . $name),
                'children' => [],
            ];
        }
    } catch (Throwable $e) { return $fallback; }
    if (!$rows) { return $fallback; }

    $children = [];
    foreach ($rows as $id => $row) { $children[$row['parent_id']][] = $id; }

    $build = function($id) use (&$build, &$rows, &$children) {
        $node = $rows[$id];
        $node['children'] = [];
        foreach (($children[$id] ?? []) as $cid) {
            if (isset($rows[$cid])) { $node['children'][] = $build($cid); }
        }
        return $node;
    };

    // Seleccion de categorias padre reales.
    // IMPORTANTE: "Incidencias" puede existir solo como carpeta tecnica, pero NO debe
    // aparecer como categoria padre seleccionable. Las categorias padre buenas son las
    // de primer nivel que ve el usuario en GLPI: Hardware, Software, Red y comunicaciones,
    // Cuentas y accesos, Solicitudes, Mantenimiento preventivo, etc.
    $isIncidentFolder = static function($row) {
        $n = mb_strtolower(trim((string)($row['name'] ?? '')), 'UTF-8');
        $c = mb_strtolower(trim((string)($row['complete'] ?? '')), 'UTF-8');
        return preg_match('/^incidencias?$/u', $n) || preg_match('/(^|\s>\s)incidencias?$/u', $c);
    };

    $topIds = [];
    foreach ($rows as $id => $row) {
        $parentId = (int)$row['parent_id'];
        if (($parentId === 0 || !isset($rows[$parentId])) && !$isIncidentFolder($row)) {
            $topIds[] = $id;
        }
    }

    // Si en alguna instalacion las categorias buenas cuelgan de una carpeta "Incidencias",
    // usamos sus hijos directos, pero seguimos ocultando la propia carpeta Incidencias.
    if (!$topIds) {
        foreach ($rows as $id => $row) {
            if ($isIncidentFolder($row)) {
                foreach (($children[$id] ?? []) as $cid) {
                    if (isset($rows[$cid]) && !$isIncidentFolder($rows[$cid])) { $topIds[] = $cid; }
                }
            }
        }
    }

    // Ultimo respaldo: cualquier categoria raiz, salvo Incidencias.
    if (!$topIds) {
        foreach ($rows as $id => $row) {
            if (!$isIncidentFolder($row)) { $topIds[] = $id; }
        }
    }

    $topIds = array_values(array_unique($topIds));
    $out = [];
    foreach ($topIds as $id) { if (isset($rows[$id])) { $out[] = $build($id); } }
    return $out ?: $fallback;
}
$itilCategories = pc_load_itil_categories();

function pc_category_label_by_id($id) {
    global $DB;
    $id = (int)$id;
    if ($id <= 0 || !isset($DB)) { return ''; }
    try {
        $table = 'glpi_itilcategories';
        if (method_exists($DB, 'tableExists') && !$DB->tableExists($table)) { return ''; }
        $field = pc_db_field_exists_safe($table, 'completename') ? 'completename' : 'name';
        $res = $DB->query("SELECT `$field` AS label FROM `$table` WHERE id = $id LIMIT 1");
        if ($res && ($row = $DB->fetchAssoc($res))) { return trim((string)$row['label']); }
    } catch (Throwable $e) { return ''; }
    return '';
}

if (isset($_REQUEST['pc_create']) && $_REQUEST['pc_create'] === '1') {
    // GLPI plugin page: evitamos el bloqueo CSRF de pagina nativa porque este formulario
    // no se envia desde un formulario core de GLPI. La validacion se hace con sesion iniciada
    // y comprobaciones propias antes de crear el objeto.
    $locationId = pc_first_request_int(['locations_id','location_id','location'], 0);
    $title = pc_post('name');
    $description = pc_post('content');
    $kind = pc_post('incident_kind', 'other');
    $categoryLabel = pc_post('category_label');
    $computerNumber = pc_post('computer_number');
    $softwareName = pc_post('software_name');
    $problemSubtype = pc_post('problem_subtype');
    $locationLabel = pc_post('location_label');
    $locationCode = pc_post('location_code');
    $cat = (int)pc_post('itilcategories_id', 0);
    $affectedAsset = pc_post('affected_asset', '__none__');
    $affectedAssetCustom = pc_post('affected_asset_custom');
    [$affectedAssetLabel, $affectedAssetType, $affectedAssetId] = pc_asset_label_for_ticket($affectedAsset);
    if ($categoryLabel === '' && $cat > 0) { $categoryLabel = pc_category_label_by_id($cat); }

    if ($title === '') { $message='Falta el título de la incidencia.'; $messageType='error'; }
    elseif ($description === '') { $message='Falta la descripción de la incidencia.'; $messageType='error'; }
    elseif ($locationId <= 0) { $message='Selecciona una ubicación desde la lista o el plano.'; $messageType='error'; }
    elseif ($cat <= 0) { $message='Selecciona una categoría de incidencia.'; $messageType='error'; }
    elseif (($kind === 'computer' || $kind === 'software') && $affectedAsset === '__none__' && $computerNumber === '') { $message='Indica el número del ordenador afectado o selecciona un activo del aula.'; $messageType='error'; }
    elseif ($kind === 'software' && $softwareName === '') { $message='Indica el programa o software afectado.'; $messageType='error'; }
    elseif (!class_exists('Ticket')) { $message='No se encuentra la clase Ticket de GLPI.'; $messageType='error'; }
    else {
        $fullContent = "Categoría GLPI: " . ($categoryLabel ?: ('ID ' . $cat)) . "\n";
        $fullContent .= "Tipo detectado: " . pc_label_from_kind($kind) . "\n";
        if ($problemSubtype !== '') { $fullContent .= "Detalle técnico: " . $problemSubtype . "\n"; }
        if ($locationLabel !== '' || $locationCode !== '') {
            $fullContent .= "Ubicación: " . trim($locationLabel . ' ' . ($locationCode ? '(' . $locationCode . ')' : '')) . "\n";
        }
        $fullContent .= "ID ubicación GLPI: " . $locationId . "\n";
        if ($affectedAsset === '__custom__' && $affectedAssetCustom !== '') {
            $fullContent .= "Activo afectado: " . $affectedAssetCustom . " (personalizado)\n";
        } elseif ($affectedAssetLabel !== '') {
            $fullContent .= "Activo afectado: " . $affectedAssetLabel . ($affectedAssetType !== '' && $affectedAssetId > 0 ? " [" . $affectedAssetType . ":" . $affectedAssetId . "]" : "") . "\n";
        }
        if ($computerNumber !== '') { $fullContent .= "Número de ordenador/equipo: " . $computerNumber . "\n"; }
        if ($softwareName !== '') { $fullContent .= "Software afectado: " . $softwareName . "\n"; }
        $fullContent .= "\nDescripción del problema:\n" . $description;

        $ticket = new Ticket();
        $input = [
            'name' => $title,
            'content' => $fullContent,
            'entities_id' => Session::getActiveEntity(),
            'locations_id' => $locationId,
            'type' => defined('Ticket::INCIDENT_TYPE') ? Ticket::INCIDENT_TYPE : 1,
            'urgency' => (int)pc_post('urgency', 3),
            'impact' => (int)pc_post('impact', 3),
            'priority' => (int)pc_post('priority', 3),
            'itilcategories_id' => $cat,
            '_users_id_requester' => Session::getLoginUserID(),
        ];
        if (defined('CommonITILObject::INCOMING')) { $input['status'] = CommonITILObject::INCOMING; }
        if (class_exists('RequestType')) {
            try {
                global $DB;
                if (isset($DB) && method_exists($DB, 'tableExists') && $DB->tableExists('glpi_requesttypes')) {
                    $rq = $DB->query("SELECT id FROM glpi_requesttypes WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
                    if ($rq && ($rr = $DB->fetchAssoc($rq)) && (int)$rr['id'] > 0) { $input['requesttypes_id'] = (int)$rr['id']; }
                }
            } catch (Throwable $e) {}
        }
        $newId = $ticket->add($input);
        if ($newId) {
            if ($affectedAssetType !== '' && $affectedAssetId > 0) { pc_link_asset_to_ticket((int)$newId, $affectedAssetType, $affectedAssetId); }
            if (function_exists('smgr_auto_assign_ticket')) {
                try { smgr_auto_assign_ticket((int)$newId, (int)$locationId, $aulasAll); } catch (Throwable $e) {}
            }
            Html::redirect($root . '/plugins/schoolmanager/front/solicitud_detalle.php?id=' . (int)$newId . '&created=1&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION));
        }
        else {
            $detail = '';
            if (method_exists($ticket, 'getErrorMessages')) {
                $errs = $ticket->getErrorMessages();
                if (is_array($errs) && $errs) { $detail = ' Detalle: ' . implode(' | ', array_map('strval', $errs)); }
            } elseif (method_exists($ticket, 'getErrorMessage')) {
                $err = $ticket->getErrorMessage();
                if ($err) { $detail = ' Detalle: ' . $err; }
            }
            $message='No se pudo crear la incidencia. Revisa permisos o campos obligatorios.' . $detail; $messageType='error';
        }
    }
}

Html::header('Nueva incidencia guiada', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
$roomsJson = json_encode(array_values(array_filter($aulasAll, static fn($a) => !empty($a['id']))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$catsJson = json_encode($itilCategories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$submitted = isset($_REQUEST['pc_create']) && $_REQUEST['pc_create'] === '1';
$oldLocationId = pc_first_request_int(['locations_id','location_id','location'], 0);
$oldLocationLabel = pc_post('location_label');
if ($oldLocationLabel === '' && $oldLocationId > 0) { $oldLocationLabel = pc_location_label_by_id($oldLocationId); }
$oldLocationCode = pc_post('location_code');
$assetOptions = [];
if ($oldLocationId > 0 && function_exists('plugin_schoolmanager_assets_by_location')) {
    try { $assetOptions = plugin_schoolmanager_assets_by_location($oldLocationId, 60); } catch (Throwable $e) { $assetOptions = []; }
}
$oldAffectedAsset = pc_post('affected_asset', '__none__');
$oldCategoryId = (int)pc_post('itilcategories_id', 0);
?>
<style>
<?php
$cssFile = __DIR__ . '/../css/gestion-schoolmanager-theme.css';
if (is_file($cssFile)) { echo file_get_contents($cssFile); }
?>
/* v178: categoria compacta estable */
.pc-form{min-height:calc(100vh - 70px);padding:22px;background:linear-gradient(135deg,#f4f8fb 0%,#fff 54%,#fff9e8 100%)}
.pc-card{max-width:1420px;margin:0 auto;background:#fff;border:1px solid #d7e6ec;border-radius:28px;box-shadow:0 24px 70px rgba(8,59,84,.10);overflow:hidden}
.pc-head{display:flex;align-items:center;gap:22px;padding:24px 30px;background:linear-gradient(120deg,#fff,#f5fafc);border-bottom:1px solid #d7e6ec}
.pc-logo{width:128px;height:auto;object-fit:contain}.pc-head small{display:block;color:#0b5f7a;font-weight:950;letter-spacing:.12em}.pc-head h1{margin:0;color:#083b54;font-size:clamp(38px,5vw,64px);line-height:.95;letter-spacing:-.05em}
.pc-body{padding:28px}.pc-section{background:#fff;border:1px solid #d7e6ec;border-radius:24px;padding:22px;box-shadow:0 12px 34px rgba(8,59,84,.06)}
.pc-section h2{margin:0 0 18px;color:#083b54;font-size:clamp(28px,3vw,42px);letter-spacing:-.04em}.pc-section h3{margin:0;color:#083b54;font-size:19px}.pc-muted{color:#607684;font-weight:850}
.pc-message{margin:18px 28px 0;border-radius:18px;padding:14px 16px;font-weight:900}.pc-message.error{background:#fff5f5;border:1px solid #f1aeb5;color:#8b1e24}.pc-message.info{background:#eef9f7;border:1px solid #bfe7e2;color:#075d61}
.pc-validation-panel{display:none;margin:0 0 14px;border:1px solid #f1aeb5;background:#fff5f5;color:#8b1e24;border-radius:16px;padding:12px 14px;font-weight:850}.pc-validation-panel.show{display:block}.pc-validation-panel ul{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 0;padding:0;list-style:none}.pc-validation-panel li{background:#fff;border:1px solid #f1aeb5;border-radius:999px;padding:6px 10px}
.pc-category-block{background:linear-gradient(180deg,#fff,#f8fbfd);border:1px solid #d7e6ec;border-radius:22px;padding:16px;margin-bottom:20px}.pc-category-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.pc-category-note{background:#eef5f8;border:1px solid #d4e4eb;border-radius:999px;padding:7px 11px;color:#547081;font-weight:900;font-size:13px}.pc-current-cat{background:#eff6f9;border:1px solid #d4e4eb;border-radius:16px;padding:10px 12px;color:#083b54;font-weight:950;margin-bottom:12px}.pc-current-cat span{display:block;color:#607684;font-size:12px;margin-top:2px}.pc-category-layout{display:grid;grid-template-columns:300px 1fr;gap:12px;align-items:stretch}.pc-label-mini{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#607684;font-weight:950;margin-bottom:7px}.pc-parent-list{display:grid;gap:8px;max-height:278px;overflow:auto;padding-right:3px}.pc-parent-btn{width:100%;border:1px solid #d4e4eb;background:#fff;border-radius:16px;padding:10px 12px;display:flex;align-items:center;gap:10px;cursor:pointer;text-align:left;color:#083b54;font-weight:950;transition:.16s ease}.pc-parent-btn:hover{background:#f3f8fb;border-color:#0b5f7a;transform:translateY(-1px)}.pc-parent-btn.active{background:#083b54;border-color:#083b54;color:#fff;box-shadow:0 12px 28px rgba(8,59,84,.22)}.pc-parent-ico{width:32px;height:32px;border-radius:12px;background:#edf6fa;display:grid;place-items:center;flex:0 0 auto}.pc-parent-btn.active .pc-parent-ico{background:rgba(255,255,255,.18)}.pc-child-box{background:#f8fbfd;border:1px solid #d4e4eb;border-radius:20px;padding:12px;min-height:150px}.pc-subcat-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;max-height:240px;overflow:auto;padding-right:3px}.pc-subcat{border:1px solid #d4e4eb;background:#fff;border-radius:15px;padding:9px 10px;cursor:pointer;text-align:left;color:#083b54;font-weight:950;min-height:48px;transition:.16s ease}.pc-subcat:hover{border-color:#0b5f7a;background:#f3f8fb;transform:translateY(-1px)}.pc-subcat.active{background:#0b5f7a;border-color:#0b5f7a;color:#fff;box-shadow:0 12px 24px rgba(11,95,122,.20)}.pc-subcat small{display:block;color:#607684;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}.pc-subcat.active small{color:#eaf6fb}.pc-empty-subcat{border:1px dashed #c6d8e1;border-radius:14px;background:#fff;color:#607684;font-weight:850;padding:18px}.pc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.pc-field{display:block}.pc-field.full{grid-column:1/-1}.pc-label{display:block;margin-bottom:7px;color:#083b54;font-weight:950}.pc-input,.pc-textarea,.pc-select{width:100%;border:1px solid #d4e4eb;border-radius:16px;background:#fff;color:#102638;padding:12px 14px;font-weight:850;min-height:48px;box-shadow:none}.pc-textarea{min-height:120px;resize:vertical}.pc-input:focus,.pc-textarea:focus,.pc-select:focus{outline:none;border-color:#0b5f7a;box-shadow:0 0 0 4px rgba(11,95,122,.12)}.pc-location{background:#f8fbfd;border:1px solid #d4e4eb;border-radius:20px;display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;padding:10px}.pc-selected{padding:8px 10px}.pc-selected b{display:block;color:#083b54;font-size:24px;line-height:1.05}.pc-selected span{color:#607684;font-weight:850}.pc-btn{border:1px solid #083b54;border-radius:15px;padding:12px 16px;font-weight:950;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.16s ease}.pc-btn.primary,.pc-btn.gold,button[type="submit"]{background:#083b54;color:#fff!important;border-color:#083b54;box-shadow:0 12px 28px rgba(8,59,84,.17)}.pc-btn.primary:hover,.pc-btn.gold:hover{background:#0b5f7a;border-color:#0b5f7a;transform:translateY(-1px)}.pc-btn.secondary{background:#fff;color:#083b54!important;border-color:#d4e4eb}.pc-hint,.pc-priority-ai{background:#fff9e8;border:1px solid #efd486;color:#6b4b00;border-radius:16px;padding:10px 12px;font-weight:900;margin:12px 0}.pc-conditional{display:none}.pc-conditional.show{display:block}.pc-normal-fields{grid-template-columns:repeat(3,minmax(0,1fr))}.pc-submit{position:sticky;bottom:0;background:linear-gradient(180deg,rgba(255,255,255,.72),#fff);border-top:1px solid #dbe7ed;backdrop-filter:blur(12px);padding:14px 28px;display:flex;justify-content:flex-end;gap:10px}.pc-required-missing .pc-input,.pc-required-missing .pc-textarea,.pc-required-missing.pc-location-field .pc-location,.pc-category-missing{border-color:#e25555!important;box-shadow:0 0 0 4px rgba(226,85,85,.11)!important;background:#fffafa!important}.pc-required-missing .pc-label{color:#8b1e24!important}.pc-category-missing{border-radius:20px}.pc-is-complete .pc-input,.pc-is-complete .pc-textarea,.pc-location-field.pc-is-complete .pc-location,.pc-category-block.pc-is-complete{border-color:#b7d7c5!important}@media(max-width:1000px){.pc-category-layout{grid-template-columns:1fr}.pc-parent-list{grid-template-columns:repeat(2,minmax(0,1fr));max-height:none}.pc-fields,.pc-normal-fields{grid-template-columns:1fr}.pc-location{grid-template-columns:1fr}.pc-submit{position:static;display:grid}.pc-btn{width:100%}}@media(max-width:620px){.pc-form{padding:10px}.pc-head{padding:16px;gap:12px}.pc-logo{width:96px}.pc-body{padding:14px}.pc-section{padding:14px}.pc-parent-list{grid-template-columns:1fr}.pc-subcat-list{grid-template-columns:1fr}.pc-category-top{display:block}.pc-category-note{display:inline-flex;margin-top:8px}.pc-head h1{font-size:36px}}

/* v178: selector de categoria compacto, estable y sin mostrar todas las subcategorias */
.pc-category-block{padding:12px!important;margin-bottom:16px!important;border-radius:20px!important;background:#fff!important;box-shadow:0 10px 26px rgba(8,59,84,.055)!important}
.pc-category-top{margin-bottom:8px!important}.pc-category-top h3{font-size:18px!important}.pc-category-note{font-size:12px!important;padding:6px 10px!important}
.pc-current-cat{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:10px!important;min-height:44px!important;margin-bottom:10px!important;padding:9px 12px!important;border-radius:14px!important;font-size:15px!important;background:#f4f8fb!important}
.pc-current-cat span{display:inline!important;margin:0!important;font-size:11px!important;text-align:right!important;max-width:45%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-category-layout{display:grid!important;grid-template-columns:230px minmax(0,1fr)!important;gap:10px!important;align-items:start!important}
.pc-label-mini{font-size:10px!important;margin-bottom:5px!important;color:#607684!important}
.pc-parent-list{display:flex!important;flex-direction:column!important;gap:6px!important;max-height:230px!important;overflow:auto!important;padding-right:4px!important}
.pc-parent-btn{min-height:42px!important;border-radius:14px!important;padding:7px 9px!important;font-size:14px!important;line-height:1.05!important;gap:8px!important;box-shadow:none!important;white-space:normal!important}
.pc-parent-ico{width:28px!important;height:28px!important;border-radius:10px!important;font-size:14px!important}.pc-parent-btn span:last-child{overflow:hidden;text-overflow:ellipsis}
.pc-child-box{min-height:0!important;height:260px!important;padding:10px!important;border-radius:18px!important;background:#f8fbfd!important;overflow:hidden!important}
.pc-child-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px}.pc-child-head .pc-label-mini{margin:0!important}
.pc-category-tools{display:flex;gap:6px;align-items:center}.pc-cat-search{height:34px;min-height:34px;width:min(260px,42vw);border:1px solid #d4e4eb;border-radius:999px;background:#fff;color:#083b54;padding:0 12px;font-weight:850;outline:none}.pc-cat-search:focus{border-color:#0b5f7a;box-shadow:0 0 0 3px rgba(11,95,122,.12)}
.pc-cat-clear{height:34px;border:1px solid #d4e4eb;border-radius:999px;background:#fff;color:#083b54;font-weight:950;padding:0 10px;cursor:pointer}.pc-cat-clear:hover{background:#eef6fa}
.pc-subcat-list{display:none!important;grid-template-columns:repeat(auto-fill,minmax(138px,1fr))!important;gap:7px!important;max-height:205px!important;overflow:auto!important;padding-right:4px!important}
.pc-subcat-list.active{display:grid!important}.pc-subcat[hidden]{display:none!important}
.pc-subcat{min-height:44px!important;border-radius:13px!important;padding:8px 9px!important;font-size:13px!important;line-height:1.08!important;box-shadow:none!important}.pc-subcat b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-subcat small{font-size:9.5px!important}.pc-subcat.active{background:#083b54!important;border-color:#083b54!important;color:#fff!important;box-shadow:0 8px 20px rgba(8,59,84,.18)!important}
.pc-empty-subcat{height:calc(100% - 42px);display:grid!important;place-items:center;border-radius:14px!important;padding:12px!important;font-size:14px!important;text-align:center}.pc-empty-subcat.hide{display:none!important}
.pc-category-missing .pc-current-cat{border-color:#e25555!important;background:#fffafa!important}
@media(max-width:1000px){.pc-category-layout{grid-template-columns:1fr!important}.pc-parent-list{flex-direction:row!important;overflow-x:auto!important;max-height:none!important;padding-bottom:4px}.pc-parent-btn{min-width:170px!important}.pc-child-box{height:auto!important;max-height:300px!important}.pc-subcat-list{max-height:210px!important}.pc-current-cat{display:block!important}.pc-current-cat span{display:block!important;max-width:none!important;text-align:left!important;margin-top:3px!important}.pc-child-head{align-items:stretch;flex-direction:column}.pc-cat-search{width:100%}}
@media(max-width:620px){.pc-category-block{padding:10px!important}.pc-parent-btn{min-width:150px!important}.pc-subcat-list{grid-template-columns:1fr!important}.pc-current-cat{font-size:14px!important}.pc-child-box{padding:8px!important}}


/* v180: categoria profesional, compacta y con seleccion mucho más visible */
.pc-category-block{background:linear-gradient(145deg,#ffffff,#f7fbfd)!important;border-radius:22px!important;padding:14px!important;border-color:#cfe2ea!important}
.pc-category-top{align-items:center!important}.pc-category-note{background:#eaf4f8!important;color:#315a68!important;border-color:#c8dee8!important}
.pc-current-cat{background:#eef7fa!important;border:1px solid #cce3eb!important;box-shadow:inset 5px 0 0 #efa300!important;color:#07384d!important}
.pc-current-cat strong,.pc-current-cat b{color:#07384d!important}.pc-current-cat span{color:#4f6d79!important}
.pc-category-layout{grid-template-columns:220px minmax(0,1fr)!important;gap:12px!important}
.pc-parent-list{max-height:218px!important;gap:7px!important;padding-right:6px!important}
.pc-parent-list::-webkit-scrollbar,.pc-subcat-list::-webkit-scrollbar{width:8px!important}.pc-parent-list::-webkit-scrollbar-thumb,.pc-subcat-list::-webkit-scrollbar-thumb{background:#adcbd6!important;border-radius:999px!important}
.pc-parent-btn{position:relative!important;overflow:hidden!important;background:linear-gradient(135deg,#fff,#f7fbfd)!important;border-color:#cfe0e8!important;color:#07384d!important;box-shadow:0 6px 16px rgba(7,56,77,.04)!important}
.pc-parent-btn:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:#dbe9ef;transition:.18s ease!important}
.pc-parent-btn:hover{border-color:#0b5f7a!important;transform:translateY(-1px)!important;background:#fff!important}
.pc-parent-btn.active{background:linear-gradient(135deg,#07384d,#0b5f7a)!important;border-color:#07384d!important;color:#fff!important;box-shadow:0 14px 30px rgba(7,56,77,.23)!important;transform:translateY(-1px)!important}
.pc-parent-btn.active:before{background:#efa300!important;width:6px!important}.pc-parent-btn.active .pc-parent-ico{background:rgba(255,255,255,.18)!important;color:#fff!important;border-color:rgba(255,255,255,.22)!important}.pc-parent-ico{background:#edf6fa!important;border:1px solid #d4e7ef!important;font-size:15px!important}
.pc-child-box{height:234px!important;background:linear-gradient(145deg,#f9fcfd,#ffffff)!important;border-color:#cfe2ea!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.7)!important}
.pc-child-head{gap:8px!important}.pc-cat-search{background:#fff!important;border-color:#cfe0e8!important}.pc-cat-clear{background:#f6fbfd!important;border-color:#cfe0e8!important;color:#07384d!important}
.pc-subcat-list{grid-template-columns:repeat(auto-fill,minmax(128px,1fr))!important;max-height:184px!important;gap:7px!important}
.pc-subcat{position:relative!important;background:#fff!important;border-color:#d4e4eb!important;border-radius:14px!important;min-height:42px!important;padding:8px 9px 8px 11px!important;color:#07384d!important;box-shadow:0 5px 14px rgba(7,56,77,.035)!important}
.pc-subcat:before{content:"";position:absolute;left:0;top:10px;bottom:10px;width:3px;border-radius:999px;background:#dbe9ef!important}.pc-subcat:hover{background:#f4fbfd!important;border-color:#0b5f7a!important;box-shadow:0 10px 20px rgba(7,56,77,.07)!important}
.pc-subcat.active{background:linear-gradient(135deg,#0b5f7a,#07384d)!important;border-color:#07384d!important;color:#fff!important;box-shadow:0 14px 28px rgba(7,56,77,.26)!important;transform:translateY(-1px)!important}.pc-subcat.active:before{background:#efa300!important}.pc-subcat.active:after{content:"";position:absolute;right:8px;top:7px;width:18px;height:18px;border-radius:999px;background:#efa300;display:block;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E") center/13px 13px no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E") center/13px 13px no-repeat}.pc-subcat.active b{padding-right:20px}.pc-subcat small{color:#607684!important}.pc-subcat.active small{color:#dff4f8!important}.pc-empty-subcat{background:#fff!important;border-color:#cfe0e8!important;color:#607684!important}
@media(max-width:1000px){.pc-category-layout{grid-template-columns:1fr!important}.pc-parent-list{display:grid!important;grid-template-columns:repeat(3,minmax(145px,1fr))!important;overflow:auto!important}.pc-child-box{height:auto!important}.pc-subcat-list{max-height:210px!important}}
@media(max-width:620px){.pc-parent-list{grid-template-columns:1fr!important}.pc-subcat-list{grid-template-columns:1fr!important}.pc-current-cat{display:block!important}.pc-current-cat span{max-width:none!important;text-align:left!important}}


.pc-asset-picker{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(220px,.8fr);gap:10px;align-items:center;background:#f8fbfd;border:1px solid #d4e4eb;border-radius:20px;padding:10px}.pc-help{display:block;margin-top:7px;color:#607684;font-weight:850}.pc-asset-picker .pc-input{display:none}.pc-asset-picker.custom .pc-input{display:block}.pc-asset-field.pc-is-complete .pc-asset-picker{border-color:#b7d7c5!important}@media(max-width:900px){.pc-asset-picker{grid-template-columns:1fr}}

/* v229: mejoras visuales incidencia guiada */
.pc-head{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:20px!important;background:linear-gradient(135deg,#fff,#f8fbfd 55%,#fff8df)!important;border-bottom:1px solid #d7e6ec!important}.pc-head-main{display:flex;align-items:center;gap:18px;min-width:0}.pc-head-copy{display:grid;gap:4px}.pc-head-copy p{margin:0;color:#5f7580;font-weight:850;max-width:720px}.pc-head-copy small{display:inline-flex;align-items:center;gap:8px}.pc-head-side{display:grid;grid-template-columns:repeat(3,minmax(120px,1fr));gap:10px}.pc-head-chip{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:18px;background:#fff;border:1px solid #d7e6ec;box-shadow:0 8px 22px rgba(8,59,84,.05)}.pc-head-chip i{width:38px;height:38px;border-radius:14px;display:grid;place-items:center;background:#eef6fa;color:#083b54;font-size:18px}.pc-head-chip span{display:grid;line-height:1.05}.pc-head-chip b{color:#083b54;font-size:14px}.pc-head-chip small{color:#607684;font-size:12px;font-weight:850;letter-spacing:normal}.pc-section h2 i,.pc-section h3 i,.pc-category-note i,.pc-btn i{margin-right:6px}.pc-section h2{display:flex;align-items:center;gap:8px}.pc-category-top h3{display:flex;align-items:center;gap:8px}.pc-category-note{display:inline-flex;align-items:center;gap:6px}.pc-btn.secondary{background:#fff!important}.pc-submit{align-items:center!important}.pc-submit .pc-btn{min-width:210px}.pc-submit .pc-btn.secondary{justify-content:center}.pc-message{display:flex;align-items:flex-start;gap:10px}.pc-message small{display:block;margin-top:4px}@media(max-width:1100px){.pc-head{grid-template-columns:1fr!important}.pc-head-side{grid-template-columns:repeat(3,minmax(0,1fr))!important}}@media(max-width:720px){.pc-head-copy p{font-size:14px}.pc-head-side{grid-template-columns:1fr!important}.pc-head-chip{padding:10px 12px}.pc-submit .pc-btn{min-width:0;width:100%}}

/* v238: cabecera limpia y boton de inicio vintage rojo */
.pc-head{grid-template-columns:minmax(0,1fr) auto!important;background:linear-gradient(135deg,#ffffff 0%,#fbfcfd 70%,#fff8e5 100%)!important;border-bottom:1px solid #d7e6ec!important;align-items:center!important}
.pc-head-side,.pc-head-chip{display:none!important}.pc-head-actions{display:flex!important;align-items:center!important;justify-content:flex-end!important;min-width:max-content}.pc-head-copy p{max-width:760px!important;color:#5f7580!important}.pc-logo{background:transparent!important;box-shadow:none!important;border:0!important;padding:0!important}
@media(max-width:760px){.pc-head{grid-template-columns:1fr!important}.pc-head-main{align-items:flex-start!important}.pc-head-actions{justify-content:flex-start!important;width:100%;margin-top:8px}.pc-head-actions .pc-header-home{width:auto!important}}

/* v238: boton inicio especifico de Incidencia guiada, rojo vintage */
.pc-form .pc-head-actions .pc-header-home-red{
  min-height:50px!important;
  padding:12px 18px 12px 13px!important;
  border:1px solid #8B1E1E!important;
  border-radius:999px!important;
  background:linear-gradient(135deg,#B6252B 0%,#8B1E1E 100%)!important;
  color:#fff!important;
  box-shadow:0 16px 34px rgba(139,30,30,.22), inset 0 1px 0 rgba(255,255,255,.18)!important;
  letter-spacing:-.01em!important;
  gap:11px!important;
}
.pc-form .pc-head-actions .pc-header-home-red .pc-home-ico{
  width:32px!important;
  height:32px!important;
  border-radius:50%!important;
  background:rgba(255,255,255,.16)!important;
  border:1px solid rgba(255,255,255,.34)!important;
  color:#fff!important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.18)!important;
}
.pc-form .pc-head-actions .pc-header-home-red i{color:#fff!important;font-size:16px!important}
.pc-form .pc-head-actions .pc-header-home-red span:last-child{color:#fff!important;font-weight:950!important}
.pc-form .pc-head-actions .pc-header-home-red:hover{
  background:linear-gradient(135deg,#8B1E1E 0%,#6f1717 100%)!important;
  border-color:#6f1717!important;
  color:#fff!important;
  transform:translateY(-2px)!important;
  box-shadow:0 20px 42px rgba(139,30,30,.28), inset 0 1px 0 rgba(255,255,255,.16)!important;
}
.pc-form .pc-head-actions .pc-header-home-red:active{transform:translateY(0)!important;box-shadow:0 10px 24px rgba(139,30,30,.20)!important}
@media(max-width:760px){
  .pc-form .pc-head-actions .pc-header-home-red{width:100%!important;justify-content:center!important;min-height:48px!important}
}


/* v238: icono inline SVG estable para el boton rojo de inicio */
.pc-header-home-red .pc-home-ico svg{width:18px;height:18px;stroke:#fff;stroke-width:2.25;stroke-linecap:round;stroke-linejoin:round}.pc-header-home-red .pc-home-ico{background:rgba(255,255,255,.16)!important;border-color:rgba(255,255,255,.28)!important}.pc-header-home-red{background:#8b1e1e!important;border-color:#8b1e1e!important;color:#fff!important;box-shadow:0 18px 38px rgba(139,30,30,.22)!important}.pc-header-home-red:hover{background:#a92828!important;border-color:#a92828!important;color:#fff!important;transform:translateY(-2px)!important}.pc-header-home-red span{color:#fff!important}
/* v238: ubicación limpia, sin franja degradada, y botones más serios */
.pc-form{background:linear-gradient(135deg,#f4f8fb 0%,#ffffff 66%,#fff8e8 100%)!important}.pc-body{background:linear-gradient(180deg,#fff,#fbfdfe)!important}.pc-location-v237{position:relative;overflow:hidden;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:18px!important;align-items:center!important;padding:18px 20px!important;border:1px solid #d3e5e2!important;background:#fff!important;border-radius:24px!important;box-shadow:0 16px 42px rgba(8,59,84,.07)!important}.pc-location-v237:before{display:none!important}.pc-location-main{display:flex;align-items:center;gap:16px;min-width:0}.pc-location-icon{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;background:#fff8e8!important;color:#8b1e1e!important;border:1px solid #f1d2c9!important;box-shadow:0 12px 30px rgba(139,30,30,.08);flex:0 0 auto}.pc-location-icon svg{width:25px;height:25px;stroke:#8b1e1e;stroke-width:2.35}.pc-selected{padding:0!important;min-width:0}.pc-selected b{font-size:clamp(22px,2.4vw,31px)!important;color:#083b54!important;letter-spacing:-.025em!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pc-selected span{display:inline-flex!important;align-items:center!important;margin-top:5px!important;color:#5c7180!important;font-weight:950!important;line-height:1.2!important}.pc-btn-location{min-height:52px!important;border-radius:17px!important;padding:14px 22px!important;background:#07384d!important;border:1px solid #07384d!important;color:#fff!important;box-shadow:0 18px 38px rgba(7,56,77,.18)!important;white-space:nowrap!important}.pc-btn-location svg{width:18px;height:18px;stroke:#fff;stroke-width:2.2}.pc-btn-location:hover{transform:translateY(-2px)!important;background:#075d61!important;border-color:#075d61!important;box-shadow:0 22px 44px rgba(7,56,77,.22)!important}.pc-submit{margin-top:20px!important;border:1px solid #d7e6ec!important;border-radius:24px!important;background:#fff!important;box-shadow:0 16px 44px rgba(8,59,84,.07)!important;padding:16px 20px!important;gap:12px!important;display:flex!important;justify-content:flex-end!important}.pc-submit .pc-btn{border-radius:18px!important;min-height:52px!important;padding:14px 24px!important;font-size:16px!important;display:inline-flex!important;align-items:center!important;gap:9px!important}.pc-submit svg{width:18px;height:18px;stroke-width:2.2}.pc-btn-cancel{border:1px solid #d7e6ec!important;background:#fff!important;color:#07384d!important;box-shadow:0 10px 24px rgba(8,59,84,.055)!important}.pc-btn-cancel svg{stroke:#07384d}.pc-btn-cancel:hover{border-color:#8b1e1e!important;color:#8b1e1e!important;background:#fff8f6!important;transform:translateY(-1px)!important}.pc-btn-cancel:hover svg{stroke:#8b1e1e}.pc-btn-create{background:#07384d!important;border:1px solid #07384d!important;color:#fff!important;box-shadow:0 18px 38px rgba(8,59,84,.18)!important}.pc-btn-create svg{stroke:#fff}.pc-btn-create:hover{background:#075d61!important;border-color:#075d61!important;transform:translateY(-1px)!important}@media(max-width:820px){.pc-location-v237{grid-template-columns:1fr!important;padding:16px!important}.pc-location-main{align-items:flex-start}.pc-location-icon{width:48px;height:48px;border-radius:16px}.pc-btn-location{width:100%!important}.pc-selected b{white-space:normal}.pc-submit{display:grid!important}.pc-submit .pc-btn{width:100%!important}}

/* v238 ajustes finos: iconos limpios y ubicación estilo vintage */
.pc-form .pc-head-actions .pc-header-home-red{gap:10px!important;padding:12px 18px!important;border-radius:999px!important;background:#8b1e1e!important;border:1px solid #7c1b1b!important;box-shadow:0 16px 34px rgba(139,30,30,.22)!important}
.pc-form .pc-head-actions .pc-header-home-red .pc-home-ico{width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.14)!important;border:1px solid rgba(255,255,255,.22)!important;box-shadow:none!important;display:inline-grid!important;place-items:center!important;flex:0 0 auto!important}
.pc-header-home-red .pc-home-ico svg{width:17px!important;height:17px!important;stroke:#fff!important;stroke-width:2.35!important;stroke-linecap:round!important;stroke-linejoin:round!important}
.pc-location-v237{border-color:#d7e6ec!important;background:linear-gradient(135deg,#fff,#fbfdfe)!important;box-shadow:0 12px 32px rgba(8,59,84,.065)!important;padding:16px 18px!important}
.pc-location-icon{width:50px!important;height:50px!important;border-radius:16px!important;background:#fff8e8!important;border:1px solid #efd9a0!important;color:#8b1e1e!important;box-shadow:0 10px 24px rgba(239,163,0,.10)!important}
.pc-location-icon svg{width:22px!important;height:22px!important;stroke:#8b1e1e!important;stroke-width:2.35!important;stroke-linecap:round!important;stroke-linejoin:round!important}
.pc-btn-location{background:#07384d!important;border-color:#07384d!important;border-radius:17px!important;gap:9px!important}
.pc-btn-location:hover{background:#075d61!important;border-color:#075d61!important}
.pc-submit{background:linear-gradient(135deg,#fff,#f9fcfd)!important;border-color:#d7e6ec!important;box-shadow:0 16px 36px rgba(8,59,84,.065)!important}
.pc-submit .pc-btn{border-radius:18px!important;gap:10px!important;font-weight:950!important;letter-spacing:-.01em!important}
.pc-submit .pc-btn svg{width:19px!important;height:19px!important;stroke-width:2.45!important;stroke-linecap:round!important;stroke-linejoin:round!important}
.pc-btn-cancel{background:#fff!important;color:#07384d!important;border-color:#d7e6ec!important;box-shadow:0 10px 24px rgba(8,59,84,.06)!important}
.pc-btn-cancel:hover{background:#fff8f6!important;border-color:#8b1e1e!important;color:#8b1e1e!important}
.pc-btn-create{background:#07384d!important;border-color:#07384d!important;box-shadow:0 18px 36px rgba(7,56,77,.18)!important}
.pc-btn-create:hover{background:#075d61!important;border-color:#075d61!important}


/* v239: inicio limpio sin icono y hover mas elegante */
.pc-form .pc-head-copy small{padding-left:0!important;color:#d94848!important;letter-spacing:.16em!important;font-weight:950!important;display:block!important}
.pc-form .pc-head-actions .pc-header-home-clean{min-height:52px!important;padding:0 28px!important;border-radius:999px!important;background:linear-gradient(135deg,#8b1e1e 0%,#a32626 100%)!important;border:1px solid #7c1b1b!important;color:#fff!important;box-shadow:0 18px 38px rgba(139,30,30,.24), inset 0 1px 0 rgba(255,255,255,.12)!important;transition:transform .22s ease, box-shadow .22s ease, background .22s ease!important;position:relative!important;overflow:hidden!important}
.pc-form .pc-head-actions .pc-header-home-clean::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent);transform:translateX(-120%);transition:transform .55s ease}
.pc-form .pc-head-actions .pc-header-home-clean:hover{transform:translateY(-5px)!important;background:linear-gradient(135deg,#9f2424 0%,#bb2f2f 100%)!important;box-shadow:0 26px 48px rgba(139,30,30,.32), inset 0 1px 0 rgba(255,255,255,.16)!important}
.pc-form .pc-head-actions .pc-header-home-clean:hover::after{transform:translateX(120%)}
.pc-form .pc-head-actions .pc-header-home-clean:active{transform:translateY(-2px)!important;box-shadow:0 16px 30px rgba(139,30,30,.24)!important}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-ico{display:none!important}
.pc-form .pc-head-actions .pc-header-home-clean span{position:relative!important;z-index:1!important;color:#fff!important;font-weight:950!important;letter-spacing:-.01em!important}
@media(max-width:760px){.pc-form .pc-head-actions .pc-header-home-clean{width:100%!important;min-height:50px!important}}


/* v240: boton de inicio refinado, sin iconos raros y con hover animado */
.pc-form .pc-head-copy small::before,
.pc-form .pc-head-copy small::after{display:none!important;content:none!important;}
.pc-form .pc-head-copy small{
  padding-left:0!important;
  color:#B6252B!important;
  -webkit-text-fill-color:#B6252B!important;
  letter-spacing:.18em!important;
  font-weight:950!important;
  display:block!important;
}
.pc-form .pc-head-actions{align-items:center!important;justify-content:flex-end!important;}
.pc-form .pc-head-actions .pc-header-home-clean{
  min-height:46px!important;
  height:46px!important;
  padding:0 24px!important;
  border-radius:18px!important;
  background:#8B1E1E!important;
  border:1px solid #7A1919!important;
  color:#fff!important;
  font-size:16px!important;
  line-height:1!important;
  box-shadow:0 12px 28px rgba(139,30,30,.20), inset 0 1px 0 rgba(255,255,255,.14)!important;
  transform:translateY(0)!important;
  transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, background .22s ease, border-color .22s ease!important;
  position:relative!important;
  overflow:hidden!important;
  isolation:isolate!important;
}
.pc-form .pc-head-actions .pc-header-home-clean::before{
  content:""!important;
  position:absolute!important;
  inset:0!important;
  background:linear-gradient(110deg,transparent 0%,rgba(255,255,255,.16) 45%,transparent 70%)!important;
  transform:translateX(-130%) skewX(-12deg)!important;
  transition:transform .55s ease!important;
  z-index:0!important;
}
.pc-form .pc-head-actions .pc-header-home-clean::after{display:none!important;content:none!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-ico,
.pc-form .pc-head-actions .pc-header-home-clean i,
.pc-form .pc-head-actions .pc-header-home-clean svg{display:none!important;}
.pc-form .pc-head-actions .pc-header-home-clean span{
  position:relative!important;
  z-index:1!important;
  color:#fff!important;
  -webkit-text-fill-color:#fff!important;
  font-weight:950!important;
  letter-spacing:-.015em!important;
  line-height:1!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:hover{
  background:#A72828!important;
  border-color:#8B1E1E!important;
  transform:translateY(-4px)!important;
  box-shadow:0 20px 42px rgba(139,30,30,.27), inset 0 1px 0 rgba(255,255,255,.18)!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:hover::before{transform:translateX(135%) skewX(-12deg)!important;}
.pc-form .pc-head-actions .pc-header-home-clean:active{
  transform:translateY(-1px)!important;
  box-shadow:0 12px 26px rgba(139,30,30,.22)!important;
}
@media(max-width:760px){
  .pc-form .pc-head-actions{width:100%!important;justify-content:flex-start!important;}
  .pc-form .pc-head-actions .pc-header-home-clean{width:auto!important;min-width:150px!important;height:44px!important;min-height:44px!important;padding:0 20px!important;}
}


/* v241: boton Volver al inicio centrado y limpio */
.pc-form .pc-head-actions{
  display:flex!important;
  align-items:center!important;
  justify-content:flex-end!important;
}
.pc-form .pc-head-actions .pc-header-home-clean{
  display:inline-grid!important;
  place-items:center!important;
  width:auto!important;
  min-width:174px!important;
  height:50px!important;
  min-height:50px!important;
  padding:0 26px!important;
  margin:0!important;
  border-radius:999px!important;
  background:#8B1E1E!important;
  border:1px solid #741818!important;
  color:#fff!important;
  font-size:16px!important;
  font-weight:950!important;
  line-height:1!important;
  text-align:center!important;
  text-decoration:none!important;
  box-shadow:0 14px 30px rgba(139,30,30,.22), inset 0 1px 0 rgba(255,255,255,.13)!important;
  transform:translateY(0)!important;
  transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, background .22s ease, border-color .22s ease!important;
  overflow:hidden!important;
  isolation:isolate!important;
}
.pc-form .pc-head-actions .pc-header-home-clean span{
  display:block!important;
  position:relative!important;
  z-index:1!important;
  margin:0!important;
  padding:0!important;
  height:auto!important;
  line-height:1!important;
  color:#fff!important;
  -webkit-text-fill-color:#fff!important;
  font-size:16px!important;
  font-weight:950!important;
  letter-spacing:-.01em!important;
  transform:translateY(0)!important;
}
.pc-form .pc-head-actions .pc-header-home-clean::before{
  content:""!important;
  position:absolute!important;
  inset:0!important;
  z-index:0!important;
  background:linear-gradient(110deg,transparent 0%,rgba(255,255,255,.16) 45%,transparent 70%)!important;
  transform:translateX(-135%) skewX(-12deg)!important;
  transition:transform .58s ease!important;
}
.pc-form .pc-head-actions .pc-header-home-clean::after,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-ico,
.pc-form .pc-head-actions .pc-header-home-clean i,
.pc-form .pc-head-actions .pc-header-home-clean svg{
  display:none!important;
  content:none!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:hover{
  background:#A32626!important;
  border-color:#8B1E1E!important;
  transform:translateY(-4px)!important;
  box-shadow:0 22px 42px rgba(139,30,30,.30), inset 0 1px 0 rgba(255,255,255,.18)!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:hover::before{
  transform:translateX(135%) skewX(-12deg)!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:active{
  transform:translateY(-1px)!important;
  box-shadow:0 12px 24px rgba(139,30,30,.23)!important;
}
@media(max-width:760px){
  .pc-form .pc-head-actions{justify-content:flex-start!important;width:100%!important;}
  .pc-form .pc-head-actions .pc-header-home-clean{min-width:168px!important;height:48px!important;min-height:48px!important;padding:0 22px!important;}
}


/* v249: botón Inicio unificado con icono y logo robusto */
.pc-form .pc-head-main .pc-logo{display:block!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-form .pc-head-actions .pc-header-home-clean{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-width:150px!important;min-height:48px!important;padding:0 22px!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;position:relative!important;z-index:1!important;flex:0 0 auto!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-label{display:inline-flex!important;align-items:center!important;line-height:1!important;color:#fff!important;font-weight:950!important;position:relative!important;z-index:1!important;}
@media(max-width:760px){.pc-form .pc-head-actions .pc-header-home-clean{width:auto!important;min-width:140px!important;}}


/* v250: Inicio unificado en Incidencia guiada */
.pc-form .pc-head-actions .pc-header-home-clean{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-width:138px!important;min-height:50px!important;padding:0 22px!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;position:relative!important;z-index:1!important;flex:0 0 auto!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge i{display:block!important;color:#fff!important;font-size:16px!important;line-height:1!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-label{display:inline-flex!important;align-items:center!important;line-height:1!important;color:#fff!important;font-weight:950!important;position:relative!important;z-index:1!important;}
.pc-form .pc-head-actions .pc-header-home-clean svg{display:none!important;}

</style>

<style>
/* v243: hovers unificados y botones mas finos */
.pc-form .pc-head-actions{display:flex!important;align-items:center!important;justify-content:center!important;height:100%!important}
.pc-form .pc-head-actions .pc-header-home-clean,
.pc-btn-location,
.pc-btn-cancel,
.pc-btn-create{
  position:relative!important;
  overflow:hidden!important;
  transition:transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s ease, background-color .22s ease, border-color .22s ease, color .22s ease!important;
}
.pc-form .pc-head-actions .pc-header-home-clean::before,
.pc-btn-location::before,
.pc-btn-cancel::before,
.pc-btn-create::before{
  content:"";
  position:absolute;
  inset:0;
  background:linear-gradient(120deg, transparent 0%, rgba(255,255,255,.06) 30%, rgba(255,255,255,.18) 48%, rgba(255,255,255,.06) 66%, transparent 100%);
  transform:translateX(-135%);
  transition:transform .55s ease;
  pointer-events:none;
}
.pc-form .pc-head-actions .pc-header-home-clean:hover,
.pc-btn-location:hover,
.pc-btn-cancel:hover,
.pc-btn-create:hover{
  transform:translateY(-4px)!important;
}
.pc-form .pc-head-actions .pc-header-home-clean:hover::before,
.pc-btn-location:hover::before,
.pc-btn-cancel:hover::before,
.pc-btn-create:hover::before{
  transform:translateX(135%);
}
.pc-form .pc-head-actions .pc-header-home-clean{
  min-height:58px!important;
  padding:0 28px!important;
  border-radius:18px!important;
  font-size:17px!important;
  font-weight:950!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  line-height:1!important;
  background:linear-gradient(135deg,#8f1d1d 0%, #a72323 55%, #b52a2a 100%)!important;
  border:1px solid #922020!important;
  box-shadow:0 18px 38px rgba(139,30,30,.22)!important;
}
.pc-form .pc-head-actions .pc-header-home-clean span{display:inline-flex!important;align-items:center!important;line-height:1!important;transform:translateY(-1px)}
.pc-form .pc-head-actions .pc-header-home-clean:hover{
  background:linear-gradient(135deg,#a52323 0%, #bb2f2f 100%)!important;
  box-shadow:0 24px 44px rgba(139,30,30,.30)!important;
}
.pc-btn-location,
.pc-btn-cancel,
.pc-btn-create{
  min-height:54px!important;
  border-radius:18px!important;
  padding:14px 24px!important;
  gap:10px!important;
  font-size:16px!important;
  font-weight:950!important;
}
.pc-btn-location,
.pc-btn-create{
  background:#07384d!important;
  border-color:#07384d!important;
  color:#fff!important;
  box-shadow:0 16px 34px rgba(8,59,84,.16)!important;
}
.pc-btn-location:hover,
.pc-btn-create:hover{
  background:#075d61!important;
  border-color:#075d61!important;
  box-shadow:0 24px 44px rgba(8,59,84,.22)!important;
}
.pc-btn-cancel{
  background:#fff!important;
  border:1px solid #d4e4eb!important;
  color:#07384d!important;
  box-shadow:0 12px 28px rgba(8,59,84,.06)!important;
}
.pc-btn-cancel:hover{
  background:#f4f8fb!important;
  border-color:#aebfca!important;
  color:#07384d!important;
  box-shadow:0 20px 38px rgba(8,59,84,.12)!important;
}
.pc-btn-location svg,
.pc-btn-cancel svg,
.pc-btn-create svg{
  width:18px!important;
  height:18px!important;
  stroke-width:2.25!important;
  stroke-linecap:round!important;
  stroke-linejoin:round!important;
  flex:0 0 auto!important;
}
.pc-btn-location span,
.pc-btn-cancel span,
.pc-btn-create span{line-height:1!important;transform:translateY(-1px)}
.pc-submit{gap:14px!important}
@media(max-width:820px){
  .pc-form .pc-head-actions .pc-header-home-clean,
  .pc-btn-location,
  .pc-btn-cancel,
  .pc-btn-create{width:100%!important}
}


/* v249: botón Inicio unificado con icono y logo robusto */
.pc-form .pc-head-main .pc-logo{display:block!important;object-fit:contain!important;background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important;filter:none!important;mix-blend-mode:multiply!important;}
.pc-form .pc-head-actions .pc-header-home-clean{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;min-width:150px!important;min-height:48px!important;padding:0 22px!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{display:inline-grid!important;place-items:center!important;width:28px!important;height:28px!important;border-radius:999px!important;background:rgba(255,255,255,.16)!important;border:1px solid rgba(255,255,255,.24)!important;position:relative!important;z-index:1!important;flex:0 0 auto!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge svg{display:block!important;width:16px!important;height:16px!important;stroke:#fff!important;stroke-width:2.15!important;stroke-linecap:round!important;stroke-linejoin:round!important;fill:none!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-label{display:inline-flex!important;align-items:center!important;line-height:1!important;color:#fff!important;font-weight:950!important;position:relative!important;z-index:1!important;}
@media(max-width:760px){.pc-form .pc-head-actions .pc-header-home-clean{width:auto!important;min-width:140px!important;}}

</style>

<style id="schoolmanager-v251-home-buttons">
/* v251: Inicio rojo y sin circulo en el icono */
.av .av-btn.home,
.pc-requests .pc-btn-home,
.pc-form .pc-head-actions .pc-header-home-clean{
  background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%)!important;
  border:1px solid #7c1b1b!important;
  color:#fff!important;
  box-shadow:0 18px 38px rgba(139,30,30,.24)!important;
}
.av .av-btn.home:hover,
.pc-requests .pc-btn-home:hover,
.pc-form .pc-head-actions .pc-header-home-clean:hover{
  background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%)!important;
  transform:translateY(-4px)!important;
  box-shadow:0 26px 46px rgba(139,30,30,.30)!important;
}
.pc-requests .pc-btn-home .pc-home-badge,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  width:auto!important;
  height:auto!important;
  min-width:0!important;
  min-height:0!important;
  padding:0!important;
  margin:0!important;
  border:0!important;
  border-radius:0!important;
  background:transparent!important;
  box-shadow:none!important;
  flex:0 0 auto!important;
  position:relative!important;
  z-index:2!important;
}
.pc-requests .pc-btn-home .pc-home-badge svg,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge svg{
  display:block!important;
  width:19px!important;
  height:19px!important;
  stroke:#fff!important;
  stroke-width:2.25!important;
  stroke-linecap:round!important;
  stroke-linejoin:round!important;
  fill:none!important;
}
.pc-requests .pc-btn-home span:last-child,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-label,
.pc-form .pc-head-actions .pc-header-home-clean span:last-child{
  color:#fff!important;
  font-weight:950!important;
  line-height:1!important;
  position:relative!important;
  z-index:2!important;
}
.pc-requests .pc-btn-home,
.pc-form .pc-head-actions .pc-header-home-clean{
  gap:10px!important;
  min-height:52px!important;
  padding:0 24px!important;
  border-radius:999px!important;
}
@media(max-width:760px){
  .pc-form .pc-head-actions .pc-header-home-clean{width:auto!important;min-width:130px!important;}
}

/* v252: botones menos redondos, rectangulares con bordes redondeados */
.pc-requests .pc-btn-home,
.pc-requests .pc-btn-primary,
.pc-requests .pc-btn-secondary,
.pc-requests .pc-btn-detail,
.pc-requests .pc-btn-native,
.pc-requests .pc-card-actions .pc-btn,
.pc-requests .pc-hero-actions .pc-btn,
.pc-form .pc-head-actions .pc-header-home-clean,
.pc-form .pc-btn-location,
.pc-form .pc-btn-cancel,
.pc-form .pc-btn-create,
.pc-alerts .pc-btn-back,
.pc-alerts .pc-btn-detail{border-radius:18px!important;}

.pc-requests .pc-btn-home,
.pc-form .pc-head-actions .pc-header-home-clean,
.pc-form .pc-btn-location,
.pc-form .pc-btn-cancel,
.pc-form .pc-btn-create,
.pc-alerts .pc-btn-back,
.pc-alerts .pc-btn-detail{padding-left:22px!important;padding-right:22px!important;}

.pc-requests .pc-btn-home .pc-home-badge,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{border-radius:10px!important;width:24px!important;height:24px!important;background:rgba(255,255,255,.12)!important;border:0!important;box-shadow:none!important;}

.pc-requests .pc-btn-home .pc-home-badge svg,
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge svg{width:15px!important;height:15px!important;}

.pc-requests .pc-btn-home:hover,
.pc-form .pc-head-actions .pc-header-home-clean:hover,
.pc-form .pc-btn-location:hover,
.pc-form .pc-btn-cancel:hover,
.pc-form .pc-btn-create:hover,
.pc-alerts .pc-btn-back:hover,
.pc-alerts .pc-btn-detail:hover{transform:translateY(-4px)!important;}

@media(max-width:760px){
  .pc-requests .pc-btn-home,
  .pc-form .pc-head-actions .pc-header-home-clean,
  .pc-form .pc-btn-location,
  .pc-form .pc-btn-cancel,
  .pc-form .pc-btn-create,
  .pc-alerts .pc-btn-back,
  .pc-alerts .pc-btn-detail{border-radius:16px!important;}
}

</style>

<div class="pc-form" id="pcForm" data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>' data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>" data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>" data-old-category="<?= (int)$oldCategoryId ?>">
  <div class="pc-card">
    <div class="pc-head"><div class="pc-head-main"><img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" class="pc-logo" alt="Logo del centro" onerror="this.onerror=null;this.src='<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/logo.svg?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>';"><div class="pc-head-copy"><small><?= htmlspecialchars(plugin_schoolmanager_tr('plugin_kicker','GLPI School Manager'), ENT_QUOTES, 'UTF-8') ?></small><h1>Incidencia guiada</h1><p>Crea tickets de soporte de forma clara, rápida y adaptada al centro educativo.</p></div></div><div class="pc-head-actions"><a class="pc-header-home pc-header-home-red pc-header-home-clean" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>" aria-label="Inicio"><svg class="pc-btn-svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/></svg><span class="pc-home-label">Inicio</span></a></div></div>
    <?php if ($message): ?><div class="pc-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><span class="pc-svgicon pc-i-warning" aria-hidden="true"></span> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?><br><small>Hemos conservado lo que habías escrito. Revisa el campo marcado y vuelve a enviar.</small></div><?php endif; ?>
    <form method="post" id="ticketGuidedForm" novalidate>
      <?php pc_csrf_token_field(); ?>
      <input type="hidden" name="pc_create" value="1"><input type="hidden" name="v" value="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="locations_id" id="pcLocationId" value="<?= (int)$oldLocationId ?>">
      <input type="hidden" name="location_label" id="pcLocationLabel" value="<?= htmlspecialchars($oldLocationLabel, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="location_code" id="pcLocationCode" value="<?= htmlspecialchars($oldLocationCode, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="incident_kind" id="incidentKind" value="<?= pc_old('incident_kind', 'other') ?>">
      <input type="hidden" name="itilcategories_id" id="pcCategoryId" value="<?= (int)pc_post('itilcategories_id', 0) ?>">
      <input type="hidden" name="category_label" id="pcCategoryLabel" value="<?= pc_old('category_label') ?>">
      <div class="pc-body"><section class="pc-section"><h2><i class="bi bi-journal-richtext"></i> Datos de la incidencia</h2>
        <div class="pc-validation-panel" id="pcValidationPanel"><b>Revisa estos datos antes de crear la incidencia</b><ul id="pcValidationList"></ul></div>
        <div class="pc-category-block" id="pcCategoryBlock">
          <div class="pc-category-top"><h3><i class="bi bi-diagram-3"></i> Categoría de incidencia</h3><span class="pc-category-note"><i class="bi bi-info-circle"></i> Elige un bloque y después el problema</span></div>
          <div class="pc-current-cat" id="pcCurrentCat"><?= pc_post('category_label') !== '' ? htmlspecialchars(pc_post('category_label'), ENT_QUOTES, 'UTF-8') : 'Selecciona una categoría' ?><span><?= pc_post('category_label') !== '' ? 'Categoría conservada.' : 'El formulario se adapta automáticamente.' ?></span></div>
          <div class="pc-category-layout">
            <div><div class="pc-label-mini">Categoría padre</div><div class="pc-parent-list" id="pcParentCategories">
              <?php foreach ($itilCategories as $i => $cat): $pid=(int)($cat['id'] ?? 0); $pname=(string)($cat['name'] ?? 'Categoría'); $pcomplete=(string)($cat['complete'] ?? $pname); $pchildren=$cat['children'] ?? []; ?>
                <button type="button" class="pc-parent-btn" data-parent-id="<?= $pid ?>" data-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-complete="<?= htmlspecialchars($pcomplete, ENT_QUOTES, 'UTF-8') ?>" data-asset-types="<?= htmlspecialchars(pc_asset_types_attr($pcomplete . ' ' . $pname), ENT_QUOTES, 'UTF-8') ?>">
                  <span class="pc-parent-ico pc-svgicon <?= htmlspecialchars(pc_category_icon($pname), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span><span><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></span>
                </button>
              <?php endforeach; ?>
            </div></div>
            <div class="pc-child-box" id="pcChildBox"><div class="pc-child-head"><div class="pc-label-mini">Problema concreto</div><div class="pc-category-tools"><input class="pc-cat-search" id="pcCategorySearch" type="search" placeholder="Buscar problema..."><button type="button" class="pc-cat-clear" id="pcCategoryClear">Limpiar</button></div></div><div class="pc-empty-subcat" id="pcEmptySubcat">Elige un bloque de la izquierda para ver sus problemas.</div>
              <?php foreach ($itilCategories as $cat): $pid=(int)($cat['id'] ?? 0); $pname=(string)($cat['name'] ?? 'Categoría'); $pcomplete=(string)($cat['complete'] ?? $pname); $children=$cat['children'] ?? []; ?>
                <div class="pc-subcat-list" data-parent="<?= $pid ?>" style="display:none">
                  <?php if (!$children): ?>
                    <button type="button" class="pc-subcat" data-id="<?= $pid ?>" data-parent-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-complete="<?= htmlspecialchars($pcomplete, ENT_QUOTES, 'UTF-8') ?>" data-asset-types="<?= htmlspecialchars(pc_asset_types_attr($pcomplete . ' ' . $pname), ENT_QUOTES, 'UTF-8') ?>"><b><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars($pcomplete, ENT_QUOTES, 'UTF-8') ?></small></button>
                  <?php endif; ?>
                  <?php foreach ($children as $child): $cid=(int)($child['id'] ?? 0); $cname=(string)($child['name'] ?? 'Subcategoría'); $ccomplete=(string)($child['complete'] ?? ($pname.' > '.$cname)); ?>
                    <button type="button" class="pc-subcat" data-id="<?= $cid ?>" data-parent-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?>" data-complete="<?= htmlspecialchars($ccomplete, ENT_QUOTES, 'UTF-8') ?>" data-asset-types="<?= htmlspecialchars(pc_asset_types_attr($ccomplete . ' ' . $cname), ENT_QUOTES, 'UTF-8') ?>"><b><?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars(str_replace(' > ', ' › ', $ccomplete), ENT_QUOTES, 'UTF-8') ?></small></button>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="pc-fields">
          <label class="pc-field full"><span class="pc-label">Título *</span><input class="pc-input" name="name" id="ticketTitle" value="<?= pc_old('name') ?>" placeholder="Ej: No funciona el proyector del aula 206" required></label>
          <div class="pc-field full pc-location-field"><span class="pc-label">Ubicación *</span><div class="pc-location pc-location-v237"><div class="pc-location-main"><span class="pc-location-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.4"/></svg></span><div class="pc-selected" id="pcSelectedLocation"><b><?= $oldLocationLabel !== '' ? htmlspecialchars($oldLocationLabel, ENT_QUOTES, 'UTF-8') : 'Sin ubicación seleccionada' ?></b><span><?= $oldLocationCode !== '' ? htmlspecialchars($oldLocationCode, ENT_QUOTES, 'UTF-8') : ($oldLocationId > 0 ? ('ID GLPI ' . (int)$oldLocationId) : 'Selecciona el aula desde el plano o desde la lista') ?></span></div></div><button type="button" class="pc-btn primary pc-btn-location" id="pcOpenSelector"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18 3.8 20.2V6.2L9 4m0 14 6 2.2m-6-2.2V4m6 16.2 5.2-2.2V4L15 6.2m0 14V6.2M9 4l6 2.2"/></svg><span>Elegir ubicación</span></button></div></div>
          <div class="pc-field full pc-asset-field"><span class="pc-label">Activo afectado del aula</span><div class="pc-asset-picker"><select class="pc-select" name="affected_asset" id="affectedAsset"><option value="__none__" data-type="" <?= $oldAffectedAsset === '__none__' ? 'selected' : '' ?>>No especificar activo concreto</option><?php foreach ($assetOptions as $asset): $val = ($asset['type'] ?? '') . ':' . (int)($asset['id'] ?? 0); $display = trim((string)($asset['display_name'] ?? $asset['name'] ?? '')); $txt = trim(($display !== '' ? $display : ($asset['name'] ?? 'Activo')) . ' · ID ' . (int)($asset['id'] ?? 0)); if (!empty($asset['serial'])) { $txt .= ' · S/N ' . $asset['serial']; } ?><option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($asset['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $oldAffectedAsset === $val ? 'selected' : '' ?>><?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><option value="__custom__" data-type="custom" <?= $oldAffectedAsset === '__custom__' ? 'selected' : '' ?>>Otro activo no listado / personalizado</option></select><input class="pc-input" name="affected_asset_custom" id="affectedAssetCustom" value="<?= pc_old('affected_asset_custom') ?>" placeholder="Ej: portátil del profesor, cable HDMI, ratón, monitor sin etiqueta..."></div><small class="pc-help" id="assetSmartHelp"><?php if ($oldLocationId > 0): ?>Activos cargados desde GLPI para esta ubicación. La lista se filtrará según la categoría seleccionada. Si falta alguno, usa la opción personalizada.<?php else: ?>Al abrir desde Detalle del aula se cargarán aquí sus activos de GLPI.<?php endif; ?></small></div>
        </div>
        <div class="pc-hint" id="kindHint">Selecciona una categoría para adaptar el formulario.</div>
        <div class="pc-fields">
          <label class="pc-field pc-conditional" data-show-for="computer software"><span class="pc-label">Número del ordenador *</span><input class="pc-input" name="computer_number" id="computerNumber" value="<?= pc_old('computer_number') ?>" placeholder="Ej: PC-03, 08, equipo profesor..."></label>
          <label class="pc-field pc-conditional" data-show-for="software"><span class="pc-label">Software afectado *</span><input class="pc-input" name="software_name" id="softwareName" value="<?= pc_old('software_name') ?>" placeholder="Ej: Chrome, Office, AutoCAD..."></label>
          <label class="pc-field"><span class="pc-label">Detalle técnico opcional</span><select class="pc-select" name="problem_subtype" id="problemSubtype" data-old-value="<?= pc_old('problem_subtype', 'Otro') ?>"><option><?= pc_old('problem_subtype', 'Otro') ?></option></select></label>
        </div>
        <div class="pc-fields" style="margin-top:14px"><label class="pc-field full"><span class="pc-label">Descripción *</span><textarea class="pc-textarea" name="content" id="ticketContent" placeholder="Explica qué ocurre, desde cuándo, equipo afectado, pasos probados..." required><?= pc_old('content') ?></textarea></label></div>
        <div class="pc-fields pc-normal-fields" style="margin-top:14px">
          <?php foreach (['urgency'=>'Urgencia','impact'=>'Impacto','priority'=>'Prioridad'] as $name=>$label): ?>
            <label class="pc-field"><span class="pc-label"><?= $label ?> *</span><select class="pc-select" name="<?= $name ?>"><option value="1"<?= pc_selected($name, 1) ?>>Muy baja</option><option value="2"<?= pc_selected($name, 2) ?>>Baja</option><option value="3"<?= pc_selected($name, 3, true) ?>>Media</option><option value="4"<?= pc_selected($name, 4) ?>>Alta</option><option value="5"<?= pc_selected($name, 5) ?>>Muy alta</option></select></label>
          <?php endforeach; ?>
        </div>
        <div class="pc-priority-ai" id="priorityAI">Prioridad inteligente: selecciona una categoría y ajustaremos una recomendación editable.</div>
      </section></div>
      <div class="pc-submit"><a class="pc-btn secondary pc-btn-cancel" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6"/><path d="M8 12h10"/></svg><span>Cancelar</span></a><button class="pc-btn gold pc-btn-create" type="submit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 6v12"/><path d="M6 12h12"/></svg><span>Crear incidencia</span></button></div>
    </form>
  </div>
  <?php include(__DIR__ . '/../inc/location_modal_markup.php'); ?>
</div>
<?php include(__DIR__ . '/../inc/location_modal_script.php'); ?>
<script>
(function(){
  const form=document.getElementById('ticketGuidedForm');
  const catId=document.getElementById('pcCategoryId'), catLabel=document.getElementById('pcCategoryLabel'), current=document.getElementById('pcCurrentCat'), kind=document.getElementById('incidentKind'), hint=document.getElementById('kindHint'), subtype=document.getElementById('problemSubtype');
  const validationPanel=document.getElementById('pcValidationPanel'), validationList=document.getElementById('pcValidationList');
  const affectedAsset=document.getElementById('affectedAsset'), affectedAssetCustom=document.getElementById('affectedAssetCustom');
  const assetsEndpoint='<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/assets_aula.php';
  let currentAssetTypes=[];
  function updateAssetCustom(){const wrap=affectedAsset?affectedAsset.closest('.pc-asset-picker'):null; if(wrap)wrap.classList.toggle('custom', affectedAsset && affectedAsset.value==='__custom__');}
  function assetTypesFromText(t){t=norm(t); if(/monitor/.test(t))return['Monitor']; if(/ordenador|equipo|\bpc\b|portatil|chromebook/.test(t))return['Computer']; if(/impresora|impresion|toner|tinta|escaner/.test(t))return['Printer']; if(/red|internet|wifi|conexion|router|switch|punto de red/.test(t))return['NetworkEquipment']; if(/proyector/.test(t))return['Projector']; if(/teclado|raton|periferico|hdmi|cable|adaptador/.test(t))return['Peripheral']; if(/telefono/.test(t))return['Phone']; return[];}
  function setAssetFilter(types,label){if(!affectedAsset)return; types=Array.isArray(types)?types.filter(Boolean):[]; currentAssetTypes=types; let visible=0; Array.from(affectedAsset.options).forEach(o=>{const val=o.value, typ=o.dataset.type||''; const special=val==='__none__'||val==='__custom__'; const show=special||!types.length||types.includes(typ); o.hidden=!show; o.disabled=!show; if(show&&!special)visible++;}); const cur=affectedAsset.selectedOptions[0]; if(cur && cur.disabled) affectedAsset.value='__none__'; const help=document.getElementById('assetSmartHelp'); if(help){help.textContent=types.length ? ('Filtro inteligente: mostrando '+(label||'activos relacionados')+'. Encontrados: '+visible+'. Si no aparece, usa activo personalizado.') : 'Activos cargados desde GLPI para esta ubicación. Si falta alguno, usa la opción personalizada.';} updateAssetCustom();}
  function assetOptionText(a){let name=(a.display_name||a.name||('ID '+a.id)); let txt=name+' · ID '+a.id; if(a.serial)txt+=' · S/N '+a.serial; return txt;}
  function rebuildAssetOptions(assets){if(!affectedAsset)return; const old=affectedAsset.value; affectedAsset.innerHTML=''; const none=document.createElement('option'); none.value='__none__'; none.dataset.type=''; none.textContent='No especificar activo concreto'; affectedAsset.appendChild(none); (assets||[]).forEach(a=>{const o=document.createElement('option'); o.value=(a.type||'')+':'+parseInt(a.id||0,10); o.dataset.type=a.type||''; o.textContent=assetOptionText(a); affectedAsset.appendChild(o);}); const custom=document.createElement('option'); custom.value='__custom__'; custom.dataset.type='custom'; custom.textContent='Otro activo no listado / personalizado'; affectedAsset.appendChild(custom); if(Array.from(affectedAsset.options).some(o=>o.value===old)) affectedAsset.value=old; setAssetFilter(currentAssetTypes,'activos relacionados');}
  function loadAssetsForLocation(id){id=parseInt(id||0,10); if(!id||!affectedAsset)return; const help=document.getElementById('assetSmartHelp'); if(help)help.textContent='Cargando activos del aula desde GLPI...'; fetch(assetsEndpoint+'?location_id='+encodeURIComponent(id),{credentials:'same-origin'}).then(r=>r.json()).then(j=>{if(j&&j.ok)rebuildAssetOptions(j.assets||[]); else if(help)help.textContent='No se pudieron cargar activos. Usa activo personalizado.';}).catch(()=>{if(help)help.textContent='No se pudieron cargar activos. Usa activo personalizado.';});}
  const subtypeOptions={monitor:['No enciende','No da imagen','Cableado','Relacionado con ordenador conectado','Otro'],computer:['No enciende','No inicia Windows','Va lento','No tiene Internet','Teclado / ratón / pantalla','Otro'],software:['No abre','Da error','No está instalado','Licencia caducada','Actualización necesaria','Otro'],network:['Sin Internet','WiFi no funciona','Cable de red','Conexión lenta','Otro'],projector:['No proyecta','Sin imagen','Sin sonido','Mando / cable','Otro'],printer:['No imprime','Atasco de papel','Sin tóner/tinta','No aparece en el equipo','Otro'],whiteboard:['No enciende','No responde táctil','Problema de imagen','Cableado','Otro'],audio:['No hay sonido','Micrófono','Altavoces','Cableado','Otro'],account:['No puede iniciar sesión','Contraseña','Permisos','Correo','Otro'],request:['Solicitud de soporte','Instalación','Cambio de aula','Préstamo de equipo','Otro'],maintenance:['Revisión preventiva','Actualización','Limpieza','Backup','Otro'],other:['Incidencia general','Revisión técnica','Otro']};
  const hints={monitor:'Selecciona el monitor afectado. Si GLPI tiene informado el nombre de usuario alternativo, se mostrará el PC conectado. Si no aparece, usa activo personalizado.',computer:'Indica el número del ordenador/equipo para localizarlo rápido.',software:'Indica número de equipo y programa afectado.',network:'Indica si afecta a aula completa, WiFi, cable, switch, router o Internet.',projector:'Indica si falla imagen, sonido, mando, cable o pantalla.',printer:'Indica si no imprime, hay atasco o falta consumible.',whiteboard:'Indica si falla táctil, imagen, encendido o cableado.',audio:'Indica si falla sonido, micrófono, altavoces o cableado.',account:'Indica usuario afectado y servicio si procede.',request:'Indica exactamente qué se solicita.',maintenance:'Indica qué revisión preventiva hay que realizar.',other:'Describe el problema con el máximo detalle posible.'};
  function norm(s){return String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
  function kFrom(t){t=norm(t); if(/proyector|pantalla|hdmi|imagen/.test(t))return'projector'; if(/impresora|impresion|toner|tinta/.test(t))return'printer'; if(/pizarra|tactil/.test(t))return'whiteboard'; if(/audio|sonido|altavoz|microfono/.test(t))return'audio'; if(/monitor/.test(t))return'monitor'; if(/ordenador|equipo|pc|portatil|chromebook/.test(t))return'computer'; if(/teclado|raton|escaner|periferico/.test(t))return'other'; if(/software|program|aplicacion|licencia|office|chrome|moodle|navegador|antivirus/.test(t))return'software'; if(/red|internet|wifi|conexion|router|switch|servidor|dns|dhcp/.test(t))return'network'; if(/usuario|contrasena|cuenta|correo|login|permiso|microsoft/.test(t))return'account'; if(/solicitud|prestamo|alta de equipo|cambio de aula|reserva|compra|sustitucion/.test(t))return'request'; if(/mantenimiento|revision|actualizacion|limpieza|backup/.test(t))return'maintenance'; return'other';}
  function setSubtype(k){const old=subtype.dataset.oldValue||''; subtype.innerHTML=''; (subtypeOptions[k]||subtypeOptions.other).forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;if(old===v)o.selected=true;subtype.appendChild(o);});}
  let lastAppliedKind='';
  function hasSpecificAsset(){return affectedAsset && affectedAsset.value && affectedAsset.value!=='__none__';}
  function applyKind(k){kind.value=k||'other'; document.querySelectorAll('.pc-conditional').forEach(el=>{const needsComputer=(el.querySelector('#computerNumber')!==null); const show=(el.dataset.showFor||'').split(/\s+/).includes(kind.value) && !(needsComputer && hasSpecificAsset()); el.classList.toggle('show',show);}); hint.textContent=(hasSpecificAsset() && (kind.value==='computer'||kind.value==='software'))?'Activo seleccionado: no hace falta indicar número de ordenador.':(hints[kind.value]||hints.other); if(lastAppliedKind!==kind.value){setSubtype(kind.value); lastAppliedKind=kind.value;}}
  function setPriority(label,k){let v=3,txt='Media'; const t=norm(label); if(k==='network'||/internet|wifi|switch|router|servidor|dns|dhcp/.test(t)){v=4;txt='Alta'} if(/servidor|dns|dhcp|internet/.test(t)){v=5;txt='Muy alta'} if(k==='request'||k==='maintenance'){v=2;txt='Baja'} ['urgency','impact','priority'].forEach(n=>{const e=form.querySelector('[name="'+n+'"]'); if(e)e.value=String(v);}); document.getElementById('priorityAI').textContent='Prioridad inteligente sugerida: '+txt+'. Puedes cambiarla antes de enviar.';}
  function showParent(id,btn){document.querySelectorAll('.pc-parent-btn').forEach(b=>b.classList.remove('active')); if(btn)btn.classList.add('active'); document.querySelectorAll('.pc-subcat-list').forEach(l=>{l.classList.remove('active');l.style.display='none';}); const empty=document.getElementById('pcEmptySubcat'); const list=document.querySelector('.pc-subcat-list[data-parent="'+id+'"]'); if(list){list.classList.add('active');list.style.display='grid'; if(empty){empty.classList.add('hide');empty.style.display='none';}} else if(empty){empty.classList.remove('hide');empty.style.display='grid';} current.innerHTML=btn.dataset.name+'<span>Ahora elige una subcategoría.</span>'; catId.value=''; catLabel.value=''; const search=document.getElementById('pcCategorySearch'); if(search)search.value=''; document.querySelectorAll('.pc-subcat').forEach(x=>x.hidden=false); applyKind(kFrom(btn.dataset.complete||btn.dataset.name)); setAssetFilter((btn.dataset.assetTypes||'').split(',').filter(Boolean), btn.dataset.name||'');}
  document.querySelectorAll('.pc-parent-btn').forEach(btn=>btn.addEventListener('click',()=>showParent(btn.dataset.parentId,btn)));
  document.querySelectorAll('.pc-subcat').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.pc-subcat').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); catId.value=btn.dataset.id||''; catLabel.value=btn.dataset.complete||btn.dataset.name||''; current.innerHTML=(btn.dataset.parentName||'Categoría')+' → '+(btn.dataset.name||'Subcategoría')+'<span>Categoría GLPI seleccionada · ID '+(btn.dataset.id||'')+'</span>'; const k=kFrom((btn.dataset.complete||'')+' '+(btn.dataset.name||'')); applyKind(k); setAssetFilter((btn.dataset.assetTypes||'').split(',').filter(Boolean).length ? (btn.dataset.assetTypes||'').split(',').filter(Boolean) : assetTypesFromText((btn.dataset.complete||'')+' '+(btn.dataset.name||'')), btn.dataset.name||''); setPriority(btn.dataset.complete||btn.dataset.name,k); if(!document.getElementById('ticketTitle').value.trim()) document.getElementById('ticketTitle').value='Incidencia - '+(btn.dataset.name||'soporte'); updateState();}));
  function mark(el,bad){const w=el?(el.closest('.pc-field')||el.closest('.pc-location-field')):null; if(w)w.classList.toggle('pc-required-missing',!!bad)}
  function updateState(){document.getElementById('pcCategoryBlock').classList.toggle('pc-is-complete',!!catId.value); document.querySelector('.pc-location-field').classList.toggle('pc-is-complete',Number(document.getElementById('pcLocationId').value)>0); const af=document.querySelector('.pc-asset-field'); if(af&&affectedAsset)af.classList.toggle('pc-is-complete', hasSpecificAsset()); updateAssetCustom(); applyKind(kind.value||'other');}
  function validate(){document.querySelectorAll('.pc-required-missing').forEach(e=>e.classList.remove('pc-required-missing')); document.getElementById('pcChildBox').classList.remove('pc-category-missing'); validationPanel.classList.remove('show'); const errs=[]; if(!catId.value){errs.push('Elige una subcategoría.'); document.getElementById('pcChildBox').classList.add('pc-category-missing')} if(Number(document.getElementById('pcLocationId').value)<=0){errs.push('Elige una ubicación.'); document.querySelector('.pc-location-field').classList.add('pc-required-missing')} const title=document.getElementById('ticketTitle'), content=document.getElementById('ticketContent'); if(!title.value.trim()){errs.push('Escribe un título.'); mark(title,true)} if(!content.value.trim()){errs.push('Describe el problema.'); mark(content,true)} if((kind.value==='computer'||kind.value==='software')&&!hasSpecificAsset()&&!document.getElementById('computerNumber').value.trim()){errs.push('Indica el número del ordenador/equipo o selecciona un activo del aula.'); mark(document.getElementById('computerNumber'),true)} if(kind.value==='software'&&!document.getElementById('softwareName').value.trim()){errs.push('Indica el software afectado.'); mark(document.getElementById('softwareName'),true)} if(errs.length){validationList.innerHTML=errs.map(e=>'<li>'+e+'</li>').join(''); validationPanel.classList.add('show'); validationPanel.scrollIntoView({behavior:'smooth',block:'center'}); return false} return true}
  const catSearch=document.getElementById('pcCategorySearch'), catClear=document.getElementById('pcCategoryClear');
  function normalizeCat(t){return String(t||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
  function filterCats(){const q=normalizeCat(catSearch&&catSearch.value); const active=document.querySelector('.pc-subcat-list.active'); if(!active)return; active.querySelectorAll('.pc-subcat').forEach(b=>{b.hidden=!!q && !normalizeCat(b.textContent).includes(q);});}
  if(catSearch)catSearch.addEventListener('input',filterCats); if(catClear)catClear.addEventListener('click',()=>{if(catSearch)catSearch.value='';filterCats();catSearch&&catSearch.focus();});
  form.addEventListener('submit',e=>{if(!validate()){e.preventDefault();return} const b=form.querySelector('button[type=submit]'); if(b){b.disabled=true;b.textContent='Creando incidencia...'}});
  document.addEventListener('input',updateState); document.addEventListener('change',updateState); if(affectedAsset)affectedAsset.addEventListener('change',updateAssetCustom); window.addEventListener('schoolmanager:location-selected',e=>{updateState(); const id=(e.detail&&e.detail.id)||document.getElementById('pcLocationId').value; loadAssetsForLocation(id);});
  const old=String(document.getElementById('pcCategoryId').value||''); if(old){const c=document.querySelector('.pc-subcat[data-id="'+old.replace(/"/g,'')+'"]'); if(c){const p=document.querySelector('.pc-parent-btn[data-parent-id="'+c.closest('.pc-subcat-list').dataset.parent+'"]'); if(p)showParent(p.dataset.parentId,p); c.click();}} else {const first=document.querySelector('.pc-parent-btn'); if(first)showParent(first.dataset.parentId,first)}
  if(catLabel.value){setAssetFilter(assetTypesFromText(catLabel.value), catLabel.value);}
  updateState();
})();
</script>

<style id="v255-incidencia-home-unificado">
.pc-form .pc-head-actions .pc-header-home-clean{border-radius:18px!important;background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%)!important;border-color:#7c1b1b!important;color:#fff!important;}
.pc-form .pc-head-actions .pc-header-home-clean:hover{background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%)!important;transform:translateY(-4px)!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-svgicon{display:inline-grid!important;width:18px!important;height:18px!important;background:#fff!important;color:#fff!important;position:relative!important;z-index:2!important;}
.pc-form .pc-head-actions .pc-header-home-clean .pc-home-badge{display:none!important;}
</style>


<?php Html::footer(); ?>




<style id="v258-iconos-finales">
/* v258: icon system final - no solid squares */
.pc-svgicon,.gsm-svgicon{
  display:inline-block!important;width:20px!important;height:20px!important;min-width:20px!important;
  background:transparent!important;color:currentColor!important;border:0!important;box-shadow:none!important;
  -webkit-mask:none!important;mask:none!important;overflow:visible!important;text-indent:0!important;line-height:1!important;
  flex:0 0 auto!important;position:relative!important;vertical-align:middle!important;
}
.pc-svgicon:before,.gsm-svgicon:before{
  content:""!important;display:block!important;width:100%!important;height:100%!important;
  background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;
}
.av .av-btn .pc-svgicon,.av .av-btn.home .pc-svgicon,.av .av-btn.primary .pc-svgicon,
.pc-form .pc-btn .pc-svgicon,.pc-form #pcOpenSelector .pc-svgicon,.pc-form .pc-btn-create .pc-svgicon,.pc-form .pc-btn-cancel .pc-svgicon,.pc-form .pc-btn-back .pc-svgicon,
.pc-home .pc-action .pc-svgicon,.pc-home .pc-asset-icon .pc-svgicon,.pc-home .pc-ico.pc-svgicon{
  background:transparent!important;-webkit-mask:none!important;mask:none!important;color:currentColor!important;
}
.av .av-btn .pc-svgicon:before,.av .av-btn.home .pc-svgicon:before,.av .av-btn.primary .pc-svgicon:before,
.pc-form .pc-btn .pc-svgicon:before,.pc-form #pcOpenSelector .pc-svgicon:before,.pc-form .pc-btn-create .pc-svgicon:before,.pc-form .pc-btn-cancel .pc-svgicon:before,.pc-form .pc-btn-back .pc-svgicon:before,
.pc-home .pc-action .pc-svgicon:before,.pc-home .pc-asset-icon .pc-svgicon:before,.pc-home .pc-ico.pc-svgicon:before{
  display:block!important;content:""!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;
}
.av .av-btn.home .pc-svgicon,.pc-form .pc-btn-home .pc-svgicon,.pc-form .pc-btn-create .pc-svgicon,.pc-form #pcOpenSelector .pc-svgicon,.av .av-btn.primary .pc-svgicon{color:#fff!important;}
.pc-btn-svg{width:20px;height:20px;display:block;stroke:currentColor;stroke-width:2.35;stroke-linecap:round;stroke-linejoin:round;fill:none;flex:0 0 auto;position:relative;z-index:1;}
</style>

