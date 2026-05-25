window.SCHOOLMANAGER_CONFIG = {"locale":"es_ES","menuTitle":"School Manager","appName":"School Manager","theme":{"palette":"teal-red","name":"Teal red","primary":"#07384D","secondary":"#075D61","accent":"#B6252B","soft":"#F4FAFC","background":"#EEF6F9","card":"#FFFFFF","text":"#07384D","muted":"#5F7180","border":"#D7E6EC","dark":false,"radius":"18px","pad":"18px","gap":"18px","font":"15px","density":"comfortable"},"themeCss":"/plugins/schoolmanager/css/themes/teal-red.css?v=1779697067"};
(function(){
  var cfg = window.SCHOOLMANAGER_CONFIG || {}, t = cfg.theme || {};
  var vars = {'--sm-primary':t.primary,'--sm-secondary':t.secondary,'--sm-accent':t.accent,'--sm-soft':t.soft,'--sm-bg':t.background,'--sm-card':t.card,'--sm-text':t.text,'--sm-muted':t.muted,'--sm-border':t.border,'--sm-radius':t.radius,'--sm-pad':t.pad,'--sm-gap':t.gap,'--sm-font':t.font};
  function applyVars(){
    document.documentElement.setAttribute('data-sm-theme', t.palette || 'teal-red');
    document.documentElement.setAttribute('data-sm-dark', t.dark ? '1' : '0');
    if (document.body) { document.body.setAttribute('data-sm-theme', t.palette || 'teal-red'); document.body.setAttribute('data-sm-dark', t.dark ? '1' : '0'); }
    Object.keys(vars).forEach(function(k){ if(vars[k]) document.documentElement.style.setProperty(k, vars[k]); });
    if (cfg.themeCss && !document.getElementById('schoolmanager-active-theme')) {
      var l=document.createElement('link'); l.id='schoolmanager-active-theme'; l.rel='stylesheet'; l.href=cfg.themeCss; document.head.appendChild(l);
    }
  }
  function replaceTextNodes(el, value){
    Array.prototype.forEach.call(el.childNodes, function(n){
      if(n.nodeType === 3 && /school manager|gesti[oó]n escolar|gesti[oó]n school manager|glpi school manager|centro educativo manager/i.test(n.nodeValue || '')){ n.nodeValue = value; }
      else if(n.nodeType === 1 && !/^(svg|path|i)$/i.test(n.nodeName)){ replaceTextNodes(n, value); }
    });
  }
  function isSchoolManagerLink(a){ return /\/plugins\/schoolmanager\//.test(a.getAttribute('href') || ''); }
  function refreshMenu(){
    var title = cfg.menuTitle || cfg.appName || 'School Manager';
    Array.prototype.forEach.call(document.querySelectorAll('a,span,div'), function(el){
      var txt=(el.textContent||'').trim();
      if(/^(GLPI\s+School\s+Manager|School\s+Manager|Gestión\s+Escolar\s+GLPI|Gestion\s+Escolar\s+GLPI|Gestión\s+School\s+Manager|Gestion\s+School\s+Manager|Centro\s+educativo\s+Manager)$/.test(txt)){
        if(el.matches('a') && !isSchoolManagerLink(el)) return;
        replaceTextNodes(el, title);
      }
    });
  }
  function run(){applyVars(); refreshMenu();}
  if(document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 250); setTimeout(run, 1000); setTimeout(run, 2200);
})();