<?php
$plansEnabled = function_exists('plugin_schoolmanager_feature_enabled') ? plugin_schoolmanager_feature_enabled('plans') : true;
$buildingLabels = function_exists('plugin_schoolmanager_building_labels') ? plugin_schoolmanager_building_labels() : ['A'=>'Edificio A','B'=>'Edificio B','C'=>'Edificio C'];
$floorsByBuilding = function_exists('plugin_schoolmanager_floors_by_building') ? plugin_schoolmanager_floors_by_building() : [
  'A' => ['PB'=>['PB','Planta baja'],'P1'=>['P1','Primera planta'],'P2'=>['P2','Segunda planta']],
  'B' => ['PB'=>['PB','Planta baja'],'P1'=>['P1','Primera planta'],'P2'=>['P2','Segunda planta']],
  'C' => ['PB'=>['PB','Planta baja']],
];
$defaultBuilding = array_key_first($buildingLabels) ?: 'A';
$defaultFloor = isset($floorsByBuilding[$defaultBuilding]) ? (array_key_first($floorsByBuilding[$defaultBuilding]) ?: '') : '';
$smTr = static fn(string $key, string $fallback): string => function_exists('plugin_schoolmanager_tr') ? plugin_schoolmanager_tr($key, $fallback) : $fallback;
?>
<style>
body.pc-location-modal-open { overflow: hidden !important; }
#pcLocationModal.pc-modal {
  position: fixed !important;
  inset: 0 !important;
  background: rgba(15, 41, 81, 0.62) !important;
  backdrop-filter: blur(6px) !important;
  -webkit-backdrop-filter: blur(6px) !important;
  display: none !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 16px !important;
  z-index: 999999 !important;
  box-sizing: border-box !important;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
}
#pcLocationModal.pc-modal * {
  box-sizing: border-box !important;
}
#pcLocationModal.pc-modal.show {
  display: flex !important;
}
#pcLocationModal .pc-modal-card {
  width: min(1380px, 96vw) !important;
  height: min(830px, 90vh) !important;
  max-height: 90vh !important;
  background: #FFFFFF !important;
  border-radius: 20px !important;
  border: 1px solid #CBD5E1 !important;
  box-shadow: 0 24px 64px rgba(15, 41, 81, 0.28) !important;
  display: flex !important;
  flex-direction: column !important;
  grid-template-rows: none !important;
  overflow: hidden !important;
  margin: 0 !important;
}
#pcLocationModal[data-plans-enabled="0"] .pc-modal-card {
  width: min(860px, 95vw) !important;
  height: min(720px, 88vh) !important;
}
#pcLocationModal .pc-modal-head {
  flex: 0 0 auto !important;
  padding: 16px 22px !important;
  background: #0F2951 !important;
  color: #FFFFFF !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 12px !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
}
#pcLocationModal .pc-modal-head-left {
  display: flex !important;
  align-items: center !important;
  gap: 12px !important;
  min-width: 0 !important;
}
#pcLocationModal .pc-modal-head-icon {
  width: 38px !important;
  height: 38px !important;
  border-radius: 10px !important;
  background: rgba(255, 255, 255, 0.14) !important;
  color: #FFFFFF !important;
  display: inline-grid !important;
  place-items: center !important;
  flex-shrink: 0 !important;
}
#pcLocationModal .pc-modal-head h3 {
  margin: 0 !important;
  font-size: 18px !important;
  font-weight: 800 !important;
  color: #FFFFFF !important;
  line-height: 1.2 !important;
}
#pcLocationModal .pc-modal-head small {
  display: block !important;
  color: #CBD5E1 !important;
  font-weight: 600 !important;
  font-size: 12.5px !important;
  margin-top: 2px !important;
}
#pcLocationModal #pcCloseSelector {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 6px !important;
  height: 38px !important;
  padding: 0 14px !important;
  border-radius: 10px !important;
  background: rgba(255, 255, 255, 0.14) !important;
  color: #FFFFFF !important;
  border: 1px solid rgba(255, 255, 255, 0.24) !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  cursor: pointer !important;
  flex-shrink: 0 !important;
}
#pcLocationModal #pcCloseSelector:hover {
  background: rgba(255, 255, 255, 0.24) !important;
}

/* Mobile View Switcher (List vs Map) */
#pcLocationModal .pc-modal-mobile-switch {
  flex: 0 0 auto !important;
  display: none !important;
  padding: 8px 14px !important;
  background: #F1F5F9 !important;
  border-bottom: 1px solid #E2E8F0 !important;
  gap: 8px !important;
}
#pcLocationModal .pc-modal-mode-btn {
  flex: 1 !important;
  height: 38px !important;
  border-radius: 10px !important;
  border: 1px solid #CBD5E1 !important;
  background: #FFFFFF !important;
  color: #0F2951 !important;
  font-size: 13px !important;
  font-weight: 750 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 6px !important;
  cursor: pointer !important;
}
#pcLocationModal .pc-modal-mode-btn.active {
  background: #0F2951 !important;
  border-color: #0F2951 !important;
  color: #FFFFFF !important;
}

#pcLocationModal .pc-modal-body {
  flex: 1 1 0% !important;
  min-height: 0 !important;
  height: 100% !important;
  display: grid !important;
  grid-template-columns: 380px minmax(0, 1fr) !important;
  background: #F8FAFC !important;
  overflow: hidden !important;
}
#pcLocationModal[data-plans-enabled="0"] .pc-modal-body {
  grid-template-columns: 1fr !important;
}
#pcLocationModal .pc-modal-side {
  border-right: 1px solid #E2E8F0 !important;
  background: #FFFFFF !important;
  padding: 16px !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 10px !important;
  min-height: 0 !important;
  height: 100% !important;
  overflow: hidden !important;
}
#pcLocationModal[data-plans-enabled="0"] .pc-modal-side {
  border-right: 0 !important;
  padding: 18px 22px !important;
  gap: 12px !important;
}
#pcLocationModal .pc-tabs {
  flex: 0 0 auto !important;
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(96px, 1fr)) !important;
  gap: 6px !important;
}
#pcLocationModal .pc-tab {
  min-height: 38px !important;
  border: 1px solid #CBD5E1 !important;
  border-radius: 10px !important;
  background: #F8FAFC !important;
  font-weight: 750 !important;
  font-size: 12.5px !important;
  color: #0F2951 !important;
  cursor: pointer !important;
  padding: 6px 10px !important;
  text-align: center !important;
  transition: all 0.15s ease !important;
}
#pcLocationModal .pc-tab:hover {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
}
#pcLocationModal .pc-tab.active {
  background: #0F2951 !important;
  color: #FFFFFF !important;
  border-color: #0F2951 !important;
}
#pcLocationModal .pc-search {
  flex: 0 0 auto !important;
  width: 100% !important;
  height: 42px !important;
  border: 1.5px solid #CBD5E1 !important;
  border-radius: 10px !important;
  padding: 0 14px !important;
  font-weight: 650 !important;
  font-size: 13.5px !important;
  color: #0F2951 !important;
  background: #FFFFFF !important;
  outline: none !important;
}
#pcLocationModal .pc-search:focus {
  border-color: #088E91 !important;
  box-shadow: 0 0 0 3px rgba(8, 142, 145, 0.15) !important;
}
#pcLocationModal .pc-room-list {
  flex: 1 1 0% !important;
  min-height: 0 !important;
  overflow-y: auto !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 8px !important;
  padding-right: 4px !important;
}
#pcLocationModal[data-plans-enabled="0"] .pc-room-list {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)) !important;
  align-content: start !important;
  gap: 10px !important;
}
#pcLocationModal .pc-room {
  width: 100% !important;
  text-align: left !important;
  background: #FFFFFF !important;
  border: 1.5px solid #E2E8F0 !important;
  border-radius: 12px !important;
  padding: 10px 12px !important;
  display: grid !important;
  grid-template-columns: 38px minmax(0, 1fr) auto !important;
  gap: 10px !important;
  align-items: center !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
  flex-shrink: 0 !important;
}
#pcLocationModal .pc-room:hover {
  border-color: #088E91 !important;
  background: #F8FAFC !important;
}
#pcLocationModal .pc-room.active {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
  box-shadow: 0 0 0 2px rgba(8, 142, 145, 0.16) !important;
}
#pcLocationModal .pc-avatar {
  width: 38px !important;
  height: 38px !important;
  border-radius: 10px !important;
  background: #EFF6FF !important;
  border: 1px solid #BFDBFE !important;
  display: grid !important;
  place-items: center !important;
  font-weight: 850 !important;
  font-size: 12px !important;
  color: #0F2951 !important;
}
#pcLocationModal .pc-room.active .pc-avatar {
  background: #088E91 !important;
  border-color: #088E91 !important;
  color: #FFFFFF !important;
}
#pcLocationModal .pc-room-name {
  font-weight: 800 !important;
  color: #0F2951 !important;
  display: block !important;
  line-height: 1.2 !important;
  font-size: 13.5px !important;
}
#pcLocationModal .pc-room-desc {
  font-size: 11.5px !important;
  color: #64748B !important;
  font-weight: 600 !important;
  display: block !important;
  margin-top: 2px !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
}
#pcLocationModal .pc-code {
  font-size: 11px !important;
  font-weight: 800 !important;
  color: #0F2951 !important;
  background: #F1F5F9 !important;
  border: 1px solid #CBD5E1 !important;
  border-radius: 999px !important;
  padding: 4px 8px !important;
}
#pcLocationModal .pc-plan-side {
  padding: 14px 16px !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 10px !important;
  min-height: 0 !important;
  height: 100% !important;
  background: #F8FAFC !important;
  overflow: hidden !important;
}
#pcLocationModal .pc-floorbar {
  flex: 0 0 auto !important;
  display: flex !important;
  gap: 8px !important;
  flex-wrap: wrap !important;
  align-items: center !important;
}
#pcLocationModal .pc-floor {
  height: 36px !important;
  padding: 0 14px !important;
  border-radius: 10px !important;
  border: 1px solid #CBD5E1 !important;
  background: #FFFFFF !important;
  color: #0F2951 !important;
  font-weight: 750 !important;
  font-size: 12.5px !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
}
#pcLocationModal .pc-floor:hover {
  border-color: #088E91 !important;
  background: #F0FDFA !important;
}
#pcLocationModal .pc-floor.active {
  background: #088E91 !important;
  color: #FFFFFF !important;
  border-color: #088E91 !important;
}
#pcLocationModal .pc-plan-wrap {
  flex: 1 1 0% !important;
  min-height: 0 !important;
  height: 100% !important;
  background: #FFFFFF !important;
  border: 1px solid #E2E8F0 !important;
  border-radius: 14px !important;
  overflow: hidden !important;
  display: flex !important;
}
#pcLocationModal .pc-plan-frame {
  width: 100% !important;
  height: 100% !important;
  min-height: 0 !important;
  border: 0 !important;
  display: block !important;
  background: #FFFFFF !important;
  flex: 1 1 0% !important;
}
#pcLocationModal .pc-modal-foot {
  flex: 0 0 auto !important;
  padding: 14px 22px !important;
  background: #FFFFFF !important;
  border-top: 1px solid #E2E8F0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 12px !important;
  flex-wrap: wrap !important;
}
#pcLocationModal .pc-choice strong {
  display: block !important;
  color: #0F2951 !important;
  font-size: 14.5px !important;
  font-weight: 850 !important;
}
#pcLocationModal .pc-choice span {
  display: block !important;
  color: #64748B !important;
  font-size: 12px !important;
  font-weight: 650 !important;
  margin-top: 2px !important;
}
#pcLocationModal #pcUseLocation {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  min-height: 42px !important;
  padding: 0 22px !important;
  border-radius: 10px !important;
  background: #0F2951 !important;
  color: #FFFFFF !important;
  border: 1px solid #0F2951 !important;
  font-size: 13.5px !important;
  font-weight: 750 !important;
  cursor: pointer !important;
  transition: background-color 0.15s ease !important;
}
#pcLocationModal #pcUseLocation:hover:not(:disabled) {
  background: #088E91 !important;
  border-color: #088E91 !important;
}
#pcLocationModal #pcUseLocation:disabled {
  opacity: 0.5 !important;
  cursor: not-allowed !important;
}

@media (max-width: 860px) {
  #pcLocationModal.pc-modal {
    padding: 0 !important;
  }
  #pcLocationModal .pc-modal-card,
  #pcLocationModal[data-plans-enabled="0"] .pc-modal-card {
    width: 100vw !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    border-radius: 0 !important;
    border: 0 !important;
  }
  #pcLocationModal .pc-modal-head {
    padding: 12px 16px !important;
  }
  #pcLocationModal .pc-modal-head h3 {
    font-size: 16px !important;
  }
  #pcLocationModal .pc-modal-head small {
    display: none !important;
  }
  #pcLocationModal[data-plans-enabled="1"] .pc-modal-mobile-switch {
    display: flex !important;
  }
  #pcLocationModal .pc-modal-body {
    grid-template-columns: 1fr !important;
  }
  #pcLocationModal .pc-modal-side {
    border-right: 0 !important;
  }
  #pcLocationModal[data-plans-enabled="1"][data-mobile-view="list"] .pc-plan-side {
    display: none !important;
  }
  #pcLocationModal[data-plans-enabled="1"][data-mobile-view="map"] .pc-modal-side {
    display: none !important;
  }
  #pcLocationModal[data-plans-enabled="0"] .pc-room-list {
    grid-template-columns: 1fr !important;
  }
  #pcLocationModal .pc-modal-foot {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
  }
  #pcLocationModal #pcUseLocation {
    width: 100% !important;
    min-height: 46px !important;
  }
}
</style>
<div class="pc-modal" id="pcLocationModal" aria-hidden="true"
     data-plans-enabled="<?= $plansEnabled ? '1' : '0' ?>"
     data-mobile-view="list"
     data-floors='<?= htmlspecialchars(json_encode($floorsByBuilding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'
     data-default-building="<?= htmlspecialchars((string)$defaultBuilding, ENT_QUOTES, 'UTF-8') ?>"
     data-default-floor="<?= htmlspecialchars((string)$defaultFloor, ENT_QUOTES, 'UTF-8') ?>"
     data-msg-no-results="<?= htmlspecialchars($smTr('no_results', 'No hay resultados.'), ENT_QUOTES, 'UTF-8') ?>"
     data-msg-no-choice="<?= htmlspecialchars($smTr('no_location_selected', 'Ninguna ubicación seleccionada'), ENT_QUOTES, 'UTF-8') ?>"
     data-msg-prompt="<?= htmlspecialchars($plansEnabled ? $smTr('select_classroom_prompt', 'Selecciona un aula desde la lista o el plano.') : $smTr('select_classroom_list_prompt', 'Selecciona un aula desde la lista para asignarla.'), ENT_QUOTES, 'UTF-8') ?>"
     data-msg-location="<?= htmlspecialchars($smTr('location', 'Ubicación'), ENT_QUOTES, 'UTF-8') ?>">
  <div class="pc-modal-card" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars($smTr('location_selector_title', 'Selector de ubicación'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="pc-modal-head">
      <div class="pc-modal-head-left">
        <span class="pc-modal-head-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
        </span>
        <div>
          <h3><?= htmlspecialchars($smTr('select_incident_location', 'Seleccionar ubicación del centro'), ENT_QUOTES, 'UTF-8') ?></h3>
          <small><?= htmlspecialchars($plansEnabled ? $smTr('select_incident_location_desc', 'Busca por aula, código o descripción, o pulsa directamente sobre el plano exportado de diagrams.net.') : $smTr('select_incident_location_list_desc', 'Filtra por edificio o planta y selecciona un aula de la lista.'), ENT_QUOTES, 'UTF-8') ?></small>
        </div>
      </div>
      <button type="button" id="pcCloseSelector">✕ <?= htmlspecialchars($smTr('close', 'Cerrar'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>

    <?php if ($plansEnabled): ?>
      <div class="pc-modal-mobile-switch">
        <button type="button" class="pc-modal-mode-btn active" data-modal-mode="list">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          <span>Lista de aulas</span>
        </button>
        <button type="button" class="pc-modal-mode-btn" data-modal-mode="map">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
          <span>Plano interactivo</span>
        </button>
      </div>
    <?php endif; ?>

    <div class="pc-modal-body">
      <aside class="pc-modal-side">
        <div class="pc-tabs" id="pcBuildingTabs">
          <?php foreach ($buildingLabels as $bCode => $bLabel): ?>
            <button type="button" class="pc-tab<?= $bCode === $defaultBuilding ? ' active' : '' ?>" data-building="<?= htmlspecialchars((string)$bCode, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$bLabel, ENT_QUOTES, 'UTF-8') ?></button>
          <?php endforeach; ?>
        </div>
        <?php if (!$plansEnabled): ?>
          <div class="pc-floorbar" id="pcFloorbar"></div>
        <?php endif; ?>
        <input class="pc-search" id="pcModalSearch" type="search" placeholder="<?= htmlspecialchars($smTr('search_classroom_placeholder', 'Buscar aula, código o descripción...'), ENT_QUOTES, 'UTF-8') ?>">
        <div class="pc-room-list" id="pcModalList"></div>
      </aside>
      <?php if ($plansEnabled): ?>
        <section class="pc-plan-side">
          <div class="pc-floorbar" id="pcFloorbar"></div>
          <div class="pc-plan-wrap"><iframe class="pc-plan-frame" id="pcPlanFrame" title="<?= htmlspecialchars($smTr('center_map_title', 'Plano del centro'), ENT_QUOTES, 'UTF-8') ?>"></iframe></div>
        </section>
      <?php endif; ?>
    </div>

    <div class="pc-modal-foot">
      <div class="pc-choice">
        <strong id="pcChoiceName"><?= htmlspecialchars($smTr('no_location_selected', 'Ninguna ubicación seleccionada'), ENT_QUOTES, 'UTF-8') ?></strong>
        <span id="pcChoiceMeta"><?= htmlspecialchars($plansEnabled ? $smTr('select_classroom_prompt', 'Selecciona un aula desde la lista o el plano.') : $smTr('select_classroom_list_prompt', 'Selecciona un aula desde la lista para asignarla.'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <button type="button" id="pcUseLocation">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= htmlspecialchars($smTr('use_this_location', 'Usar esta ubicación'), ENT_QUOTES, 'UTF-8') ?></span>
      </button>
    </div>
  </div>
</div>
