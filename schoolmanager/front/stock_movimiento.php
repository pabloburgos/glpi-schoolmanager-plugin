<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stock_helpers.php');

$root = $CFG_GLPI['root_doc'] ?? '';

// Esta pagina es solo de acciones. Si se abre a mano por GET, volvemos al panel y evitamos pantalla roja de GLPI.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    Html::redirect($root . '/plugins/schoolmanager/front/stock_glpi.php?v=282');
}

$canAssets = function_exists('plugin_schoolmanager_can_create_asset') && plugin_schoolmanager_can_create_asset(null);
$canStock = function_exists('plugin_schoolmanager_can_manage_stock') ? plugin_schoolmanager_can_manage_stock() : $canAssets;
if (!$canStock) {
    Html::redirect($root . '/plugins/schoolmanager/front/error.php?title=' . rawurlencode('Acceso restringido') . '&v=282');
}

$kind = ($_POST['kind'] ?? 'consumable') === 'cartridge' ? 'cartridge' : 'consumable';
$item_id = (int)($_POST['item_id'] ?? 0);
$qty = max(1, min(200, (int)($_POST['qty'] ?? 1)));
$action = (string)($_POST['action'] ?? ($_POST['_action'] ?? ''));
$return = (string)($_POST['return'] ?? 'list');
$msg = '';
$err = '';

if ($action === 'crear_categoria') {
    $name = trim((string)($_POST['category_name'] ?? ''));
    [$ok, $text, $newId] = smgr_stock_create_type($kind, $name);
    $ok ? $msg = $text : $err = $text;
} elseif ($action === 'crear_articulo') {
    $name = trim((string)($_POST['item_name'] ?? ''));
    $typeId = (int)($_POST['type_id'] ?? 0);
    $newCategory = trim((string)($_POST['category_new'] ?? ''));
    if ($newCategory !== '') {
        [$catOk, $catText, $catId] = smgr_stock_create_type($kind, $newCategory);
        if ($catOk && (int)$catId > 0) {
            $typeId = (int)$catId;
        } else {
            $err = $catText !== '' ? $catText : 'No se pudo crear la categoria nueva.';
        }
    }
    $ref = trim((string)($_POST['ref'] ?? ''));
    $threshold = (int)($_POST['threshold'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    $initialQty = max(0, min(200, (int)($_POST['initial_qty'] ?? 0)));
    if ($err === '') {
        [$ok, $text, $newId] = smgr_stock_create_item($kind, $name, $typeId, $ref, $threshold, $comment);
    } else {
        $ok = false; $text = $err; $newId = 0;
    }
    if ($ok && $newId > 0 && $initialQty > 0) {
        $done = smgr_stock_add_units($kind, $newId, $initialQty, 'Alta inicial desde Gestion School Manager');
        $text .= ' · stock inicial: ' . $done . ' unidad(es)';
    }
    if ($ok) { $msg = $text; $item_id = (int)$newId; $return = 'detail'; } else { $err = $text; }
} elseif ($item_id <= 0) {
    $err = 'Articulo no valido.';
} elseif ($action === 'entrada') {
    $note = trim((string)($_POST['note'] ?? ''));
    if ($note === '') { $note = 'Entrada desde Gestion School Manager'; }
    $done = smgr_stock_add_units($kind, $item_id, $qty, $note);
    $done > 0 ? $msg = 'Entrada registrada: ' . $done . ' unidad(es).' : $err = 'No se pudo registrar la entrada en GLPI.';
} elseif ($action === 'salida') {
    $note = trim((string)($_POST['note'] ?? ''));
    if ($note === '') { $note = 'Salida desde Gestion School Manager'; }
    $done = smgr_stock_remove_units($kind, $item_id, $qty, $note);
    $done > 0 ? $msg = 'Salida registrada: ' . $done . ' unidad(es).' : $err = 'No hay unidades disponibles o esta instalación de GLPI no ha permitido marcar la salida.';
} elseif ($action === 'ajustar') {
    $target = max(0, min(999, (int)($_POST['target_qty'] ?? 0)));
    $note = trim((string)($_POST['note'] ?? ''));
    if ($note === '') { $note = 'Ajuste desde Gestion School Manager'; }
    [$ok, $text] = smgr_stock_adjust_units($kind, $item_id, $target, $note);
    $ok ? $msg = $text : $err = $text;
} elseif ($action === 'actualizar_articulo') {
    [$ok, $text] = smgr_stock_update_item($kind, $item_id, $_POST);
    $ok ? $msg = $text : $err = $text;
} elseif ($action === 'archivar') {
    [$ok, $text] = smgr_stock_archive_item($kind, $item_id);
    $ok ? $msg = $text : $err = $text;
    if ($ok) { $return = 'list'; }
} else {
    $err = 'Accion no valida.';
}

$params = ['kind' => $kind, 'v' => '282'];
if ($msg !== '') { $params['msg'] = $msg; }
if ($err !== '') { $params['err'] = $err; }
if ($return === 'detail' && $item_id > 0) {
    $params['id'] = $item_id;
    Html::redirect($root . '/plugins/schoolmanager/front/stock_item.php?' . http_build_query($params));
}
Html::redirect($root . '/plugins/schoolmanager/front/stock_glpi.php?' . http_build_query($params));
