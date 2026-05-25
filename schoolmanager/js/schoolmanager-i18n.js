(function(){
  let dict=null;
  let keys=[];
  let running=false;
  function keepOuterWhitespace(text, value){
    const start = (text.match(/^\s*/) || [''])[0];
    const end = (text.match(/\s*$/) || [''])[0];
    return start + value + end;
  }
  function escapeRegExp(s){ return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function translateText(text){
    if(!dict || !text) return text;
    const clean = String(text).replace(/\s+/g,' ').trim();
    if(!clean) return text;
    if(Object.prototype.hasOwnProperty.call(dict, clean)) return keepOuterWhitespace(text, dict[clean]);
    if(clean.length > 420) return text;
    let next = clean;
    for(const k of keys){
      if(k.length < 3 || !Object.prototype.hasOwnProperty.call(dict,k)) continue;
      if(next.indexOf(k) !== -1){ next = next.replace(new RegExp(escapeRegExp(k),'g'), dict[k]); }
    }
    return next !== clean ? keepOuterWhitespace(text, next) : text;
  }
  function translateAttr(node, attr){
    const v=node.getAttribute(attr);
    if(!v) return;
    const t=translateText(v);
    if(t !== v) node.setAttribute(attr,t);
  }
  function walk(node){
    if(!node || !dict) return;
    if(node.nodeType === 3){
      const next = translateText(node.nodeValue);
      if(next !== node.nodeValue) node.nodeValue = next;
      return;
    }
    if(node.nodeType !== 1) return;
    const tag = node.tagName ? node.tagName.toLowerCase() : '';
    if(node.getAttribute){
      ['placeholder','title','aria-label','alt'].forEach(a=>translateAttr(node,a));
      if(tag === 'input'){
        const type=(node.getAttribute('type')||'').toLowerCase();
        if(['button','submit','reset'].indexOf(type)!==-1) translateAttr(node,'value');
      }
    }
    if(['script','style','textarea','code','pre'].indexOf(tag) !== -1) return;
    for(let i=0;i<node.childNodes.length;i++) walk(node.childNodes[i]);
  }
  function run(){
    if(running || !dict || !document.body) return;
    running=true;
    try{ walk(document.body); } finally { running=false; }
  }
  function detectBase(){
    if(window.CFG_GLPI && window.CFG_GLPI.root_doc) return window.CFG_GLPI.root_doc;
    const m=(location.pathname||'').match(/^(.*)\/plugins\/schoolmanager\//);
    return m ? m[1] : '';
  }
  function load(){
    fetch(detectBase() + '/plugins/schoolmanager/front/i18n.php?v=' + Date.now(), {credentials:'same-origin'})
      .then(r=>r.json())
      .then(j=>{
        if(!j || !j.map) return;
        dict=j.map;
        keys=Object.keys(dict).sort((a,b)=>b.length-a.length);
        run();
        if(document.title){ const nt=translateText(document.title); if(nt!==document.title) document.title=nt; }
        const mo = new MutationObserver(function(){ clearTimeout(window.__smgrI18nTimer); window.__smgrI18nTimer=setTimeout(run,60); });
        mo.observe(document.body,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['placeholder','title','aria-label','alt','value']});
      }).catch(function(){});
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', load); else load();
})();
