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
            Html::redirect($root . '/plugins/schoolmanager/front/ticket_detail.php?id=' . (int)$newId . '&created=1&v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION));
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

require_once(__DIR__ . '/../inc/ui_helpers.php');
require_once(__DIR__ . '/../inc/layout.php');
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

sm_page_header([
    'title'       => plugin_schoolmanager_tr('menu_create_ticket', 'Nueva Incidencia'),
    'subtitle'    => plugin_schoolmanager_tr('create_ticket_desc', 'Crea tickets de soporte de forma clara, rápida y adaptada al centro educativo.'),
    'active_tab'  => 'nueva_incidencia',
]);
?>

<style>
/* ==========================================================================
   SCHOOL MANAGER - NUEVA INCIDENCIA GUIADA (Unificado con Alta de Activos)
   ========================================================================== */
.pc-form-view {
  --sm-navy: #0F2951;
  --sm-teal: #088E91;
  --sm-red: #B6252B;
  --sm-bg: #F8FAFC;
  --sm-card: #FFFFFF;
  --sm-border: #D7E6EC;
  --sm-border-subtle: #E8F0F4;
  --sm-muted: #64748B;
  --sm-ink: #0F2951;

  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: var(--sm-ink);
  min-height: calc(100vh - 75px);
  padding: 0 !important;
  width: 100% !important;
  max-width: 100% !important;
  margin: 0 !important;
  box-sizing: border-box;
}
.pc-form-view * { box-sizing: border-box; }

.pc-form-container {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
}

/* Interactive Step Progress Bar */
.pc-steps-tracker {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 18px;
}
.pc-step-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 12px;
  cursor: pointer;
  text-align: left;
  transition: all 0.16s ease;
  box-shadow: 0 2px 6px rgba(7, 56, 77, 0.03);
}
.pc-step-pill:hover {
  border-color: #088E91;
  background: #F8FAFC;
}
.pc-step-pill-num {
  width: 24px;
  height: 24px;
  border-radius: 7px;
  background: #E2E8F0;
  color: #0F2951;
  font-size: 12px;
  font-weight: 850;
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
  transition: all 0.16s ease;
}
.pc-step-pill.is-done {
  border-color: #99F6E4;
  background: #F0FDFA;
}
.pc-step-pill.is-done .pc-step-pill-num {
  background: #088E91;
  color: #FFFFFF;
}
.pc-step-pill-text {
  min-width: 0;
}
.pc-step-pill-text strong {
  display: block;
  font-size: 12.5px;
  font-weight: 800;
  color: #0F2951;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pc-step-pill-text small {
  display: block;
  font-size: 11px;
  font-weight: 650;
  color: #64748B;
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pc-step-pill.is-done .pc-step-pill-text small {
  color: #0F766E;
}

/* Alert & Validation Boxes */
.pc-message {
  margin: 0 0 16px;
  border-radius: 14px;
  padding: 14px 18px;
  font-weight: 750;
  font-size: 13.5px;
}
.pc-message.error { background: #FEE2E2; border: 1px solid #FCA5A5; color: #991B1B; }
.pc-message.info { background: #F0FDFA; border: 1px solid #99F6E4; color: #0F766E; }

.pc-validation-panel {
  display: none;
  margin: 0 0 18px;
  border: 1px solid #FCA5A5;
  background: #FFF5F5;
  color: #991B1B;
  border-radius: 14px;
  padding: 14px 18px;
  font-weight: 750;
}
.pc-validation-panel.show { display: block; }
.pc-validation-panel ul {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
}
.pc-validation-panel li {
  background: #FFFFFF;
  border: 1px solid #FCA5A5;
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 12px;
}

/* Step Cards Layout (Matches new_asset.php) */
.pc-form-sections {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pc-section-card {
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
}
.pc-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--sm-border-subtle);
  flex-wrap: wrap;
}
.pc-section-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pc-section-step {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: var(--sm-navy);
  color: #FFFFFF;
  font-size: 12px;
  font-weight: 800;
  flex-shrink: 0;
}
.pc-section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--sm-navy);
  letter-spacing: -0.015em;
}
.pc-section-title .req {
  color: #B6252B;
  font-weight: 900;
  margin-left: 2px;
}

/* Status Badges */
.pc-loc-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 800;
  border: 1px solid transparent;
}
.pc-loc-badge.ready {
  background: #E0F2FE;
  color: #0369A1;
  border-color: #BAE6FD;
}
.pc-loc-badge.selected {
  background: #F0FDFA;
  color: #0F766E;
  border-color: #99F6E4;
}

/* STEP 1: Category Selector */
.pc-current-cat {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 44px;
  margin-bottom: 14px;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 800;
  background: #F8FAFC;
  border: 1.5px solid #CBD5E1;
  color: #0F2951;
  flex-wrap: wrap;
  transition: all 0.16s ease;
}
.pc-current-cat.is-selected {
  background: #F0FDFA;
  border-color: #99F6E4;
  color: #0F766E;
}
.pc-current-cat span {
  font-size: 12px;
  color: #64748B;
  font-weight: 650;
}
.pc-category-layout {
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr);
  gap: 14px;
  align-items: start;
}
.pc-label-mini {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748B;
  font-weight: 800;
  margin-bottom: 8px;
}
.pc-parent-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}
.pc-parent-btn {
  width: 100%;
  border: 1px solid #D7E6EC;
  background: #FFFFFF;
  border-radius: 12px;
  padding: 9px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  text-align: left;
  color: #0F2951;
  font-weight: 750;
  font-size: 13.5px;
  transition: all 0.16s ease;
}
.pc-parent-btn:hover {
  background: #F4F8FA;
  border-color: #088E91;
}
.pc-parent-btn.active {
  background: #0F2951 !important;
  background-image: none !important;
  border-color: #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 8px rgba(15, 41, 81, 0.18);
}
.pc-parent-btn.active span {
  color: #FFFFFF !important;
}
.pc-parent-ico {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #F0F6F9;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: #0F2951;
}
.pc-parent-btn.active .pc-parent-ico {
  background: rgba(255, 255, 255, 0.18);
  color: #FFFFFF !important;
}
.pc-child-box {
  background: #F8FAFC;
  border: 1px solid #D7E6EC;
  border-radius: 14px;
  padding: 14px;
  min-height: 260px;
  display: flex;
  flex-direction: column;
}
.pc-child-box.pc-category-missing {
  border-color: #FCA5A5;
  background: #FFF5F5;
}
.pc-child-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.pc-category-tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.pc-cat-search {
  height: 38px;
  border: 1.5px solid #CBD5E1;
  border-radius: 10px;
  background: #FFFFFF;
  padding: 0 12px;
  font-size: 13px;
  font-weight: 600;
  outline: none;
  width: 230px;
  color: #0F2951;
}
.pc-cat-search:focus {
  border-color: #088E91;
  box-shadow: 0 0 0 3px rgba(8, 142, 145, 0.14);
}
.pc-cat-clear {
  height: 38px;
  border: 1px solid #CBD5E1;
  border-radius: 10px;
  background: #FFFFFF;
  padding: 0 12px;
  font-size: 12.5px;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
}
.pc-cat-clear:hover {
  background: #F1F5F9;
  color: #0F2951;
}
.pc-subcat-list:not(.active) {
  display: none !important;
}
.pc-subcat-list.active {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
  gap: 10px;
  max-height: 280px;
  overflow-y: auto;
  padding-right: 4px;
}
.pc-subcat[hidden] {
  display: none !important;
}
.pc-subcat {
  border: 1.5px solid #D7E6EC;
  background: #FFFFFF;
  border-radius: 12px;
  padding: 10px 12px;
  cursor: pointer;
  text-align: left;
  color: #0F2951;
  font-weight: 750;
  font-size: 13px;
  min-height: 48px;
  transition: all 0.16s ease;
}
.pc-subcat:hover {
  border-color: #088E91;
  background: #F0FDFA;
}
.pc-subcat.active {
  background: #0F2951 !important;
  background-image: none !important;
  border-color: #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 8px rgba(15, 41, 81, 0.18);
}
.pc-subcat.active b {
  color: #FFFFFF !important;
}
.pc-subcat small {
  display: block;
  font-size: 10.5px;
  color: #64748B;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pc-subcat.active small { color: #E2E8F0 !important; }
.pc-empty-subcat {
  display: grid;
  place-items: center;
  border: 1.5px dashed #CBD5E1;
  border-radius: 12px;
  padding: 24px;
  color: #64748B;
  font-weight: 650;
  font-size: 13px;
  text-align: center;
  flex: 1;
}

/* STEP 2: Location Interactive Card (Matches new_asset.php) */
.pc-location-interactive-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  border-radius: 14px;
  background: #F8FAFC;
  border: 1.5px solid #CBD5E1;
  transition: all 0.2s ease;
  flex-wrap: wrap;
}
.pc-location-interactive-card.is-selected {
  border-style: solid;
  border-color: #088E91;
  background: #F0FDFA;
  box-shadow: 0 4px 14px rgba(8, 142, 145, 0.08);
}
.pc-location-interactive-card.pc-required-missing {
  border-color: #FCA5A5;
  background: #FFF5F5;
}
.pc-loc-meta-box {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 220px;
  flex: 1;
}
.pc-loc-icon-bubble {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: inline-grid;
  place-items: center;
  background: #E0F2FE;
  color: #0F2951;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.pc-location-interactive-card.is-selected .pc-loc-icon-bubble {
  background: #0F2951;
  color: #FFFFFF;
  box-shadow: 0 4px 12px rgba(15, 41, 81, 0.24);
}
.pc-loc-info-text {
  min-width: 0;
}
.pc-loc-name {
  margin: 0;
  font-size: 15px;
  font-weight: 850;
  color: var(--sm-navy);
  line-height: 1.25;
}
.pc-loc-meta {
  margin: 3px 0 0;
  font-size: 12.5px;
  font-weight: 650;
  color: var(--sm-muted);
  line-height: 1.3;
}
.pc-location-interactive-card.is-selected .pc-loc-name {
  color: #065F46;
}
.pc-loc-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.pc-loc-quick-select {
  min-width: 240px;
  max-width: 320px;
  height: 42px;
  border: 1px solid #CBD5E1;
  border-radius: 10px;
  padding: 0 34px 0 12px;
  font-size: 13.5px;
  font-weight: 650;
  color: #0F2951;
  background-color: #FFFFFF;
}

/* Form Fields (Matches new_asset.php) */
.pc-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 18px;
}
.pc-fields-3 {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px 18px;
}
.pc-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.pc-field.full { grid-column: 1 / -1; }
.pc-label {
  font-size: 13px;
  font-weight: 850;
  color: #173844;
}
.pc-label .req {
  color: #B6252B;
  font-weight: 900;
  margin-left: 2px;
}
.pc-input, .pc-textarea, .pc-select {
  width: 100%;
  border: 1px solid #CBD5E1;
  border-radius: 12px;
  background: #FFFFFF;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 600;
  color: #0F2951;
  min-height: 42px;
  box-sizing: border-box;
  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}
.pc-input:focus, .pc-textarea:focus, .pc-select:focus {
  outline: none;
  border-color: #088E91;
  box-shadow: 0 0 0 3.5px rgba(8, 142, 145, 0.14);
}
.pc-textarea { min-height: 110px; resize: vertical; line-height: 1.45; }
.pc-field.pc-required-missing .pc-input,
.pc-field.pc-required-missing .pc-textarea {
  border-color: #EF4444;
  background: #FFF5F5;
}

.pc-asset-picker {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 10px;
}
.pc-asset-picker.custom {
  grid-template-columns: minmax(0, 1.1fr) minmax(220px, 0.9fr);
}
.pc-asset-picker .pc-input { display: none; }
.pc-asset-picker.custom .pc-input { display: block; }
.pc-help { font-size: 12px; color: #64748B; font-weight: 600; margin-top: 2px; }

.pc-hint {
  background: #FEF9EE;
  border: 1px solid #F6E2B3;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 12.5px;
  font-weight: 700;
  color: #8C6207;
  margin-bottom: 14px;
}
.pc-priority-ai {
  background: #F0FDFA;
  border: 1px solid #99F6E4;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 12.5px;
  font-weight: 700;
  color: #0F766E;
  margin-top: 14px;
}
.pc-conditional { display: none; }
.pc-conditional.show { display: flex; }

/* Quick Symptom Chips */
.pc-quick-chips {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 6px;
}
.pc-quick-chip-label {
  font-size: 11.5px;
  font-weight: 750;
  color: #64748B;
  margin-right: 2px;
}
.pc-quick-chip {
  border: 1px solid #CBD5E1;
  background: #F8FAFC;
  color: #0F2951;
  border-radius: 8px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}
.pc-quick-chip:hover {
  background: #0F2951;
  border-color: #0F2951;
  color: #FFFFFF;
}

/* Action Bar (Matches new_asset.php) */
.pc-action-bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
  padding: 16px 24px;
  background: #FFFFFF;
  border: 1px solid var(--sm-border);
  border-radius: 16px;
  box-shadow: 0 4px 14px rgba(7, 56, 77, 0.04);
}
.pc-btn-action {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  min-height: 42px !important;
  padding: 0 22px !important;
  border-radius: 10px !important;
  font-size: 13.5px !important;
  font-weight: 700 !important;
  text-decoration: none !important;
  cursor: pointer !important;
  white-space: nowrap !important;
  transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease !important;
}
.pc-btn-secondary {
  background: #FFFFFF !important;
  background-image: none !important;
  color: #0F2951 !important;
  border: 1px solid #CBD5E1 !important;
  box-shadow: 0 1px 2px rgba(15, 41, 81, 0.05) !important;
}
.pc-btn-secondary:hover {
  border-color: #94A3B8 !important;
  color: #0F2951 !important;
  background: #F1F5F9 !important;
}
.pc-btn-primary {
  background: #0F2951 !important;
  background-image: none !important;
  border: 1px solid #0F2951 !important;
  color: #FFFFFF !important;
  box-shadow: 0 1px 3px rgba(15, 41, 81, 0.18) !important;
}
.pc-btn-primary:hover {
  background: #088E91 !important;
  background-image: none !important;
  border-color: #088E91 !important;
  color: #FFFFFF !important;
}
.pc-btn-action svg {
  width: 17px;
  height: 17px;
  stroke: currentColor;
  stroke-width: 2.2;
  fill: none;
  flex-shrink: 0;
}

/* Responsive Clean Base - Handled uniformly by form_mobile_ux.php */
.pc-conditional { display: none; }
.pc-conditional.show { display: flex; }
</style>
<?php require_once __DIR__ . '/../inc/form_mobile_ux.php'; ?>

<?php
$plansActive = function_exists('plugin_schoolmanager_feature_enabled') ? plugin_schoolmanager_feature_enabled('plans') : true;
$bLabelsForSelect = function_exists('plugin_schoolmanager_building_labels') ? plugin_schoolmanager_building_labels() : ['A' => 'Edificio A', 'B' => 'Edificio B', 'C' => 'Edificio C'];
$roomsByBuilding = [];
foreach ($aulasAll as $rRow) {
    if (empty($rRow['id'])) { continue; }
    $bKey = (string)($rRow['building'] ?? 'A');
    $roomsByBuilding[$bKey][] = $rRow;
}
?>

<div class="pc-form-view pc-form" id="pcForm" data-rooms='<?= htmlspecialchars($roomsJson, ENT_QUOTES, 'UTF-8') ?>' data-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>" data-version="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>" data-old-category="<?= (int)$oldCategoryId ?>">
  <div class="pc-form-container">

    <?php if ($message): ?>
      <div class="pc-message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
        <span class="pc-svgicon pc-i-warning" aria-hidden="true"></span> <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?><br>
        <small>Hemos conservado lo que habías escrito. Revisa el campo marcado y vuelve a enviar.</small>
      </div>
    <?php endif; ?>

    <div class="pc-validation-panel" id="pcValidationPanel">
      <b>Revisa estos datos antes de crear la incidencia:</b>
      <ul id="pcValidationList"></ul>
    </div>

    <!-- Interactive Step Progress Bar -->
    <div class="pc-steps-tracker" aria-label="Progreso de la incidencia">
      <button type="button" class="pc-step-pill" id="pcStepPill1" data-scroll-target="#pcSection1">
        <span class="pc-step-pill-num">1</span>
        <span class="pc-step-pill-text">
          <strong>Categoría</strong>
          <small id="pcStepSub1"><?= $oldCategoryId > 0 ? '✓ Seleccionada' : 'Tipo de problema' ?></small>
        </span>
      </button>
      <button type="button" class="pc-step-pill" id="pcStepPill2" data-scroll-target="#pcSection2">
        <span class="pc-step-pill-num">2</span>
        <span class="pc-step-pill-text">
          <strong>Ubicación y Activo</strong>
          <small id="pcStepSub2"><?= $oldLocationId > 0 ? '✓ Aula asignada' : 'Aula y equipo' ?></small>
        </span>
      </button>
      <button type="button" class="pc-step-pill" id="pcStepPill3" data-scroll-target="#pcSection3">
        <span class="pc-step-pill-num">3</span>
        <span class="pc-step-pill-text">
          <strong>Descripción</strong>
          <small id="pcStepSub3">Título y detalles</small>
        </span>
      </button>
      <button type="button" class="pc-step-pill is-done" id="pcStepPill4" data-scroll-target="#pcSection4">
        <span class="pc-step-pill-num">4</span>
        <span class="pc-step-pill-text">
          <strong>Prioridad</strong>
          <small id="pcStepSub4">Ajuste inteligente</small>
        </span>
      </button>
    </div>

    <form method="post" id="ticketGuidedForm" novalidate>
      <?php pc_csrf_token_field(); ?>
      <input type="hidden" name="pc_create" value="1">
      <input type="hidden" name="v" value="<?= htmlspecialchars(PLUGIN_SCHOOLMANAGER_VERSION, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="locations_id" id="pcLocationId" value="<?= (int)$oldLocationId ?>">
      <input type="hidden" name="location_label" id="pcLocationLabel" value="<?= htmlspecialchars($oldLocationLabel, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="location_code" id="pcLocationCode" value="<?= htmlspecialchars($oldLocationCode, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="incident_kind" id="incidentKind" value="<?= pc_old('incident_kind', 'other') ?>">
      <input type="hidden" name="itilcategories_id" id="pcCategoryId" value="<?= (int)pc_post('itilcategories_id', 0) ?>">
      <input type="hidden" name="category_label" id="pcCategoryLabel" value="<?= pc_old('category_label') ?>">

      <div class="pc-form-sections">

        <!-- PASO 1: Categoría de la Incidencia -->
        <section class="pc-section-card" id="pcSection1">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">1</span>
              <h2 class="pc-section-title">Categoría de la Incidencia <span class="req">*</span></h2>
            </div>
            <span id="pcCatStatusBadge" class="pc-loc-badge <?= $oldCategoryId > 0 ? 'selected' : 'ready' ?>">
              <?= $oldCategoryId > 0 ? '✓ Categoría seleccionada' : '📍 Elige bloque y problema' ?>
            </span>
          </div>

          <div id="pcCategoryBlock">
            <div class="pc-current-cat <?= pc_post('category_label') !== '' ? 'is-selected' : '' ?>" id="pcCurrentCat">
              <?= pc_post('category_label') !== '' ? htmlspecialchars(pc_post('category_label'), ENT_QUOTES, 'UTF-8') : 'Selecciona una categoría' ?>
              <span><?= pc_post('category_label') !== '' ? 'Categoría conservada.' : 'El formulario se adapta automáticamente.' ?></span>
            </div>

            <div class="pc-category-layout">
              <div>
                <div class="pc-label-mini">1A · Bloque principal</div>
                <div class="pc-parent-list" id="pcParentCategories">
                  <?php foreach ($itilCategories as $i => $cat):
                    $pid = (int)($cat['id'] ?? 0);
                    $pname = (string)($cat['name'] ?? 'Categoría');
                    $pcomplete = (string)($cat['complete'] ?? $pname);
                  ?>
                    <button type="button" class="pc-parent-btn" data-parent-id="<?= $pid ?>" data-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-complete="<?= htmlspecialchars($pcomplete, ENT_QUOTES, 'UTF-8') ?>" data-asset-types="<?= htmlspecialchars(pc_asset_types_attr($pcomplete . ' ' . $pname), ENT_QUOTES, 'UTF-8') ?>">
                      <span class="pc-parent-ico pc-svgicon <?= htmlspecialchars(pc_category_icon($pname), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                      <span><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="pc-child-box" id="pcChildBox">
                <div class="pc-child-head">
                  <div class="pc-label-mini" style="margin-bottom:0">1B · Problema concreto</div>
                  <div class="pc-category-tools">
                    <input class="pc-cat-search" id="pcCategorySearch" type="search" placeholder="Buscar problema...">
                    <button type="button" class="pc-cat-clear" id="pcCategoryClear">Limpiar</button>
                  </div>
                </div>
                <div class="pc-empty-subcat" id="pcEmptySubcat">Elige un bloque de la izquierda para ver sus problemas.</div>
                <?php foreach ($itilCategories as $cat):
                  $pid = (int)($cat['id'] ?? 0);
                  $pname = (string)($cat['name'] ?? 'Categoría');
                  $pcomplete = (string)($cat['complete'] ?? $pname);
                  $children = $cat['children'] ?? [];
                ?>
                  <div class="pc-subcat-list" data-parent="<?= $pid ?>" style="display:none">
                    <?php if (!$children): ?>
                      <button type="button" class="pc-subcat" data-id="<?= $pid ?>" data-parent-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-complete="<?= htmlspecialchars($pcomplete, ENT_QUOTES, 'UTF-8') ?>" data-asset-types="<?= htmlspecialchars(pc_asset_types_attr($pcomplete . ' ' . $pname), ENT_QUOTES, 'UTF-8') ?>">
                        <b><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></b>
                        <small><?= htmlspecialchars($pcomplete, ENT_QUOTES, 'UTF-8') ?></small>
                      </button>
                    <?php endif; ?>
                    <?php foreach ($children as $child):
                      $cid = (int)($child['id'] ?? 0);
                      $cname = (string)($child['name'] ?? 'Subcategoría');
                      $ccomplete = (string)($child['complete'] ?? ($pname . ' > ' . $cname));
                    ?>
                      <button type="button" class="pc-subcat" data-id="<?= $cid ?>" data-parent-name="<?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?>" data-complete="<?= htmlspecialchars($ccomplete, ENT_QUOTES, 'UTF-8') ?>" data-asset-types="<?= htmlspecialchars(pc_asset_types_attr($ccomplete . ' ' . $cname), ENT_QUOTES, 'UTF-8') ?>">
                        <b><?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?></b>
                        <small><?= htmlspecialchars(str_replace(' > ', ' › ', $ccomplete), ENT_QUOTES, 'UTF-8') ?></small>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </section>

        <!-- PASO 2: Ubicación en el Centro y Activo Afectado (Mismo estilo que Nuevo Activo) -->
        <section class="pc-section-card" id="pcSection2">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">2</span>
              <h2 class="pc-section-title">Ubicación en el Centro Educativo y Equipo Afectado <span class="req">*</span></h2>
            </div>
            <span id="pcLocStatusBadge" class="pc-loc-badge <?= $oldLocationId > 0 ? 'selected' : 'ready' ?>">
              <?= $oldLocationId > 0 ? '✓ Aula asignada' : '📍 Selector activo' ?>
            </span>
          </div>

          <div class="pc-location-field">
            <div class="pc-location-interactive-card <?= $oldLocationId > 0 ? 'is-selected' : '' ?>" id="pcSelectedLocationCard">
              <div class="pc-loc-meta-box" id="pcSelectedLocation">
                <div class="pc-loc-icon-bubble">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                </div>
                <div class="pc-loc-info-text">
                  <h4 class="pc-loc-name" id="pcLocTitle">
                    <?= $oldLocationLabel !== '' ? htmlspecialchars($oldLocationLabel, ENT_QUOTES, 'UTF-8') : 'Selecciona el aula o espacio del centro' ?>
                  </h4>
                  <p class="pc-loc-meta" id="pcLocMeta">
                    <?= $oldLocationCode !== '' ? htmlspecialchars($oldLocationCode, ENT_QUOTES, 'UTF-8') : ($oldLocationId > 0 ? ('ID GLPI #' . (int)$oldLocationId) : 'Elige directamente del desplegable rápido o abre el plano interactivo') ?>
                  </p>
                </div>
              </div>

              <div class="pc-loc-controls">
                <select class="pc-loc-quick-select" id="pcQuickLocationSelect" aria-label="Selección rápida de aula">
                  <option value="0">— Selección rápida de aula —</option>
                  <?php foreach ($roomsByBuilding as $bCode => $bRooms): ?>
                    <optgroup label="<?= htmlspecialchars((string)($bLabelsForSelect[$bCode] ?? ('Edificio ' . $bCode)), ENT_QUOTES, 'UTF-8') ?>">
                      <?php foreach ($bRooms as $rItem):
                        $rId = (int)($rItem['id'] ?? 0);
                        $rText = trim(($rItem['aula'] ?? $rItem['codigo'] ?? '') . (!empty($rItem['descripcion']) ? ' · ' . $rItem['descripcion'] : '') . ' (' . ($rItem['codigo'] ?? '') . ')');
                      ?>
                        <option value="<?= $rId ?>" <?= $oldLocationId === $rId ? 'selected' : '' ?>><?= htmlspecialchars($rText, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>

                <button type="button" class="pc-btn-action pc-btn-primary" id="pcOpenSelector">
                  <?php if ($plansActive): ?>
                    <svg viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <span id="pcBtnLocText"><?= $oldLocationId > 0 ? 'Ver en plano / Cambiar' : 'Abrir plano y buscador' ?></span>
                  <?php else: ?>
                    <svg viewBox="0 0 24 24"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <span id="pcBtnLocText"><?= $oldLocationId > 0 ? 'Cambiar aula' : 'Seleccionar aula en lista' ?></span>
                  <?php endif; ?>
                </button>
              </div>
            </div>
          </div>

          <div class="pc-fields" style="margin-top: 16px;">
            <div class="pc-field full pc-asset-field">
              <span class="pc-label">Activo afectado del aula (conectado a inventario GLPI)</span>
              <div class="pc-asset-picker <?= $oldAffectedAsset === '__custom__' ? 'custom' : '' ?>">
                <select class="pc-select" name="affected_asset" id="affectedAsset">
                  <option value="__none__" data-type="" <?= $oldAffectedAsset === '__none__' ? 'selected' : '' ?>>No especificar activo concreto</option>
                  <?php foreach ($assetOptions as $asset):
                    $val = ($asset['type'] ?? '') . ':' . (int)($asset['id'] ?? 0);
                    $display = trim((string)($asset['display_name'] ?? $asset['name'] ?? ''));
                    $txt = trim(($display !== '' ? $display : ($asset['name'] ?? 'Activo')) . ' · ID ' . (int)($asset['id'] ?? 0));
                    if (!empty($asset['serial'])) { $txt .= ' · S/N ' . $asset['serial']; }
                  ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($asset['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= $oldAffectedAsset === $val ? 'selected' : '' ?>><?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                  <option value="__custom__" data-type="custom" <?= $oldAffectedAsset === '__custom__' ? 'selected' : '' ?>>Otro activo no listado / personalizado</option>
                </select>
                <input class="pc-input" name="affected_asset_custom" id="affectedAssetCustom" value="<?= pc_old('affected_asset_custom') ?>" placeholder="Ej: portátil del profesor, cable HDMI, ratón, monitor sin etiqueta...">
              </div>
              <small class="pc-help" id="assetSmartHelp">
                <?php if ($oldLocationId > 0): ?>
                  Activos cargados desde GLPI para esta ubicación. La lista se filtrará según la categoría seleccionada.
                <?php else: ?>
                  Al elegir el aula arriba se cargarán automáticamente aquí todos sus equipos registrados en GLPI.
                <?php endif; ?>
              </small>
            </div>
          </div>
        </section>

        <!-- PASO 3: Detalles y Descripción del Problema -->
        <section class="pc-section-card" id="pcSection3">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">3</span>
              <h2 class="pc-section-title">Detalles y Descripción del Problema <span class="req">*</span></h2>
            </div>
          </div>

          <div class="pc-hint" id="kindHint">Selecciona una categoría para adaptar los campos técnicos del problema.</div>

          <div class="pc-fields">
            <label class="pc-field full">
              <span class="pc-label">Título de la incidencia <span class="req">*</span></span>
              <input class="pc-input" name="name" id="ticketTitle" value="<?= pc_old('name') ?>" placeholder="Ej: No funciona el proyector del aula 206" required>
            </label>

            <label class="pc-field pc-conditional" data-show-for="computer software">
              <span class="pc-label">Número o identificador del ordenador <span class="req">*</span></span>
              <input class="pc-input" name="computer_number" id="computerNumber" value="<?= pc_old('computer_number') ?>" placeholder="Ej: PC-03, Puesto 08, Equipo Profesor...">
            </label>

            <label class="pc-field pc-conditional" data-show-for="software">
              <span class="pc-label">Programa o software afectado <span class="req">*</span></span>
              <input class="pc-input" name="software_name" id="softwareName" value="<?= pc_old('software_name') ?>" placeholder="Ej: Chrome, LibreOffice, Windows, AutoCAD...">
            </label>

            <label class="pc-field">
              <span class="pc-label">Detalle técnico rápido</span>
              <select class="pc-select" name="problem_subtype" id="problemSubtype" data-old-value="<?= pc_old('problem_subtype', 'Otro') ?>">
                <option><?= pc_old('problem_subtype', 'Otro') ?></option>
              </select>
            </label>

            <div class="pc-field full">
              <label class="pc-label" for="ticketContent">Descripción detallada del problema <span class="req">*</span></label>
              <textarea class="pc-textarea" name="content" id="ticketContent" placeholder="Explica qué ocurre, desde cuándo, si muestra algún mensaje en pantalla o qué pruebas has realizado..." required><?= pc_old('content') ?></textarea>
              <div class="pc-quick-chips">
                <span class="pc-quick-chip-label">Añadir síntoma rápido:</span>
                <button type="button" class="pc-quick-chip" data-symptom="El equipo no enciende ni da señal luminosa.">+ No enciende</button>
                <button type="button" class="pc-quick-chip" data-symptom="No muestra imagen en pantalla / proyector.">+ Sin imagen</button>
                <button type="button" class="pc-quick-chip" data-symptom="No tiene conexión a Internet / red del centro.">+ Sin Internet</button>
                <button type="button" class="pc-quick-chip" data-symptom="Muestra error al intentar iniciar sesión o abrir la aplicación.">+ Error al abrir</button>
                <button type="button" class="pc-quick-chip" data-symptom="Falla el cableado o periférico conectado (teclado/ratón/HDMI).">+ Falla cable / periférico</button>
              </div>
            </div>
          </div>
        </section>

        <!-- PASO 4: Prioridad, Urgencia e Impacto -->
        <section class="pc-section-card" id="pcSection4">
          <div class="pc-section-header">
            <div class="pc-section-header-left">
              <span class="pc-section-step">4</span>
              <h2 class="pc-section-title">Prioridad, Urgencia e Impacto</h2>
            </div>
            <span class="pc-loc-badge selected">✓ Ajuste inteligente</span>
          </div>

          <div class="pc-fields-3 pc-normal-fields">
            <?php foreach (['urgency' => 'Urgencia', 'impact' => 'Impacto', 'priority' => 'Prioridad'] as $name => $label): ?>
              <label class="pc-field">
                <span class="pc-label"><?= $label ?> <span class="req">*</span></span>
                <select class="pc-select" name="<?= $name ?>">
                  <option value="1"<?= pc_selected($name, 1) ?>>Muy baja</option>
                  <option value="2"<?= pc_selected($name, 2) ?>>Baja</option>
                  <option value="3"<?= pc_selected($name, 3, true) ?>>Media</option>
                  <option value="4"<?= pc_selected($name, 4) ?>>Alta</option>
                  <option value="5"<?= pc_selected($name, 5) ?>>Muy alta</option>
                </select>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="pc-priority-ai" id="priorityAI">Prioridad inteligente: selecciona una categoría y ajustaremos una recomendación editable.</div>
        </section>

      </div>

      <!-- Footer Action Bar (Matches new_asset.php) -->
      <footer class="pc-action-bar">
        <a class="pc-btn-action pc-btn-secondary pc-btn-cancel" href="<?= htmlspecialchars($root . '/plugins/schoolmanager/front/dashboard.php?v=' . urlencode(PLUGIN_SCHOOLMANAGER_VERSION), ENT_QUOTES, 'UTF-8') ?>">
          <svg viewBox="0 0 24 24"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
          <span>Cancelar</span>
        </a>
        <button class="pc-btn-action pc-btn-primary pc-btn-create" type="submit">
          <svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Crear incidencia</span>
        </button>
      </footer>
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
  const assetsEndpoint='<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/classroom_assets.php';
  let currentAssetTypes=[];

  // Smooth scroll on step tracker click
  document.querySelectorAll('.pc-step-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      const target = document.querySelector(pill.dataset.scrollTarget || '');
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  // Quick Symptom Chips
  document.querySelectorAll('.pc-quick-chip[data-symptom]').forEach(chip => {
    chip.addEventListener('click', () => {
      const contentEl = document.getElementById('ticketContent');
      if (!contentEl) return;
      const symptom = chip.dataset.symptom || '';
      const cur = contentEl.value.trim();
      if (!cur) {
        contentEl.value = symptom;
      } else if (!cur.includes(symptom)) {
        contentEl.value = cur + '\n- ' + symptom;
      }
      contentEl.focus();
      updateState();
    });
  });

  function updateAssetCustom(){const wrap=affectedAsset?affectedAsset.closest('.pc-asset-picker'):null; if(wrap)wrap.classList.toggle('custom', affectedAsset && affectedAsset.value==='__custom__');}
  function assetTypesFromText(t){t=norm(t); if(/monitor/.test(t))return['Monitor']; if(/ordenador|equipo|\bpc\b|portatil|chromebook/.test(t))return['Computer']; if(/impresora|impresion|toner|tinta|escaner/.test(t))return['Printer']; if(/red|internet|wifi|conexion|router|switch|punto de red/.test(t))return['NetworkEquipment']; if(/proyector/.test(t))return['Projector']; if(/teclado|raton|periferico|hdmi|cable|adaptador/.test(t))return['Peripheral']; if(/telefono/.test(t))return['Phone']; return[];}
  function setAssetFilter(types,label){if(!affectedAsset)return; types=Array.isArray(types)?types.filter(Boolean):[]; currentAssetTypes=types; let visible=0; Array.from(affectedAsset.options).forEach(o=>{const val=o.value, typ=o.dataset.type||''; const special=val==='__none__'||val==='__custom__'; const show=special||!types.length||types.includes(typ); o.hidden=!show; o.disabled=!show; if(show&&!special)visible++;}); const cur=affectedAsset.selectedOptions[0]; if(cur && cur.disabled) affectedAsset.value='__none__'; affectedAsset.dispatchEvent(new Event('change')); const help=document.getElementById('assetSmartHelp'); if(help){help.textContent=types.length ? ('Filtro inteligente: mostrando '+(label||'activos relacionados')+'. Encontrados: '+visible+'. Si no aparece, usa activo personalizado.') : 'Activos cargados desde GLPI para esta ubicación. Si falta alguno, usa la opción personalizada.';} updateAssetCustom();}
  function assetOptionText(a){let name=(a.display_name||a.name||('ID '+a.id)); let txt=name+' · ID '+a.id; if(a.serial)txt+=' · S/N '+a.serial; return txt;}
  function rebuildAssetOptions(assets){if(!affectedAsset)return; const old=affectedAsset.value; affectedAsset.innerHTML=''; const none=document.createElement('option'); none.value='__none__'; none.dataset.type=''; none.textContent='No especificar activo concreto'; affectedAsset.appendChild(none); (assets||[]).forEach(a=>{const o=document.createElement('option'); o.value=(a.type||'')+':'+parseInt(a.id||0,10); o.dataset.type=a.type||''; o.textContent=assetOptionText(a); affectedAsset.appendChild(o);}); const custom=document.createElement('option'); custom.value='__custom__'; custom.dataset.type='custom'; custom.textContent='Otro activo no listado / personalizado'; affectedAsset.appendChild(custom); if(Array.from(affectedAsset.options).some(o=>o.value===old)) affectedAsset.value=old; setAssetFilter(currentAssetTypes,'activos relacionados');}
  function loadAssetsForLocation(id){id=parseInt(id||0,10); if(!id||!affectedAsset)return; const help=document.getElementById('assetSmartHelp'); if(help)help.textContent='Cargando activos del aula desde GLPI...'; fetch(assetsEndpoint+'?location_id='+encodeURIComponent(id),{credentials:'same-origin'}).then(r=>r.json()).then(j=>{if(j&&j.ok)rebuildAssetOptions(j.assets||[]); else if(help)help.textContent='No se pudieron cargar activos. Usa activo personalizado.';}).catch(()=>{if(help)help.textContent='No se pudieron cargar activos. Usa activo personalizado.';});}
  const subtypeOptions={monitor:['No enciende','No da imagen','Cableado','Relacionado con ordenador conectado','Otro'],computer:['No enciende','No inicia Windows','Va lento','No tiene Internet','Teclado / ratón / pantalla','Otro'],software:['No abre','Da error','No está instalado','Licencia caducada','Actualización necesaria','Otro'],network:['Sin Internet','WiFi no funciona','Cable de red','Conexión lenta','Otro'],projector:['No proyecta','Sin imagen','Sin sonido','Mando / cable','Otro'],printer:['No imprime','Atasco de papel','Sin tóner/tinta','No aparece en el equipo','Otro'],whiteboard:['No enciende','No responde táctil','Problema de imagen','Cableado','Otro'],audio:['No hay sonido','Micrófono','Altavoces','Cableado','Otro'],account:['No puede iniciar sesión','Contraseña','Permisos','Correo','Otro'],request:['Solicitud de soporte','Instalación','Cambio de aula','Préstamo de equipo','Otro'],maintenance:['Revisión preventiva','Actualización','Limpieza','Backup','Otro'],other:['Incidencia general','Revisión técnica','Otro']};
  const hints={monitor:'Selecciona el monitor afectado. Si GLPI tiene informado el nombre de usuario alternativo, se mostrará el PC conectado. Si no aparece, usa activo personalizado.',computer:'Indica el número del ordenador/equipo para localizarlo rápido.',software:'Indica número de equipo y programa afectado.',network:'Indica si afecta a aula completa, WiFi, cable, switch, router o Internet.',projector:'Indica si falla imagen, sonido, mando, cable o pantalla.',printer:'Indica si no imprime, hay atasco o falta consumible.',whiteboard:'Indica si falla táctil, imagen, encendido o cableado.',audio:'Indica si falla sonido, micrófono, altavoces o cableado.',account:'Indica usuario afectado y servicio si procede.',request:'Indica exactamente qué se solicita.',maintenance:'Indica qué revisión preventiva hay que realizar.',other:'Describe el problema con el máximo detalle posible.'};
  function norm(s){return String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
  function kFrom(t){t=norm(t); if(/proyector|pantalla|hdmi|imagen/.test(t))return'projector'; if(/impresora|impresion|toner|tinta/.test(t))return'printer'; if(/pizarra|tactil/.test(t))return'whiteboard'; if(/audio|sonido|altavoz|microfono/.test(t))return'audio'; if(/monitor/.test(t))return'monitor'; if(/ordenador|equipo|pc|portatil|chromebook/.test(t))return'computer'; if(/teclado|raton|escaner|periferico/.test(t))return'other'; if(/software|program|aplicacion|licencia|office|chrome|moodle|navegador|antivirus/.test(t))return'software'; if(/red|internet|wifi|conexion|router|switch|servidor|dns|dhcp/.test(t))return'network'; if(/usuario|contrasena|cuenta|correo|login|permiso|microsoft/.test(t))return'account'; if(/solicitud|prestamo|alta de equipo|cambio de aula|reserva|compra|sustitucion/.test(t))return'request'; if(/mantenimiento|revision|actualizacion|limpieza|backup/.test(t))return'maintenance'; return'other';}
  function setSubtype(k){const old=subtype.dataset.oldValue||''; subtype.innerHTML=''; (subtypeOptions[k]||subtypeOptions.other).forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;if(old===v)o.selected=true;subtype.appendChild(o);}); subtype.dispatchEvent(new Event('change'));}
  let lastAppliedKind='';
  function hasSpecificAsset(){return affectedAsset && affectedAsset.value && affectedAsset.value!=='__none__';}
  function applyKind(k){kind.value=k||'other'; document.querySelectorAll('.pc-conditional').forEach(el=>{const needsComputer=(el.querySelector('#computerNumber')!==null); const show=(el.dataset.showFor||'').split(/\s+/).includes(kind.value) && !(needsComputer && hasSpecificAsset()); el.classList.toggle('show',show);}); hint.textContent=(hasSpecificAsset() && (kind.value==='computer'||kind.value==='software'))?'Activo seleccionado: no hace falta indicar número de ordenador.':(hints[kind.value]||hints.other); if(lastAppliedKind!==kind.value){setSubtype(kind.value); lastAppliedKind=kind.value;}}
  function setPriority(label,k){let v=3,txt='Media'; const t=norm(label); if(k==='network'||/internet|wifi|switch|router|servidor|dns|dhcp/.test(t)){v=4;txt='Alta'} if(/servidor|dns|dhcp|internet/.test(t)){v=5;txt='Muy alta'} if(k==='request'||k==='maintenance'){v=2;txt='Baja'} ['urgency','impact','priority'].forEach(n=>{const e=form.querySelector('[name="'+n+'"]'); if(e){e.value=String(v); e.dispatchEvent(new Event('change'));}}); document.getElementById('priorityAI').textContent='Prioridad inteligente sugerida: '+txt+'. Puedes cambiarla antes de enviar.';}
  function showParent(id,btn){document.querySelectorAll('.pc-parent-btn').forEach(b=>b.classList.remove('active')); if(btn)btn.classList.add('active'); document.querySelectorAll('.pc-subcat-list').forEach(l=>{l.classList.remove('active');l.style.display='none';}); const empty=document.getElementById('pcEmptySubcat'); const list=document.querySelector('.pc-subcat-list[data-parent="'+id+'"]'); if(list){list.classList.add('active');list.style.display='grid'; if(empty){empty.classList.add('hide');empty.classList.remove('is-visible');empty.style.display='none';}} else if(empty){empty.classList.remove('hide');empty.classList.add('is-visible');empty.style.display='grid';} current.classList.remove('is-selected'); current.innerHTML=btn.dataset.name+'<span>Ahora elige un problema concreto a la derecha.</span>'; catId.value=''; catLabel.value=''; const search=document.getElementById('pcCategorySearch'); if(search)search.value=''; document.querySelectorAll('.pc-subcat').forEach(x=>{x.hidden=false;x.style.display='';}); applyKind(kFrom(btn.dataset.complete||btn.dataset.name)); setAssetFilter((btn.dataset.assetTypes||'').split(',').filter(Boolean), btn.dataset.name||''); updateState();}
  document.querySelectorAll('.pc-parent-btn').forEach(btn=>btn.addEventListener('click',()=>showParent(btn.dataset.parentId,btn)));
  document.querySelectorAll('.pc-subcat').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.pc-subcat').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); const parentList = btn.closest('.pc-subcat-list'); if(parentList && parentList.dataset.parent){ const pBtn = document.querySelector('.pc-parent-btn[data-parent-id="'+parentList.dataset.parent+'"]'); if(pBtn){ document.querySelectorAll('.pc-parent-btn').forEach(b=>b.classList.remove('active')); pBtn.classList.add('active'); } } catId.value=btn.dataset.id||''; catLabel.value=btn.dataset.complete||btn.dataset.name||''; current.classList.add('is-selected'); current.innerHTML='✓ '+(btn.dataset.parentName||'Categoría')+' → '+(btn.dataset.name||'Subcategoría')+'<span>Categoría GLPI seleccionada · ID '+(btn.dataset.id||'')+'</span>'; const k=kFrom((btn.dataset.complete||'')+' '+(btn.dataset.name||'')); applyKind(k); setAssetFilter((btn.dataset.assetTypes||'').split(',').filter(Boolean).length ? (btn.dataset.assetTypes||'').split(',').filter(Boolean) : assetTypesFromText((btn.dataset.complete||'')+' '+(btn.dataset.name||'')), btn.dataset.name||''); setPriority(btn.dataset.complete||btn.dataset.name,k); if(!document.getElementById('ticketTitle').value.trim()) document.getElementById('ticketTitle').value='Incidencia - '+(btn.dataset.name||'soporte'); updateState();}));
  function mark(el,bad){const w=el?(el.closest('.pc-field')||el.closest('.pc-location-field')):null; if(w)w.classList.toggle('pc-required-missing',!!bad)}
  function updateState(){
    const hasCat = !!catId.value;
    const hasLoc = Number(document.getElementById('pcLocationId').value) > 0;
    const titleVal = (document.getElementById('ticketTitle')?.value || '').trim();
    const contentVal = (document.getElementById('ticketContent')?.value || '').trim();
    const hasDesc = !!(titleVal && contentVal);

    document.getElementById('pcCategoryBlock').classList.toggle('pc-is-complete', hasCat);
    const catBadge = document.getElementById('pcCatStatusBadge');
    if (catBadge) {
      catBadge.className = 'pc-loc-badge ' + (hasCat ? 'selected' : 'ready');
      catBadge.textContent = hasCat ? '✓ Categoría seleccionada' : '📍 Elige bloque y problema';
    }

    const locField = document.querySelector('.pc-location-field');
    if (locField) locField.classList.toggle('pc-is-complete', hasLoc);
    const af = document.querySelector('.pc-asset-field');
    if (af && affectedAsset) af.classList.toggle('pc-is-complete', hasSpecificAsset());

    // Update top step tracker pills
    const p1 = document.getElementById('pcStepPill1');
    const p2 = document.getElementById('pcStepPill2');
    const p3 = document.getElementById('pcStepPill3');
    if (p1) p1.classList.toggle('is-done', hasCat);
    if (p2) p2.classList.toggle('is-done', hasLoc);
    if (p3) p3.classList.toggle('is-done', hasDesc);

    const sub1 = document.getElementById('pcStepSub1');
    const sub2 = document.getElementById('pcStepSub2');
    const sub3 = document.getElementById('pcStepSub3');
    if (sub1) sub1.textContent = hasCat ? '✓ Seleccionada' : 'Tipo de problema';
    if (sub2) sub2.textContent = hasLoc ? '✓ Aula asignada' : 'Aula y equipo';
    if (sub3) sub3.textContent = hasDesc ? '✓ Completado' : 'Título y detalles';

    updateAssetCustom();
    applyKind(kind.value||'other');
  }
  function validate(){document.querySelectorAll('.pc-required-missing').forEach(e=>e.classList.remove('pc-required-missing')); document.getElementById('pcChildBox').classList.remove('pc-category-missing'); document.getElementById('pcSelectedLocationCard')?.classList.remove('pc-required-missing'); validationPanel.classList.remove('show'); const errs=[]; if(!catId.value){errs.push('Paso 1: Elige un problema concreto en Categoría.'); document.getElementById('pcChildBox').classList.add('pc-category-missing')} if(Number(document.getElementById('pcLocationId').value)<=0){errs.push('Paso 2: Elige un aula o ubicación del centro.'); document.getElementById('pcSelectedLocationCard')?.classList.add('pc-required-missing')} const title=document.getElementById('ticketTitle'), content=document.getElementById('ticketContent'); if(!title.value.trim()){errs.push('Paso 3: Escribe un título para la incidencia.'); mark(title,true)} if(!content.value.trim()){errs.push('Paso 3: Describe el problema.'); mark(content,true)} if((kind.value==='computer'||kind.value==='software')&&!hasSpecificAsset()&&!document.getElementById('computerNumber').value.trim()){errs.push('Paso 3: Indica el número del ordenador/equipo o selecciona un activo del aula.'); mark(document.getElementById('computerNumber'),true)} if(kind.value==='software'&&!document.getElementById('softwareName').value.trim()){errs.push('Paso 3: Indica el software afectado.'); mark(document.getElementById('softwareName'),true)} if(errs.length){validationList.innerHTML=errs.map(e=>'<li>'+e+'</li>').join(''); validationPanel.classList.add('show'); validationPanel.scrollIntoView({behavior:'smooth',block:'center'}); return false} return true}
  const catSearch=document.getElementById('pcCategorySearch'), catClear=document.getElementById('pcCategoryClear');
  function normalizeCat(t){return String(t||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
  function filterCats(){
    const q=normalizeCat(catSearch&&catSearch.value).trim();
    const lists=document.querySelectorAll('.pc-subcat-list');
    const empty=document.getElementById('pcEmptySubcat');
    if (q) {
      let totalMatches=0;
      lists.forEach(l=>{
        let listMatches=0;
        l.querySelectorAll('.pc-subcat').forEach(b=>{
          const hay=normalizeCat(b.textContent+' '+(b.dataset.parentName||'')+' '+(b.dataset.complete||''));
          const match=hay.includes(q);
          b.hidden=!match;
          b.style.display=match?'':'none';
          if(match){listMatches++; totalMatches++;}
        });
        if(listMatches>0){
          l.classList.add('active');
          l.style.display='grid';
        } else {
          l.classList.remove('active');
          l.style.display='none';
        }
      });
      if(empty){
        if(totalMatches===0){
          empty.textContent='No se encontraron categorías para "'+(catSearch.value||'')+'".';
          empty.classList.remove('hide');
          empty.classList.add('is-visible');
          empty.style.display='grid';
        } else {
          empty.classList.add('hide');
          empty.classList.remove('is-visible');
          empty.style.display='none';
        }
      }
    } else {
      lists.forEach(l=>{
        l.querySelectorAll('.pc-subcat').forEach(b=>{b.hidden=false; b.style.display='';});
      });
      const activeParent=document.querySelector('.pc-parent-btn.active')||document.querySelector('.pc-parent-btn');
      if(activeParent){
        const pid=activeParent.dataset.parentId;
        lists.forEach(l=>{
          const isAct=(l.dataset.parent===pid);
          l.classList.toggle('active',isAct);
          l.style.display=isAct?'grid':'none';
        });
      }
      if(empty){
        empty.textContent='Elige un bloque de la izquierda para ver sus problemas.';
        empty.classList.add('hide');
        empty.classList.remove('is-visible');
        empty.style.display='none';
      }
    }
  }
  if(catSearch)catSearch.addEventListener('input',filterCats); if(catClear)catClear.addEventListener('click',()=>{if(catSearch)catSearch.value='';filterCats();catSearch&&catSearch.focus();});
  form.addEventListener('submit',e=>{if(!validate()){e.preventDefault();return} const b=form.querySelector('button[type=submit]'); if(b){b.disabled=true;b.innerHTML='<span>Creando incidencia...</span>'}});
  document.addEventListener('input',updateState); document.addEventListener('change',updateState); if(affectedAsset)affectedAsset.addEventListener('change',updateAssetCustom);
  window.addEventListener('schoolmanager:location-selected',e=>{
    const r = (e && e.detail) || {};
    const card = document.getElementById('pcSelectedLocationCard');
    const badge = document.getElementById('pcLocStatusBadge');
    const title = document.getElementById('pcLocTitle');
    const meta = document.getElementById('pcLocMeta');
    const btnText = document.getElementById('pcBtnLocText');
    if (card) {
      card.classList.add('is-selected');
      card.classList.remove('pc-required-missing');
    }
    if (badge) {
      badge.className = 'pc-loc-badge selected';
      badge.textContent = '✓ Aula asignada';
    }
    if (title && (r.aula || r.codigo)) {
      title.textContent = (r.aula || r.codigo || 'Aula') + (r.descripcion ? ' · ' + r.descripcion : '');
    }
    if (meta && (r.building || r.codigo || r.planta || r.floor)) {
      meta.textContent = (r.building ? 'Edificio ' + r.building : '') + (r.planta || r.floor ? ' · ' + (r.planta || r.floor) : '') + (r.codigo ? ' · ' + r.codigo : '');
    }
    if (btnText) {
      btnText.textContent = <?= json_encode($plansActive ? 'Ver en plano / Cambiar' : 'Cambiar aula') ?>;
    }
    updateState();
    const id=(e.detail&&e.detail.id)||document.getElementById('pcLocationId').value;
    loadAssetsForLocation(id);
  });
  const old=String(document.getElementById('pcCategoryId').value||''); if(old && old !== '0'){const c=document.querySelector('.pc-subcat[data-id="'+old.replace(/"/g,'')+'"]'); if(c){const p=document.querySelector('.pc-parent-btn[data-parent-id="'+c.closest('.pc-subcat-list').dataset.parent+'"]'); if(p)showParent(p.dataset.parentId,p); c.click();}} else {const first=document.querySelector('.pc-parent-btn'); if(first)showParent(first.dataset.parentId,first)}
  if(catLabel.value){setAssetFilter(assetTypesFromText(catLabel.value), catLabel.value);}
  updateState();
})();
</script>

<?php sm_page_footer(); ?>

