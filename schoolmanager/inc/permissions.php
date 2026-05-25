<?php
require_once(__DIR__ . '/config.php');
/**
 * Permisos centralizados del plugin Gestion School Manager.
 * La idea es que un profesor vea incidencias/planos/lista,
 * y que solo perfiles con permiso de inventario vean altas de activos.
 */

function plugin_schoolmanager_right_create_value() {
    return defined('CREATE') ? CREATE : 4;
}

function plugin_schoolmanager_safe_have_right($right, $value = null) {
    if ($value === null) { $value = plugin_schoolmanager_right_create_value(); }
    if (!class_exists('Session') || !method_exists('Session', 'haveRight')) { return false; }
    try { return (bool) Session::haveRight($right, $value); }
    catch (Throwable $e) { return false; }
}

function plugin_schoolmanager_class_can_create($itemtype) {
    if (!class_exists($itemtype)) { return false; }
    try {
        if (method_exists($itemtype, 'canCreate')) {
            return (bool) call_user_func([$itemtype, 'canCreate']);
        }
    } catch (Throwable $e) { /* fallback below */ }
    return false;
}

function plugin_schoolmanager_can_create_asset($itemtype = null) {
    // Roles del plugin: Técnico TIC y Admin TIC pueden crear activos desde la interfaz simplificada.
    if (class_exists('Session') && Session::getLoginUserID()) {
        if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) { return true; }
        if (function_exists('plugin_schoolmanager_is_super_admin_v176') && plugin_schoolmanager_is_super_admin_v176()) { return true; }
        if (function_exists('plugin_schoolmanager_is_tic_user') && plugin_schoolmanager_is_tic_user()) { return true; }
    }
    $map = [
        'Computer' => ['computer', 'Computer'],
        'Monitor' => ['monitor', 'Monitor'],
        'Printer' => ['printer', 'Printer'],
        'NetworkEquipment' => ['networking', 'networkequipment', 'NetworkEquipment'],
        'Peripheral' => ['peripheral', 'Peripheral'],
        'Phone' => ['phone', 'Phone'],
    ];

    if ($itemtype !== null) {
        if (!isset($map[$itemtype])) { return false; }
        foreach ($map[$itemtype] as $candidate) {
            if (class_exists($candidate) && plugin_schoolmanager_class_can_create($candidate)) { return true; }
            if (is_string($candidate) && plugin_schoolmanager_safe_have_right($candidate)) { return true; }
        }
        return false;
    }

    foreach (array_keys($map) as $type) {
        if (plugin_schoolmanager_can_create_asset($type)) { return true; }
    }
    return false;
}

function plugin_schoolmanager_can_create_ticket() {
    // En un centro educativo interesa que profesores puedan poner incidencias.
    // Si el perfil no declara el permiso de forma detectable, no ocultamos el acceso;
    // GLPI validara permisos al crear el ticket.
    if (plugin_schoolmanager_safe_have_right('ticket')) { return true; }
    if (plugin_schoolmanager_safe_have_right('ticketvalidation')) { return true; }
    return Session::getLoginUserID() > 0;
}

function plugin_schoolmanager_can_view_locations() {
    return Session::getLoginUserID() > 0;
}

function plugin_schoolmanager_user_mode() {
    if (function_exists('plugin_schoolmanager_is_tic_user') && plugin_schoolmanager_is_tic_user()) { return 'tecnico'; }
    if (plugin_schoolmanager_can_create_asset(null)) { return 'tecnico'; }
    return 'profesor';
}




function plugin_schoolmanager_profile_text_looks_tic($text) {
    $text = strtolower(trim((string)$text));
    $text = strtr($text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    if ($text === '') { return false; }
    if (preg_match('/(^|[^a-z0-9])tic([^a-z0-9]|$)/', $text)) { return true; }
    foreach (['tecnico','tecnic','soporte','informatica','informatico','sistemas','support','helpdesk'] as $needle) {
        if (strpos($text, $needle) !== false) { return true; }
    }
    return false;
}


function plugin_schoolmanager_active_profile_name_text() {
    $p = (!empty($_SESSION['glpiactiveprofile']) && is_array($_SESSION['glpiactiveprofile'])) ? $_SESSION['glpiactiveprofile'] : [];
    return trim((string)($p['name'] ?? '') . ' ' . (string)($p['interface'] ?? ''));
}

function plugin_schoolmanager_active_profile_looks_tic() {
    $text = plugin_schoolmanager_active_profile_name_text();
    return plugin_schoolmanager_profile_text_looks_tic($text);
}

function plugin_schoolmanager_active_profile_looks_admin_tic() {
    $text = plugin_schoolmanager_active_profile_name_text();
    return plugin_schoolmanager_profile_text_looks_admin_tic($text);
}

function plugin_schoolmanager_session_profile_looks_tic() {
    // Importante: GLPI puede tener varios perfiles asignados, pero el usuario trabaja con el perfil ACTIVO.
    // Antes se miraban todos los perfiles y un profesor que tambien tuviera Admin TIC/TIC acababa entrando como TIC.
    return plugin_schoolmanager_active_profile_looks_tic();
}


function plugin_schoolmanager_profile_text_looks_admin_tic($text) {
    $text = strtolower(trim((string)$text));
    $text = strtr($text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    if ($text === '') { return false; }
    if (preg_match('/(^|[^a-z0-9])(admin|administrador|responsable|coordinador|jefe)\s*(tic|ti|sistemas|informatica)([^a-z0-9]|$)/', $text)) { return true; }
    if (preg_match('/(^|[^a-z0-9])(tic|ti|sistemas|informatica)\s*(admin|administrador|responsable|coordinador|jefe)([^a-z0-9]|$)/', $text)) { return true; }
    return false;
}

function plugin_schoolmanager_session_profile_looks_admin_tic() {
    // Solo cuenta el perfil activo, no todos los perfiles asignados al usuario.
    return plugin_schoolmanager_active_profile_looks_admin_tic();
}

function plugin_schoolmanager_is_admin_tic_user() {
    if (!class_exists('Session') || !Session::getLoginUserID()) { return false; }
    if (plugin_schoolmanager_session_profile_looks_admin_tic()) { return true; }
    if (function_exists('smgr_user_has_admin_tic_profile') && smgr_user_has_admin_tic_profile((int)Session::getLoginUserID())) { return true; }
    return false;
}

function plugin_schoolmanager_is_tic_user() {
    if (!class_exists('Session') || !Session::getLoginUserID()) { return false; }
    if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) { return true; }
    if (function_exists('plugin_schoolmanager_is_super_admin_v176') && plugin_schoolmanager_is_super_admin_v176()) { return true; }
    if (plugin_schoolmanager_session_profile_looks_tic()) { return true; }
    if (function_exists('smgr_user_has_tecnico_tic_profile') && smgr_user_has_tecnico_tic_profile((int)Session::getLoginUserID())) { return true; }
    return false;
}

function plugin_schoolmanager_can_manage_tic_assignments() {
    if (!class_exists('Session') || !Session::getLoginUserID()) { return false; }
    if (function_exists('smgr_can_manage_tic_assignments')) { return smgr_can_manage_tic_assignments(); }
    if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) { return true; }
    if (function_exists('plugin_schoolmanager_is_super_admin_v176') && plugin_schoolmanager_is_super_admin_v176()) { return true; }
    return plugin_schoolmanager_is_admin_tic_user();
}

function plugin_schoolmanager_can_manage_stock() {
    if (!class_exists('Session') || !Session::getLoginUserID()) { return false; }
    if (function_exists('smgr_is_super_admin_user') && smgr_is_super_admin_user()) { return true; }
    if (function_exists('plugin_schoolmanager_is_super_admin_v176') && plugin_schoolmanager_is_super_admin_v176()) { return true; }
    if (function_exists('plugin_schoolmanager_is_tic_user') && plugin_schoolmanager_is_tic_user()) { return true; }
    if (plugin_schoolmanager_can_create_asset(null)) { return true; }
    if (function_exists('smgr_user_has_tecnico_tic_profile') && smgr_user_has_tecnico_tic_profile((int)Session::getLoginUserID())) { return true; }
    if (plugin_schoolmanager_session_profile_looks_tic()) { return true; }
    foreach (['consumable','cartridge','consumableitem','cartridgeitem','printer','computer','monitor','peripheral'] as $right) {
        if (plugin_schoolmanager_safe_have_right($right, defined('READ') ? READ : 1)) { return true; }
        if (plugin_schoolmanager_safe_have_right($right, defined('CREATE') ? CREATE : 4)) { return true; }
        if (plugin_schoolmanager_safe_have_right($right, defined('UPDATE') ? UPDATE : 2)) { return true; }
    }
    return false;
}

function plugin_schoolmanager_can_open_native_locations() {
    if (plugin_schoolmanager_is_super_admin_v176()) { return true; }
    if (function_exists('plugin_schoolmanager_is_tic_user') && plugin_schoolmanager_is_tic_user()) { return true; }
    if (plugin_schoolmanager_can_create_asset(null)) { return true; }
    return false;
}

function plugin_schoolmanager_gestion_url() {
    global $CFG_GLPI;
    return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/formularios.php?v=' . (defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1');
}

function plugin_schoolmanager_error_page($title = 'No se puede acceder', $message = '', $details = '') {
    global $CFG_GLPI;
    $root = $CFG_GLPI['root_doc'] ?? '';
    if ($message === '') {
        $message = 'Tu perfil no tiene permisos para abrir esta zona o se ha producido un problema al cargar la página.';
    }
    Html::header($title, $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
    echo '<style>
    .pc-error-wrap{min-height:calc(100vh - 86px);display:grid;place-items:center;padding:26px;background:radial-gradient(circle at 16% 10%,rgba(239,163,0,.28),transparent 30%),radial-gradient(circle at 88% 18%,rgba(15,143,134,.24),transparent 34%),linear-gradient(135deg,#f8fffd,#fff7df 54%,#eef8ff);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#06384a}
    .pc-error-card{width:min(860px,100%);position:relative;overflow:hidden;background:rgba(255,255,255,.94);border:1px solid #bfe3dc;border-radius:32px;padding:28px;box-shadow:0 28px 80px rgba(6,56,74,.16)}
    .pc-error-card:before{content:"";position:absolute;left:0;top:0;bottom:0;width:10px;background:linear-gradient(180deg,#0f8f86,#efa300,#a32222)}
    .pc-error-head{display:flex;align-items:center;gap:18px;margin-bottom:16px}.pc-error-logo{height:72px;max-width:230px;object-fit:contain;background:#fff;border:1px solid #d6ece7;border-radius:18px;padding:8px}.pc-error-kicker{margin:0;color:#0f8f86;font-weight:950;letter-spacing:.12em;text-transform:uppercase;font-size:12px}.pc-error-card h1{margin:2px 0 0;color:#06384a;font-size:clamp(30px,4vw,48px);line-height:.98}.pc-error-message{font-size:18px;line-height:1.45;color:#465e69;font-weight:850;margin:12px 0 18px}.pc-error-details{background:#fff8df;border:1px solid #f1d27b;color:#765300;border-radius:18px;padding:12px 14px;font-weight:850;margin:0 0 18px}.pc-error-actions{display:flex;gap:10px;flex-wrap:wrap}.pc-error-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:16px;padding:12px 16px;font-weight:950;text-decoration:none!important;border:1px solid #cfe7e1;background:#fff;color:#06384a!important}.pc-error-btn.primary{background:linear-gradient(135deg,#06384a,#075d61);border-color:#06384a;color:#fff!important}.pc-error-btn.gold{background:#fff4d7;border-color:#f1d27b;color:#6c4a00!important}@media(max-width:640px){.pc-error-head{display:block;text-align:center}.pc-error-logo{margin:0 auto 12px;display:block}.pc-error-actions{display:grid}.pc-error-btn{width:100%}}
    </style>';
    echo '<div class="pc-error-wrap"><section class="pc-error-card"><div class="pc-error-head"><img class="pc-error-logo" src="' . htmlspecialchars((function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg')), ENT_QUOTES, 'UTF-8') . '" alt="Logo"><div><p class="pc-error-kicker">' . htmlspecialchars(function_exists('plugin_schoolmanager_app_name') ? plugin_schoolmanager_app_name() : 'GLPI School Manager', ENT_QUOTES, 'UTF-8') . '</p><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></div></div><p class="pc-error-message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($details !== '') { echo '<div class="pc-error-details">' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</div>'; }
    echo '<div class="pc-error-actions"><a class="pc-error-btn primary" href="' . htmlspecialchars(plugin_schoolmanager_gestion_url(), ENT_QUOTES, 'UTF-8') . '">Volver a ' . htmlspecialchars(function_exists('plugin_schoolmanager_app_name') ? plugin_schoolmanager_app_name() : 'GLPI School Manager', ENT_QUOTES, 'UTF-8') . '</a><a class="pc-error-btn gold" href="' . htmlspecialchars($root . '/plugins/schoolmanager/front/nueva_incidencia.php?v=' . (defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1'), ENT_QUOTES, 'UTF-8') . '">Crear incidencia</a><a class="pc-error-btn" href="' . htmlspecialchars($root . '/plugins/schoolmanager/front/selector.php?v=' . (defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1'), ENT_QUOTES, 'UTF-8') . '">Abrir plano</a></div></section></div>';
    Html::footer();
    exit;
}

function plugin_schoolmanager_access_denied_page($title = 'Acceso restringido', $message = '') {
    if ($message === '') {
        $message = 'Tu perfil puede consultar aulas y crear incidencias, pero no tiene permisos para realizar esta acción.';
    }
    plugin_schoolmanager_error_page($title, $message, 'Si crees que deberías tener acceso, revisa el perfil asignado en GLPI o avisa al equipo TIC.');
}

// smgr_force_superadmin_v176: comprobacion extra para instalaciones GLPI donde el nombre del perfil no llega igual.
function plugin_schoolmanager_is_super_admin_v176() {
    if (isset($_SESSION['glpiactiveprofile']) && is_array($_SESSION['glpiactiveprofile'])) {
        $p = $_SESSION['glpiactiveprofile'];
        $name = strtolower((string)($p['name'] ?? ''));
        $name = strtr($name, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return in_array($name, ['super-admin','super admin','superadmin'], true) || (int)($p['id'] ?? 0) === 4;
    }
    return function_exists('plugin_schoolmanager_is_superadmin') ? plugin_schoolmanager_is_superadmin() : false;
}
