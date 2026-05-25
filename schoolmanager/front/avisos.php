<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
require_once(__DIR__ . '/../inc/stats_helpers.php');
$root=$CFG_GLPI['root_doc'] ?? ''; $logoUrl=function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root.'/plugins/schoolmanager/logo.svg');
$isTech = plugin_schoolmanager_user_mode()==='tecnico';
[$tickets,$loadError]=smgr_fetch_tickets(500,!$isTech);
$items=[]; foreach($tickets as $t){ $st=(int)($t['status']??0); $p=(int)($t['priority']??3); $msg=''; $type='info'; if($st==4){$msg='La solicitud está en espera. Puede que el equipo TIC necesite información o material.';$type='wait';} elseif($st==5){$msg='Solicitud resuelta. Revisa la solución y confirma si está correcto.';$type='done';} elseif($st<5 && $p>=4){$msg='Prioridad alta. Conviene revisarla cuanto antes.';$type='high';} elseif($st<5){$last=smgr_fetch_last_public_followup((int)$t['id']); if($last){$msg='Hay una respuesta reciente del equipo TIC.';$type='reply';}} if($msg){$t['notice']=$msg;$t['notice_type']=$type;$items[]=$t;} }
Html::header('Avisos', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');
require_once(__DIR__ . '/../inc/ui_helpers.php');
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
?>
<style>
.av{--teal:#0b4f6c;--teal2:#07384d;--red:#9f1f24;--red2:#b72c31;--line:#d9e7ef;--muted:#627386;--ink:#102638;min-height:calc(100vh - 76px);padding:clamp(10px,1.4vw,22px);background:linear-gradient(135deg,#f6f9fc,#eef6fb);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
.av-wrap{max-width:1300px;margin:0 auto;display:grid;gap:14px}.av-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;background:#fffdfa;border:1px solid var(--line);border-radius:28px;padding:18px 24px;box-shadow:0 18px 42px rgba(7,56,77,.08)}
.av-brand{display:flex;gap:18px;align-items:center;min-width:0}.av-logo{height:76px;max-width:230px;object-fit:contain;mix-blend-mode:multiply;background:transparent;border:0;box-shadow:none}.av h1{margin:0;color:var(--teal2);font-size:clamp(38px,4.2vw,58px);letter-spacing:-.05em;line-height:.92}.av-sub{color:var(--muted);font-weight:900;font-size:clamp(14px,1.25vw,18px);line-height:1.15}.av-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.av-btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:52px;border:1px solid var(--line);border-radius:18px;padding:0 20px;text-decoration:none!important;color:var(--teal2)!important;font-weight:950;background:#fff;box-shadow:0 12px 28px rgba(7,56,77,.07);transition:transform .22s cubic-bezier(.2,.8,.2,1),box-shadow .22s ease,background .22s ease,border-color .22s ease,color .22s ease}.av-btn:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.04) 30%,rgba(255,255,255,.18) 48%,rgba(255,255,255,.04) 66%,transparent 100%);transform:translateX(-135%);transition:transform .55s ease;pointer-events:none}.av-btn:hover{transform:translateY(-4px);box-shadow:0 22px 44px rgba(7,56,77,.14);border-color:#a9c2cf;background:#f8fbfd}.av-btn:hover:before{transform:translateX(135%)}.av-btn i,.av-btn svg{position:relative;z-index:1;font-size:18px;line-height:1}.av-btn span{position:relative;z-index:1}.av-btn.primary{background:linear-gradient(135deg,var(--teal) 0%,#0b6379 100%);border-color:#0a526a;color:#fff!important;box-shadow:0 18px 38px rgba(7,56,77,.18)}.av-btn.primary:hover{background:linear-gradient(135deg,#0b5f7a 0%,#117a80 100%);box-shadow:0 24px 46px rgba(7,56,77,.24)}.av-btn.home{background:linear-gradient(135deg,var(--red) 0%,var(--red2) 100%);border-color:#922020;color:#fff!important;box-shadow:0 18px 38px rgba(165,31,36,.22)}.av-btn.home:hover{box-shadow:0 24px 46px rgba(165,31,36,.28);background:linear-gradient(135deg,#a32323 0%,#c23636 100%)}
.av-list{display:grid;gap:12px}.av-item{background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:24px;padding:18px;display:grid;grid-template-columns:auto 1fr auto;gap:14px;align-items:center;box-shadow:0 14px 36px rgba(7,56,77,.07);transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease,background .22s ease}.av-item:hover{transform:translateY(-3px);box-shadow:0 22px 46px rgba(7,56,77,.12);border-color:#b8d4df}.av-ico{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;font-size:26px;background:#e8f7f5;color:var(--teal2);box-shadow:inset 0 0 0 1px rgba(7,56,77,.05)}.av-item h2{margin:0;color:var(--teal2);font-size:clamp(20px,2vw,26px);letter-spacing:-.025em}.av-item p{margin:5px 0;color:var(--muted);font-weight:900}.av-meta{color:#3f5964!important}.av-pill{border-radius:999px;padding:7px 10px;font-weight:950}.av-item.wait{background:#fbf7ff;border-color:#d7c2f4}.av-item.done{background:#f1fff5;border-color:#a9dfb8}.av-item.high{background:#fff5f5;border-color:#efb6b6}.av-item.reply{background:#fffaf0;border-color:#efd58b}.av-item.info{background:#f4fbfd;border-color:#b7dce9}.av-item.wait .av-ico{background:#f2eaff;color:#5c3796}.av-item.done .av-ico{background:#e4f8eb;color:#176a31}.av-item.high .av-ico{background:#ffe1e1;color:#a42020}.av-item.reply .av-ico{background:#fff3ca;color:#806000}.av-item.info .av-ico{background:#e8f7f5;color:#07384d}.av-empty{background:#fff;border:1px dashed var(--line);border-radius:22px;padding:28px;text-align:center;color:var(--muted);font-weight:900;box-shadow:0 12px 28px rgba(7,56,77,.05)}
@media(max-width:860px){.av-hero{align-items:flex-start;flex-direction:column}.av-actions{width:100%;justify-content:flex-start}.av-item{grid-template-columns:auto 1fr}.av-item .av-btn{grid-column:1/-1;width:100%}.av-logo{height:62px}.av h1{font-size:42px}}
@media(max-width:560px){.av{padding:8px}.av-hero{border-radius:22px;padding:16px}.av-brand{align-items:flex-start;gap:12px}.av-logo{height:50px;max-width:160px}.av-item{grid-template-columns:1fr;padding:15px}.av-ico{width:50px;height:50px}.av-btn{width:100%}}
</style>
<style id="gestion-schoolmanager-global-override"><?php @readfile(__DIR__ . '/../css/gestion-schoolmanager-theme.css'); ?></style>


<style id="schoolmanager-v251-avisos-buttons">
.av .av-btn.home{background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%)!important;border-color:#7c1b1b!important;color:#fff!important;box-shadow:0 18px 38px rgba(139,30,30,.24)!important;}
.av .av-btn.home:hover{background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%)!important;transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(139,30,30,.30)!important;}
.av .av-btn.home i{font-size:19px!important;line-height:1!important;}
.av .av-btn.primary i{font-size:18px!important;line-height:1!important;}

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

<style id="pc-local-icons-hotfix">
/* Hotfix iconos locales: evita cuadrados cuando no carga ninguna fuente de iconos */
.pc-svgicon,.gsm-svgicon{display:inline-block!important;width:20px!important;height:20px!important;min-width:20px!important;flex:0 0 auto!important;background:transparent!important;color:currentColor!important;border:0!important;box-shadow:none!important;text-indent:0!important;overflow:visible!important;-webkit-mask:none!important;mask:none!important;line-height:1!important;vertical-align:middle!important;}
.pc-svgicon:before,.gsm-svgicon:before{content:""!important;display:block!important;width:100%!important;height:100%!important;background:currentColor!important;-webkit-mask:var(--pc-icon) center/contain no-repeat!important;mask:var(--pc-icon) center/contain no-repeat!important;}
.pc-i-home,.gsm-i-home{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M3.8%2010.6%2012%204.3l8.2%206.3%22%2F%3E%3Cpath%20d%3D%22M6.8%209.8v9.7h10.4V9.8%22%2F%3E%3Cpath%20d%3D%22M10%2019.5v-5a2%202%200%200%201%204%200v5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-plus,.gsm-i-plus{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%205v14M5%2012h14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-list,.gsm-i-list{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M8%206h12M8%2012h12M8%2018h12%22%2F%3E%3Cpath%20d%3D%22M4%206h.01M4%2012h.01M4%2018h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-search,.gsm-i-search{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Ccircle%20cx%3D%2211%22%20cy%3D%2211%22%20r%3D%227%22%2F%3E%3Cpath%20d%3D%22m20%2020-3.5-3.5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-eye,.gsm-i-eye{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M2.8%2012s3.5-6%209.2-6%209.2%206%209.2%206-3.5%206-9.2%206-9.2-6-9.2-6Z%22%2F%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2212%22%20r%3D%222.8%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-external,.gsm-i-external{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M14%205h5v5%22%2F%3E%3Cpath%20d%3D%22M10%2014%2019%205%22%2F%3E%3Cpath%20d%3D%22M19%2013v5H5V5h5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-info,.gsm-i-info{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2212%22%20r%3D%229%22%2F%3E%3Cpath%20d%3D%22M12%2010v6%22%2F%3E%3Cpath%20d%3D%22M12%207.5h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-check,.gsm-i-check{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4.5%2012.5%209.2%2017%2019.5%207%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-alert,.gsm-i-alert{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%203%202.8%2020h18.4L12%203Z%22%2F%3E%3Cpath%20d%3D%22M12%209v5M12%2017h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-warning,.gsm-i-warning{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%203%202.8%2020h18.4L12%203Z%22%2F%3E%3Cpath%20d%3D%22M12%209v5M12%2017h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-clock,.gsm-i-clock{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2212%22%20r%3D%228.5%22%2F%3E%3Cpath%20d%3D%22M12%207.5V12l3%202%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-message,.gsm-i-message{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%205.5h16v10H8l-4%203v-13Z%22%2F%3E%3Cpath%20d%3D%22M8%209h8M8%2012h5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-location,.gsm-i-location{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%2021s7-5.2%207-11a7%207%200%201%200-14%200c0%205.8%207%2011%207%2011Z%22%2F%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2210%22%20r%3D%222.5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-pin,.gsm-i-pin{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%2021s7-5.2%207-11a7%207%200%201%200-14%200c0%205.8%207%2011%207%2011Z%22%2F%3E%3Ccircle%20cx%3D%2212%22%20cy%3D%2210%22%20r%3D%222.5%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-map,.gsm-i-map{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M9%2018%203.5%2020V6L9%204l6%202%205.5-2v14L15%2020l-6-2Z%22%2F%3E%3Cpath%20d%3D%22M9%204v14M15%206v14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-ticket,.gsm-i-ticket{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%207.5A2.5%202.5%200%200%201%206.5%205h11A2.5%202.5%200%200%201%2020%207.5V10a2%202%200%200%200%200%204v2.5a2.5%202.5%200%200%201-2.5%202.5h-11A2.5%202.5%200%200%201%204%2016.5V14a2%202%200%200%200%200-4V7.5Z%22%2F%3E%3Cpath%20d%3D%22M13%205v14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-box,.gsm-i-box{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%207.5%2012%203l8%204.5v9L12%2021l-8-4.5v-9Z%22%2F%3E%3Cpath%20d%3D%22M4%207.5%2012%2012l8-4.5%22%2F%3E%3Cpath%20d%3D%22M12%2012v9%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-keyboard,.gsm-i-keyboard{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%223%22%20y%3D%226%22%20width%3D%2218%22%20height%3D%2212%22%20rx%3D%222.2%22%2F%3E%3Cpath%20d%3D%22M7%2010h.01M10%2010h.01M13%2010h.01M16%2010h.01M7%2014h10%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-printer,.gsm-i-printer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M7%208V4h10v4%22%2F%3E%3Crect%20x%3D%227%22%20y%3D%2214%22%20width%3D%2210%22%20height%3D%226%22%20rx%3D%221.2%22%2F%3E%3Cpath%20d%3D%22M7%2018H5a2%202%200%200%201-2-2v-5a3%203%200%200%201%203-3h12a3%203%200%200%201%203%203v5a2%202%200%200%201-2%202h-2%22%2F%3E%3Cpath%20d%3D%22M17%2011h.01%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-filter,.gsm-i-filter{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%205h16l-6.5%207.5V19l-3%201v-7.5L4%205Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-refresh,.gsm-i-refresh{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M20%206v5h-5%22%2F%3E%3Cpath%20d%3D%22M4%2018v-5h5%22%2F%3E%3Cpath%20d%3D%22M18.5%209A7%207%200%200%200%206.2%206.2%22%2F%3E%3Cpath%20d%3D%22M5.5%2015A7%207%200%200%200%2017.8%2017.8%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-minus,.gsm-i-minus{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M5%2012h14%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-computer,.gsm-i-computer{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%223.5%22%20y%3D%224.5%22%20width%3D%2217%22%20height%3D%2211.2%22%20rx%3D%222.2%22%2F%3E%3Cpath%20d%3D%22M8.3%2020h7.4M12%2015.7V20%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-monitor,.gsm-i-monitor{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%223.5%22%20y%3D%224.5%22%20width%3D%2217%22%20height%3D%2211.2%22%20rx%3D%222.2%22%2F%3E%3Cpath%20d%3D%22M8.3%2020h7.4M12%2015.7V20%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-network,.gsm-i-network{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%225%22%20y%3D%2213%22%20width%3D%2214%22%20height%3D%226%22%20rx%3D%222%22%2F%3E%3Cpath%20d%3D%22M8%2016h.01M11%2016h.01M14%2016h.01%22%2F%3E%3Cpath%20d%3D%22M8%209a6%206%200%200%201%208%200%22%2F%3E%3Cpath%20d%3D%22M10.3%2011.2a3%203%200%200%201%203.4%200%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-phone,.gsm-i-phone{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M8%203h8a1.5%201.5%200%200%201%201.5%201.5v15A1.5%201.5%200%200%201%2016%2021H8a1.5%201.5%200%200%201-1.5-1.5v-15A1.5%201.5%200%200%201%208%203Z%22%2F%3E%3Cpath%20d%3D%22M11%2018h2%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-tools,.gsm-i-tools{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M14.7%206.3a4%204%200%200%200-5.4%205.4L4.8%2016.2a2%202%200%200%200%202.8%202.8l4.5-4.5a4%204%200%200%200%205.5-5.2l-2.7%202.7-2.2-2.2%202.7-2.7Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-note,.gsm-i-note{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M6%203h9l3%203v15H6V3Z%22%2F%3E%3Cpath%20d%3D%22M14%203v4h4%22%2F%3E%3Cpath%20d%3D%22M9%2012h6M9%2016h6%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-lock,.gsm-i-lock{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Crect%20x%3D%225%22%20y%3D%2210%22%20width%3D%2214%22%20height%3D%2210%22%20rx%3D%222%22%2F%3E%3Cpath%20d%3D%22M8%2010V7a4%204%200%200%201%208%200v3%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-bell,.gsm-i-bell{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M18%209a6%206%200%200%200-12%200c0%207-3%207-3%207h18s-3%200-3-7%22%2F%3E%3Cpath%20d%3D%22M10%2020a2.4%202.4%200%200%200%204%200%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-chart,.gsm-i-chart{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M4%2019V5%22%2F%3E%3Cpath%20d%3D%22M8%2017V9M13%2017V5M18%2017v-6%22%2F%3E%3Cpath%20d%3D%22M3%2019h18%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-fire,.gsm-i-fire{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M12%2022c4%200%207-2.7%207-6.8%200-3.4-2.4-5.9-4.3-7.8-.3%202.7-1.6%204.1-3.1%205.2.1-3.4-1.1-6.5-3.5-8.6.2%204.2-3.1%206.1-3.1%2011.1C5%2019.3%208%2022%2012%2022Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-puzzle,.gsm-i-puzzle{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M8%203h5v4h3a2%202%200%201%201%200%204h-3v3h-4v3a2%202%200%201%201-4%200v-3H3V9h5V3Z%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-construction,.gsm-i-construction{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M3%2021h18%22%2F%3E%3Cpath%20d%3D%22M5%2021V8l7-4%207%204v13%22%2F%3E%3Cpath%20d%3D%22M9%2021v-6h6v6%22%2F%3E%3C%2Fsvg%3E")!important;}
.pc-i-arrow-left,.gsm-i-arrow-left{--pc-icon:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22black%22%20stroke-width%3D%222.35%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22M19%2012H5%22%2F%3E%3Cpath%20d%3D%22m12%205-7%207%207%207%22%2F%3E%3C%2Fsvg%3E")!important;}
</style>
<div class="av"><div class="av-wrap"><section class="av-hero"><div class="av-brand"><img class="av-logo" src="<?= smgr_h($logoUrl) ?>" alt="Logo del centro" onerror="this.onerror=null;this.src='<?= smgr_h($root) ?>/plugins/schoolmanager/logo.svg?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>';"><div><h1>Avisos</h1><div class="av-sub"><?= $isTech?'Alertas importantes del soporte TIC.':'Actualizaciones importantes de tus solicitudes.' ?></div></div></div><div class="av-actions"><a class="av-btn home" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/formularios.php?v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.75 10.5 12 4.25l8.25 6.25"/><path d="M6.75 9.75v9.5h10.5v-9.5"/><path d="M10 19.25V14.5a2 2 0 0 1 4 0v4.75"/></svg><span>Inicio</span></a></div></section>
<?php if ($loadError): ?><div class="av-empty">No se pudieron cargar los avisos: <?= smgr_h($loadError) ?></div><?php endif; ?><section class="av-list"><?php if(!$items && !$loadError): ?><div class="av-empty">No hay avisos pendientes.</div><?php endif; ?><?php foreach($items as $t): [$sl,$sc]=smgr_status_label($t['status']); $ico=$t['notice_type']==='done'?'<span class="pc-svgicon pc-i-check" aria-hidden="true"></span>':($t['notice_type']==='high'?'<span class="pc-svgicon pc-i-alert" aria-hidden="true"></span>':($t['notice_type']==='wait'?'<span class="pc-svgicon pc-i-clock" aria-hidden="true"></span>':'<span class="pc-svgicon pc-i-message" aria-hidden="true"></span>')); ?><article class="av-item <?= smgr_h($t['notice_type'] ?? 'info') ?>"><div class="av-ico"><?= $ico ?></div><div><h2>#<?= (int)$t['id'] ?> · <?= smgr_h($t['name']) ?></h2><p><?= smgr_h($t['notice']) ?></p><p class="av-meta"><?= smgr_h($t['location_name'] ?: 'Sin ubicación') ?> · <?= smgr_h($t['category_name'] ?: 'Sin categoría') ?></p></div><a class="av-btn primary" href="<?= smgr_h($root) ?>/plugins/schoolmanager/front/solicitud_detalle.php?id=<?= (int)$t['id'] ?>&v=<?= urlencode(PLUGIN_SCHOOLMANAGER_VERSION) ?>"><svg class="pc-btn-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg><span>Ver detalle</span></a></article><?php endforeach; ?></section></div></div>
<style id="v255-avisos-iconos-unificados">
.av .av-btn .pc-svgicon{width:18px!important;height:18px!important;background:transparent!important;color:currentColor!important;margin:0!important;}
.av .av-btn.home .pc-svgicon{background:transparent!important;color:#fff!important;}
</style>


<?php Html::footer(); ?>


<style id="v254-home-icon-direct">
.pc-btn-home .pc-home-badge,.av-btn.home .pc-home-badge{display:none!important}
.pc-btn-home i,.av-btn.home i{display:inline-block!important;color:#fff!important;font-size:18px!important;line-height:1!important;background:transparent!important;border:0!important;box-shadow:none!important}
.pc-btn-home,.av-btn.home{border-radius:18px!important;background:linear-gradient(135deg,#8f1d1d 0%,#b72e2e 100%)!important;border-color:#922020!important}
</style>




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



<style id="v258-avisos-final">
.av .av-btn{border-radius:18px!important;gap:10px!important;min-height:54px!important;padding:0 24px!important;position:relative!important;overflow:hidden!important;transition:transform .22s cubic-bezier(.2,.8,.2,1),box-shadow .22s ease,background .22s ease,border-color .22s ease!important;}
.av .av-btn:before{content:""!important;position:absolute!important;inset:0!important;background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.06) 30%,rgba(255,255,255,.20) 48%,rgba(255,255,255,.06) 66%,transparent 100%)!important;transform:translateX(-135%)!important;transition:transform .55s ease!important;pointer-events:none!important;}
.av .av-btn:hover{transform:translateY(-4px)!important;box-shadow:0 26px 46px rgba(8,59,84,.22)!important;}
.av .av-btn:hover:before{transform:translateX(135%)!important;}
.av .av-btn.home{background:linear-gradient(135deg,#8b1e1e 0%,#b72c31 100%)!important;border-color:#7c1b1b!important;color:#fff!important;box-shadow:0 18px 38px rgba(139,30,30,.24)!important;}
.av .av-btn.home:hover{background:linear-gradient(135deg,#a32323 0%,#c23636 100%)!important;box-shadow:0 26px 46px rgba(139,30,30,.30)!important;}
.av .av-btn.primary{background:linear-gradient(135deg,#07384d 0%,#0b5f7a 100%)!important;border-color:#0a526a!important;color:#fff!important;}
.av .av-btn span,.av .av-btn svg{position:relative!important;z-index:1!important;}
</style>

