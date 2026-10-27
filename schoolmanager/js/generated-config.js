window.SCHOOLMANAGER_CONFIG = {"locale":"es_ES","menuTitle":"School Manager","appName":"School Manager","theme":{"palette":"teal-red","name":"Modern Teal (Signature)","primary":"#07384D","secondary":"#075D61","accent":"#B6252B","soft":"#F4FAFC","background":"#EEF6F9","card":"#FFFFFF","text":"#07384D","muted":"#5F7180","border":"#D7E6EC","dark":false,"radius":"18px","pad":"18px","gap":"18px","font":"15px","density":"comfortable"},"themeCss":"/plugins/schoolmanager/css/themes/teal-red.css"};
(function(){
  var cfg = window.SCHOOLMANAGER_CONFIG || {}, t = cfg.theme || {};
  var vars = {
    '--sm-primary':t.primary,'--sm-secondary':t.secondary,'--sm-accent':t.accent,'--sm-soft':t.soft,'--sm-bg':t.background,
    '--sm-card':t.card,'--sm-text':t.text,'--sm-muted':t.muted,'--sm-border':t.border,'--sm-radius':t.radius,'--sm-pad':t.pad,
    '--sm-gap':t.gap,'--sm-font':t.font,'--sm-navy':t.primary,'--sm-blue':t.secondary,'--sm-red':t.accent,'--sm-ink':t.text,
    '--navy':t.primary,'--teal':t.secondary,'--red':t.accent,'--pc-dark':t.primary,'--pc-teal':t.secondary,'--pc-red':t.accent
  };
  function applyVars(){
    document.documentElement.setAttribute('data-sm-theme', t.palette || 'teal-red');
    document.documentElement.setAttribute('data-sm-dark', t.dark ? '1' : '0');
    if (document.body) {
      document.body.setAttribute('data-sm-theme', t.palette || 'teal-red');
      document.body.setAttribute('data-sm-dark', t.dark ? '1' : '0');
    }
    Object.keys(vars).forEach(function(k){
      if(vars[k]) {
        document.documentElement.style.setProperty(k, vars[k], 'important');
        if (document.body) document.body.style.setProperty(k, vars[k], 'important');
      }
    });
    if (cfg.themeCss) {
      var l = document.getElementById('schoolmanager-active-theme');
      if (l) {
        if (l.getAttribute('href') !== cfg.themeCss) l.setAttribute('href', cfg.themeCss);
      } else {
        l = document.createElement('link');
        l.id = 'schoolmanager-active-theme';
        l.rel = 'stylesheet';
        l.href = cfg.themeCss;
        document.head.appendChild(l);
      }
    }
  }
  function refreshMenu(){
    var title = cfg.menuTitle || cfg.appName || 'School Manager';
    document.querySelectorAll('a[href*="/plugins/schoolmanager/"]').forEach(function(a){
      var span = a.querySelector('.text-wrap, span:not(.badge)');
      if (span) { span.textContent = title; }
      if (a.hasAttribute('title')) { a.setAttribute('title', title); }
    });
  }
  function run(){applyVars(); refreshMenu();}
  if(document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 150); setTimeout(run, 600);
})();