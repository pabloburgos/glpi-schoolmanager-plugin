<?php
require_once(__DIR__ . '/../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/config.php');

$building = preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string)($_GET['building'] ?? plugin_schoolmanager_default_building_code())));
$floor = preg_replace('/[^A-Z0-9_-]/', '', strtoupper((string)($_GET['floor'] ?? plugin_schoolmanager_default_floor_code($building))));
$mode = strtolower((string)($_GET['mode'] ?? 'normal')) === 'select' ? 'select' : 'normal';

if (!in_array($building, plugin_schoolmanager_building_codes(), true) || !plugin_schoolmanager_floor($building, $floor)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Plan not found';
    exit;
}

$allRooms = plugin_schoolmanager_room_rows();
$floorRooms = array_values(array_filter($allRooms, static fn($r) => strtoupper((string)($r['building'] ?? '')) === $building && strtoupper((string)($r['floor'] ?? '')) === $floor));
if (!$floorRooms) {
    $floorRooms = array_values(array_filter($allRooms, static fn($r) => strtoupper((string)($r['building'] ?? '')) === $building));
}
$roomsJsonForPlan = json_encode(array_map(static fn($r) => [
    'id'    => (int)($r['id'] ?? 0),
    'aula'  => (string)($r['aula'] ?? ''),
    'code'  => (string)($r['codigo'] ?? ''),
    'desc'  => (string)($r['descripcion'] ?? ''),
], $floorRooms), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$file = plugin_schoolmanager_plan_path($building, $floor, $mode);
if (!$file || !is_file($file) || !plugin_schoolmanager_plan_is_supported($file)) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $bMeta = plugin_schoolmanager_building($building) ?: [];
    $fMeta = plugin_schoolmanager_floor($building, $floor) ?: [];
    $bName = plugin_schoolmanager_label($bMeta, 'name', $building);
    $fName = plugin_schoolmanager_label($fMeta, 'label', $floor);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <style>
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;width:100%;height:100%;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:#F8FAFC;color:#0F2951;overflow:hidden}
        .map-shell{height:100%;padding:14px;display:flex;flex-direction:column;gap:12px}
        .map-banner{flex:0 0 auto;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;box-shadow:0 2px 6px rgba(15,41,81,.04);flex-wrap:wrap}
        .map-banner-left{display:flex;align-items:center;gap:10px}
        .map-badge{padding:5px 10px;border-radius:8px;background:#0F2951;color:#FFFFFF;font-size:12px;font-weight:800;letter-spacing:.03em}
        .map-title{font-size:14px;font-weight:800;color:#0F2951}
        .map-sub{font-size:11.5px;font-weight:600;color:#64748B}
        .map-diagrams-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:#F0FDFA;border:1px solid #99F6E4;color:#0F766E;font-size:11px;font-weight:750}
        .blueprint{flex:1 1 0%;min-height:0;overflow-y:auto;background:#FFFFFF;border:1.5px solid #CBD5E1;border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:14px;background-image:radial-gradient(#CBD5E1 1px, transparent 1px);background-size:18px 18px}
        .corridor{flex:0 0 auto;padding:7px 14px;border-radius:8px;background:#F1F5F9;border:1px dashed #94A3B8;text-align:center;font-size:11px;font-weight:800;color:#64748B;letter-spacing:.08em;text-transform:uppercase}
        .wing-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;align-content:start}
        .room-zone{display:flex;flex-direction:column;justify-content:space-between;gap:8px;min-height:96px;padding:12px 14px;border-radius:10px;background:#FFFFFF;border:2px solid #0F2951;color:#0F2951;text-align:left;cursor:pointer;transition:all .15s ease;box-shadow:0 3px 8px rgba(15,41,81,.05)}
        .room-zone:hover,.room-zone:focus{background:#F0FDFA;border-color:#088E91;transform:translateY(-2px);box-shadow:0 6px 14px rgba(8,142,145,.15);outline:none}
        .room-zone.active{background:#CCFBF1;border-color:#088E91;box-shadow:0 0 0 3px rgba(8,142,145,.22)}
        .room-top{display:flex;align-items:center;justify-content:space-between;gap:6px}
        .room-num{font-size:15px;font-weight:850;color:#0F2951}
        .room-code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:10.5px;font-weight:800;padding:2px 6px;border-radius:5px;background:#F1F5F9;color:#334155}
        .room-zone:hover .room-code,.room-zone.active .room-code{background:#088E91;color:#FFFFFF}
        .room-desc{font-size:12px;font-weight:650;color:#475569;line-height:1.3}
        .room-cta{font-size:11px;font-weight:800;color:#088E91;display:inline-flex;align-items:center;gap:4px}
      </style>
    </head>
    <body>
      <div class="map-shell">
        <div class="map-banner">
          <div class="map-banner-left">
            <span class="map-badge"><?= htmlspecialchars($building . ' · ' . $floor, ENT_QUOTES, 'UTF-8') ?></span>
            <div>
              <div class="map-title"><?= htmlspecialchars($bName . ' — ' . $fName, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="map-sub">Pulsa sobre cualquier aula detectada en el diagrama para seleccionarla</div>
            </div>
          </div>
          <span class="map-diagrams-pill">📐 Esquema diagrams.net auto-detectado</span>
        </div>
        <div class="blueprint">
          <div class="corridor">Pasillo · <?= htmlspecialchars($fName, ENT_QUOTES, 'UTF-8') ?> (<?= count($floorRooms) ?> aulas detectadas)</div>
          <div class="wing-grid">
            <?php foreach ($floorRooms as $rm):
              $rid = (int)($rm['id'] ?? 0);
              $rcode = (string)($rm['codigo'] ?? '');
              $raula = (string)($rm['aula'] ?? $rcode);
              $rdesc = (string)($rm['descripcion'] ?? 'Espacio lectivo');
            ?>
              <button type="button" class="room-zone"
                      data-id="<?= $rid ?>"
                      data-code="<?= htmlspecialchars($rcode, ENT_QUOTES, 'UTF-8') ?>"
                      data-label="<?= htmlspecialchars($raula, ENT_QUOTES, 'UTF-8') ?>">
                <div class="room-top">
                  <span class="room-num">Aula <?= htmlspecialchars($raula, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="room-code"><?= htmlspecialchars($rcode, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="room-desc"><?= htmlspecialchars($rdesc ?: 'Espacio lectivo', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="room-cta">✓ Seleccionar aula</div>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <script>
        document.querySelectorAll('.room-zone').forEach(function(btn){
          btn.addEventListener('click', function(){
            document.querySelectorAll('.room-zone').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({
                type: 'schoolmanager-plan-click',
                id: btn.getAttribute('data-id') || '',
                code: btn.getAttribute('data-code') || '',
                label: btn.getAttribute('data-label') || '',
                href: 'location.form.php?id=' + encodeURIComponent(btn.getAttribute('data-id') || '')
              }, '*');
            }
          });
        });
        window.addEventListener('message', function(ev){
          var d = ev.data || {};
          if (d.type === 'schoolmanager-highlight-room') {
            document.querySelectorAll('.room-zone').forEach(function(b){
              var match = (d.id && b.getAttribute('data-id') === String(d.id)) || (d.code && b.getAttribute('data-code') === String(d.code));
              b.classList.toggle('active', !!match);
            });
          }
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}

$ext = plugin_schoolmanager_plan_type_from_path($file);
$mime = match($ext) {
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', default => 'text/html'
};
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($ext === 'svg') {
    header('Content-Type: text/html; charset=UTF-8');
    $svgRaw = (string)file_get_contents($file);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <style>
        html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#FFFFFF;display:flex;align-items:center;justify-content:center}
        .svg-wrap{width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:8px}
        .svg-wrap svg{max-width:100%!important;max-height:100%!important;width:auto!important;height:auto!important}
        .sm-detected-room{cursor:pointer!important;transition:filter .15s ease}
        .sm-detected-room:hover{filter:drop-shadow(0 0 6px rgba(8,142,145,.55))}
      </style>
    </head>
    <body>
      <div class="svg-wrap"><?= $svgRaw ?></div>
      <script>
        (function(){
          var rooms = <?= $roomsJsonForPlan ?>;
          function norm(s){ return String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]/g,''); }
          function matchRoomFromText(txt){
            var t = norm(txt);
            if (!t) return null;
            for (var i=0; i<rooms.length; i++){
              var r = rooms[i];
              var c = norm(r.code), a = norm(r.aula), ca = norm('aula' + r.aula);
              if (t === c || t === a || t === ca || (c && t.indexOf(c) !== -1) || (a && t === a)) return r;
            }
            return null;
          }
          document.addEventListener('click', function(ev){
            for (var el = ev.target; el && el !== document.body; el = el.parentElement) {
              var href = (el.getAttribute && (el.getAttribute('href') || el.getAttribute('xlink:href') || el.getAttribute('data-href'))) || '';
              if (href && href !== '#') {
                window.parent.postMessage({type:'schoolmanager-plan-click', href:href, label:el.textContent||''}, '*');
                ev.preventDefault();
                return;
              }
              var hit = matchRoomFromText(el.textContent || '');
              if (hit) {
                window.parent.postMessage({type:'schoolmanager-plan-click', id:hit.id, code:hit.code, label:hit.aula, href:'location.form.php?id='+hit.id}, '*');
                ev.preventDefault();
                return;
              }
            }
          }, true);
        })();
      </script>
    </body>
    </html>
    <?php
    exit;
}

if (in_array($ext, ['png','jpg','jpeg','webp'], true)) {
    header('Content-Type: text/html; charset=UTF-8');
    $data = base64_encode((string)file_get_contents($file));
    $src = 'data:' . $mime . ';base64,' . $data;
    echo '<!doctype html><meta charset="utf-8"><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#fff}body{display:grid;place-items:center}.plan{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain}.note{position:fixed;left:10px;bottom:10px;background:rgba(255,255,255,.9);border:1px solid #d7e6ec;border-radius:12px;padding:6px 10px;font:700 12px system-ui;color:#07384d}</style><img class="plan" alt="Plan" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"><div class="note">' . htmlspecialchars(strtoupper($ext) . ' plan', ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$html = (string)file_get_contents($file);
$html = str_replace('&quot;toolbar&quot;:&quot;zoom layers lightbox&quot;,', '', $html);
$html = str_replace('&quot;toolbar&quot;:&quot;zoom layers lightbox&quot;', '&quot;toolbar&quot;:&quot;&quot;', $html);
$html = str_replace('&quot;toolbar&quot;:&quot;zoom&quot;', '&quot;toolbar&quot;:&quot;&quot;', $html);
$html = str_replace('border:1px solid transparent;', 'border:0!important;width:100%!important;height:100%!important;max-width:100%!important;', $html);
$inject = <<<HTML
<style id="schoolmanager-clean-plan">
html,body{margin:0!important;padding:0!important;width:100%!important;height:100%!important;min-height:100%!important;overflow:hidden!important;background:#fff!important;}body{display:flex!important;align-items:center!important;justify-content:center!important;}.mxgraph{box-sizing:border-box!important;width:100%!important;height:100%!important;max-width:100%!important;border:0!important;overflow:hidden!important;background:#fff!important;display:flex!important;align-items:center!important;justify-content:center!important;}.mxgraph svg,.mxgraph img,.mxgraph canvas{max-width:100%!important;max-height:100%!important;width:auto!important;height:auto!important;object-fit:contain!important;}.geToolbarContainer,.geSidebarContainer,.geFooterContainer,.geMenubarContainer,.geStatus,.mxToolbarMode,.mxLightbox,.mxPopupMenu{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;}
</style>
<script>
(function(){
  var rooms = {$roomsJsonForPlan};
  function norm(s){ return String(s||'').toLowerCase().normalize('NFD').replace(/[\\u0300-\\u036f]/g,'').replace(/[^a-z0-9]/g,''); }
  function matchRoomFromText(txt){
    var t = norm(txt);
    if (!t || t.length > 40) return null;
    for (var i=0; i<rooms.length; i++){
      var r = rooms[i];
      var c = norm(r.code), a = norm(r.aula), ca = norm('aula' + r.aula);
      if (t === c || t === a || t === ca || (c && t.indexOf(c) !== -1)) return r;
    }
    return null;
  }
  function hrefFromNode(n){
    for(var el=n;el&&el.nodeType===1;el=el.parentElement){
      var href=(el.getAttribute&&(el.getAttribute('data-pc-real-href')||el.getAttribute('href')||el.getAttribute('xlink:href')||el.getAttribute('data-href')||el.getAttribute('data-url')))||'';
      if(href&&href!=='#') return {href:href, label:el.textContent||''};
      var title=(el.getAttribute&&(el.getAttribute('title')||el.getAttribute('aria-label')))||'';
      if(title) {
        var hitT = matchRoomFromText(title);
        if (hitT) return {id:hitT.id, code:hitT.code, label:hitT.aula, href:'location.form.php?id='+hitT.id};
        return {href:title, label:title};
      }
      var txt = (el.textContent || '').trim();
      if (txt && txt.length <= 32) {
        var hit = matchRoomFromText(txt);
        if (hit) return {id:hit.id, code:hit.code, label:hit.aula, href:'location.form.php?id='+hit.id};
      }
    }
    return null;
  }
  document.addEventListener('click',function(ev){
    var found=hrefFromNode(ev.target);
    if(found && window.parent && window.parent!==window){
      window.parent.postMessage({
        type:'schoolmanager-plan-click',
        id:found.id||'',
        code:found.code||'',
        label:found.label||'',
        href:found.href||''
      },'*');
      ev.preventDefault();
      ev.stopPropagation();
    }
  },true);
})();
</script>
HTML;
if (stripos($html, '</head>') !== false) { $html = preg_replace('/<\/head>/i', $inject . "\n</head>", $html, 1); } else { $html = $inject . $html; }
echo "<!-- GLPI School Manager diagrams.net plan frame | $building-$floor | " . basename($file) . " -->\n";
echo $html;
