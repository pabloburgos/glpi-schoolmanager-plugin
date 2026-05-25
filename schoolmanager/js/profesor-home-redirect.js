(function () {
  'use strict';

  function rootDoc() {
    var path = window.location.pathname || '';
    var markers = ['/front/', '/plugins/'];
    for (var i = 0; i < markers.length; i++) {
      var pos = path.indexOf(markers[i]);
      if (pos >= 0) return path.substring(0, pos);
    }
    return '';
  }

  function isPluginPage() {
    return (window.location.pathname || '').indexOf('/plugins/schoolmanager/') >= 0;
  }

  function looksLikeProfessorHome() {
    var path = window.location.pathname || '';
    var text = (document.body && document.body.innerText ? document.body.innerText : '').toLowerCase();

    if (path.indexOf('/front/helpdesk.public.php') >= 0) return true;
    if (path.indexOf('/front/central.php') >= 0 && text.indexOf('¿cómo podemos ayudarle?') >= 0) return true;
    if (text.indexOf('¿cómo podemos ayudarle?') >= 0 && text.indexOf('catálogo de servicios') >= 0) return true;

    return false;
  }

  function redirectToPanel(info) {
    if (!info || !info.logged || info.mode !== 'profesor') return;
    if (!looksLikeProfessorHome()) return;
    if (isPluginPage()) return;

    var target = info.target || (rootDoc() + '/plugins/schoolmanager/front/formularios.php?v=139');
    if (window.location.href.indexOf(target) >= 0) return;

    window.location.replace(target);
  }

  function init() {
    if (isPluginPage()) return;

    fetch(rootDoc() + '/plugins/schoolmanager/front/user_mode.php', {
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(redirectToPanel)
      .catch(function () { /* si no se puede comprobar, no molestamos */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
