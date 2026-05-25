<?php
require_once(__DIR__ . '/config.php');
$smgr_modal_buildings = plugin_schoolmanager_buildings();
$smgr_modal_default_building = plugin_schoolmanager_default_building_code();
$smgr_modal_floors = [];
$smgr_modal_floor_defaults = [];
foreach ($smgr_modal_buildings as $smgr_b) {
    $smgr_bc = strtoupper((string)$smgr_b['code']);
    $smgr_modal_floors[$smgr_bc] = [];
    foreach (plugin_schoolmanager_floors($smgr_bc) as $smgr_f) {
        $smgr_fc = strtoupper((string)$smgr_f['code']);
        if (!isset($smgr_modal_floor_defaults[$smgr_bc])) { $smgr_modal_floor_defaults[$smgr_bc] = $smgr_fc; }
        $smgr_modal_floors[$smgr_bc][$smgr_fc] = [(string)($smgr_f['number'] ?? $smgr_fc), plugin_schoolmanager_label($smgr_f, 'label', $smgr_fc)];
    }
}
?>
<style id="pc-location-modal-v206">
/* v206 - Modal selector REAL: se mueve al body y queda por encima de GLPI */
body.pc-location-modal-open{overflow:hidden!important;}
.pc-modal-backdrop{
  position:fixed!important;
  inset:0!important;
  width:100vw!important;
  height:100vh!important;
  z-index:2147483647!important;
  display:none!important;
  align-items:center!important;
  justify-content:center!important;
  padding:24px!important;
  background:rgba(4,24,34,.64)!important;
  backdrop-filter:blur(4px)!important;
  box-sizing:border-box!important;
  overflow:hidden!important;
}
.pc-modal-backdrop.show{display:flex!important;}
.pc-modal-backdrop *{box-sizing:border-box!important;}
.pc-modal{
  width:min(960px,calc(100vw - 64px))!important;
  height:min(640px,calc(100vh - 64px))!important;
  max-width:960px!important;
  max-height:640px!important;
  margin:0!important;
  background:#fff!important;
  border:1px solid #d6e6ee!important;
  border-radius:22px!important;
  overflow:hidden!important;
  box-shadow:0 30px 90px rgba(7,56,77,.36)!important;
  display:grid!important;
  grid-template-rows:52px minmax(0,1fr)!important;
  font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif!important;
  color:#082f3f!important;
}
.pc-modal-head{display:flex!important;align-items:center!important;gap:12px!important;padding:10px 14px!important;border-bottom:1px solid #dbe9ef!important;background:#fff!important;min-height:52px!important;}
.pc-modal-head h2{margin:0!important;flex:1!important;color:#07384d!important;font-size:22px!important;font-weight:950!important;line-height:1!important;}
.pc-modal-body{display:grid!important;grid-template-columns:265px minmax(0,1fr)!important;min-height:0!important;height:100%!important;background:#f7fafc!important;}
.pc-modal-left{min-height:0!important;padding:10px!important;border-right:1px solid #dbe9ef!important;display:grid!important;grid-template-rows:auto auto minmax(0,1fr)!important;gap:8px!important;background:#fff!important;overflow:hidden!important;}
.pc-modal-right{min-height:0!important;padding:10px!important;display:grid!important;grid-template-rows:auto minmax(0,1fr) 70px!important;gap:8px!important;overflow:hidden!important;}
.pc-tabs{display:grid!important;grid-template-columns:1fr 1fr!important;gap:6px!important;background:#f1f6f8!important;border:1px solid #dbe9ef!important;border-radius:15px!important;padding:4px!important;}
.pc-tab{border:0!important;border-radius:12px!important;background:transparent!important;color:#07384d!important;font-weight:950!important;padding:9px 8px!important;cursor:pointer!important;font-size:14px!important;}
.pc-tab.active{background:#b6252b!important;color:#fff!important;box-shadow:none!important;}
.pc-room-search{width:100%!important;border:1px solid #dbe9ef!important;border-radius:14px!important;padding:11px 12px!important;font-weight:900!important;color:#082f3f!important;background:#fff!important;box-shadow:none!important;min-height:42px!important;}
.pc-room-search:focus{outline:2px solid rgba(11,95,122,.14)!important;border-color:#0b5f7a!important;}
.pc-room-list{min-height:0!important;overflow:auto!important;border:1px solid #dbe9ef!important;border-radius:16px!important;background:#fff!important;padding:5px!important;}
.pc-room-list::-webkit-scrollbar{width:8px!important}.pc-room-list::-webkit-scrollbar-thumb{background:#bdd5df!important;border-radius:99px!important}
.pc-room{width:100%!important;display:grid!important;grid-template-columns:38px minmax(0,1fr)!important;gap:9px!important;align-items:center!important;border:0!important;border-radius:13px!important;background:#fff!important;padding:8px!important;cursor:pointer!important;text-align:left!important;margin:0 0 4px!important;color:#082f3f!important;min-height:54px!important;}
.pc-room:hover{background:#f0f7fa!important}.pc-room.active{background:#fff6f6!important;box-shadow:inset 4px 0 0 #b6252b!important;}
.pc-avatar{display:grid!important;place-items:center!important;width:38px!important;height:38px!important;border-radius:13px!important;background:#e8f2f6!important;color:#07384d!important;font-weight:950!important;font-size:13px!important;}
.pc-room.active .pc-avatar{background:#b6252b!important;color:#fff!important;}
.pc-room-name{display:block!important;font-weight:950!important;color:#07384d!important;font-size:16px!important;line-height:1.05!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;}
.pc-room-desc{display:block!important;font-weight:850!important;color:#617386!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;font-size:11px!important;margin-top:3px!important;}
.pc-code{display:none!important;}
.pc-floorbar{display:flex!important;gap:8px!important;flex-wrap:wrap!important;align-items:center!important;min-height:42px!important;}
.pc-floor{border:1px solid #dbe9ef!important;background:#fff!important;border-radius:13px!important;padding:9px 11px!important;font-weight:950!important;color:#07384d!important;cursor:pointer!important;box-shadow:none!important;font-size:14px!important;}
.pc-floor:hover{background:#f0f7fa!important}.pc-floor.active{background:#b6252b!important;border-color:#b6252b!important;color:#fff!important;}
.pc-plan{border:1px solid #dbe9ef!important;border-radius:18px!important;overflow:hidden!important;min-height:0!important;background:#fff!important;box-shadow:none!important;display:block!important;}
.pc-plan iframe{width:100%!important;height:100%!important;min-height:0!important;border:0!important;display:block!important;background:#fff!important;}
.pc-choice{border:1px solid #dbe9ef!important;border-radius:18px!important;background:#fff!important;padding:10px!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:10px!important;align-items:center!important;min-height:70px!important;overflow:hidden!important;}
.pc-choice b{font-size:18px!important;color:#07384d!important;display:block!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;}
.pc-choice span{display:block!important;font-weight:850!important;color:#617386!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;font-size:12px!important;}
.pc-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;border:1px solid #dbe9ef!important;border-radius:14px!important;padding:10px 13px!important;font-weight:950!important;text-decoration:none!important;cursor:pointer!important;white-space:nowrap!important;background:#fff!important;color:#07384d!important;line-height:1!important;}
.pc-btn.primary{background:#07384d!important;border-color:#07384d!important;color:#fff!important;}.pc-btn.secondary{background:#eef6fa!important;color:#07384d!important}.pc-btn:hover{filter:brightness(.98)!important;transform:none!important;}
@media(max-width:980px){
 .pc-modal-backdrop{padding:12px!important;align-items:center!important;}
 .pc-modal{width:calc(100vw - 24px)!important;height:calc(100vh - 24px)!important;max-width:none!important;max-height:none!important;border-radius:18px!important;}
 .pc-modal-body{grid-template-columns:1fr!important;grid-template-rows:230px minmax(0,1fr)!important;}
 .pc-modal-left{border-right:0!important;border-bottom:1px solid #dbe9ef!important;}
 .pc-modal-right{grid-template-rows:auto minmax(0,1fr) auto!important;}
 .pc-room-list{max-height:none!important;}
 .pc-choice{grid-template-columns:1fr!important;}
 .pc-btn{width:100%!important;}
}
@media(max-width:560px){
 .pc-modal-backdrop{padding:0!important;}
 .pc-modal{width:100vw!important;height:100vh!important;border-radius:0!important;}
 .pc-modal-head{padding:9px 10px!important;}
 .pc-modal-head h2{font-size:18px!important;}
 .pc-modal-body{grid-template-rows:210px minmax(0,1fr)!important;}
 .pc-floor{font-size:13px!important;padding:8px 9px!important;}
 .pc-choice b{font-size:16px!important;}
}
</style>
<div class="pc-modal-backdrop" id="pcLocationModal" aria-hidden="true" data-default-building="<?= htmlspecialchars($smgr_modal_default_building, ENT_QUOTES, 'UTF-8') ?>" data-default-floor="<?= htmlspecialchars($smgr_modal_floor_defaults[$smgr_modal_default_building] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-floors="<?= htmlspecialchars(json_encode($smgr_modal_floors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>">
  <div class="pc-modal" role="dialog" aria-modal="true" aria-label="Selector de ubicación">
    <div class="pc-modal-head"><h2>Plano de clases</h2><button type="button" class="pc-btn secondary" id="pcCloseSelector">Cerrar</button></div>
    <div class="pc-modal-body">
      <div class="pc-modal-left">
        <div class="pc-tabs" style="grid-template-columns:repeat(<?= max(1, count($smgr_modal_buildings)) ?>,minmax(0,1fr))!important"><?php foreach ($smgr_modal_buildings as $smgr_b): $smgr_bc = strtoupper((string)$smgr_b['code']); ?><button type="button" class="pc-tab <?= $smgr_bc === $smgr_modal_default_building ? 'active' : '' ?>" data-building="<?= htmlspecialchars($smgr_bc, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(plugin_schoolmanager_label($smgr_b, 'name', $smgr_bc), ENT_QUOTES, 'UTF-8') ?></button><?php endforeach; ?></div>
        <input class="pc-room-search" id="pcModalSearch" placeholder="Buscar aula, descripción, código...">
        <div class="pc-room-list" id="pcModalList"></div>
      </div>
      <div class="pc-modal-right">
        <div class="pc-floorbar" id="pcFloorbar"></div>
        <div class="pc-plan"><iframe id="pcPlanFrame" title="Plano seleccionable"></iframe></div>
        <div class="pc-choice"><div><b id="pcChoiceName">Ninguna ubicación seleccionada</b><span id="pcChoiceMeta">Selecciona un aula desde la lista o el plano.</span></div><button type="button" class="pc-btn primary" id="pcUseLocation">Aplicar ubicación</button></div>
      </div>
    </div>
  </div>
</div>
