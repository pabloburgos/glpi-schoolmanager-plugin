<?php
if (!function_exists('plugin_schoolmanager_home_button')) {
    function plugin_schoolmanager_home_button($label = null) {
        if ($label === null) {
            $label = function_exists('plugin_schoolmanager_tr') ? plugin_schoolmanager_tr('menu_home', 'Inicio') : 'Inicio';
        }
        global $CFG_GLPI;
        $root = $CFG_GLPI['root_doc'] ?? '';
        $href = htmlspecialchars($root . '/plugins/schoolmanager/front/formularios.php?v=' . (defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : time()), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
        return '<style id="sm-home-toolbar-v2">
.sm-toolbar{display:flex;justify-content:flex-end;align-items:center;gap:12px;max-width:1240px;margin:16px auto 0;padding:0 8px}.sm-toolbar.sm-toolbar-left{justify-content:flex-start}.sm-home-btn{display:inline-flex;align-items:center;gap:10px;min-height:48px;border:1px solid #7c1b1b;background:linear-gradient(135deg,#8b1e1e 0%,#a92323 58%,#b72c31 100%);color:#fff!important;border-radius:16px;padding:0 18px;text-decoration:none!important;font-weight:900;box-shadow:0 12px 28px rgba(139,30,30,.18);transition:transform .18s ease,box-shadow .18s ease,background .18s ease}.sm-home-btn:hover{transform:translateY(-2px);background:linear-gradient(135deg,#9f2424 0%,#bd3131 100%);color:#fff!important;box-shadow:0 16px 34px rgba(139,30,30,.24)}.sm-home-btn .sm-home-btn-ico{display:inline-grid;place-items:center;width:18px;height:18px}.sm-home-btn .sm-home-btn-ico svg{width:18px;height:18px;display:block}@media(max-width:760px){.sm-toolbar{justify-content:flex-start;padding:0 12px;margin-top:12px}.sm-home-btn{width:auto}}
</style>'
        . '<div class="sm-toolbar"><a class="sm-home-btn" href="' . $href . '"><span class="sm-home-btn-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.75 10.5 12 4.25l8.25 6.25" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.75 9.75v9.5h10.5v-9.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 19.25V14.5a2 2 0 0 1 4 0v4.75" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>' . $label . '</span></a></div>';
    }
}
