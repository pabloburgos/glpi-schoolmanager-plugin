(function () {
  'use strict';

  const PATH = window.location.pathname || '';
  const IS_TARGET_FORM = /\/front\/(computer|ticket)\.form\.php$/i.test(PATH);
  if (!IS_TARGET_FORM) return;

  const ROOT = (window.GLPI_SCHOOLMANAGER_ROOT || getRootDoc());
  const SELECTOR_URL = ROOT + '/plugins/schoolmanager/front/selector.php?building=ED1&floor=P0&mode=select&embed=1&compact=1&v=238';
  const BUTTON_ID = 'schoolmanager-location-button';
  const FAB_ID = 'schoolmanager-location-fab';
  const STATUS_ID = 'schoolmanager-location-status';
  const DEBUG = /[?&]schoolmanager_debug=1/.test(window.location.search);

  function log() { if (DEBUG && window.console) console.log.apply(console, ['[schoolmanager]'].concat([].slice.call(arguments))); }

  function getRootDoc() {
    const m = PATH.match(/^(.*?)\/front\//);
    return m ? m[1] : '';
  }

  function qsAll(sel, root = document) {
    try { return Array.from(root.querySelectorAll(sel)); } catch (e) { return []; }
  }

  function norm(txt) {
    return String(txt || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
  }

  function isVisible(el) {
    if (!el) return false;
    const r = el.getBoundingClientRect();
    return !!(r.width || r.height || el.getClientRects().length);
  }

  function getLocationRow() {
    const labelCandidates = qsAll('label, .form-label, th, td, div, span')
      .filter(el => {
        const t = norm(el.textContent);
        return t === 'ubicaciones' || t === 'ubicacion' || t === 'localizacion' || t.includes('ubicaciones') || t.includes('ubicacion') || t.includes('localizacion');
      })
      .sort((a, b) => (a.textContent || '').length - (b.textContent || '').length);

    for (const label of labelCandidates) {
      const row = label.closest('.form-field, .form-group, .mb-3, .row, tr, .asset-field, .input-group') || label.parentElement;
      if (row && row.querySelector && row.querySelector('input,select,textarea,button,.select2,.select2-container')) {
        return row;
      }
    }
    return null;
  }

  function findLocationField() {
    const row = getLocationRow();
    const selectors = [
      'select[name="locations_id"]',
      'input[name="locations_id"]',
      'select[name$="[locations_id]"]',
      'input[name$="[locations_id]"]',
      'select[id*="locations_id"]',
      'input[id*="locations_id"]',
      'select[name*="locations_id"]',
      'input[name*="locations_id"]',
      'select[name*="location"]',
      'input[name*="location"]',
      'select[name*="locations"]',
      'input[name*="locations"]',
      'input[id*="dropdown_locations"]',
      'select[id*="dropdown_locations"]',
      'input[id*="location"]',
      'select[id*="location"]'
    ];

    let candidates = [];
    selectors.forEach(sel => {
      candidates.push(...qsAll(sel, row || document));
      if (row) candidates.push(...qsAll(sel));
    });

    candidates = candidates.filter((el, idx, arr) => arr.indexOf(el) === idx);
    let field = candidates.find(el => !el.disabled && isVisible(el));
    if (!field) field = candidates.find(el => !el.disabled);
    if (field) return field;

    // Fallback: crea un campo oculto estándar dentro del formulario.
    const form = (row && row.closest('form')) || document.querySelector('form');
    if (form) {
      field = form.querySelector('input[name="locations_id"]');
      if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'locations_id';
        field.id = 'schoolmanager_locations_id';
        form.appendChild(field);
      }
      return field;
    }
    return null;
  }

  function findInsertionPoint() {
    const row = getLocationRow();
    if (row) {
      const inputGroup = row.querySelector('.input-group, .field-container, .select2-container, select, input');
      return inputGroup || row;
    }
    const field = findLocationField();
    if (!field) return null;
    return field.closest('.input-group, .form-field, .form-group, .mb-3, .row, td, div') || field;
  }

  function ensureButton() {
    if (document.getElementById(BUTTON_ID)) return true;

    const insertion = findInsertionPoint();
    if (!insertion) {
      log('No insertion point yet');
      return false;
    }

    const wrap = document.createElement('div');
    wrap.className = 'schoolmanager-picker-wrap';
    wrap.innerHTML = [
      '<button type="button" id="' + BUTTON_ID + '" class="schoolmanager-picker-btn">',
      '<span class="schoolmanager-picker-icon pc-i-location" aria-hidden="true"></span>',
      '<span>Elegir en plano</span>',
      '</button>',
      '<span id="' + STATUS_ID + '" class="schoolmanager-picker-status" hidden></span>'
    ].join('');

    if (insertion.parentNode) {
      insertion.parentNode.insertBefore(wrap, insertion.nextSibling);
    } else {
      insertion.insertAdjacentElement('afterend', wrap);
    }

    const btn = document.getElementById(BUTTON_ID);
    if (btn) btn.addEventListener('click', openSelectorModal);
    log('Button inserted');
    return true;
  }

  function ensureFloatingButton() {
    if (document.getElementById(FAB_ID) || document.getElementById(BUTTON_ID)) return true;
    const fab = document.createElement('button');
    fab.type = 'button';
    fab.id = FAB_ID;
    fab.className = 'schoolmanager-picker-fab';
    fab.innerHTML = '<span class="schoolmanager-picker-icon pc-i-location" aria-hidden="true"></span><strong>Elegir ubicación</strong>';
    fab.addEventListener('click', openSelectorModal);
    document.body.appendChild(fab);
    log('Floating fallback button inserted');
    return true;
  }


  function applyModalSafeArea(modal) {
    if (!modal) return;
    document.documentElement.style.setProperty('--pc-glpi-modal-left', '0px');
    document.documentElement.style.setProperty('--pc-glpi-modal-top', '0px');
    modal.style.setProperty('position', 'fixed', 'important');
    modal.style.setProperty('inset', '0', 'important');
    modal.style.setProperty('left', '0', 'important');
    modal.style.setProperty('top', '0', 'important');
    modal.style.setProperty('right', '0', 'important');
    modal.style.setProperty('bottom', '0', 'important');
    modal.style.setProperty('width', '100vw', 'important');
    modal.style.setProperty('height', '100vh', 'important');
    modal.style.setProperty('display', 'flex', 'important');
    modal.style.setProperty('align-items', 'center', 'important');
    modal.style.setProperty('justify-content', 'center', 'important');
    modal.style.setProperty('z-index', '2147483647', 'important');
    modal.style.setProperty('overflow', 'hidden', 'important');
    const card = modal.querySelector('.schoolmanager-modal-card');
    if (card) {
      card.style.setProperty('width', 'min(940px, calc(100vw - 72px))', 'important');
      card.style.setProperty('height', 'min(640px, calc(100vh - 72px))', 'important');
      card.style.setProperty('max-width', 'calc(100vw - 72px)', 'important');
      card.style.setProperty('max-height', 'calc(100vh - 72px)', 'important');
      card.style.setProperty('margin', '0', 'important');
      card.style.setProperty('transform', 'none', 'important');
    }
  }

  function openSelectorModal() {
    let modal = document.getElementById('schoolmanager-selector-modal');
    if (modal) modal.remove();
    document.body.classList.remove('schoolmanager-modal-open');

    modal = document.createElement('div');
    modal.id = 'schoolmanager-selector-modal';
    modal.className = 'schoolmanager-modal';
    modal.innerHTML = [
      '<div class="schoolmanager-modal-card" role="dialog" aria-modal="true">',
      '<div class="schoolmanager-modal-head">',
      '<div><strong>Seleccionar ubicación</strong><small>Elige un aula desde la lista o desde el plano.</small></div>',
      '<button type="button" class="schoolmanager-modal-close" aria-label="Cerrar">×</button>',
      '</div>',
      '<iframe class="schoolmanager-modal-frame" src="' + SELECTOR_URL + '"></iframe>',
      '</div>'
    ].join('');
    document.body.appendChild(modal);
    applyModalSafeArea(modal);
    document.body.classList.add('schoolmanager-modal-open');

    window.addEventListener('resize', function () { const m = document.getElementById('schoolmanager-selector-modal'); if (m) applyModalSafeArea(m); }, { passive: true });
    modal.querySelector('.schoolmanager-modal-close').addEventListener('click', closeSelectorModal);
    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) closeSelectorModal();
    });
    document.addEventListener('keydown', escClose, { once: true });
  }

  function escClose(ev) {
    if (ev.key === 'Escape') closeSelectorModal();
    else document.addEventListener('keydown', escClose, { once: true });
  }

  function closeSelectorModal() {
    const modal = document.getElementById('schoolmanager-selector-modal');
    if (modal) modal.remove();
    document.body.classList.remove('schoolmanager-modal-open');
  }

  function setFieldValue(field, id, text) {
    if (!field) return false;
    const tag = (field.tagName || '').toLowerCase();

    if (tag === 'select') {
      let opt = Array.from(field.options).find(o => String(o.value) === String(id));
      if (!opt) {
        opt = new Option(text, id, true, true);
        field.appendChild(opt);
      }
      field.value = String(id);
      if (window.jQuery) {
        try { window.jQuery(field).val(String(id)).trigger('change'); } catch (e) {}
      }
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    }

    field.value = String(id);
    field.setAttribute('value', String(id));
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }

  function updateVisibleLabel(text) {
    const row = getLocationRow();
    if (!row) return;
    const targets = qsAll('.select2-selection__rendered, .ts-control .item, .form-control, button.dropdown-toggle', row);
    targets.forEach(t => {
      if (t.tagName && /input|select|textarea/i.test(t.tagName)) return;
      const old = (t.textContent || '').trim();
      if (!old || old === '-----' || old.includes('-----') || t.classList.contains('select2-selection__rendered')) {
        t.textContent = text;
        t.setAttribute('title', text);
      }
    });
  }

  function fillLocation(payload) {
    const id = payload && payload.id ? String(payload.id) : '';
    if (!id) return;

    const label = [payload.name || 'Ubicación', payload.code ? '(' + payload.code + ')' : ''].filter(Boolean).join(' ');
    const field = findLocationField();
    const ok = setFieldValue(field, id, label);

    qsAll('input[name="locations_id"], input[name$="[locations_id]"], input[id*="locations_id"], select[name="locations_id"], select[name$="[locations_id]"], select[id*="locations_id"]').forEach(el => {
      if (!el.disabled) setFieldValue(el, id, label);
    });

    if (window.jQuery && field) {
      try {
        const $field = window.jQuery(field);
        if ($field.data('select2')) {
          const option = new Option(label, id, true, true);
          $field.append(option).trigger('change');
          $field.trigger({ type: 'select2:select', params: { data: { id: id, text: label } } });
        }
      } catch (e) {}
    }

    updateVisibleLabel(label);
    updateStatus(ok, payload);
    closeSelectorModal();
  }

  function updateStatus(ok, payload) {
    let status = document.getElementById(STATUS_ID);
    if (!status) {
      ensureButton();
      status = document.getElementById(STATUS_ID);
    }
    if (!status) return;
    status.hidden = false;
    status.className = 'schoolmanager-picker-status ' + (ok ? 'ok' : 'warn');
    status.textContent = ok
      ? 'Ubicación seleccionada: ' + (payload.name || '') + ' · ID ' + payload.id
      : 'Ubicación seleccionada. Revisa antes de guardar: ID ' + payload.id;
  }

  window.addEventListener('message', function (event) {
    if (!event.data || event.data.type !== 'schoolmanager:location-selected') return;
    fillLocation(event.data);
  });

  function boot() {
    document.documentElement.setAttribute('data-schoolmanager-integration', 'loaded');
    log('Integration boot on', PATH);
    ensureButton();
    let tries = 0;
    const timer = setInterval(function () {
      tries++;
      const ok = ensureButton();
      if (!ok && tries === 8) ensureFloatingButton();
      if (ok || tries > 60) clearInterval(timer);
    }, 350);

    const observer = new MutationObserver(function () {
      if (!document.getElementById(BUTTON_ID)) ensureButton();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
