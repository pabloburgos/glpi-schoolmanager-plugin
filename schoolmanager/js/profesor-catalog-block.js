(function () {
  'use strict';

  var cachedMode = null;
  var VERSION = '138';

  function rootDoc() {
    var path = window.location.pathname || '';
    var markers = ['/front/', '/plugins/', '/Helpdesk', '/ServiceCatalog'];
    for (var i = 0; i < markers.length; i++) {
      var pos = path.indexOf(markers[i]);
      if (pos >= 0) return path.substring(0, pos);
    }
    return '';
  }

  function pluginHome() {
    return rootDoc() + '/plugins/schoolmanager/front/formularios.php?v=' + VERSION;
  }

  function normalize(s) {
    return (s || '').toString().toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ').trim();
  }

  function isPluginPage() {
    return (window.location.pathname || '').indexOf('/plugins/schoolmanager/') >= 0;
  }

  function currentPath() {
    return normalize(window.location.pathname || '');
  }

  function isForbiddenNativePage() {
    if (isPluginPage()) return false;
    var p = currentPath();
    var txt = normalize(document.body && document.body.innerText ? document.body.innerText : '');

    // Catálogo / helpdesk nativo: siempre fuera para profesor.
    if (p.indexOf('/helpdesk') >= 0 || p.indexOf('/servicecatalog') >= 0 || p.indexOf('/front/helpdesk') >= 0) return true;
    if (txt.indexOf('catalogo de servicios') >= 0 && (txt.indexOf('reportar un problema') >= 0 || txt.indexOf('solicitar un servicio') >= 0)) return true;
    if (txt.indexOf('como podemos ayudarle') >= 0) return true;

    // Configuración nativa que no queremos para profesor.
    var forbidden = [
      '/front/dropdown', '/front/itilcategory', '/front/config', '/front/rule', '/front/profile',
      '/front/entity', '/front/group', '/front/user.php', '/front/plugin', '/front/setup',
      '/front/crontask', '/front/notification', '/front/slm', '/front/sla', '/front/ola',
      '/front/link', '/front/fieldunicity', '/front/documenttype', '/front/requesttype',
      '/front/pendingreason', '/front/status', '/front/type', '/front/manufacturer', '/front/model'
    ];
    for (var i = 0; i < forbidden.length; i++) {
      if (p.indexOf(forbidden[i]) >= 0) return true;
    }

    return false;
  }

  function isAllowedText(text) {
    text = normalize(text);
    if (!text) return false;
    return text === 'inicio' ||
           text.indexOf('gestion schoolmanager') >= 0 ||
           text.indexOf('solicitudes') >= 0 ||
           text.indexOf('mis solicitudes') >= 0 ||
           text.indexOf('tickets') >= 0 ||
           text.indexOf('preferencias') >= 0 ||
           text.indexOf('mi cuenta') >= 0 ||
           text.indexOf('perfil') >= 0 ||
           text.indexOf('cerrar sesion') >= 0 ||
           text.indexOf('plegar menu') >= 0 ||
           text.indexOf('encuentra el menu') >= 0;
  }

  function isBlockedLink(el) {
    if (!el) return false;
    var text = normalize(el.innerText || el.textContent || el.getAttribute('title') || el.getAttribute('aria-label') || '');
    var href = normalize(el.getAttribute('href') || '');

    // Nunca bloquear plugin ni enlaces básicos de cuenta.
    if (href.indexOf('/plugins/schoolmanager/') >= 0) return false;
    if (href.indexOf('/front/preference') >= 0 || href.indexOf('/front/user.form') >= 0 || href.indexOf('/logout') >= 0) return false;
    if (isAllowedText(text) && href.indexOf('/servicecatalog') < 0 && href.indexOf('/helpdesk') < 0) return false;

    // Bloquear catálogo y configuración.
    if (text.indexOf('catalogo de servicios') >= 0 || text.indexOf('crear una peticion') >= 0) return true;
    if (text === 'configuracion' || text.indexOf('desplegables') >= 0 || text.indexOf('motivos de espera') >= 0) return true;
    if (href.indexOf('/helpdesk') >= 0 || href.indexOf('/servicecatalog') >= 0 || href.indexOf('helpdesk.public') >= 0) return true;
    if (href.indexOf('/front/dropdown') >= 0 || href.indexOf('/front/itilcategory') >= 0 || href.indexOf('/front/config') >= 0) return true;

    return false;
  }

  function hideElement(el) {
    if (!el || el.dataset.schoolmanagerHidden === '1') return;
    el.dataset.schoolmanagerHidden = '1';
    el.style.setProperty('display', 'none', 'important');
  }

  function closestMenuBlock(el) {
    if (!el) return null;
    return el.closest('li, .nav-item, .menu-item, .list-group-item, .sidebar-item, [class*="menu"], [class*="nav-item"]') || el;
  }

  function lockProfessorMenu() {
    if (cachedMode !== 'profesor') return;
    document.documentElement.classList.add('schoolmanager-profesor-mode');

    // Ocultar enlaces de catálogo/configuración y sus bloques visuales.
    Array.prototype.slice.call(document.querySelectorAll('a, button')).forEach(function (el) {
      if (isBlockedLink(el)) hideElement(closestMenuBlock(el));
    });

    // Ocultar grupos enteros del sidebar que no queremos, salvo Gestion School Manager / Solicitudes.
    Array.prototype.slice.call(document.querySelectorAll('nav a, aside a, .sidebar a, #navbar-menu a, .main-sidebar a, .navbar-vertical a, [class*="sidebar"] a')).forEach(function (a) {
      var text = normalize(a.innerText || a.textContent || a.getAttribute('title') || '');
      if (!text) return;
      var href = normalize(a.getAttribute('href') || '');

      if (href.indexOf('/plugins/schoolmanager/') >= 0) return;
      if (text.indexOf('solicitudes') >= 0) return;
      if (text === 'inicio') return;
      if (text.indexOf('plegar menu') >= 0 || text.indexOf('encuentra el menu') >= 0) return;

      // Todo lo demás del menú lateral queda fuera para profesor.
      var parent = closestMenuBlock(a);
      if (parent) hideElement(parent);
    });

    // Si GLPI pinta las tarjetas del portal, ocultarlas.
    Array.prototype.slice.call(document.querySelectorAll('.card, .tile, .service-card, [class*="card"], [class*="tile"]')).forEach(function (el) {
      var text = normalize(el.innerText || '');
      if (text.indexOf('crear una peticion') >= 0 || text.indexOf('explorar articulos') >= 0 || text.indexOf('catalogo de servicios') >= 0 || text.indexOf('reportar un problema') >= 0 || text.indexOf('solicitar un servicio') >= 0) {
        hideElement(el);
      }
    });
  }

  function addGestionLink() {
    if (cachedMode !== 'profesor') return;
    if (document.querySelector('[data-schoolmanager-profesor-home]')) return;

    var containers = [
      document.querySelector('.navbar-nav'),
      document.querySelector('nav ul'),
      document.querySelector('header ul'),
      document.querySelector('.topbar ul'),
      document.querySelector('.page-header ul')
    ];
    var nav = containers.filter(Boolean)[0];
    if (!nav) return;

    var a = document.createElement('a');
    a.href = pluginHome();
    a.setAttribute('data-schoolmanager-profesor-home', '1');
    a.textContent = (window.SCHOOLMANAGER_CONFIG && (window.SCHOOLMANAGER_CONFIG.menuTitleEs || window.SCHOOLMANAGER_CONFIG.menuTitleEn)) || 'School Manager';
    a.style.cssText = 'font-weight:900;text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border-radius:10px;';

    var li = document.createElement('li');
    li.appendChild(a);
    nav.appendChild(li);
  }

  function redirectIfNeeded() {
    if (cachedMode !== 'profesor') return;
    if (!isForbiddenNativePage()) return;
    window.location.replace(pluginHome());
  }

  function activate(mode) {
    cachedMode = mode;
    if (cachedMode !== 'profesor') return;

    redirectIfNeeded();
    lockProfessorMenu();
    addGestionLink();

    document.addEventListener('click', function (event) {
      var link = event.target.closest && event.target.closest('a, button');
      if (!link || !isBlockedLink(link)) return;
      event.preventDefault();
      event.stopPropagation();
      window.location.href = pluginHome();
    }, true);

    var mo = new MutationObserver(function () {
      redirectIfNeeded();
      lockProfessorMenu();
      addGestionLink();
    });
    if (document.body) mo.observe(document.body, { childList: true, subtree: true });
  }

  function init() {
    if (isPluginPage()) return;

    fetch(rootDoc() + '/plugins/schoolmanager/front/user_mode.php?v=' + VERSION, {
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (info) { activate(info && info.mode ? info.mode : null); })
      .catch(function () {});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
