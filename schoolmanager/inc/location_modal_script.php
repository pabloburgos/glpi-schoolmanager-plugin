<script>
(function(){
  const wrap=document.getElementById('pcForm'); if(!wrap) return;
  const ROOT=wrap.dataset.root||'';
  const VERSION=wrap.dataset.version||'1.0.0';
  let rooms=[];
  try { rooms=JSON.parse(wrap.dataset.rooms||'[]').filter(r=>r.id || r.codigo || r.aula); } catch(e) { rooms=[]; }
  const modal=document.getElementById('pcLocationModal'), openBtn=document.getElementById('pcOpenSelector'), closeBtn=document.getElementById('pcCloseSelector');
  const list=document.getElementById('pcModalList'), search=document.getElementById('pcModalSearch'), floorbar=document.getElementById('pcFloorbar'), frame=document.getElementById('pcPlanFrame');
  const choiceName=document.getElementById('pcChoiceName'), choiceMeta=document.getElementById('pcChoiceMeta'), useBtn=document.getElementById('pcUseLocation');
  const hidden=document.getElementById('pcLocationId'), selectedBox=document.getElementById('pcSelectedLocation'), hiddenLabel=document.getElementById('pcLocationLabel'), hiddenCode=document.getElementById('pcLocationCode');
  let floors={};
  try { floors=JSON.parse((modal&&modal.dataset.floors)||'{}')||{}; } catch(e) { floors={}; }
  const defaultBuilding=(modal&&modal.dataset.defaultBuilding)||Object.keys(floors)[0]||(rooms[0]&&rooms[0].building)||'';
  let building=defaultBuilding;
  let floor=(modal&&modal.dataset.defaultFloor)||(floors[building]&&Object.keys(floors[building])[0])||(rooms.find(r=>r.building===building)||{}).floor||'';
  let selected=null;
  const n=s=>(s||'').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  function buildingLabel(code){ const tab=document.querySelector('.pc-tab[data-building="'+CSS.escape(String(code||''))+'"]'); return tab ? tab.textContent.trim() : String(code||''); }
  function cleanLocationLabel(r){ const b=buildingLabel(r.building||building); const a=(r.aula||''); const d=shortDesc(r.descripcion)||''; return (b ? b+' · ' : '') + a + (d ? ' · '+d : ''); }
  function cleanLocationMeta(r){ const b=buildingLabel(r.building||building); return (b ? b+' · ' : '') + (r.codigo||'') + ' · ' + (r.planta||r.floor||''); }
  function shortDesc(v){
    let s=String(v||'').trim();
    const map=[[/Secretaría y administración/gi,'Secretaría'],[/Secretaría/gi,'Secret.'],[/Dirección/gi,'Direcc.'],[/Biblioteca/gi,'Bibl.'],[/Usos múltiples/gi,'Usos múlt.'],[/Informática/gi,'Info.'],[/Orientación/gi,'Orient.'],[/Sala profesorado/gi,'Sala prof.'],[/Sala Profesores/gi,'Sala prof.'],[/Administración/gi,'Admin.'],[/Laboratorio/gi,'Lab.']];
    map.forEach(([r,t])=>{s=s.replace(r,t)});
    return s;
  }
  function open(){
    if(!modal) return;
    if(modal.parentElement!==document.body){document.body.appendChild(modal);}
    document.body.classList.add('pc-location-modal-open');
    modal.classList.add('show');
    modal.setAttribute('aria-hidden','false');
    renderAll();
    setTimeout(()=>search&&search.focus(),100);
  }
  function close(){ if(!modal) return; modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('pc-location-modal-open'); }
  function roomText(r){return n([r.aula,r.codigo,r.descripcion,r.planta,r.building,r.floor].join(' '))}
  function filtered(){const q=n(search&&search.value); return rooms.filter(r=>r.building===building && (q ? roomText(r).includes(q) : String(r.floor||'')===String(floor||''))).sort((a,b)=> (Number(a.sort_floor||99)-Number(b.sort_floor||99))||String(a.aula).localeCompare(String(b.aula),undefined,{numeric:true}))}
  function renderTabs(){document.querySelectorAll('.pc-tab').forEach(t=>t.classList.toggle('active',t.dataset.building===building))}
  function renderFloors(){
    if(!floorbar) return;
    floorbar.innerHTML='';
    const fs=floors[building]||{};
    const keys=Object.keys(fs);
    if(!keys.length){ floor=''; loadPlan(); return; }
    if(!fs[floor]) floor=keys[0];
    Object.entries(fs).forEach(([key,val])=>{const b=document.createElement('button'); b.type='button'; b.className='pc-floor'+(key===floor?' active':''); b.textContent=(Array.isArray(val)?val[1]:key)||key; b.onclick=()=>{floor=key; renderAll()}; floorbar.appendChild(b)});
    loadPlan();
  }
  function renderList(){
    const data=filtered(); if(!list) return; list.innerHTML='';
    data.forEach(r=>{const btn=document.createElement('button'); btn.type='button'; btn.className='pc-room'+(selected&&selected.codigo===r.codigo?' active':''); btn.innerHTML=`<span class="pc-avatar">${escapeHtml(String(r.aula||r.codigo||'').slice(0,3))}</span><span><span class="pc-room-name">${escapeHtml(r.aula||r.codigo||'')}</span><span class="pc-room-desc">${escapeHtml(shortDesc(r.descripcion)||'Sin desc.')} · ${escapeHtml(r.planta||r.floor||'')}</span></span><span class="pc-code">${escapeHtml(r.codigo||'')}</span>`; btn.onclick=()=>selectRoom(r,true); btn.ondblclick=()=>{selectRoom(r,true); useSelected();}; list.appendChild(btn)});
    if(!data.length){list.innerHTML='<div style="padding:24px;text-align:center;font-weight:900;color:#617781">No hay resultados.</div>'}
  }
  function loadPlan(){ if(!frame || !building || !floor) return; const url=ROOT+'/plugins/schoolmanager/front/plan_frame.php?building='+encodeURIComponent(building)+'&floor='+encodeURIComponent(floor)+'&mode=select&v='+encodeURIComponent(VERSION); if(frame.dataset.src!==url){frame.dataset.src=url; frame.src=url;} }
  function findFromPayload(p){
    const payload=p||{};
    const id=String(payload.id||'').trim();
    const code=String(payload.code||'').trim().toUpperCase();
    const label=String(payload.label||'').trim();
    const href=String(payload.href||'').trim();
    const raw=[code,label,href].join(' ');
    if(id){ const byId=rooms.find(r=>String(r.id)===id); if(byId) return byId; }
    if(code){ const byCode=rooms.find(r=>String(r.codigo).toUpperCase()===code); if(byCode) return byCode; }
    const idMatch=raw.match(/location\.form\.php\?id=(\d+)/i) || raw.match(/[?&]id=(\d+)/i);
    if(idMatch){ const byId=rooms.find(r=>String(r.id)===idMatch[1]); if(byId) return byId; }
    const compact=n(label).replace(/[^a-z0-9]/g,'');
    if(compact){
      const sameFloor=rooms.filter(r=>r.building===building && r.floor===floor);
      let hit=sameFloor.find(r=>n(r.aula).replace(/[^a-z0-9]/g,'')===compact || n(r.codigo).replace(/[^a-z0-9]/g,'')===compact);
      if(hit) return hit;
    }
    return null;
  }
  function selectRoom(r, syncFloor){selected=r; if(syncFloor && r.floor && r.floor!==floor){floor=r.floor;} choiceName.textContent=(r.aula||r.codigo||'Ubicación')+' · '+(shortDesc(r.descripcion)||''); choiceMeta.textContent=cleanLocationMeta(r); renderFloors(); renderList();}
  function useSelected(){if(!selected) return; if(hidden) hidden.value=selected.id||''; if(hiddenLabel) hiddenLabel.value=cleanLocationLabel(selected); if(hiddenCode) hiddenCode.value=selected.codigo||''; if(selectedBox) selectedBox.innerHTML='<b>'+escapeHtml(cleanLocationLabel(selected))+'</b><span>'+escapeHtml(cleanLocationMeta(selected))+'</span>'; hidden&&hidden.dispatchEvent(new Event('change',{bubbles:true})); window.dispatchEvent(new CustomEvent('schoolmanager:location-selected',{detail:selected})); close();}
  function renderAll(){renderTabs(); renderFloors(); renderList()}
  function escapeHtml(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
  openBtn&&openBtn.addEventListener('click',open); closeBtn&&closeBtn.addEventListener('click',close); useBtn&&useBtn.addEventListener('click',useSelected); search&&search.addEventListener('input',renderList);
  document.querySelectorAll('.pc-tab').forEach(t=>t.addEventListener('click',()=>{building=t.dataset.building; floor=(floors[building]&&Object.keys(floors[building])[0])||''; selected=null; choiceName.textContent='Ninguna ubicación seleccionada'; choiceMeta.textContent='Selecciona un aula desde la lista o el plano.'; renderAll()}));
  modal&&modal.addEventListener('click',e=>{if(e.target===modal) close()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape' && modal && modal.classList.contains('show')) close();});
  window.addEventListener('message',ev=>{const p=ev.data||{}; if(p.type!=='schoolmanager-plan-click') return; const hit=findFromPayload(p); if(hit){selectRoom(hit,true)}});
})();
</script>
