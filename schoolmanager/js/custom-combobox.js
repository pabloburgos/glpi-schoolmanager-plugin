(function(){
  'use strict';
  // Native select mode: keeps standard browser <select> elements styled cleanly via CSS
  // without floating custom DOM menus that can clip or overlap.
  function restoreNativeSelects(root){
    const scope = root || document;
    scope.querySelectorAll('.pc-combo').forEach(function(combo){
      combo.remove();
    });
    scope.querySelectorAll('select.pc-native-select-hidden, select[data-pc-combo]').forEach(function(sel){
      sel.classList.remove('pc-native-select-hidden');
      sel.removeAttribute('data-pc-combo');
      sel.removeAttribute('tabindex');
      sel.style.position = '';
      sel.style.left = '';
      sel.style.width = '';
      sel.style.height = '';
      sel.style.opacity = '';
      sel.style.pointerEvents = '';
    });
  }

  window.initSchoolmanagerComboboxes = restoreNativeSelects;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ restoreNativeSelects(document); });
  } else {
    restoreNativeSelects(document);
  }
})();
