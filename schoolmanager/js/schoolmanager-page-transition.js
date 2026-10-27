(function(){
  function isPluginPage(){ return /\/plugins\/schoolmanager\//.test(location.pathname); }
  if(!isPluginPage()) return;
  var html = document.documentElement;
  html.classList.add('schoolmanager-page-js');

  var preloaderTimer = null;
  var progressInterval = null;

  function getOrCreatePreloader(){
    var el = document.getElementById('smPagePreloader');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'smPagePreloader';
    el.className = 'sm-preloader';
    el.innerHTML = '<div class="sm-preloader-bar" id="smPreloaderBar"></div><div class="sm-preloader-corner"><div class="sm-preloader-spinner"></div><span>Cargando...</span></div>';
    var target = document.body || document.documentElement;
    if (target) {
      target.appendChild(el);
    }
    return el;
  }

  function showPreloader(){
    var el = getOrCreatePreloader();
    if (el) {
      el.classList.add('sm-preloader-active');
      var bar = document.getElementById('smPreloaderBar');
      if (bar) {
        bar.style.width = '35%';
        clearInterval(progressInterval);
        progressInterval = setInterval(function(){
          var cur = parseFloat(bar.style.width) || 35;
          if (cur < 85) {
            bar.style.width = (cur + Math.random() * 12) + '%';
          }
        }, 200);
      }
    }
  }

  function dismissPreloader(){
    clearTimeout(preloaderTimer);
    clearInterval(progressInterval);
    var el = document.getElementById('smPagePreloader');
    if (el) {
      var bar = document.getElementById('smPreloaderBar');
      if (bar) { bar.style.width = '100%'; }
      setTimeout(function(){
        el.classList.remove('sm-preloader-active');
        if (bar) { bar.style.width = '0%'; }
      }, 120);
    }
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
  window.addEventListener('load', ready);

  // Debounced non-blocking preloader on navigation link clicks:
  // ONLY shows if request takes longer than 260ms. Fast loads NEVER show an annoying blocker!
  document.addEventListener('click', function(ev){
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || /^javascript:/i.test(href)) return;
    try {
      var url = new URL(href, location.href);
      if (url.origin === location.origin && /\/plugins\/schoolmanager\//.test(url.pathname)) {
        clearTimeout(preloaderTimer);
        preloaderTimer = setTimeout(function(){
          html.classList.add('schoolmanager-page-leaving');
          showPreloader();
        }, 260);
      }
    } catch(e) {}
  }, true);
})();
