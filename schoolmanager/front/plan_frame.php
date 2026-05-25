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
$file = plugin_schoolmanager_plan_path($building, $floor, $mode);
if (!$file || !is_file($file) || !plugin_schoolmanager_plan_is_supported($file)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><style>body{margin:0;font-family:system-ui;background:#fff;color:#07384d;display:grid;place-items:center;min-height:100vh}.box{border:1px solid #d9e7ef;border-radius:18px;padding:22px;box-shadow:0 14px 40px rgba(7,56,77,.08)}b{color:#b6252b}</style><div class="box"><b>Plan not found</b><br>' . htmlspecialchars($building . '-' . $floor, ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}
$ext = plugin_schoolmanager_plan_type_from_path($file);
$mime = match($ext) {
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', default => 'text/html'
};
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (in_array($ext, ['svg','png','jpg','jpeg','webp'], true)) {
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
$inject = <<<'HTML'
<style id="schoolmanager-clean-plan">
html,body{margin:0!important;padding:0!important;width:100%!important;height:100%!important;min-height:100%!important;overflow:hidden!important;background:#fff!important;}body{display:flex!important;align-items:center!important;justify-content:center!important;}.mxgraph{box-sizing:border-box!important;width:100%!important;height:100%!important;max-width:100%!important;border:0!important;overflow:hidden!important;background:#fff!important;display:flex!important;align-items:center!important;justify-content:center!important;}.mxgraph svg,.mxgraph img,.mxgraph canvas{max-width:100%!important;max-height:100%!important;width:auto!important;height:auto!important;object-fit:contain!important;}.geToolbarContainer,.geSidebarContainer,.geFooterContainer,.geMenubarContainer,.geStatus,.mxToolbarMode,.mxLightbox,.mxPopupMenu{display:none!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important;}
</style>
<script>(function(){function hrefFromNode(n){for(var el=n;el&&el.nodeType===1;el=el.parentElement){var href=(el.getAttribute&&(el.getAttribute('data-pc-real-href')||el.getAttribute('href')||el.getAttribute('xlink:href')||el.getAttribute('data-href')||el.getAttribute('data-url')))||''; if(href&&href!=='#') return href; var title=(el.getAttribute&&(el.getAttribute('title')||el.getAttribute('aria-label')))||''; if(title) return title;}return '';}function send(raw){if(window.parent&&window.parent!==window){window.parent.postMessage({type:'schoolmanager-plan-click',href:raw,title:raw},'*');return true}return false}document.addEventListener('click',function(ev){var raw=hrefFromNode(ev.target); if(raw&&send(raw)){ev.preventDefault();ev.stopPropagation();}},true);})();</script>
HTML;
if (stripos($html, '</head>') !== false) { $html = preg_replace('/<\/head>/i', $inject . "\n</head>", $html, 1); } else { $html = $inject . $html; }
echo "<!-- GLPI School Manager plan frame | $building-$floor | " . basename($file) . " -->\n";
echo $html;
