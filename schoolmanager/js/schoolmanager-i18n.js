(function(){
  // Only execute within School Manager plugin pages
  if (!location.pathname.includes('/plugins/schoolmanager/')) {
    return;
  }

  let dict = null;
  let keys = [];
  let running = false;

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
    if (node.getAttribute && node.getAttribute('translate') === 'no') return true;
    if (node.classList && (node.classList.contains('sm-no-translate') || node.classList.contains('notranslate'))) return true;
    if (node.closest && node.closest('[translate="no"], .sm-no-translate, .notranslate')) return true;
    return false;
  }

  function translateText(text){
    if (!dict || !text) return text;
    const clean = String(text).replace(/\s+/g, ' ').trim();
    if (!clean) return text;

    // 1. Exact match (fastest and safest)
    if (Object.prototype.hasOwnProperty.call(dict, clean)) {
      return keepOuterWhitespace(text, dict[clean]);
    }

    if (clean.length > 300) return text;

    // 2. Safe whole-word / phrase match (prevent partial substring collisions like Italian -> Italianoo)
    let next = clean;
    for (const k of keys) {
      if (k.length < 3 || !Object.prototype.hasOwnProperty.call(dict, k)) continue;
      const targetVal = dict[k];
      if (next === k) return keepOuterWhitespace(text, targetVal);

      // Only match with word boundaries to prevent corrupting substrings inside words
      const rx = new RegExp('(^|[^\\p{L}\\p{N}_])' + escapeRegExp(k) + '(?=[^\\p{L}\\p{N}_]|$)', 'gu');
      if (rx.test(next)) {
        next = next.replace(rx, function(m, p1){
          return p1 + targetVal;
        });
      }
    }
    return next !== clean ? keepOuterWhitespace(text, next) : text;
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
    if (running || !dict || !document.body) return;
    running = true;
    try {
      walk(document.body);
    } finally {
      running = false;
    }
  }

  function detectBase(){
    if (window.CFG_GLPI && window.CFG_GLPI.root_doc) return window.CFG_GLPI.root_doc;
    const m = (location.pathname || '').match(/^(.*)\/plugins\/schoolmanager\//);
    return m ? m[1] : '';
  }

  function load(){
    fetch(detectBase() + '/plugins/schoolmanager/front/i18n.php?v=' + Date.now(), {credentials: 'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || !j.map) return;
        dict = j.map;
        keys = Object.keys(dict).sort(function(a, b){ return b.length - a.length; });
        run();
        if (document.title && !shouldSkip(document.querySelector('title'))) {
          const nt = translateText(document.title);
          if (nt !== document.title) document.title = nt;
        }

        // Only observe added child elements, NEVER characterData to avoid recursive loop
        const mo = new MutationObserver(function(mutations){
          if (running) return;
          for (let m = 0; m < mutations.length; m++) {
            const added = mutations[m].addedNodes;
            if (added && added.length > 0) {
              for (let n = 0; n < added.length; n++) {
                walk(added[n]);
              }
            }
          }
        });
        mo.observe(document.body, {
          subtree: true,
          childList: true,
          attributes: true,
          attributeFilter: ['placeholder', 'title', 'aria-label', 'alt', 'value']
        });
      }).catch(function(){});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
