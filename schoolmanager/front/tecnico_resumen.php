<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');
require_once(__DIR__ . '/../inc/stock_helpers.php');

if (!(function_exists('smgr_can_manage_tic_assignments') && smgr_can_manage_tic_assignments())) {
    if (function_exists('plugin_schoolmanager_access_denied_page')) { plugin_schoolmanager_access_denied_page('Acceso restringido', 'Solo Admin TIC o Super-Admin puede ver el resumen de uso de material por técnico.'); }
    Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/formularios.php');
}

$root = $CFG_GLPI['root_doc'] ?? '';
$userId = (int)($_GET['id'] ?? 0);
function ptr_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ptr_svg($name){
    $icons=[
        'back'=>'<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'user'=>'<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'ticket'=>'<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6z"/>',
        'stock'=>'<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5"/>',
        'external'=>'<path d="M14 5h5v5"/><path d="M10 14 19 5"/><path d="M19 13v5H5V5h5"/>',
        'check'=>'<path d="M20 6 9 17l-5-5"/>',
    ];
    return '<svg class="tr-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'.($icons[$name]??$icons['user']).'</svg>';
}
function ptr_user($id){
    $id=(int)$id; $out=['id'=>$id,'name'=>'Usuario #'.$id,'login'=>'','email'=>''];
    if ($id>0 && class_exists('User')) { try { $u=new User(); if ($u->getFromDB($id)) { $name=trim(((string)($u->fields['firstname']??'')).' '.((string)($u->fields['realname']??''))); if($name===''){$name=(string)($u->fields['name']??('Usuario #'.$id));} $out=['id'=>$id,'name'=>$name,'login'=>(string)($u->fields['name']??''),'email'=>(string)($u->fields['email']??'')]; } } catch(Throwable $e){} }
    return $out;
}
function ptr_ticket_name($ticketId){
    $ticketId=(int)$ticketId; if($ticketId<=0 || !class_exists('Ticket')) return 'Incidencia #'.$ticketId;
    try { $t=new Ticket(); if($t->getFromDB($ticketId)) return trim((string)($t->fields['name']??'')) ?: ('Incidencia #'.$ticketId); } catch(Throwable $e){}
    return 'Incidencia #'.$ticketId;
}

$user=ptr_user($userId);
$db=smgr_db();
$assignedOpen=0; $resolved=0; $closed=0; $materialUnits=0; $materialKinds=[]; $recentTickets=[]; $recentMaterial=[];
if ($db && method_exists($db,'request') && $userId>0) {
    try {
        $it=$db->request(['FROM'=>'glpi_tickets_users','WHERE'=>['users_id'=>$userId,'type'=>2],'LIMIT'=>3000]);
        $ticketIds=[]; foreach($it as $r){$tid=(int)($r['tickets_id']??0); if($tid>0)$ticketIds[$tid]=true;}
        foreach(array_keys($ticketIds) as $tid){
            $t=new Ticket(); if(!$t->getFromDB($tid)) continue; $st=(int)($t->fields['status']??0);
            if($st>=5){$resolved++; if($st>=6)$closed++; $recentTickets[]=['id'=>$tid,'name'=>(string)($t->fields['name']??('Incidencia #'.$tid)),'status'=>$st,'date'=>(string)($t->fields['date_mod']??'')];}
            else {$assignedOpen++;}
        }
        usort($recentTickets, fn($a,$b)=>strcmp($b['date'],$a['date'])); $recentTickets=array_slice($recentTickets,0,10);
    } catch(Throwable $e){}
}
foreach(['consumable','cartridge'] as $kind){
    foreach(smgr_stock_items($kind,'','all') as $item){
        $itemId=(int)($item['id']??0); if($itemId<=0) continue;
        $label=smgr_stock_item_display_name($kind, smgr_stock_db_row_to_array($item));
        foreach(smgr_stock_unit_source_rows($kind,$itemId,10000) as $u){
            $u=smgr_stock_db_row_to_array($u); if(smgr_stock_unit_is_available($kind,$u)) continue;
            $uid=function_exists('smgr_stock_unit_technician_id')?smgr_stock_unit_technician_id($u):(int)($u['users_id']??0);
            if($uid!==$userId) continue;
            $materialUnits++; $materialKinds[$kind.':'.$itemId]=$label;
            $tid=function_exists('smgr_stock_unit_ticket_id')?smgr_stock_unit_ticket_id($u):0;
            $recentMaterial[]=['kind'=>$kind,'item_id'=>$itemId,'label'=>$label,'ticket_id'=>$tid,'date'=>function_exists('smgr_stock_unit_out_datetime_value')?smgr_stock_unit_out_datetime_value($u):((string)($u['date_out']??''))];
        }
    }
}
usort($recentMaterial, fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date'])); $recentMaterial=array_slice($recentMaterial,0,15);

Html::header('Resumen técnico TIC', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php'); echo plugin_schoolmanager_home_button();
?>
<style>.tr{--navy:#07384d;--teal:#0c6672;--red:#b6252b;--line:#dbe9ef;--muted:#657887;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:#f5fafc;min-height:100vh;padding:24px;color:#0b2f40}.tr *{box-sizing:border-box}.tr-wrap{max-width:1280px;margin:0 auto;display:grid;gap:16px}.tr-card,.tr-hero{background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 14px 35px rgba(7,56,77,.07);padding:20px}.tr-hero{display:flex;justify-content:space-between;gap:16px;align-items:center}.tr h1{margin:0;color:var(--navy);font-size:clamp(34px,4vw,54px);letter-spacing:-.04em}.tr-sub{color:var(--muted);font-weight:850}.tr-svg{width:18px;height:18px;vertical-align:-4px}.tr-actions{display:flex;gap:10px;flex-wrap:wrap}.tr-btn{display:inline-flex;align-items:center;gap:8px;justify-content:center;text-decoration:none!important;border:1px solid var(--line);border-radius:15px;background:#fff;color:var(--navy)!important;font-weight:950;padding:12px 15px}.tr-btn.primary{background:linear-gradient(135deg,var(--teal),var(--navy));border-color:var(--navy);color:#fff!important}.tr-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.tr-metric{background:#fff;border:1px solid var(--line);border-radius:20px;padding:16px}.tr-metric b{font-size:34px;color:var(--navy);display:block}.tr-metric span{font-weight:900;color:var(--muted)}.tr-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.tr-list{display:grid;gap:8px}.tr-item{border:1px solid var(--line);border-radius:16px;padding:12px;background:#fbfdfe;display:flex;justify-content:space-between;gap:10px}.tr-item a{font-weight:950;color:var(--teal)!important;text-decoration:underline!important}.tr-small{font-size:12px;color:var(--muted);font-weight:850}.tr-empty{border:1px dashed #bdd6df;border-radius:16px;padding:20px;text-align:center;color:var(--muted);font-weight:900}@media(max-width:900px){.tr-hero{display:grid}.tr-metrics,.tr-grid{grid-template-columns:1fr}.tr-actions{display:grid}.tr-btn{width:100%}}</style>
<div class="tr"><div class="tr-wrap">
  <section class="tr-hero"><div><div class="tr-sub">Gestión School Manager · visible solo para Admin TIC</div><h1><?= ptr_h($user['name']) ?></h1><div class="tr-sub">Login: <?= ptr_h($user['login'] ?: 'No disponible') ?><?= $user['email']!==''?' · Email: '.ptr_h($user['email']):'' ?></div></div><div class="tr-actions"><a class="tr-btn" href="<?= ptr_h($root) ?>/plugins/schoolmanager/front/stock_glpi.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= ptr_svg('stock') ?> Stock</a><a class="tr-btn primary" href="<?= ptr_h($root) ?>/front/user.form.php?id=<?= (int)$userId ?>"><?= ptr_svg('external') ?> Perfil GLPI</a><a class="tr-btn" href="<?= ptr_h($root) ?>/plugins/schoolmanager/front/panel_tic.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><?= ptr_svg('back') ?> Panel TIC</a></div></section>
  <section class="tr-metrics"><div class="tr-metric"><b><?= (int)$resolved ?></b><span>incidencias resueltas</span></div><div class="tr-metric"><b><?= (int)$assignedOpen ?></b><span>asignadas abiertas</span></div><div class="tr-metric"><b><?= (int)$materialUnits ?></b><span>unidades usadas</span></div><div class="tr-metric"><b><?= count($materialKinds) ?></b><span>tipos de material</span></div></section>
  <section class="tr-grid"><article class="tr-card"><h2><?= ptr_svg('ticket') ?> Últimas incidencias resueltas</h2><div class="tr-list"><?php if(!$recentTickets): ?><div class="tr-empty">Sin incidencias resueltas detectadas.</div><?php endif; foreach($recentTickets as $t): ?><div class="tr-item"><div><a href="<?= ptr_h($root) ?>/plugins/schoolmanager/front/solicitud_detalle.php?id=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> · <?= ptr_h($t['name']) ?></a><div class="tr-small">Actualizada: <?= ptr_h($t['date']) ?></div></div><span class="tr-small"><?= ((int)$t['status']>=6?'Cerrada':'Resuelta') ?></span></div><?php endforeach; ?></div></article>
  <article class="tr-card"><h2><?= ptr_svg('stock') ?> Material usado por este técnico</h2><div class="tr-list"><?php if(!$recentMaterial): ?><div class="tr-empty">Sin material descontado por este técnico.</div><?php endif; foreach($recentMaterial as $m): ?><div class="tr-item"><div><a href="<?= ptr_h(smgr_stock_item_url($m['kind'],$m['item_id'])) ?>"><?= ptr_h($m['label']) ?></a><div class="tr-small"><?= ptr_h($m['date'] ?: 'Sin fecha') ?><?php if((int)$m['ticket_id']>0): ?> · <a href="<?= ptr_h($root) ?>/plugins/schoolmanager/front/solicitud_detalle.php?id=<?= (int)$m['ticket_id'] ?>">incidencia #<?= (int)$m['ticket_id'] ?></a><?php endif; ?></div></div><span class="tr-small">1 unidad</span></div><?php endforeach; ?></div></article></section>
</div></div><?php Html::footer(); ?>
