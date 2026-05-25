(function(){
  function isPluginPage(){ return /\/plugins\/schoolmanager\//.test(location.pathname); }
  if(!isPluginPage()) return;
  var html=document.documentElement;
  html.classList.add('schoolmanager-page-js');
  function ready(){
    requestAnimationFrame(function(){
      html.classList.add('schoolmanager-page-ready');
      html.classList.remove('schoolmanager-page-leaving');
    });
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, {once:true}); else ready();
  window.addEventListener('pageshow', ready);
  document.addEventListener('click', function(ev){
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if(!a) return;
    var href = a.getAttribute('href') || '';
    if(!href || href.charAt(0)==='#' || a.target || a.hasAttribute('download') || /^javascript:/i.test(href)) return;
    try{
      var url = new URL(href, location.href);
      if(url.origin === location.origin && /\/plugins\/schoolmanager\//.test(url.pathname)){
        html.classList.add('schoolmanager-page-leaving');
      }
    }catch(e){}
  }, true);
})();
