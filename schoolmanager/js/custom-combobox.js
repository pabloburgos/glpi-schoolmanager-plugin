(function(){
  'use strict';
  const ROOTS = '.pc-form,.pc-selector,.al-page,.gsm-edit,.gsm-files,.gsm-panel,.gsm-admin,.pc-modal,.pcd-page,.pci-page,.gsm-wrap';
  const SKIP = 'select2-hidden-accessible';

  function isInsidePlugin(select){ return !!select.closest(ROOTS); }
  function text(opt){ return (opt && (opt.textContent || opt.innerText) || '').replace(/\s+/g,' ').trim() || 'Seleccionar'; }
  function normalize(s){ return String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

  function hideNative(select){
    select.classList.add('pc-native-select-hidden');
    select.setAttribute('tabindex','-1');
    select.style.position='absolute';
    select.style.left='-99999px';
    select.style.width='1px';
    select.style.height='1px';
    select.style.opacity='0';
    select.style.pointerEvents='none';
  }

  function enhance(select){
    if(!select || select.dataset.pcCombo === '1') return;
    if(select.classList.contains(SKIP) || select.closest('.select2-container')) return;
    if(select.multiple || select.size > 1) return;
    if(!isInsidePlugin(select)) return;
    select.dataset.pcCombo='1';
    hideNative(select);

    const wrap=document.createElement('div'); wrap.className='pc-combo';
    const btn=document.createElement('button'); btn.type='button'; btn.className='pc-combo-btn'; btn.setAttribute('aria-expanded','false');
    const label=document.createElement('span'); label.className='pc-combo-label';
    const caret=document.createElement('span'); caret.className='pc-combo-caret'; caret.innerHTML='';
    btn.append(label,caret);
    const menu=document.createElement('div'); menu.className='pc-combo-menu';
    const search=document.createElement('input'); search.type='search'; search.className='pc-combo-search'; search.placeholder='Buscar opción...';
    const list=document.createElement('div'); list.className='pc-combo-list';
    menu.append(search,list); wrap.append(btn,menu);
    select.insertAdjacentElement('afterend',wrap);

    function close(){ wrap.classList.remove('open'); btn.setAttribute('aria-expanded','false'); search.value=''; filter(''); }
    function open(){ document.querySelectorAll('.pc-combo.open').forEach(x=>{ if(x!==wrap) x.classList.remove('open'); }); wrap.classList.add('open'); btn.setAttribute('aria-expanded','true'); if(select.options.length>8){ setTimeout(()=>search.focus(),30); } }
    function filter(q){ const n=normalize(q); list.querySelectorAll('.pc-combo-option').forEach(o=>{ o.hidden = !!n && !normalize(o.textContent).includes(n); }); }
    function render(){
      hideNative(select);
      const selected=select.options[select.selectedIndex]; label.textContent=text(selected);
      list.innerHTML='';
      const opts=Array.from(select.options);
      menu.classList.toggle('has-search', opts.length>8);
      opts.forEach((opt,idx)=>{
        const item=document.createElement('button'); item.type='button'; item.className='pc-combo-option'; item.textContent=text(opt);
        item.dataset.value=opt.value; item.disabled=!!opt.disabled; if(idx===select.selectedIndex)item.classList.add('active');
        item.addEventListener('click',e=>{ e.preventDefault(); if(item.disabled)return; select.selectedIndex=idx; select.value=opt.value; select.dispatchEvent(new Event('input',{bubbles:true})); select.dispatchEvent(new Event('change',{bubbles:true})); render(); close(); });
        list.appendChild(item);
      });
    }
    btn.addEventListener('click',e=>{ e.preventDefault(); e.stopPropagation(); wrap.classList.contains('open')?close():open(); });
    search.addEventListener('input',()=>filter(search.value));
    search.addEventListener('click',e=>e.stopPropagation());
    select.addEventListener('change', render);
    document.addEventListener('click',e=>{ if(!wrap.contains(e.target)) close(); });
    document.addEventListener('keydown',e=>{ if(e.key==='Escape') close(); });
    new MutationObserver(render).observe(select,{childList:true,subtree:true,attributes:true,attributeFilter:['selected','disabled','label']});
    render();
  }
  function scan(){ document.querySelectorAll('select').forEach(enhance); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',scan); else scan();
  new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});
})();
