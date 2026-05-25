<?php
/** Utilidades seguras para panel TIC, mapa de calor y avisos. */
function smgr_db() {
    global $DB;
    if (isset($DB) && is_object($DB)) { return $DB; }
    if (isset($GLOBALS['DB']) && is_object($GLOBALS['DB'])) { return $GLOBALS['DB']; }
    if (class_exists('DBConnection') && method_exists('DBConnection', 'getReadConnection')) {
        try { return DBConnection::getReadConnection(); } catch (Throwable $e) { return null; }
    }
    return null;
}
function smgr_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function smgr_plain($html) {
    $text = (string)$html;
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
    $text = preg_replace('/<\s*\/p\s*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}
function smgr_status_label($status) {
    $map = [
        1 => ['Abierta','new','Recibida'],
        2 => ['En curso','work','En revisión'],
        3 => ['Planificada','work','Planificada'],
        4 => ['En espera','wait','Esperando'],
        5 => ['Resuelta','done','Resuelta'],
        6 => ['Cerrada','closed','Cerrada'],
    ];
    return $map[(int)$status] ?? ['Estado '.(int)$status,'new','Estado'];
}
function smgr_priority_label($p) {
    $map=[1=>'Muy baja',2=>'Baja',3=>'Media',4=>'Alta',5=>'Muy alta',6=>'Mayor'];
    return $map[(int)$p] ?? 'Media';
}
function smgr_priority_class($p) {
    $p=(int)$p; if ($p>=5) return 'crit'; if ($p>=4) return 'high'; if ($p<=2) return 'low'; return 'med';
}
function smgr_get_location_name($id) {
    $id=(int)$id; if ($id<=0 || !class_exists('Location')) return '';
    $obj=new Location(); if ($obj->getFromDB($id)) return $obj->fields['completename'] ?? $obj->fields['name'] ?? '';
    return '';
}

function smgr_short_location_name($full, $fallback = 'Sin ubicación') {
    $full = trim((string)$full);
    if ($full === '') { return $fallback; }
    $parts = array_values(array_filter(array_map('trim', explode('>', $full))));
    $building = '';
    foreach ($parts as $part) {
        if (preg_match('/Edificio\s*([0-9]+)/iu', $part, $m)) {
            $building = 'Edificio ' . $m[1];
        }
    }
    $room = $parts ? trim((string)end($parts)) : '';
    // Si el último tramo es una planta, intenta buscar un aula/código al final del texto.
    if ($room === '' || preg_match('/^(planta|primera|segunda|tercera|s[oó]tano|zonas|ad|administraci[oó]n)/iu', $room)) {
        if (preg_match('/(?:^|>\s*)([A-Z]{0,3}\d{2,3}[A-Z]?|S\d{2}[A-Z]?|GYM|BIB|SEC|DIR|ORI|SALA[-\s]?PROF)\s*$/iu', $full, $m)) {
            $room = strtoupper(str_replace(' ', '-', $m[1]));
        }
    }
    if ($building && $room) { return $building . ' · ' . $room; }
    if ($room) { return $room; }
    return $fallback;
}

function smgr_get_category_name($id) {
    $id=(int)$id; if ($id<=0 || !class_exists('ITILCategory')) return '';
    $obj=new ITILCategory(); if ($obj->getFromDB($id)) return $obj->fields['completename'] ?? $obj->fields['name'] ?? '';
    return '';
}
function smgr_fetch_tickets($limit=300, $only_current_user=false) {
    $db=smgr_db(); $tickets=[]; $err='';
    if (!$db || !method_exists($db,'request')) return [[], 'No se pudo acceder al motor seguro de GLPI.'];
    try {
        $idsAllow=null;
        if ($only_current_user) {
            $idsAllow=[]; $uid=(int)Session::getLoginUserID();
            $it=$db->request(['FROM'=>'glpi_tickets_users','WHERE'=>['users_id'=>$uid,'type'=>1], 'LIMIT'=>1000]);
            foreach ($it as $row) { $idsAllow[(int)$row['tickets_id']]=true; }
            if (!$idsAllow) return [[], ''];
        }
        $it=$db->request(['FROM'=>'glpi_tickets','WHERE'=>['is_deleted'=>0], 'ORDER'=>['date_mod DESC','id DESC'], 'LIMIT'=>(int)$limit]);
        foreach ($it as $t) {
            $id=(int)($t['id']??0); if ($id<=0) continue;
            if ($idsAllow!==null && empty($idsAllow[$id])) continue;
            $t['location_name_full']=smgr_get_location_name((int)($t['locations_id']??0));
            $t['location_name']=smgr_short_location_name($t['location_name_full']);
            $t['category_name']=smgr_get_category_name((int)($t['itilcategories_id']??0));
            $tickets[]=$t;
        }
    } catch (Throwable $e) { $err=$e->getMessage(); }
    return [$tickets,$err];
}
function smgr_fetch_last_public_followup($ticketId) {
    $db=smgr_db(); if (!$db || !method_exists($db,'request')) return '';
    try {
        $it=$db->request(['FROM'=>'glpi_itilfollowups','WHERE'=>['itemtype'=>'Ticket','items_id'=>(int)$ticketId,'is_private'=>0],'ORDER'=>['date DESC','id DESC'],'LIMIT'=>1]);
        foreach ($it as $r) return smgr_plain($r['content'] ?? '');
    } catch (Throwable $e) {}
    return '';
}
function smgr_ticket_url($id) {
    global $CFG_GLPI; $root=$CFG_GLPI['root_doc'] ?? '';
    return $root . '/front/ticket.form.php?id=' . (int)$id;
}
function smgr_plugin_url($path) { global $CFG_GLPI; return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/' . ltrim($path,'/'); }

function smgr_is_ticket_assigned_to_user($ticketId, $userId = null) {
    $db = smgr_db();
    if ($userId === null) { $userId = (int)Session::getLoginUserID(); }
    if (!$db || !method_exists($db, 'request') || (int)$ticketId <= 0 || (int)$userId <= 0) { return false; }
    try {
        $it = $db->request([
            'FROM' => 'glpi_tickets_users',
            'WHERE' => ['tickets_id' => (int)$ticketId, 'users_id' => (int)$userId, 'type' => 2],
            'LIMIT' => 1,
        ]);
        foreach ($it as $row) { return true; }
    } catch (Throwable $e) {}
    return false;
}

function smgr_fetch_public_followups($ticketId, $limit = 20) {
    $db = smgr_db(); $out = [];
    if (!$db || !method_exists($db, 'request')) { return $out; }
    try {
        $it = $db->request([
            'FROM' => 'glpi_itilfollowups',
            'WHERE' => ['itemtype' => 'Ticket', 'items_id' => (int)$ticketId, 'is_private' => 0],
            'ORDER' => ['date ASC', 'id ASC'],
            'LIMIT' => (int)$limit,
        ]);
        foreach ($it as $row) { $out[] = $row; }
    } catch (Throwable $e) {}
    return $out;
}

function smgr_add_ticket_followup($ticketId, $content, $isPrivate = 0) {
    $ticketId = (int)$ticketId;
    $content = trim((string)$content);
    if ($ticketId <= 0) { return [false, 'Ticket no válido.']; }
    if ($content === '') { return [false, 'La respuesta está vacía.']; }
    if (!class_exists('ITILFollowup')) { return [false, 'No se encuentra ITILFollowup en GLPI.']; }
    try {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) { return [false, 'No existe el ticket.']; }
        $fu = new ITILFollowup();
        $input = [
            'itemtype'   => 'Ticket',
            'items_id'   => $ticketId,
            'content'    => $content,
            'is_private' => (int)$isPrivate,
            'users_id'   => (int)Session::getLoginUserID(),
            'date'       => date('Y-m-d H:i:s'),
        ];
        $id = $fu->add($input);
        if ($id) { return [true, 'Respuesta publicada correctamente.']; }
        $detail = '';
        if (method_exists($fu, 'getErrorMessages')) {
            $errs = $fu->getErrorMessages();
            if (is_array($errs) && $errs) { $detail = implode(' | ', array_map('strval', $errs)); }
        }
        return [false, 'GLPI no pudo guardar la respuesta.' . ($detail ? ' ' . $detail : '')];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }
}

function smgr_is_super_admin_user() {
    if (!class_exists('Session') || !Session::getLoginUserID()) { return false; }
    try {
        $profile = (!empty($_SESSION['glpiactiveprofile']) && is_array($_SESSION['glpiactiveprofile'])) ? $_SESSION['glpiactiveprofile'] : [];
        $name = smgr_norm_profile_name((string)($profile['name'] ?? ''));
        $id = (int)($profile['id'] ?? 0);
        return in_array($name, ['super-admin', 'super admin', 'superadmin'], true) || $id === 4;
    } catch (Throwable $e) {}
    return false;
}

function smgr_can_manage_ticket($ticketId, $userId = null) {
    if (smgr_is_super_admin_user()) { return true; }
    if (smgr_can_manage_tic_assignments()) { return true; }
    return smgr_is_ticket_assigned_to_user((int)$ticketId, $userId);
}

function smgr_norm_profile_name($name) {
    $name = strtolower(trim((string)$name));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'];
    return strtr($name, $map);
}

function smgr_contains_any($text, array $needles) {
    $text = smgr_norm_profile_name($text);
    foreach ($needles as $needle) {
        $needle = smgr_norm_profile_name($needle);
        if ($needle !== '' && strpos($text, $needle) !== false) { return true; }
    }
    return false;
}

function smgr_profile_name_is_tic($name) {
    $name = smgr_norm_profile_name($name);
    if ($name === '') { return false; }
    $exact = [
        'tecnico tic', 'tecnicos tic', 'equipo tic', 'tic', 'soporte tic',
        'tecnico', 'tecnicos', 'technician', 'technicians', 'support', 'soporte',
        'admin tic', 'administrador tic'
    ];
    if (in_array($name, $exact, true)) { return true; }
    if (smgr_contains_any($name, ['tic', 'informatica', 'informatico', 'sistemas'])
        && smgr_contains_any($name, ['tecnico', 'tecnic', 'tech', 'soporte', 'support', 'admin', 'equipo'])) {
        return true;
    }
    if (smgr_contains_any($name, ['tecnico', 'tecnic', 'technician', 'soporte'])
        && smgr_contains_any($name, ['ticket', 'incidencia', 'helpdesk'])) {
        return true;
    }
    return false;
}


function smgr_profile_name_is_admin_tic($name) {
    $name = smgr_norm_profile_name($name);
    if ($name === '') { return false; }
    $exact = ['admin tic','administrador tic','tic admin','tic administrador','responsable tic','coordinador tic','jefe tic','admin ti','administrador ti'];
    if (in_array($name, $exact, true)) { return true; }
    if (smgr_contains_any($name, ['admin','administrador','responsable','coordinador','jefe'])
        && smgr_contains_any($name, ['tic','ti','informatica','informatico','sistemas'])) { return true; }
    if (smgr_contains_any($name, ['tic','ti','informatica','sistemas'])
        && smgr_contains_any($name, ['admin','administrador','responsable','coordinador','jefe'])) { return true; }
    return false;
}

function smgr_fetch_admin_tic_profile_ids() {
    $db = smgr_db(); $ids = [];
    if (!$db || !method_exists($db, 'request')) { return $ids; }
    try {
        $it = $db->request(['FROM' => 'glpi_profiles', 'LIMIT' => 1000]);
        foreach ($it as $p) {
            if (smgr_profile_name_is_admin_tic($p['name'] ?? '')) { $ids[] = (int)$p['id']; }
        }
    } catch (Throwable $e) {}
    return array_values(array_unique(array_filter($ids)));
}

function smgr_active_profile_name() {
    $p = (!empty($_SESSION['glpiactiveprofile']) && is_array($_SESSION['glpiactiveprofile'])) ? $_SESSION['glpiactiveprofile'] : [];
    return (string)($p['name'] ?? '');
}

function smgr_session_has_admin_tic_profile() {
    // Solo el perfil activo debe marcar al usuario como Admin TIC.
    return smgr_profile_name_is_admin_tic(smgr_active_profile_name());
}

function smgr_user_has_admin_tic_profile($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) { return false; }
    $currentUser = (int)(class_exists('Session') ? Session::getLoginUserID() : 0);
    if ($userId === $currentUser) {
        // Si el usuario activo esta en perfil Profesor, aunque tenga tambien Admin TIC asignado, no debe heredar permisos de Admin TIC.
        return smgr_session_has_admin_tic_profile();
    }
    $db = smgr_db();
    if (!$db || !method_exists($db, 'request')) { return false; }
    $profileIds = smgr_fetch_admin_tic_profile_ids();
    if (!$profileIds) { return false; }
    try {
        $it = $db->request(['FROM' => 'glpi_profiles_users', 'WHERE' => ['users_id' => $userId, 'profiles_id' => $profileIds], 'LIMIT' => 1]);
        foreach ($it as $row) { return true; }
    } catch (Throwable $e) {}
    return false;
}

function smgr_can_manage_tic_assignments() {
    $uid = class_exists('Session') ? (int)Session::getLoginUserID() : 0;
    if ($uid <= 0) { return false; }
    if (smgr_is_super_admin_user()) { return true; }
    return smgr_user_has_admin_tic_profile($uid);
}

function smgr_can_view_all_tic_tickets() {
    return smgr_can_manage_tic_assignments();
}

function smgr_fetch_tecnico_tic_profile_ids() {
    $db = smgr_db(); $ids = [];
    if (!$db || !method_exists($db, 'request')) { return $ids; }
    try {
        $it = $db->request(['FROM' => 'glpi_profiles', 'LIMIT' => 1000]);
        foreach ($it as $p) {
            if (smgr_profile_name_is_tic($p['name'] ?? '')) {
                $ids[] = (int)$p['id'];
            }
        }
    } catch (Throwable $e) {}
    return array_values(array_unique(array_filter($ids)));
}

function smgr_user_label_from_row($u) {
    $id = (int)($u['id'] ?? 0);
    $label = trim(((string)($u['firstname'] ?? '')) . ' ' . ((string)($u['realname'] ?? '')));
    $login = (string)($u['name'] ?? '');
    if ($label === '') { $label = $login ?: ('Usuario ' . $id); }
    if ($login !== '' && stripos($label, $login) === false) { $label .= ' · ' . $login; }
    return $label;
}

function smgr_user_row_looks_tic($u) {
    $text = implode(' ', [
        (string)($u['name'] ?? ''),
        (string)($u['firstname'] ?? ''),
        (string)($u['realname'] ?? ''),
        (string)($u['email'] ?? ''),
        (string)($u['comment'] ?? ''),
    ]);
    $norm = smgr_norm_profile_name($text);
    if ($norm === '') { return false; }
    // Fallback pensado para instalaciones de centro donde existe un usuario llamado "tic"
    // aunque el perfil no se llame exactamente "Tecnico TIC".
    if (preg_match('/(^|[^a-z0-9])tic([^a-z0-9]|$)/i', $norm)) { return true; }
    return smgr_contains_any($norm, ['tecnico', 'tecnic', 'soporte', 'informatica', 'informatico', 'sistemas', 'support', 'sysadmin']);
}

function smgr_fetch_active_user_rows($limit = 5000) {
    $db = smgr_db(); $rows = [];
    if (!$db || !method_exists($db, 'request')) { return $rows; }
    $queries = [
        ['FROM' => 'glpi_users', 'WHERE' => ['is_deleted' => 0, 'is_active' => 1], 'ORDER' => ['realname ASC', 'firstname ASC', 'name ASC'], 'LIMIT' => (int)$limit],
        ['FROM' => 'glpi_users', 'WHERE' => ['is_deleted' => 0], 'ORDER' => ['realname ASC', 'firstname ASC', 'name ASC'], 'LIMIT' => (int)$limit],
        ['FROM' => 'glpi_users', 'ORDER' => ['realname ASC', 'firstname ASC', 'name ASC'], 'LIMIT' => (int)$limit],
    ];
    foreach ($queries as $query) {
        try {
            $it = $db->request($query);
            foreach ($it as $u) {
                $id = (int)($u['id'] ?? 0);
                if ($id > 0) { $rows[$id] = $u; }
            }
            if ($rows) { break; }
        } catch (Throwable $e) {}
    }
    return $rows;
}

function smgr_fetch_tic_candidate_user_ids($limit = 5000) {
    $ids = [];
    foreach (smgr_fetch_active_user_rows($limit) as $id => $u) {
        if (smgr_user_row_looks_tic($u)) { $ids[(int)$id] = true; }
    }
    return array_keys($ids);
}

function smgr_user_has_tecnico_tic_profile($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) { return false; }
    $currentUser = (int)(class_exists('Session') ? Session::getLoginUserID() : 0);
    if ($userId === $currentUser) {
        $active = smgr_active_profile_name();
        if ($active !== '') { return smgr_profile_name_is_tic($active); }
    }
    $db = smgr_db();
    if (!$db || !method_exists($db, 'request')) { return false; }

    $profileIds = smgr_fetch_tecnico_tic_profile_ids();
    if ($profileIds) {
        try {
            $it = $db->request([
                'FROM' => 'glpi_profiles_users',
                'WHERE' => ['users_id' => $userId, 'profiles_id' => $profileIds],
                'LIMIT' => 1,
            ]);
            foreach ($it as $row) { return true; }
        } catch (Throwable $e) {}
    }

    // Fallback: permite detectar el usuario TIC aunque el perfil no tenga nombre exacto.
    return in_array($userId, smgr_fetch_tic_candidate_user_ids(), true);
}

function smgr_fetch_assignable_technicians($limit = 500) {
    $db = smgr_db(); $users = [];
    if (!$db || !method_exists($db, 'request')) { return $users; }

    $userIds = [];
    $profileIds = smgr_fetch_tecnico_tic_profile_ids();
    if ($profileIds) {
        try {
            $itp = $db->request([
                'FROM' => 'glpi_profiles_users',
                'WHERE' => ['profiles_id' => $profileIds],
                'LIMIT' => 5000,
            ]);
            foreach ($itp as $pu) {
                $uid = (int)($pu['users_id'] ?? 0);
                if ($uid > 0) { $userIds[$uid] = true; }
            }
        } catch (Throwable $e) {}
    }

    foreach (smgr_fetch_tic_candidate_user_ids(5000) as $uid) {
        $userIds[(int)$uid] = true;
    }

    $activeRows = smgr_fetch_active_user_rows(5000);
    if (!$userIds) {
        // Fallback practico para centros: si no se detecta el perfil por nombre,
        // mostramos usuarios activos para que el Super-Admin pueda configurar reglas igualmente.
        foreach ($activeRows as $id => $u) { $userIds[(int)$id] = true; }
    }
    foreach ($activeRows as $id => $u) {
        if (empty($userIds[$id])) { continue; }
        $users[] = ['id' => (int)$id, 'label' => smgr_user_label_from_row($u)];
        if (count($users) >= (int)$limit) { break; }
    }
    return $users;
}

function smgr_ticket_assignees($ticketId) {
    $db = smgr_db(); $out = [];
    if (!$db || !method_exists($db, 'request')) { return $out; }
    try {
        $it = $db->request([
            'FROM' => 'glpi_tickets_users',
            'WHERE' => ['tickets_id' => (int)$ticketId, 'type' => 2],
            'LIMIT' => 20,
        ]);
        foreach ($it as $row) {
            $uid = (int)($row['users_id'] ?? 0);
            if ($uid <= 0) { continue; }
            $name = '';
            if (class_exists('User')) {
                $u = new User();
                if ($u->getFromDB($uid)) {
                    $name = trim(((string)($u->fields['firstname'] ?? '')) . ' ' . ((string)($u->fields['realname'] ?? '')));
                    if ($name === '') { $name = (string)($u->fields['name'] ?? ''); }
                }
            }
            $out[] = ['id' => $uid, 'name' => $name ?: ('Usuario ' . $uid)];
        }
    } catch (Throwable $e) {}
    return $out;
}

function smgr_assign_ticket_to_user($ticketId, $userId, $replace = true, $setInProgress = true) {
    $ticketId = (int)$ticketId; $userId = (int)$userId;
    if ($ticketId <= 0 || $userId <= 0) { return [false, 'Ticket o tecnico no valido.']; }
    if (!class_exists('Ticket')) { return [false, 'No se encuentra la clase Ticket.']; }
    if (!class_exists('Ticket_User')) { return [false, 'No se encuentra Ticket_User en GLPI.']; }
    if (!smgr_user_has_tecnico_tic_profile($userId)) { return [false, 'Ese usuario no tiene el perfil Técnico TIC. Crea/asigna ese perfil primero.']; }
    try {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) { return [false, 'No existe el ticket.']; }
        if ($replace) {
            try {
                $tuDel = new Ticket_User();
                if (method_exists($tuDel, 'deleteByCriteria')) {
                    $tuDel->deleteByCriteria(['tickets_id' => $ticketId, 'type' => 2]);
                } elseif (method_exists('Ticket_User', 'deleteByCriteria')) {
                    Ticket_User::deleteByCriteria(['tickets_id' => $ticketId, 'type' => 2]);
                }
            } catch (Throwable $e) {}
        }
        if (!$replace && smgr_is_ticket_assigned_to_user($ticketId, $userId)) {
            return [true, 'El ticket ya estaba asignado a ese tecnico.'];
        }
        $tu = new Ticket_User();
        $id = $tu->add([
            'tickets_id' => $ticketId,
            'users_id' => $userId,
            'type' => 2,
            'use_notification' => 1,
        ]);
        if ($id || smgr_is_ticket_assigned_to_user($ticketId, $userId)) {
            if ($setInProgress) {
                try {
                    $currentStatus = (int)($ticket->fields['status'] ?? 0);
                    if ($currentStatus > 0 && $currentStatus < 5 && $currentStatus !== 2) {
                        $ticket->update(['id' => $ticketId, 'status' => 2]);
                    }
                } catch (Throwable $e) {}
            }
            return [true, 'Ticket asignado correctamente.'];
        }
        return [false, 'GLPI no pudo asignar el ticket.'];
    } catch (Throwable $e) { return [false, $e->getMessage()]; }
}

function smgr_solve_ticket($ticketId, $solutionText) {
    $ticketId = (int)$ticketId; $solutionText = trim((string)$solutionText);
    if ($ticketId <= 0) { return [false, 'Ticket no valido.']; }
    if ($solutionText === '') { return [false, 'Escribe la solucion aplicada.']; }
    if (!class_exists('Ticket')) { return [false, 'No se encuentra Ticket en GLPI.']; }
    try {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) { return [false, 'No existe el ticket.']; }
        $solutionSaved = false;
        if (class_exists('ITILSolution')) {
            try {
                $sol = new ITILSolution();
                $sid = $sol->add([
                    'itemtype' => 'Ticket',
                    'items_id' => $ticketId,
                    'content' => $solutionText,
                    'users_id' => (int)Session::getLoginUserID(),
                    'date_creation' => date('Y-m-d H:i:s'),
                    'date_mod' => date('Y-m-d H:i:s'),
                ]);
                $solutionSaved = (bool)$sid;
            } catch (Throwable $e) {}
        }
        if (!$solutionSaved) {
            smgr_add_ticket_followup($ticketId, "Solucion propuesta/aplicada:\n" . $solutionText, 0);
        }
        $ok = $ticket->update(['id' => $ticketId, 'status' => 5]);
        if ($ok) { return [true, 'Ticket marcado como resuelto.']; }
        return [false, 'Se guardo la solucion, pero GLPI no pudo cambiar el estado.'];
    } catch (Throwable $e) { return [false, $e->getMessage()]; }
}


/** Configuracion de asignacion automatica TIC. */
function smgr_tic_assignment_default_config() {
    return [
        'enabled' => true,
        'default_user_id' => 0,
        'set_in_progress' => true,
        'rules' => [],
    ];
}

function smgr_tic_assignment_file() {
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/tic_assignment_rules.php';
}

function smgr_normalize_tic_assignment_config($cfg) {
    $def = smgr_tic_assignment_default_config();
    if (!is_array($cfg)) { $cfg = []; }
    $cfg = array_merge($def, $cfg);
    $cfg['enabled'] = !empty($cfg['enabled']);
    $cfg['default_user_id'] = (int)($cfg['default_user_id'] ?? 0);
    $cfg['set_in_progress'] = !empty($cfg['set_in_progress']);
    if (!isset($cfg['rules']) || !is_array($cfg['rules'])) { $cfg['rules'] = []; }
    $rules = [];
    foreach ($cfg['rules'] as $r) {
        if (!is_array($r)) { continue; }
        $rules[] = [
            'id' => (string)($r['id'] ?? ('r' . uniqid())),
            'enabled' => !empty($r['enabled']),
            'type' => in_array(($r['type'] ?? ''), ['aula','planta','edificio'], true) ? $r['type'] : 'aula',
            'building' => strtoupper(trim((string)($r['building'] ?? ''))),
            'floor' => strtoupper(trim((string)($r['floor'] ?? ''))),
            'code' => strtoupper(trim((string)($r['code'] ?? ''))),
            'user_id' => (int)($r['user_id'] ?? 0),
            'label' => trim((string)($r['label'] ?? '')),
        ];
    }
    $cfg['rules'] = $rules;
    return $cfg;
}

function smgr_load_tic_assignment_config() {
    $file = smgr_tic_assignment_file();
    $cfg = null;
    if (is_file($file)) {
        try { $cfg = require($file); } catch (Throwable $e) { $cfg = null; }
    }
    return smgr_normalize_tic_assignment_config($cfg);
}

function smgr_save_tic_assignment_config(array $cfg) {
    $cfg = smgr_normalize_tic_assignment_config($cfg);
    $file = smgr_tic_assignment_file();
    $dir = dirname($file);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $export = var_export($cfg, true);
    $content = "<?php\n// Reglas de asignacion automatica TIC. Editar desde el plugin, no a mano si no es necesario.\nreturn " . $export . ";\n";
    $ok = @file_put_contents($file, $content, LOCK_EX);
    return $ok !== false;
}

function smgr_room_from_location_id($locationId, $aulas = null) {
    $locationId = (int)$locationId;
    if ($locationId <= 0) { return null; }
    if ($aulas === null) {
        $file = __DIR__ . '/aulas_data.php';
        $aulas = is_file($file) ? require($file) : [];
    }
    foreach ((array)$aulas as $aula) {
        if ((int)($aula['id'] ?? 0) === $locationId) { return $aula; }
    }
    return null;
}

function smgr_find_tic_assignment_for_location($locationId, $aulas = null) {
    $cfg = smgr_load_tic_assignment_config();
    if (empty($cfg['enabled'])) { return [0, null, 'Asignacion automatica desactivada.']; }
    $room = smgr_room_from_location_id((int)$locationId, $aulas);
    $building = strtoupper((string)($room['building'] ?? ''));
    $floor = strtoupper((string)($room['floor'] ?? ''));
    $code = strtoupper((string)($room['codigo'] ?? ''));
    foreach ($cfg['rules'] as $rule) {
        if (empty($rule['enabled']) || (int)($rule['user_id'] ?? 0) <= 0) { continue; }
        $type = (string)($rule['type'] ?? 'aula');
        $ok = false;
        if ($type === 'aula') {
            $ok = $code !== '' && strtoupper((string)($rule['code'] ?? '')) === $code;
        } elseif ($type === 'planta') {
            $ok = $building !== '' && $floor !== '' && strtoupper((string)($rule['building'] ?? '')) === $building && strtoupper((string)($rule['floor'] ?? '')) === $floor;
        } elseif ($type === 'edificio') {
            $ok = $building !== '' && strtoupper((string)($rule['building'] ?? '')) === $building;
        }
        if ($ok) { return [(int)$rule['user_id'], $rule, 'Regla encontrada.']; }
    }
    if ((int)$cfg['default_user_id'] > 0) {
        return [(int)$cfg['default_user_id'], ['type'=>'default','label'=>'Tecnico por defecto'], 'Tecnico por defecto.'];
    }
    return [0, null, 'No hay regla para esta ubicacion.'];
}

function smgr_auto_assign_ticket($ticketId, $locationId = 0, $aulas = null) {
    $ticketId = (int)$ticketId; $locationId = (int)$locationId;
    if ($ticketId <= 0) { return [false, 'Ticket no valido.', 0]; }
    if ($locationId <= 0 && class_exists('Ticket')) {
        try { $t = new Ticket(); if ($t->getFromDB($ticketId)) { $locationId = (int)($t->fields['locations_id'] ?? 0); } } catch (Throwable $e) {}
    }
    if (smgr_ticket_assignees($ticketId)) { return [false, 'El ticket ya tiene tecnico asignado.', 0]; }
    [$userId, $rule, $why] = smgr_find_tic_assignment_for_location($locationId, $aulas);
    if ($userId <= 0) { return [false, $why, 0]; }
    if (!smgr_user_has_tecnico_tic_profile($userId)) { return [false, 'El usuario configurado no tiene perfil Tecnico TIC.', $userId]; }
    $cfg = smgr_load_tic_assignment_config();
    [$ok, $msg] = smgr_assign_ticket_to_user($ticketId, $userId, true, !empty($cfg['set_in_progress']));
    return [$ok, $ok ? 'Asignado automaticamente.' : $msg, $userId];
}

function smgr_apply_auto_assignment_to_open_tickets($limit = 400) {
    [$tickets, $err] = smgr_fetch_tickets((int)$limit, false);
    if ($err) { return [0, 0, $err]; }
    $done = 0; $skipped = 0;
    foreach ($tickets as $t) {
        $id = (int)($t['id'] ?? 0);
        if ($id <= 0 || (int)($t['status'] ?? 0) >= 5) { continue; }
        if (smgr_ticket_assignees($id)) { $skipped++; continue; }
        [$ok, $msg] = smgr_auto_assign_ticket($id, (int)($t['locations_id'] ?? 0));
        if ($ok) { $done++; } else { $skipped++; }
    }
    return [$done, $skipped, 'Reglas aplicadas.'];
}
