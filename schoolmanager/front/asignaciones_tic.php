<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');

if (!smgr_can_manage_tic_assignments()) {
    plugin_schoolmanager_access_denied_page('Reglas TIC restringidas', 'Solo Admin TIC o Super-Admin puede cambiar la asignacion automatica de tickets.');
}

$root = $CFG_GLPI['root_doc'] ?? '';
$version = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.0.3';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg?v=' . rawurlencode($version));
$message = '';
$messageType = 'ok';
$cfg = smgr_load_tic_assignment_config();
$techs = smgr_fetch_assignable_technicians();
$aulas = require(__DIR__ . '/../inc/aulas_data.php');

function pcas_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pcas_url($path) { global $root, $version; $sep = strpos($path, '?') === false ? '?' : '&'; return $root . $path . $sep . 'v=' . rawurlencode($version); }
function pcas_csrf() { static $tok = null; if (method_exists('Session','getNewCSRFToken')) { if ($tok === null) { $tok = Session::getNewCSRFToken(); } echo '<input type="hidden" name="_glpi_csrf_token" value="' . pcas_h($tok) . '">'; } }
function pcas_sel($a, $b) { return ((string)$a === (string)$b) ? ' selected' : ''; }
function pcas_chk($v) { return !empty($v) ? ' checked' : ''; }
function pcas_icon($name) {
    $icons = [
        'home' => '<path d="M3.8 10.6 12 4.3l8.2 6.3"/><path d="M6.8 9.8v9.7h10.4V9.8"/><path d="M10 19.5v-5a2 2 0 0 1 4 0v5"/>',
        'panel' => '<rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'save' => '<path d="M5 5h11l3 3v11H5z"/><path d="M8 5v6h8V5"/><path d="M8 19v-5h8v5"/>',
        'trash' => '<path d="M4 7h16M9 7V5h6v2M8 10v8M12 10v8M16 10v8"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'map' => '<path d="M9 18 3.5 20V6L9 4l6 2 5.5-2v14L15 20l-6-2Z"/><path d="M9 4v14M15 6v14"/>',
        'ticket' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5V10a2 2 0 0 0 0 4v2.5a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5V14a2 2 0 0 0 0-4V7.5Z"/><path d="M13 5v14"/>',
        'alert' => '<path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/>',
        'check' => '<path d="M4.5 12.5 9.2 17 19.5 7"/>',
    ];
    $body = $icons[$name] ?? $icons['panel'];
    return '<svg class="pcas-svg" viewBox="0 0 24 24" aria-hidden="true">' . $body . '</svg>';
}
function pcas_tech_label($id, $techs) {
    foreach ($techs as $t) { if ((int)$t['id'] === (int)$id) { return $t['label']; } }
    return $id ? ('Usuario ' . (int)$id) : 'Sin tecnico';
}
function pcas_rule_label($rule, $aulas) {
    $type = $rule['type'] ?? 'aula';
    if ($type === 'aula') {
        $code = strtoupper((string)($rule['code'] ?? ''));
        foreach ($aulas as $a) { if (strtoupper((string)($a['codigo'] ?? '')) === $code) { return ($a['building'] ?? '') . ' · ' . ($a['aula'] ?? $code) . ' · ' . ($a['planta'] ?? ''); } }
        return $code ?: 'Aula concreta';
    }
    if ($type === 'planta') { return ($rule['building'] ?? '') . ' · ' . ($rule['floor'] ?? '') . ' completa'; }
    return ($rule['building'] ?? '') . ' completo';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['pc_action'] ?? '');
    if ($action === 'save_global') {
        $cfg['enabled'] = !empty($_POST['enabled']);
        $cfg['set_in_progress'] = !empty($_POST['set_in_progress']);
        $cfg['default_user_id'] = (int)($_POST['default_user_id'] ?? 0);
        $message = smgr_save_tic_assignment_config($cfg) ? 'Configuracion guardada.' : 'No se pudo guardar la configuracion.';
        $messageType = ($message === 'Configuracion guardada.') ? 'ok' : 'error';
    } elseif ($action === 'add_rule') {
        $type = in_array(($_POST['rule_type'] ?? ''), ['aula','planta','edificio'], true) ? $_POST['rule_type'] : 'aula';
        $rule = [
            'id' => 'r' . date('YmdHis') . mt_rand(100,999),
            'enabled' => true,
            'type' => $type,
            'building' => strtoupper(trim((string)($_POST['building'] ?? ''))),
            'floor' => strtoupper(trim((string)($_POST['floor'] ?? ''))),
            'code' => strtoupper(trim((string)($_POST['code'] ?? ''))),
            'user_id' => (int)($_POST['user_id'] ?? 0),
            'label' => trim((string)($_POST['label'] ?? '')),
        ];
        $valid = $rule['user_id'] > 0;
        if ($type === 'aula') { $valid = $valid && $rule['code'] !== ''; }
        if ($type === 'planta') { $valid = $valid && $rule['building'] !== '' && $rule['floor'] !== ''; }
        if ($type === 'edificio') { $valid = $valid && $rule['building'] !== ''; }
        if (!$valid) {
            $message = 'Completa la regla antes de guardarla.'; $messageType = 'error';
        } else {
            $cfg['rules'][] = $rule;
            $message = smgr_save_tic_assignment_config($cfg) ? 'Regla creada correctamente.' : 'No se pudo guardar la regla.';
            $messageType = (strpos($message, 'creada') !== false) ? 'ok' : 'error';
        }
    } elseif ($action === 'update_rule') {
        $rid = (string)($_POST['rule_id'] ?? '');
        $type = in_array(($_POST['rule_type'] ?? ''), ['aula','planta','edificio'], true) ? $_POST['rule_type'] : 'aula';
        $newRule = [
            'id' => $rid,
            'enabled' => !empty($_POST['enabled']),
            'type' => $type,
            'building' => strtoupper(trim((string)($_POST['building'] ?? ''))),
            'floor' => strtoupper(trim((string)($_POST['floor'] ?? ''))),
            'code' => strtoupper(trim((string)($_POST['code'] ?? ''))),
            'user_id' => (int)($_POST['user_id'] ?? 0),
            'label' => trim((string)($_POST['label'] ?? '')),
        ];
        $valid = $rid !== '' && $newRule['user_id'] > 0;
        if ($type === 'aula') { $valid = $valid && $newRule['code'] !== ''; }
        if ($type === 'planta') { $valid = $valid && $newRule['building'] !== '' && $newRule['floor'] !== ''; }
        if ($type === 'edificio') { $valid = $valid && $newRule['building'] !== ''; }
        if (!$valid) {
            $message = 'Completa la regla antes de actualizarla.'; $messageType = 'error';
        } else {
            $found = false;
            foreach ($cfg['rules'] as &$r) {
                if ((string)($r['id'] ?? '') === $rid) { $r = array_merge($r, $newRule); $found = true; break; }
            }
            unset($r);
            $message = ($found && smgr_save_tic_assignment_config($cfg)) ? 'Regla modificada correctamente.' : 'No se pudo modificar la regla.';
            $messageType = (strpos($message, 'modificada') !== false) ? 'ok' : 'error';
        }
    } elseif ($action === 'delete_rule') {
        $rid = (string)($_POST['rule_id'] ?? '');
        $cfg['rules'] = array_values(array_filter($cfg['rules'], static fn($r) => (string)($r['id'] ?? '') !== $rid));
        $message = smgr_save_tic_assignment_config($cfg) ? 'Regla eliminada.' : 'No se pudo eliminar.';
    } elseif ($action === 'toggle_rule') {
        $rid = (string)($_POST['rule_id'] ?? '');
        foreach ($cfg['rules'] as &$r) { if ((string)($r['id'] ?? '') === $rid) { $r['enabled'] = empty($r['enabled']); } }
        unset($r);
        $message = smgr_save_tic_assignment_config($cfg) ? 'Regla actualizada.' : 'No se pudo actualizar.';
    } elseif ($action === 'apply_rules') {
        [$done, $skip, $msg] = smgr_apply_auto_assignment_to_open_tickets(800);
        $message = 'Reglas aplicadas: ' . (int)$done . ' tickets asignados, ' . (int)$skip . ' omitidos.';
    }
    $cfg = smgr_load_tic_assignment_config();
}

$buildings = array_values(array_unique(array_map(static fn($a) => (string)($a['building'] ?? ''), $aulas)));
$floorsByBuilding = [];
foreach ($aulas as $a) {
    $b = (string)($a['building'] ?? ''); $f = (string)($a['floor'] ?? '');
    if ($b !== '' && $f !== '') { $floorsByBuilding[$b][$f] = true; }
}

Html::header('Reglas TIC', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
?>
<style>
.pcas{--ink:#06384a;--muted:#607684;--blue:#07384d;--teal:#0f8f86;--red:#b21f2d;--gold:#efa300;--line:#d6e6ed;min-height:calc(100vh - 70px);padding:22px;background:radial-gradient(circle at 12% 0,rgba(239,163,0,.18),transparent 34%),radial-gradient(circle at 88% 6%,rgba(15,143,134,.16),transparent 36%),linear-gradient(135deg,#f4f8fb,#fff 56%,#fff8e8);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}.pcas *{box-sizing:border-box}.pcas a{text-decoration:none}.pcas-wrap{max-width:1480px;margin:0 auto;display:grid;gap:18px}.pcas-svg{width:20px;height:20px;display:inline-block;fill:none;stroke:currentColor;stroke-width:2.15;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}.pcas-hero,.pcas-card{background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:28px;box-shadow:0 16px 42px rgba(7,56,77,.07)}.pcas-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;padding:24px 28px}.pcas-brand{display:flex;gap:20px;align-items:center}.pcas-logo{width:170px;height:82px;object-fit:contain;mix-blend-mode:multiply;filter:none;box-shadow:none}.pcas-k{font-weight:950;letter-spacing:.16em;color:var(--red);font-size:14px}.pcas h1{margin:2px 0 4px;font-size:clamp(42px,6vw,72px);line-height:.92;letter-spacing:-.055em}.pcas p{margin:0;color:var(--muted);font-weight:850}.pcas-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.pcas-btn{min-height:52px;border-radius:17px;border:1px solid var(--line);background:#fff;color:var(--ink)!important;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:0 20px;font-weight:950;cursor:pointer;box-shadow:0 10px 22px rgba(7,56,77,.055);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease,color .18s ease}.pcas-btn:hover{transform:translateY(-2px);box-shadow:0 18px 34px rgba(7,56,77,.10);border-color:#b7d5de}.pcas-btn.primary{background:var(--blue);border-color:var(--blue);color:#fff!important}.pcas-btn.red{background:var(--red);border-color:#9e1b27;color:#fff!important;box-shadow:0 16px 32px rgba(178,31,45,.18)}.pcas-btn.gold{background:#fff7df;border-color:#efcf78;color:#715000!important}.pcas-btn.small{min-height:42px;border-radius:14px;padding:0 14px}.pcas-grid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:18px;align-items:start}.pcas-card{padding:22px}.pcas-card h2{display:flex;align-items:center;gap:10px;margin:0 0 14px;font-size:32px;letter-spacing:-.03em}.pcas-msg{border-radius:18px;padding:14px 16px;font-weight:950;display:flex;align-items:center;gap:10px;background:#eef9f7;border:1px solid #bfe7e2;color:#075d61}.pcas-msg.error{background:#fff1f2;border-color:#f0b8bd;color:var(--red)}.pcas-form{display:grid;gap:14px}.pcas-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pcas-field label{display:block;margin-bottom:7px;font-weight:950;color:var(--ink)}.pcas-input,.pcas-select{width:100%;min-height:50px;border:1px solid var(--line);border-radius:16px;background:#fff;color:var(--ink);padding:0 14px;font-weight:900;outline:none}.pcas-input:focus,.pcas-select:focus{border-color:var(--teal);box-shadow:0 0 0 4px rgba(15,143,134,.12)}.pcas-check{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:16px;padding:13px 14px;background:#fbfdfe;font-weight:950}.pcas-rules{display:grid;gap:10px}.pcas-rule{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid var(--line);border-radius:20px;background:#fff;padding:14px 16px;transition:.18s ease}.pcas-rule:hover{transform:translateY(-2px);box-shadow:0 15px 30px rgba(7,56,77,.08);border-color:#b7d5de}.pcas-rule.off{opacity:.58}.pcas-rule b{display:block;font-size:20px}.pcas-rule small{display:block;color:var(--muted);font-weight:900;margin-top:5px}.pcas-rule-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.pcas-note{border:1px solid #efcf78;background:#fff8e8;color:#755500;border-radius:18px;padding:14px;font-weight:900;line-height:1.35}.pcas-mini{display:grid;gap:10px}.pcas-mini-item{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:18px;padding:13px;background:#fbfdfe}.pcas-mini-item b{display:block}.pcas-mini-item span{color:var(--muted);font-weight:850}.pcas-hidden{display:none!important}@media(max-width:1100px){.pcas-grid{grid-template-columns:1fr}.pcas-hero{grid-template-columns:1fr}.pcas-actions{justify-content:flex-start}}@media(max-width:680px){.pcas{padding:10px}.pcas-hero,.pcas-card{border-radius:20px;padding:15px}.pcas-brand{display:grid}.pcas-logo{width:150px}.pcas-row{grid-template-columns:1fr}.pcas-rule{grid-template-columns:1fr}.pcas-rule-actions,.pcas-actions{display:grid;grid-template-columns:1fr}.pcas-btn{width:100%}}

.pcas-edit{grid-column:1/-1;border-top:1px dashed var(--line);padding-top:14px;margin-top:4px;background:#fbfdfe;border-radius:16px;padding:14px}.pcas-edit summary{cursor:pointer;font-weight:950;color:var(--red);display:inline-flex;gap:8px;align-items:center}.pcas-edit summary::-webkit-details-marker{display:none}.pcas-edit[open]{border:1px solid var(--line)}
</style>
<div class="pcas"><div class="pcas-wrap">
  <section class="pcas-hero">
    <div class="pcas-brand"><img class="pcas-logo" src="<?= pcas_h($logoUrl) ?>" alt="Logo"><div><div class="pcas-k">CENTRO DE CONTROL TIC</div><h1>Reglas TIC</h1><p>Asigna automaticamente incidencias por aula, planta o edificio y revisa los tecnicos disponibles.</p></div></div>
    <div class="pcas-actions"><a class="pcas-btn red" href="<?= pcas_h(pcas_url('/plugins/schoolmanager/front/formularios.php')) ?>"><?= pcas_icon('home') ?> Inicio</a><a class="pcas-btn primary" href="<?= pcas_h(pcas_url('/plugins/schoolmanager/front/panel_tic.php')) ?>"><?= pcas_icon('panel') ?> Panel TIC</a><form method="post" style="margin:0"><?php pcas_csrf(); ?><input type="hidden" name="pc_action" value="apply_rules"><button class="pcas-btn gold" type="submit"><?= pcas_icon('bolt') ?> Aplicar reglas</button></form></div>
  </section>
  <?php if ($message): ?><div class="pcas-msg <?= $messageType==='error'?'error':'' ?>"><?= pcas_icon($messageType==='error'?'alert':'check') ?> <?= pcas_h($message) ?></div><?php endif; ?>
  <?php if (!$techs): ?><div class="pcas-msg error"><?= pcas_icon('alert') ?> No se han encontrado usuarios con perfil Tecnico TIC. Revisa el perfil en GLPI y vuelve a cargar.</div><?php endif; ?>
  <section class="pcas-grid">
    <main class="pcas-card">
      <h2><?= pcas_icon('map') ?> Reglas de asignacion</h2>
      <form method="post" class="pcas-form" style="margin-bottom:18px"><?php pcas_csrf(); ?><input type="hidden" name="pc_action" value="save_global">
        <div class="pcas-row">
          <label class="pcas-check"><input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled'])?'checked':'' ?>> Activar asignacion automatica</label>
          <label class="pcas-check"><input type="checkbox" name="set_in_progress" value="1" <?= !empty($cfg['set_in_progress'])?'checked':'' ?>> Pasar ticket a en curso al asignar</label>
        </div>
        <div class="pcas-field"><label>Tecnico por defecto</label><select class="pcas-select" name="default_user_id"><option value="0">Sin tecnico por defecto</option><?php foreach ($techs as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$cfg['default_user_id']===(int)$t['id']?'selected':'' ?>><?= pcas_h($t['label']) ?></option><?php endforeach; ?></select></div>
        <button class="pcas-btn primary" type="submit"><?= pcas_icon('save') ?> Guardar configuracion</button>
      </form>
      <form method="post" class="pcas-form" style="border-top:1px solid var(--line);padding-top:18px;margin-bottom:18px"><?php pcas_csrf(); ?><input type="hidden" name="pc_action" value="add_rule">
        <h2 style="font-size:26px;margin-bottom:0"><?= pcas_icon('plus') ?> Nueva regla</h2>
        <div class="pcas-row">
          <div class="pcas-field"><label>Tipo</label><select class="pcas-select" name="rule_type" id="ruleType"><option value="aula">Aula concreta</option><option value="planta">Planta completa</option><option value="edificio">Edificio completo</option></select></div>
          <div class="pcas-field"><label>Tecnico TIC</label><select class="pcas-select" name="user_id" required><option value="">Elige tecnico...</option><?php foreach ($techs as $t): ?><option value="<?= (int)$t['id'] ?>"><?= pcas_h($t['label']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="pcas-row">
          <div class="pcas-field rule-building"><label>Edificio</label><select class="pcas-select" name="building" id="ruleBuilding"><option value="">Elige edificio...</option><?php foreach ($buildings as $b): if ($b==='') continue; ?><option value="<?= pcas_h($b) ?>"><?= pcas_h($b) ?></option><?php endforeach; ?></select></div>
          <div class="pcas-field rule-floor"><label>Planta</label><select class="pcas-select" name="floor" id="ruleFloor"><option value="">Elige planta...</option></select></div>
        </div>
        <div class="pcas-field rule-code"><label>Aula</label><select class="pcas-select" name="code"><option value="">Elige aula...</option><?php foreach ($aulas as $a): $code=(string)($a['codigo'] ?? ''); if ($code==='') continue; ?><option value="<?= pcas_h($code) ?>"><?= pcas_h(($a['building'] ?? '') . ' · ' . ($a['aula'] ?? '') . ' · ' . ($a['planta'] ?? '') . ' · ' . $code) ?></option><?php endforeach; ?></select></div>
        <button class="pcas-btn red" type="submit"><?= pcas_icon('plus') ?> Crear regla</button>
      </form>
      <div class="pcas-rules">
        <?php if (!$cfg['rules']): ?><div class="pcas-note">Aun no hay reglas. Puedes empezar por aulas concretas y despues crear una regla general por planta o edificio.</div><?php endif; ?>
        <?php foreach ($cfg['rules'] as $rule): ?>
          <article class="pcas-rule <?= empty($rule['enabled'])?'off':'' ?>">
            <div><b><?= pcas_h(pcas_rule_label($rule, $aulas)) ?></b><small><?= pcas_h(ucfirst((string)($rule['type'] ?? 'aula'))) ?> · Tecnico: <?= pcas_h(pcas_tech_label((int)($rule['user_id'] ?? 0), $techs)) ?> · <?= empty($rule['enabled'])?'Desactivada':'Activa' ?></small></div>
            <div class="pcas-rule-actions">
              <form method="post"><?php pcas_csrf(); ?><input type="hidden" name="pc_action" value="toggle_rule"><input type="hidden" name="rule_id" value="<?= pcas_h($rule['id'] ?? '') ?>"><button class="pcas-btn small" type="submit"><?= pcas_h(empty($rule['enabled'])?'Activar':'Pausar') ?></button></form>
              <form method="post" onsubmit="return confirm('Eliminar esta regla?')"><?php pcas_csrf(); ?><input type="hidden" name="pc_action" value="delete_rule"><input type="hidden" name="rule_id" value="<?= pcas_h($rule['id'] ?? '') ?>"><button class="pcas-btn small red" type="submit"><?= pcas_icon('trash') ?> Eliminar</button></form>
            </div>
            <details class="pcas-edit">
              <summary><?= pcas_icon('tools') ?> Editar regla</summary>
              <form method="post" class="pcas-form" style="margin-top:12px"><?php pcas_csrf(); ?><input type="hidden" name="pc_action" value="update_rule"><input type="hidden" name="rule_id" value="<?= pcas_h($rule['id'] ?? '') ?>">
                <label class="pcas-check"><input type="checkbox" name="enabled" value="1"<?= pcas_chk($rule['enabled'] ?? true) ?>> Regla activa</label>
                <div class="pcas-row">
                  <div class="pcas-field"><label>Tipo</label><select class="pcas-select" name="rule_type"><option value="aula"<?= pcas_sel($rule['type'] ?? 'aula','aula') ?>>Aula concreta</option><option value="planta"<?= pcas_sel($rule['type'] ?? '','planta') ?>>Planta completa</option><option value="edificio"<?= pcas_sel($rule['type'] ?? '','edificio') ?>>Edificio completo</option></select></div>
                  <div class="pcas-field"><label>Técnico TIC</label><select class="pcas-select" name="user_id" required><option value="">Elige técnico...</option><?php foreach ($techs as $t): ?><option value="<?= (int)$t['id'] ?>"<?= pcas_sel((int)($rule['user_id'] ?? 0),(int)$t['id']) ?>><?= pcas_h($t['label']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="pcas-row">
                  <div class="pcas-field"><label>Edificio</label><select class="pcas-select" name="building"><option value="">Sin edificio / no aplica</option><?php foreach ($buildings as $b): if ($b==='') continue; ?><option value="<?= pcas_h($b) ?>"<?= pcas_sel($rule['building'] ?? '', $b) ?>><?= pcas_h($b) ?></option><?php endforeach; ?></select></div>
                  <div class="pcas-field"><label>Planta</label><input class="pcas-input" name="floor" value="<?= pcas_h($rule['floor'] ?? '') ?>" placeholder="Ej: P0, P1, SOT"></div>
                </div>
                <div class="pcas-field"><label>Aula</label><select class="pcas-select" name="code"><option value="">Sin aula / no aplica</option><?php foreach ($aulas as $a): $code=(string)($a['codigo'] ?? ''); if ($code==='') continue; ?><option value="<?= pcas_h($code) ?>"<?= pcas_sel($rule['code'] ?? '', $code) ?>><?= pcas_h(($a['building'] ?? '') . ' · ' . ($a['aula'] ?? '') . ' · ' . ($a['planta'] ?? '') . ' · ' . $code) ?></option><?php endforeach; ?></select></div>
                <div class="pcas-field"><label>Etiqueta interna opcional</label><input class="pcas-input" name="label" value="<?= pcas_h($rule['label'] ?? '') ?>" placeholder="Ej: Técnico edificio 1"></div>
                <button class="pcas-btn primary" type="submit"><?= pcas_icon('save') ?> Guardar cambios de la regla</button>
              </form>
            </details>
          </article>
        <?php endforeach; ?>
      </div>
    </main>
    <aside class="pcas-card">
      <h2><?= pcas_icon('user') ?> Como funciona</h2>
      <div class="pcas-mini">
        <div class="pcas-mini-item"><?= pcas_icon('ticket') ?><span><b>1. Profesor crea incidencia</b>El ticket se genera desde la pagina guiada normal, sin cambiar el flujo.</span></div>
        <div class="pcas-mini-item"><?= pcas_icon('map') ?><span><b>2. Se mira la ubicacion</b>El plugin busca si el aula coincide con una regla de aula, planta o edificio.</span></div>
        <div class="pcas-mini-item"><?= pcas_icon('user') ?><span><b>3. Se asigna al tecnico</b>Solo se asigna a usuarios con perfil Tecnico TIC para evitar errores.</span></div>
        <div class="pcas-mini-item"><?= pcas_icon('bolt') ?><span><b>4. Reglas manuales</b>El boton Aplicar reglas revisa tickets abiertos sin tecnico y los reparte.</span></div>
      </div>
      <div class="pcas-note" style="margin-top:16px">Prioridad de reglas: aula concreta primero, despues planta, despues edificio y por ultimo tecnico por defecto.</div>
    </aside>
  </section>
</div></div>
<script>
(function(){
  const floors = <?= json_encode($floorsByBuilding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const type = document.getElementById('ruleType'), building = document.getElementById('ruleBuilding'), floor = document.getElementById('ruleFloor');
  function refreshFloors(){ const b = building.value; floor.innerHTML = '<option value="">Elige planta...</option>'; if(floors[b]) Object.keys(floors[b]).sort().forEach(f=>{ const o=document.createElement('option'); o.value=f; o.textContent=f; floor.appendChild(o); }); }
  function refreshVisibility(){ const t=type.value; document.querySelectorAll('.rule-code').forEach(e=>e.classList.toggle('pcas-hidden', t!=='aula')); document.querySelectorAll('.rule-floor').forEach(e=>e.classList.toggle('pcas-hidden', t!=='planta')); document.querySelectorAll('.rule-building').forEach(e=>e.classList.toggle('pcas-hidden', t==='aula')); }
  building && building.addEventListener('change',refreshFloors); type && type.addEventListener('change',refreshVisibility); refreshFloors(); refreshVisibility();
})();
</script>
<?php Html::footer(); ?>
