(function(){
  function isPluginPage(){ return /\/plugins\/schoolmanager\//.test(location.pathname); }
  if(!isPluginPage()) return;
  var html = document.documentElement;
  html.classList.add('schoolmanager-page-js');

  function getOrCreatePreloader(){
    var el = document.getElementById('smPagePreloader');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'smPagePreloader';
    el.className = 'sm-preloader';
    el.innerHTML = '<div class="sm-preloader-inner"><div class="sm-preloader-spinner"></div><div class="sm-preloader-text">School Manager</div></div>';
    var target = document.body || document.documentElement;
    if (target) {
      target.appendChild(el);
    }
    return el;
  }

  function showPreloader(){
    var el = getOrCreatePreloader();
    if (el) {
      el.classList.remove('sm-preloader-hidden');
    }
  }

  function dismissPreloader(){
    var el = document.getElementById('smPagePreloader');
    if (el) {
      el.classList.add('sm-preloader-hidden');
      setTimeout(function(){
        if (el && el.parentNode) { el.parentNode.removeChild(el); }
      }, 350);
    }
  }

  // Mount immediately during initial parsing
  if (document.readyState === 'loading') {
    getOrCreatePreloader();
  }

  function ready(){
    requestAnimationFrame(function(){
      html.classList.add('schoolmanager-page-ready');
      html.classList.remove('schoolmanager-page-leaving');
      dismissPreloader();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready, {once: true});
  } else {
    ready();
  }
  window.addEventListener('pageshow', ready);
  setTimeout(ready, 900);

  // Instant preloader feedback on link navigation
  document.addEventListener('click', function(ev){
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || /^javascript:/i.test(href)) return;
    try {
      var url = new URL(href, location.href);
      if (url.origin === location.origin && /\/plugins\/schoolmanager\//.test(url.pathname)) {
        html.classList.add('schoolmanager-page-leaving');
        showPreloader();
      }
    } catch(e) {}
  }, true);
})();
