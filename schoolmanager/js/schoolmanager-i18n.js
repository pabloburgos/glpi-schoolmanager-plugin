(function(){
  // Only execute within School Manager plugin pages
  if (!location.pathname.includes('/plugins/schoolmanager/')) {
    return;
  }

  function isSpanish(){
    var loc = (window.SCHOOLMANAGER_CONFIG && window.SCHOOLMANAGER_CONFIG.locale) || (window.CFG_GLPI && window.CFG_GLPI.current_language) || document.documentElement.lang || '';
    loc = loc.toLowerCase().replace('-', '_');
    return loc === 'es_es' || loc === 'es' || loc.indexOf('es_') === 0;
  }

  // Fast path: if language is Spanish, server-side templates are already canonical Spanish
  if (isSpanish()) {
    return;
  }

  let dict = null;
  let keys = [];
  let masterRegex = null;
  let running = false;
  let observerInitialized = false;

  function keepOuterWhitespace(text, value){
    const start = (text.match(/^\s*/) || [''])[0];
    const end = (text.match(/\s*$/) || [''])[0];
    return start + value + end;
  }

  function escapeRegExp(s){
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function shouldSkip(node){
    if (!node) return true;
    if (node.nodeType === 3) {
      node = node.parentElement;
    }
    if (!node || node.nodeType !== 1) return false;
    // CRITICAL: NEVER touch GLPI core navigation, navbar, sidebar, header or top menu
    if (node.closest && node.closest('header, nav, aside, .navbar, #header, .page-header, .glpi-header, .glpi-sidebar, #navigation-menu, .navigation-header')) {
      return true;
    }
    // Must be inside a School Manager container
    if (node.closest && !node.closest('.schoolmanager-configured, .sm-home, .smset, .pc-wrap, .pc-page, .pcd, .pcda, .stp-wrap, .sid-wrap, .gsm-wrap, .ticdesk, .al-wrap, .av, .smdoc, .smv-wrap, .pe-wrap, .pcas-wrap, .pc-form, .pc-requests, .pc-selector, .schoolmanager-credit, #smHomeRoot, .pc-alerts, .pc-card, .gsm-card')) {
      return true;
    }
    if (node.getAttribute && node.getAttribute('translate') === 'no') return true;
    if (node.classList && (node.classList.contains('sm-no-translate') || node.classList.contains('notranslate'))) return true;
    if (node.closest && node.closest('[translate="no"], .sm-no-translate, .notranslate')) return true;
    return false;
  }

  function translateText(text){
    if (!dict || !text) return text;
    const clean = String(text).replace(/\s+/g, ' ').trim();
    if (!clean) return text;

    // 1. Exact match (fastest and safest O(1))
    if (Object.prototype.hasOwnProperty.call(dict, clean)) {
      return keepOuterWhitespace(text, dict[clean]);
    }

    if (clean.length > 400 || !masterRegex) return text;

    // 2. Safe precompiled master regex replacement
    try {
      masterRegex.lastIndex = 0;
      const replaced = clean.replace(masterRegex, function(match, p1, p2){
        if (p2 && Object.prototype.hasOwnProperty.call(dict, p2)) {
          return p1 + dict[p2];
        }
        return match;
      });
      return replaced !== clean ? keepOuterWhitespace(text, replaced) : text;
    } catch(e) {
      return text;
    }
  }

  function translateAttr(node, attr){
    if (shouldSkip(node)) return;
    const v = node.getAttribute(attr);
    if (!v) return;
    const t = translateText(v);
    if (t !== v) node.setAttribute(attr, t);
  }

  function walk(node){
    if (!node || !dict) return;
    if (shouldSkip(node)) return;

    if (node.nodeType === 3) {
      if (node.__sm_translated) return;
      const orig = node.nodeValue;
      const next = translateText(orig);
      if (next !== orig) {
        node.nodeValue = next;
      }
      node.__sm_translated = true;
      return;
    }

    if (node.nodeType !== 1) return;

    const tag = node.tagName ? node.tagName.toLowerCase() : '';
    if (['script', 'style', 'textarea', 'code', 'pre'].indexOf(tag) !== -1) return;

    if (node.getAttribute) {
      ['placeholder', 'title', 'aria-label', 'alt'].forEach(function(a){ translateAttr(node, a); });
      if (tag === 'input') {
        const type = (node.getAttribute('type') || '').toLowerCase();
        if (['button', 'submit', 'reset'].indexOf(type) !== -1) {
          translateAttr(node, 'value');
        }
      }
    }

    for (let i = 0; i < node.childNodes.length; i++) {
      walk(node.childNodes[i]);
    }
  }

  function run(){
    if (running || !dict) return;
    running = true;
    try {
      var roots = document.querySelectorAll('.schoolmanager-configured, .sm-home, .smset, .pc-wrap, .pc-page, .pcd, .pcda, .stp-wrap, .sid-wrap, .gsm-wrap, .ticdesk, .al-wrap, .av, .smdoc, .smv-wrap, .pe-wrap, .pcas-wrap, .pc-form, .pc-requests, .pc-selector, .schoolmanager-credit, #smHomeRoot');
      roots.forEach(function(r){ walk(r); });
    } finally {
      running = false;
    }
  }

  function setupObserver(){
    if (observerInitialized || !document.body) return;
    observerInitialized = true;
    var pendingNodes = [];
    var walkRaf = null;
    function scheduleWalk(){
      if (walkRaf) return;
      walkRaf = requestAnimationFrame(function(){
        walkRaf = null;
        if (running) return;
        running = true;
        try {
          while (pendingNodes.length > 0) {
            var n = pendingNodes.shift();
            walk(n);
          }
        } finally {
          running = false;
        }
      });
    }

    // Only observe added child elements, NEVER characterData to avoid recursive loop
    const mo = new MutationObserver(function(mutations){
      if (running) return;
      for (let m = 0; m < mutations.length; m++) {
        const added = mutations[m].addedNodes;
        if (added && added.length > 0) {
          for (let n = 0; n < added.length; n++) {
            if (added[n].nodeType === 1 && added[n].classList && (added[n].classList.contains('sm-no-translate') || added[n].classList.contains('pc-combo'))) {
              continue;
            }
            pendingNodes.push(added[n]);
          }
        }
      }
      if (pendingNodes.length > 0) {
        scheduleWalk();
      }
    });
    mo.observe(document.body, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['placeholder', 'title', 'aria-label', 'alt', 'value']
    });
  }

  function detectBase(){
    if (window.CFG_GLPI && window.CFG_GLPI.root_doc) return window.CFG_GLPI.root_doc;
    const m = (location.pathname || '').match(/^(.*)\/plugins\/schoolmanager\//);
    return m ? m[1] : '';
  }

  function getLocaleCode(){
    return (window.SCHOOLMANAGER_CONFIG && window.SCHOOLMANAGER_CONFIG.locale) || (window.CFG_GLPI && window.CFG_GLPI.current_language) || document.documentElement.lang || 'default';
  }

  function initDict(j){
    if (!j || !j.map) return;
    dict = j.map;
    keys = Object.keys(dict).filter(function(k){ return k && k.trim().length >= 2; }).sort(function(a, b){ return b.length - a.length; });
    if (keys.length > 0) {
      try {
        var pattern = '(^|[^\\p{L}\\p{N}_])(' + keys.map(escapeRegExp).join('|') + ')(?=[^\\p{L}\\p{N}_]|$)';
        masterRegex = new RegExp(pattern, 'gu');
      } catch(e) {
        masterRegex = null;
      }
    }
    run();
    if (document.title && !shouldSkip(document.querySelector('title'))) {
      const nt = translateText(document.title);
      if (nt !== document.title) document.title = nt;
    }
    setupObserver();
  }

  function load(){
    if (isSpanish()) return;

    var locKey = 'sm_i18n_v286_' + getLocaleCode();
    try {
      var cached = sessionStorage.getItem(locKey);
      if (cached) {
        var parsed = JSON.parse(cached);
        if (parsed && parsed.map) {
          initDict(parsed);
          return;
        }
      }
    } catch(e) {}

    fetch(detectBase() + '/plugins/schoolmanager/front/i18n.php?v=286', {credentials: 'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.map) return;
        try { sessionStorage.setItem(locKey, JSON.stringify(j)); } catch(e) {}
        initDict(j);
      }).catch(function(){});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
