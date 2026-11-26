<script>
(function(){
  const wrap = document.getElementById('pcForm') || document.getElementById('pcFormView');
  if (!wrap) return;
  const ROOT = wrap.dataset.root || '';
  const VERSION = wrap.dataset.version || '1.1.0';
  let rooms = [];
  try {
    rooms = JSON.parse(wrap.dataset.rooms || '[]').filter(r => r.id || r.codigo || r.aula);
  } catch (e) {
    rooms = [];
  }

  const modal = document.getElementById('pcLocationModal');
  const plansEnabled = !modal || modal.dataset.plansEnabled !== '0';
  const openBtn = document.getElementById('pcOpenSelector');
  const closeBtn = document.getElementById('pcCloseSelector');
  const list = document.getElementById('pcModalList');
  const search = document.getElementById('pcModalSearch');
  const floorbar = document.getElementById('pcFloorbar');
  const frame = document.getElementById('pcPlanFrame');
  const choiceName = document.getElementById('pcChoiceName');
  const choiceMeta = document.getElementById('pcChoiceMeta');
  const useBtn = document.getElementById('pcUseLocation');
  const hidden = document.getElementById('pcLocationId');
  const selectedBox = document.getElementById('pcSelectedLocation');
  const hiddenLabel = document.getElementById('pcLocationLabel');
  const hiddenCode = document.getElementById('pcLocationCode');
  const quickSelect = document.getElementById('pcQuickLocationSelect');

  const msgNoResults = (modal && modal.dataset.msgNoResults) || 'No hay resultados.';
  const msgNoChoice = (modal && modal.dataset.msgNoChoice) || 'Ninguna ubicación seleccionada';
  const msgPrompt = (modal && modal.dataset.msgPrompt) || 'Selecciona un aula desde la lista.';
  const msgLocation = (modal && modal.dataset.msgLocation) || 'Ubicación';

  let floors = {};
  try {
    floors = JSON.parse((modal && modal.dataset.floors) || '{}') || {};
  } catch (e) {
    floors = {};
  }

  const defaultBuilding = (modal && modal.dataset.defaultBuilding) || Object.keys(floors)[0] || (rooms[0] && rooms[0].building) || '';
  let building = defaultBuilding;
  let floor = !plansEnabled
    ? 'ALL'
    : ((modal && modal.dataset.defaultFloor) || (floors[building] && Object.keys(floors[building])[0]) || (rooms.find(r => r.building === building) || {}).floor || '');
  let selected = null;

  const n = s => (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  function buildingLabel(code) {
    const tab = document.querySelector('#pcLocationModal .pc-tab[data-building="' + CSS.escape(String(code || '')) + '"]');
    return tab ? tab.textContent.trim() : String(code || '');
  }

  function shortDesc(v) {
    let s = String(v || '').trim();
    const map = [
      [/Secretaría y administración/gi, 'Secretaría'],
      [/Secretaría/gi, 'Secret.'],
      [/Dirección/gi, 'Direcc.'],
      [/Biblioteca/gi, 'Bibl.'],
      [/Usos múltiples/gi, 'Usos múlt.'],
      [/Informática/gi, 'Info.'],
      [/Orientación/gi, 'Orient.'],
      [/Sala profesorado/gi, 'Sala prof.'],
      [/Sala Profesores/gi, 'Sala prof.'],
      [/Administración/gi, 'Admin.'],
      [/Laboratorio/gi, 'Lab.']
    ];
    map.forEach(([r, t]) => { s = s.replace(r, t); });
    return s;
  }

  function cleanLocationLabel(r) {
    const b = buildingLabel(r.building || building);
    const a = (r.aula || '');
    const d = shortDesc(r.descripcion) || '';
    return (b ? b + ' · ' : '') + a + (d ? ' · ' + d : '');
  }

  function cleanLocationMeta(r) {
    const b = buildingLabel(r.building || building);
    return (b ? b + ' · ' : '') + (r.codigo || '') + ' · ' + (r.planta || r.floor || '');
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));
  }

  function syncCardUI(r) {
    if (!r) return;
    const label = cleanLocationLabel(r);
    const meta = cleanLocationMeta(r);
    if (hidden) hidden.value = r.id || '';
    if (hiddenLabel) hiddenLabel.value = label;
    if (hiddenCode) hiddenCode.value = r.codigo || '';
    if (quickSelect && String(quickSelect.value) !== String(r.id || '')) {
      quickSelect.value = String(r.id || '');
    }
    if (selectedBox) {
      const locTitle = document.getElementById('pcLocTitle');
      const locMeta = document.getElementById('pcLocMeta');
      if (locTitle && locMeta) {
        locTitle.textContent = label;
        locMeta.textContent = meta;
      } else {
        selectedBox.innerHTML = '<b>' + escapeHtml(label) + '</b><span>' + escapeHtml(meta) + '</span>';
      }
    }
    const locCard = document.getElementById('pcSelectedLocationCard');
    const locBadge = document.getElementById('pcLocStatusBadge');
    if (locCard) locCard.classList.add('is-selected');
    if (locBadge) {
      locBadge.className = 'pc-loc-badge selected';
      locBadge.textContent = '✓ Aula asignada';
    }
    if (hidden) hidden.dispatchEvent(new Event('change', { bubbles: true }));
    window.dispatchEvent(new CustomEvent('schoolmanager:location-selected', { detail: r }));
  }

  function open() {
    if (!modal) return;
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
    if (hidden && Number(hidden.value) > 0) {
      const existing = rooms.find(r => Number(r.id) === Number(hidden.value));
      if (existing) {
        building = existing.building || building;
        floor = plansEnabled ? (existing.floor || floor) : 'ALL';
        selected = existing;
        if (choiceName) choiceName.textContent = (existing.aula || existing.codigo || msgLocation) + ' · ' + (shortDesc(existing.descripcion) || '');
        if (choiceMeta) choiceMeta.textContent = cleanLocationMeta(existing);
      }
    }
    document.body.classList.add('pc-location-modal-open');
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    renderAll();
    if (window.innerWidth > 860) {
      setTimeout(() => search && search.focus(), 100);
    }
  }

  function close() {
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('pc-location-modal-open');
  }

  function roomText(r) {
    return n([r.aula, r.codigo, r.descripcion, r.planta, r.building, r.floor].join(' '));
  }

  function filtered() {
    const q = n(search && search.value).trim();
    return rooms
      .filter(r => {
        if (q) return roomText(r).includes(q);
        if (r.building !== building) return false;
        if (!plansEnabled && floor === 'ALL') return true;
        return String(r.floor || '') === String(floor || '');
      })
      .sort((a, b) => (Number(a.sort_floor || 99) - Number(b.sort_floor || 99)) || String(a.aula).localeCompare(String(b.aula), undefined, { numeric: true }));
  }

  function renderTabs() {
    document.querySelectorAll('#pcLocationModal .pc-tab').forEach(t => t.classList.toggle('active', t.dataset.building === building));
  }

  function renderFloors() {
    if (!floorbar) return;
    floorbar.innerHTML = '';
    const fs = floors[building] || {};
    const keys = Object.keys(fs);
    if (!keys.length) {
      floor = '';
      if (plansEnabled) loadPlan();
      return;
    }
    if (!plansEnabled) {
      const allBtn = document.createElement('button');
      allBtn.type = 'button';
      allBtn.className = 'pc-floor' + (floor === 'ALL' ? ' active' : '');
      allBtn.textContent = 'Todas las plantas';
      allBtn.onclick = () => {
        floor = 'ALL';
        renderAll();
      };
      floorbar.appendChild(allBtn);
      if (floor !== 'ALL' && !fs[floor]) floor = 'ALL';
    } else {
      if (!fs[floor]) floor = keys[0];
    }
    Object.entries(fs).forEach(([key, val]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'pc-floor' + (key === floor ? ' active' : '');
      b.textContent = (Array.isArray(val) ? val[1] : key) || key;
      b.onclick = () => {
        floor = key;
        renderAll();
      };
      floorbar.appendChild(b);
    });
    if (plansEnabled) loadPlan();
  }

  function renderList() {
    const data = filtered();
    if (!list) return;
    list.innerHTML = '';
    data.forEach(r => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pc-room' + (selected && String(selected.id) === String(r.id) ? ' active' : '');
      btn.innerHTML =
        `<span class="pc-avatar">${escapeHtml(String(r.aula || r.codigo || '').slice(0, 3))}</span>` +
        `<span><span class="pc-room-name">${escapeHtml(r.aula || r.codigo || '')}</span>` +
        `<span class="pc-room-desc">${escapeHtml(shortDesc(r.descripcion) || 'Espacio lectivo')} · ${escapeHtml(r.planta || r.floor || '')}</span></span>` +
        `<span class="pc-code">${escapeHtml(r.codigo || '')}</span>`;
      btn.onclick = () => selectRoom(r, plansEnabled);
      btn.ondblclick = () => {
        selectRoom(r, plansEnabled);
        useSelected();
      };
      list.appendChild(btn);
    });
    if (!data.length) {
      list.innerHTML = '<div style="padding:24px;text-align:center;font-weight:800;color:#64748B;grid-column:1/-1">' + escapeHtml(msgNoResults) + '</div>';
    }
  }

  function loadPlan() {
    if (!plansEnabled || !frame || !building || !floor || floor === 'ALL') return;
    const url = ROOT + '/plugins/schoolmanager/front/plan_frame.php?building=' + encodeURIComponent(building) + '&floor=' + encodeURIComponent(floor) + '&mode=select&v=' + encodeURIComponent(VERSION);
    if (frame.dataset.src !== url) {
      frame.dataset.src = url;
      frame.src = url;
    } else if (selected && frame.contentWindow) {
      try {
        frame.contentWindow.postMessage({ type: 'schoolmanager-highlight-room', id: selected.id, code: selected.codigo }, '*');
      } catch (e) {}
    }
  }

  function findFromPayload(p) {
    const payload = p || {};
    const id = String(payload.id || '').trim();
    const code = String(payload.code || '').trim().toUpperCase();
    const label = String(payload.label || '').trim();
    const href = String(payload.href || '').trim();
    const raw = [code, label, href].join(' ');
    if (id) {
      const byId = rooms.find(r => String(r.id) === id);
      if (byId) return byId;
    }
    if (code) {
      const byCode = rooms.find(r => String(r.codigo).toUpperCase() === code);
      if (byCode) return byCode;
    }
    const idMatch = raw.match(/location\.form\.php\?id=(\d+)/i) || raw.match(/[?&]id=(\d+)/i);
    if (idMatch) {
      const byId = rooms.find(r => String(r.id) === idMatch[1]);
      if (byId) return byId;
    }
    const compact = n(label || href).replace(/[^a-z0-9]/g, '');
    if (compact) {
      const sameFloor = rooms.filter(r => r.building === building && r.floor === floor);
      let hit = sameFloor.find(r => n(r.aula).replace(/[^a-z0-9]/g, '') === compact || n(r.codigo).replace(/[^a-z0-9]/g, '') === compact);
      if (hit) return hit;
    }
    return null;
  }

  function selectRoom(r, syncFloor) {
    selected = r;
    if (syncFloor && r.building && r.building !== building) {
      building = r.building;
    }
    if (syncFloor && r.floor && r.floor !== floor) {
      floor = r.floor;
    }
    if (choiceName) choiceName.textContent = (r.aula || r.codigo || msgLocation) + ' · ' + (shortDesc(r.descripcion) || '');
    if (choiceMeta) choiceMeta.textContent = cleanLocationMeta(r);
    renderTabs();
    renderFloors();
    renderList();
    if (plansEnabled && frame && frame.contentWindow) {
      try {
        frame.contentWindow.postMessage({ type: 'schoolmanager-highlight-room', id: r.id, code: r.codigo }, '*');
      } catch (e) {}
    }
  }

  function useSelected() {
    if (!selected) return;
    syncCardUI(selected);
    close();
  }

  function renderAll() {
    renderTabs();
    renderFloors();
    renderList();
  }

  if (openBtn) openBtn.addEventListener('click', open);
  if (closeBtn) closeBtn.addEventListener('click', close);
  if (useBtn) useBtn.addEventListener('click', useSelected);
  if (search) search.addEventListener('input', renderList);

  if (quickSelect) {
    quickSelect.addEventListener('change', () => {
      const val = String(quickSelect.value || '').trim();
      if (!val || val === '0') return;
      const hit = rooms.find(r => String(r.id) === val);
      if (hit) {
        selected = hit;
        syncCardUI(hit);
      }
    });
  }

  document.querySelectorAll('#pcLocationModal .pc-tab').forEach(t => {
    t.addEventListener('click', () => {
      building = t.dataset.building;
      floor = plansEnabled ? ((floors[building] && Object.keys(floors[building])[0]) || '') : 'ALL';
      renderAll();
    });
  });

  document.querySelectorAll('#pcLocationModal .pc-modal-mode-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.modalMode || 'list';
      document.querySelectorAll('#pcLocationModal .pc-modal-mode-btn').forEach(b => b.classList.toggle('active', b === btn));
      if (modal) modal.setAttribute('data-mobile-view', mode);
      if (mode === 'map') loadPlan();
    });
  });

  if (modal) {
    modal.addEventListener('click', e => {
      if (e.target === modal) close();
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal && modal.classList.contains('show')) close();
  });

  window.addEventListener('message', ev => {
    const p = ev.data || {};
    if (p.type !== 'schoolmanager-plan-click') return;
    const hit = findFromPayload(p);
    if (hit) {
      selectRoom(hit, true);
    }
  });
})();
</script>
