window.SCHOOLMANAGER_CONFIG = {"locale":"auto","menuTitle":"School Manager","appName":"School Manager","theme":{"palette":"teal-red","name":"Signature Logo (Navy & Teal)","primary":"#0F2951","secondary":"#088E91","accent":"#F0B010","soft":"#F4FAFC","background":"#F8FAFC","card":"#FFFFFF","text":"#0F2951","muted":"#64748B","border":"#D7E6EC","dark":false,"radius":"16px","pad":"20px","gap":"18px","font":"15px","density":"comfortable"},"themeCss":"","themePalette":"teal-red","themeVer":"1.1.0"};
(function(){
  var isSM = window.location.pathname.indexOf('/plugins/schoolmanager/') !== -1;
  var cfg = window.SCHOOLMANAGER_CONFIG || {}, t = cfg.theme || {};
  var vars = {
    '--sm-primary':t.primary,'--sm-secondary':t.secondary,'--sm-accent':t.accent,'--sm-soft':t.soft,'--sm-bg':t.background,
    '--sm-card':t.card,'--sm-text':t.text,'--sm-muted':t.muted,'--sm-border':t.border,'--sm-radius':t.radius,'--sm-pad':t.pad,
    '--sm-gap':t.gap,'--sm-font':t.font,'--sm-navy':t.primary,'--sm-blue':t.secondary,'--sm-red':t.accent,'--sm-ink':t.text,
    '--navy':t.primary,'--teal':t.secondary,'--red':t.accent,'--pc-dark':t.primary,'--pc-teal':t.secondary,'--pc-red':t.accent
  };
  function resolveThemeCss(){
    if (cfg.themeCss) return cfg.themeCss;
    if (!isSM) return '';
    var base = window.location.pathname.split('/plugins/schoolmanager/')[0] || '';
    var pal = encodeURIComponent(cfg.themePalette || t.palette || 'teal-red');
    var ver = encodeURIComponent(cfg.themeVer || '1.1.0');
    return base + '/plugins/schoolmanager/css/themes/' + pal + '.css?v=' + ver;
  }
  function applyVars(){
    if (!isSM) return;
    document.documentElement.setAttribute('data-sm-theme', t.palette || 'teal-red');
    document.documentElement.setAttribute('data-sm-dark', t.dark ? '1' : '0');
    document.documentElement.setAttribute('data-sm-density', t.density || 'comfortable');
    if (document.body) {
      document.body.setAttribute('data-sm-theme', t.palette || 'teal-red');
      document.body.setAttribute('data-sm-dark', t.dark ? '1' : '0');
      document.body.setAttribute('data-sm-density', t.density || 'comfortable');
    }
    Object.keys(vars).forEach(function(k){
      if(vars[k]) {
        document.documentElement.style.setProperty(k, vars[k], 'important');
        if (document.body) document.body.style.setProperty(k, vars[k], 'important');
      }
    });
    var themeHref = resolveThemeCss();
    if (themeHref) {
      var l = document.getElementById('schoolmanager-active-theme');
      if (l) {
        if (l.getAttribute('href') !== themeHref) l.setAttribute('href', themeHref);
      } else {
        l = document.createElement('link');
        l.id = 'schoolmanager-active-theme';
        l.rel = 'stylesheet';
        l.href = themeHref;
        document.head.appendChild(l);
      }
    }
  }
  function refreshMenu(){
    var title = cfg.menuTitle || cfg.appName || 'School Manager';
    var navLinks = document.querySelectorAll(
      'aside.navbar a[href*="/plugins/schoolmanager/"], ' +
      'header.navbar a[href*="/plugins/schoolmanager/"], ' +
      '#navigation-menu a[href*="/plugins/schoolmanager/"], ' +
      '.navbar-vertical a[href*="/plugins/schoolmanager/"], ' +
      '.glpi-sidebar a[href*="/plugins/schoolmanager/"], ' +
      '.sidebar a[href*="/plugins/schoolmanager/"]'
    );
    navLinks.forEach(function(a){
      if (a.closest('.schoolmanager-configured, .sm-home, .pc-wrap, .pc-requests, .pc-form, .gsm-wrap, .stp, .av, .al-wrap, .ticdesk, .pcd, .smset, .smdoc, .pe-wrap')) {
        return;
      }
      var span = a.querySelector('.text-wrap, .nav-link-title');
      if (span) { span.textContent = title; }
      if (a.hasAttribute('title')) { a.setAttribute('title', title); }
    });
  }
  function run(){applyVars(); refreshMenu();}
  if(document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 150); setTimeout(run, 600);
})();
