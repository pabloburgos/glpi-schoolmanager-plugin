<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');

global $CFG_GLPI;
$root = $CFG_GLPI['root_doc'] ?? '';
$pluginVersion = defined('PLUGIN_SCHOOLMANAGER_VERSION') ? PLUGIN_SCHOOLMANAGER_VERSION : '1.0.0';
$glpiVersion = defined('GLPI_VERSION') ? GLPI_VERSION : 'Detectada por GLPI';
$logoUrl = function_exists('plugin_schoolmanager_logo_url') ? plugin_schoolmanager_logo_url() : ($root . '/plugins/schoolmanager/logo.svg?v=' . rawurlencode($pluginVersion));

Html::header('Versiones y documentacion School Manager', $_SERVER['PHP_SELF'], 'tools', 'PluginSchoolmanagerMapa');

function schoolmanager_doc_svg($name) {
    $icons = [
        'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.8 10.5 12 4.2l8.2 6.3"/><path d="M6.8 9.8v9.5h10.4V9.8"/><path d="M10 19.3v-4.7a2 2 0 0 1 4 0v4.7"/></svg>',
        'map' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18 3.5 21V6L9 3l6 3 5.5-3v15L15 21z"/><path d="M9 3v15"/><path d="M15 6v15"/></svg>',
        'panel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8"/><path d="M8 13h5"/><path d="M8 17h8"/></svg>',
        'ticket' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v4a3 3 0 0 0 0 6v4H5v-4a3 3 0 0 0 0-6z"/><path d="M10 8v8"/><path d="M14 8v8"/></svg>',
        'assets' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="12.8" height="9.4" rx="1.8"/><path d="M7.2 17.7h4.4M9.4 13.4v4.3"/><rect x="17.2" y="6.2" width="3.8" height="10.8" rx="1.2"/><path d="M18.5 14.2h1.2M5.3 20h13.4"/></svg>',
        'stock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5-8 4.5z"/><path d="M4 7.5v9L12 21l8-4.5v-9"/><path d="M12 12v9"/></svg>',
        'version' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V7a2 2 0 0 1 2-2h12"/><path d="M8 21h10a2 2 0 0 0 2-2V7"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>',
        'file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>',
        'folder' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 7.5a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/></svg>',
        'code' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 9-4 3 4 3"/><path d="m16 9 4 3-4 3"/><path d="m13 5-2 14"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 19 6v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6z"/><path d="m9 12 2 2 4-5"/></svg>',
        'spark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 14.3 8.7 20 11l-5.7 2.3L12 19l-2.3-5.7L4 11l5.7-2.3z"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6"/><path d="m16 16 4 4"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>',
        'book' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h10a4 4 0 0 1 4 4v12H9a4 4 0 0 0-4-4z"/><path d="M5 4v12"/><path d="M9 8h6M9 12h6"/></svg>',
        'glpi' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>',
        'ui' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M8 8h3M8 12h8"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.85"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'db' => '<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5"/><path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>',
        'route' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M18 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M8.5 13.5 15.5 9"/><path d="M6 18v2"/><path d="M18 6V4"/></svg>',
        'wrench' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-3-3z"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="3"/></svg>',
        'editor' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16"/><path d="M14.7 4.3a2.1 2.1 0 0 1 3 3L8 17l-4 1 1-4z"/><path d="m13 6 3 3"/></svg>',
        'heart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 0 0-7.1 7.1L12 21l8.8-8.3a5 5 0 0 0 0-7.1z"/></svg>',
    ];
    return $icons[$name] ?? $icons['check'];
}

function schoolmanager_doc_bytes($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) { return $bytes . ' B'; }
    if ($bytes < 1048576) { return round($bytes / 1024, 1) . ' KB'; }
    return round($bytes / 1048576, 1) . ' MB';
}

function schoolmanager_doc_collect_files($base) {
    $out = [];
    $base = realpath($base);
    if (!$base || !is_dir($base)) { return $out; }
    $skipDirs = ['.git', 'vendor', 'node_modules', 'tmp', 'cache'];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            function ($current, $key, $iterator) use ($skipDirs) {
                if ($current->isDir() && in_array($current->getFilename(), $skipDirs, true)) { return false; }
                return true;
            }
        )
    );
    foreach ($it as $file) {
        if (!$file->isFile()) { continue; }
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $folder = trim(dirname($rel), '.');
        if ($folder === '') { $folder = 'raiz'; }
        $out[] = [
            'path' => $rel,
            'folder' => $folder,
            'ext' => $ext ?: 'archivo',
            'size' => $file->getSize(),
            'mtime' => $file->getMTime(),
        ];
    }
    usort($out, function($a, $b) { return strcmp($a['path'], $b['path']); });
    return $out;
}

function schoolmanager_doc_file_module($path) {
    if (strpos($path, 'nueva_incidencia') !== false || strpos($path, 'mis_solicitudes') !== false || strpos($path, 'solicitud_detalle') !== false || strpos($path, 'panel_tic') !== false || strpos($path, 'avisos') !== false) { return 'Soporte'; }
    if (strpos($path, 'selector') !== false || strpos($path, 'plan_frame') !== false || strpos($path, 'detalle_aula') !== false || strpos($path, 'aulas_data') !== false || strpos($path, 'ubicaciones') !== false || strpos($path, 'plan_zones') !== false || strpos($path, 'maps/') !== false) { return 'Planos y aulas'; }
    if (strpos($path, 'gestion_activos') !== false || strpos($path, 'nuevo_activo') !== false || strpos($path, 'editar_activo') !== false || strpos($path, 'assets_') !== false) { return 'Inventario'; }
    if (strpos($path, 'stock_') !== false) { return 'Stock'; }
    if (strpos($path, 'versiones') !== false) { return 'Documentación'; }
    return 'Núcleo';
}

$pluginBase = realpath(__DIR__ . '/..');
$files = schoolmanager_doc_collect_files($pluginBase);
$fileCount = count($files);
$folderCount = count(array_unique(array_map(function($x){ return $x['folder']; }, $files)));
$totalSize = array_sum(array_map(function($x){ return $x['size']; }, $files));

$quickLinks = [
    ['Inicio', 'home', $root . '/plugins/schoolmanager/front/formularios.php?v=' . rawurlencode($pluginVersion), 'Panel principal del plugin.'],
    ['Plano', 'map', $root . '/plugins/schoolmanager/front/selector.php?building=' . rawurlencode(plugin_schoolmanager_default_building_code()) . '&floor=' . rawurlencode(plugin_schoolmanager_default_floor_code(plugin_schoolmanager_default_building_code())) . '&v=' . rawurlencode($pluginVersion), 'Selector de aulas y edificios.'],
    ['Panel TIC', 'panel', $root . '/plugins/schoolmanager/front/panel_tic.php?v=' . rawurlencode($pluginVersion), 'Control técnico de tickets.'],
    ['Activos', 'assets', $root . '/plugins/schoolmanager/front/gestion_activos.php?v=' . rawurlencode($pluginVersion), 'Inventario simplificado.'],
    ['Editor', 'editor', $root . '/plugins/schoolmanager/front/archivos.php?v=' . rawurlencode($pluginVersion), 'Editar datos internos con backup.'],
];

$modules = [
    ['id'=>'support', 'icon'=>'ticket', 'title'=>'Soporte e incidencias', 'goal'=>'Crear, consultar, responder y cerrar incidencias sin entrar en pantallas complejas de GLPI.', 'text'=>'El profesor comunica el problema desde un formulario guiado. El técnico lo ve en el Centro de control TIC, puede gestionarlo, asignarlo, responder y resolverlo. El usuario puede revisar su estado en Mis solicitudes y Avisos.', 'files'=>['front/nueva_incidencia.php','front/mis_solicitudes.php','front/solicitud_detalle.php','front/panel_tic.php','front/avisos.php']],
    ['id'=>'maps', 'icon'=>'map', 'title'=>'Planos y aulas', 'goal'=>'Elegir ubicaciones de forma visual y conectar cada aula con su ubicación real de GLPI.', 'text'=>'El plano permite cambiar edificio y planta, buscar por aula, seleccionar desde lista o desde el plano, ver detalles del aula y abrir la ubicación nativa de GLPI cuando procede.', 'files'=>['front/selector.php','front/plan_frame.php','front/detalle_aula.php','inc/aulas_data.php','inc/ubicaciones_ids.php','inc/plan_zones.php']],
    ['id'=>'assets', 'icon'=>'assets', 'title'=>'Inventario y activos', 'goal'=>'Gestionar ordenadores, monitores, impresoras, red y periféricos desde una vista más clara.', 'text'=>'El inventario guiado crea y edita activos de GLPI manteniendo ubicación, fabricante, modelo, estado, número de serie e inventario. Cada activo puede abrir su ficha nativa si hace falta.', 'files'=>['front/gestion_activos.php','front/nuevo_activo.php','front/editar_activo.php','inc/assets_helpers.php']],
    ['id'=>'stock', 'icon'=>'stock', 'title'=>'Control de stock TIC', 'goal'=>'Consultar consumibles y cartuchos reales de GLPI sin perderse en la interfaz nativa.', 'text'=>'Lee modelos de consumibles, cartuchos y unidades disponibles. Permite controlar material rápido como cables, ratones, tóner, adaptadores o repuestos del departamento TIC.', 'files'=>['front/stock_glpi.php','front/stock_movimiento.php','inc/stock_helpers.php']],
    ['id'=>'core', 'icon'=>'code', 'title'=>'Núcleo y estilo', 'goal'=>'Mantener integración con GLPI, permisos, tema visual, rutas y utilidades comunes.', 'text'=>'Incluye configuración del plugin, hooks, permisos, datos compartidos, tema CSS, transiciones, componentes de interfaz y helpers para reducir código repetido.', 'files'=>['setup.php','hook.php','inc/permissions.php','inc/ui_helpers.php','css/gestion-schoolmanager-theme.css','js/schoolmanager-page-transition.js']],
];

$flows = [
    ['1', 'Entrada', 'El usuario entra desde Gestión School Manager y elige si quiere crear incidencia, consultar solicitudes, abrir plano, gestionar activos o stock.'],
    ['2', 'Ubicación', 'Cuando una pantalla necesita aula, abre el selector de planos y guarda el ID real de ubicación de GLPI.'],
    ['3', 'Acción GLPI', 'El plugin crea tickets, modifica activos o consulta stock usando las tablas y clases reales de GLPI.'],
    ['4', 'Seguimiento', 'Las pantallas simplificadas muestran estado, respuesta, solución, técnico asignado y enlaces nativos.'],
];

$manual = [
    ['icon'=>'ticket','title'=>'Crear una incidencia','text'=>'Entrar en Crear incidencia, elegir ubicación desde plano o lista, seleccionar categoría, añadir descripción y enviar. El ticket queda registrado en GLPI.'],
    ['icon'=>'eye','title'=>'Revisar una solicitud','text'=>'Entrar en Mis solicitudes o Avisos. Las tarjetas muestran si está abierta, en espera, respondida o resuelta, con acceso al detalle.'],
    ['icon'=>'panel','title'=>'Gestionar como técnico','text'=>'Abrir Centro de control TIC. Revisar tickets abiertos, filtrar sin técnico o en espera, gestionar respuesta y asignación.'],
    ['icon'=>'assets','title'=>'Actualizar inventario','text'=>'Entrar en Gestión de activos, filtrar por tipo/aula, modificar el activo o crear uno nuevo desde Alta guiada.'],
    ['icon'=>'map','title'=>'Usar los planos','text'=>'Cambiar edificio y planta, buscar aula, seleccionar una zona y usar Detalles del aula o Ver en GLPI para ir más rápido.'],
    ['icon'=>'stock','title'=>'Controlar stock','text'=>'Entrar en Control de stock para revisar consumibles/cartuchos y registrar movimientos si procede.'],
];

$maintenance = [
    ['title'=>'Actualizar el plugin', 'text'=>'Subir el ZIP al servidor, descomprimir en /tmp, sincronizar schoolmanager y public_maps, aplicar permisos, limpiar caché y reiniciar Apache.'],
    ['title'=>'Dónde viven los planos', 'text'=>'Los planos de ejemplo están en maps/planos dentro del plugin y los planos subidos se conservan en maps/uploads. Opcionalmente se pueden publicar recursos en /var/www/glpi/maps.'],
    ['title'=>'Dónde se guardan los IDs', 'text'=>'Los IDs reales de ubicaciones se centralizan en inc/ubicaciones_ids.php para abrir fichas nativas con /front/location.form.php?id=ID.'],
    ['title'=>'Qué revisar si algo se ve raro', 'text'=>'Ctrl + F5, cache:clear de GLPI, permisos www-data, y que no queden versiones antiguas fuera de /plugins/schoolmanager cargando CSS/JS.'],
];

$descriptions = [
    'setup.php' => 'Declara versión, compatibilidad, recursos y ciclo de instalación del plugin.',
    'hook.php' => 'Conecta el plugin con menús y puntos de integración de GLPI.',
    'logo.svg' => 'Logo genérico usado por defecto; puede cambiarse desde la configuración.',
    'front/formularios.php' => 'Panel principal Gestión School Manager con accesos a soporte, aulas, inventario, stock y ajustes.',
    'front/selector.php' => 'Plano de clases interactivo con edificios, plantas, lista y acciones sobre aulas.',
    'front/plan_frame.php' => 'Carga segura del HTML de plano seleccionado y controla errores si falta un archivo.',
    'front/nueva_incidencia.php' => 'Formulario guiado para crear tickets de soporte con ubicación, categoría y detalle técnico.',
    'front/mis_solicitudes.php' => 'Vista del usuario para consultar estado, respuesta y solución de sus incidencias.',
    'front/solicitud_detalle.php' => 'Detalle de ticket con estado, descripción, gestión, respuestas y enlaces nativos.',
    'front/panel_tic.php' => 'Centro de control para técnicos: filtros, tarjetas de trabajo, asignación y gestión.',
    'front/avisos.php' => 'Avisos importantes para el usuario: respuestas nuevas, esperas y solicitudes resueltas.',
    'front/gestion_activos.php' => 'Inventario simplificado con filtros por tipo, aula, búsqueda y acciones rápidas.',
    'front/nuevo_activo.php' => 'Alta guiada de activos con selector de ubicación y campos simplificados.',
    'front/editar_activo.php' => 'Edición de activos de GLPI con resumen, ubicación y ficha nativa.',
    'front/detalle_aula.php' => 'Ficha de aula con inventario vinculado, acciones y enlaces al plano/GLPI.',
    'front/assets_aula.php' => 'Listado de activos asociados a un aula concreta.',
    'front/stock_glpi.php' => 'Control de stock leyendo consumibles y cartuchos reales de GLPI.',
    'front/stock_movimiento.php' => 'Registro de entradas/salidas o movimientos de stock.',
    'front/versiones.php' => 'Página de versión pública y documentación interactiva del plugin.',
    'front/archivos.php' => 'Editor/gestor interno de archivos del plugin cuando se habilita su uso.',
    'front/error.php' => 'Pantalla auxiliar de errores controlados.',
    'front/locate.php' => 'Resolución de enlaces antiguos o rutas de ubicación.',
    'inc/aulas_data.php' => 'Catálogo de aulas, edificios, plantas, nombres y descripciones visibles.',
    'inc/ubicaciones_ids.php' => 'Mapa de códigos de aula con IDs reales de ubicación GLPI.',
    'inc/location_modal_markup.php' => 'HTML reutilizable del modal de selección de ubicación.',
    'inc/location_modal_script.php' => 'JS del modal de ubicación integrado en formularios.',
    'inc/permissions.php' => 'Permisos, roles y comprobaciones de acceso del plugin.',
    'inc/stats_helpers.php' => 'Funciones de estadísticas para incidencias, aulas y paneles.',
    'inc/plan_zones.php' => 'Definición/ayuda de zonas de planos y coordenadas.',
    'inc/assets_helpers.php' => 'Funciones comunes para crear, editar y listar activos de GLPI.',
    'inc/stock_helpers.php' => 'Funciones de lectura de stock, consumibles y cartuchos de GLPI.',
    'inc/ui_helpers.php' => 'Componentes visuales, iconos, botones y utilidades de interfaz.',
    'js/location-selector-integration.js' => 'Integra el selector de aulas en formularios y modales.',
    'js/schoolmanager-page-transition.js' => 'Transiciones ligeras entre páginas del plugin.',
    'css/gestion-schoolmanager-theme.css' => 'Tema visual global del plugin: paleta, botones, cards y responsive.',
];

$groups = [];
foreach ($files as $file) { $groups[$file['folder']][] = $file; }
?>
<style id="schoolmanager-docs-101-css">
:root{--sd-dark:#07384d;--sd-ink:#092f42;--sd-muted:#5f7481;--sd-line:#d9e9ee;--sd-bg:#f4fafb;--sd-card:#ffffff;--sd-red:#b01f2a;--sd-red2:#c72b37;--sd-gold:#f2a900;--sd-teal:#0b8d83;--sd-soft:#eef8f8;--sd-shadow:0 16px 44px rgba(7,56,77,.08)}
.smdoc,.smdoc *{box-sizing:border-box}.smdoc{max-width:1540px;margin:0 auto 42px;padding:22px;color:var(--sd-dark);font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;animation:smdocIn .22s ease both}.smdoc svg{width:20px;height:20px;display:block;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}.smdoc a{text-decoration:none}.smdoc-hero{border:1px solid #cfe4e9;border-radius:30px;background:linear-gradient(105deg,#fff 0%,#f9fcfd 68%,#fff7e8 100%);box-shadow:var(--sd-shadow);padding:26px;display:grid;grid-template-columns:150px minmax(0,1fr) auto;gap:24px;align-items:center}.smdoc-logo{width:150px;height:104px;display:flex;align-items:center;justify-content:center;background:transparent;border:0;box-shadow:none;overflow:visible}.smdoc-logo img{max-width:150px;max-height:104px;object-fit:contain;display:block;filter:none;mix-blend-mode:multiply}.smdoc-kicker{margin:0 0 5px;color:var(--sd-red);font-size:14px;letter-spacing:.16em;text-transform:uppercase;font-weight:1000}.smdoc-title{margin:0;font-size:58px;line-height:.92;font-weight:1000;letter-spacing:-.045em;color:var(--sd-dark)}.smdoc-sub{margin:10px 0 0;color:var(--sd-muted);font-weight:900;font-size:17px;line-height:1.35;max-width:760px}.smdoc-actions{display:flex;gap:12px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.smdoc-btn{height:48px;min-width:132px;padding:0 18px;border-radius:16px;border:1px solid #cfe2e8;background:#fff;color:var(--sd-dark);display:inline-flex;align-items:center;justify-content:center;gap:10px;font-weight:1000;box-shadow:0 10px 22px rgba(7,56,77,.06);transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease,color .18s ease}.smdoc-btn:hover{transform:translateY(-2px);box-shadow:0 18px 36px rgba(7,56,77,.13);border-color:#b8d8df}.smdoc-btn.red{background:var(--sd-red);border-color:var(--sd-red);color:#fff;box-shadow:0 14px 34px rgba(176,31,42,.20)}.smdoc-btn.red:hover{background:var(--sd-red2);border-color:var(--sd-red2);box-shadow:0 20px 42px rgba(176,31,42,.26)}.smdoc-btn.dark{background:var(--sd-dark);border-color:var(--sd-dark);color:#fff}.smdoc-nav{margin:16px 0;display:flex;gap:10px;flex-wrap:wrap}.smdoc-tab{border:1px solid #d6e8ed;background:#fff;color:var(--sd-dark);height:44px;padding:0 16px;border-radius:15px;font-weight:1000;display:inline-flex;align-items:center;gap:9px;cursor:pointer;transition:.18s ease}.smdoc-tab:hover{transform:translateY(-2px);border-color:#bddce2;box-shadow:0 12px 24px rgba(7,56,77,.08)}.smdoc-tab.active{background:var(--sd-dark);border-color:var(--sd-dark);color:#fff}.smdoc-view{display:none}.smdoc-view.active{display:block;animation:smdocIn .18s ease both}.smdoc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.smdoc-stat{background:#fff;border:1px solid #d8e9ee;border-radius:22px;min-height:104px;padding:18px;box-shadow:0 10px 26px rgba(7,56,77,.045);display:grid;grid-template-columns:auto 1fr;gap:10px 14px;align-items:center}.smdoc-stat .ico{width:44px;height:44px;border-radius:15px;background:#eef8f8;color:var(--sd-dark);display:flex;align-items:center;justify-content:center;grid-row:1/3}.smdoc-stat strong{font-size:27px;line-height:1;font-weight:1000;color:var(--sd-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.smdoc-stat span:last-child{color:var(--sd-muted);font-weight:900;line-height:1.2}.smdoc-grid{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(360px,.92fr);gap:16px;margin-top:16px}.smdoc-card{background:#fff;border:1px solid #d8e9ee;border-radius:24px;padding:20px;box-shadow:0 12px 32px rgba(7,56,77,.055);overflow:hidden}.smdoc-card h2{margin:0 0 12px;font-size:30px;line-height:1.05;font-weight:1000;color:var(--sd-dark);display:flex;align-items:center;gap:10px}.smdoc-card h3{margin:18px 0 8px;font-size:20px;font-weight:1000;color:var(--sd-dark)}.smdoc-card p{margin:0;color:var(--sd-muted);font-weight:850;line-height:1.42}.smdoc-card .mini{width:36px;height:36px;border-radius:12px;background:#eef8f8;display:flex;align-items:center;justify-content:center;color:var(--sd-dark)}.smdoc-note{border:1px dashed #c9dfe3;border-radius:18px;background:#fbfefd;padding:14px;color:#4e6875;font-weight:850;line-height:1.45}.smdoc-quick{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}.smdoc-quick a{border:1px solid #d8e9ee;background:#fff;border-radius:20px;padding:16px;color:var(--sd-dark);display:flex;gap:12px;align-items:center;transition:.18s ease;min-height:88px}.smdoc-quick a:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(7,56,77,.10);border-color:#bbd8df}.smdoc-quick .qicon{width:46px;height:46px;border-radius:15px;background:var(--sd-dark);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 24px rgba(7,56,77,.14)}.smdoc-quick b{font-size:18px;font-weight:1000;display:block}.smdoc-quick span span{display:block;color:var(--sd-muted);font-weight:850;font-size:13px;line-height:1.25;margin-top:3px}.smdoc-flow{display:grid;gap:10px;margin-top:14px}.smdoc-flow-row{display:grid;grid-template-columns:44px minmax(0,1fr);gap:12px;align-items:start;border:1px solid #e2eef1;border-radius:18px;padding:13px;background:#fff;transition:.18s ease}.smdoc-flow-row:hover{transform:translateX(3px);border-color:#bfdce2;background:#fbfefd}.smdoc-flow-row .num{width:44px;height:44px;border-radius:15px;background:var(--sd-red);color:#fff;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:1000;box-shadow:0 12px 24px rgba(176,31,42,.16)}.smdoc-flow-row b{display:block;font-size:17px;font-weight:1000;color:var(--sd-dark);margin-bottom:3px}.smdoc-flow-row p{font-size:14px}.smdoc-modules{display:grid;gap:10px}.smdoc-module{border:1px solid #d9e9ee;border-radius:20px;background:#fff;overflow:hidden;transition:.18s ease}.smdoc-module:hover{border-color:#bedde3}.smdoc-module-head{width:100%;border:0;background:#fff;color:var(--sd-dark);padding:15px;display:grid;grid-template-columns:48px 1fr;gap:12px;text-align:left;align-items:center;cursor:pointer}.smdoc-module-head .micon{width:48px;height:48px;border-radius:16px;background:#eef8f8;display:flex;align-items:center;justify-content:center;color:var(--sd-dark)}.smdoc-module-head b{display:block;font-size:18px;font-weight:1000;color:var(--sd-dark)}.smdoc-module-head span span{display:block;margin-top:3px;color:var(--sd-muted);font-size:13px;font-weight:850;line-height:1.25}.smdoc-module-body{display:none;padding:0 15px 15px}.smdoc-module.open .smdoc-module-body{display:block}.smdoc-filechips{display:flex;gap:8px;flex-wrap:wrap}.smdoc-chip{border:1px solid #d8e9ee;background:#f8fcfd;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:900;color:var(--sd-dark);display:inline-flex;align-items:center;gap:6px}.smdoc-chip svg{width:14px;height:14px}.smdoc-manual{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:16px}.smdoc-step{border:1px solid #d8e9ee;border-radius:22px;background:#fff;padding:17px;min-height:158px;box-shadow:0 10px 26px rgba(7,56,77,.045);transition:.18s ease}.smdoc-step:hover{transform:translateY(-3px);box-shadow:0 18px 36px rgba(7,56,77,.09)}.smdoc-step .sico{width:48px;height:48px;border-radius:16px;background:#eef8f8;color:var(--sd-dark);display:flex;align-items:center;justify-content:center;margin-bottom:12px}.smdoc-step h3{margin:0 0 6px;font-size:20px}.smdoc-maint{display:grid;gap:10px;margin-top:14px}.smdoc-maint details{border:1px solid #d8e9ee;border-radius:18px;background:#fff;padding:0;overflow:hidden}.smdoc-maint summary{cursor:pointer;padding:15px 16px;font-weight:1000;color:var(--sd-dark);display:flex;align-items:center;gap:10px}.smdoc-maint details p{padding:0 16px 16px}.smdoc-files{margin-top:16px}.smdoc-files-top{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;margin:14px 0 16px}.smdoc-search{height:56px;border:1px solid #d8e9ee;border-radius:18px;background:#fff;display:grid;grid-template-columns:48px 1fr;align-items:center;overflow:hidden;transition:.18s ease}.smdoc-search:focus-within{border-color:#9ccbd0;box-shadow:0 0 0 4px rgba(11,141,131,.10),0 12px 26px rgba(7,56,77,.06)}.smdoc-search svg{margin-left:17px;color:var(--sd-dark);width:19px;height:19px}.smdoc-search input{height:100%;width:100%;border:0;outline:0;background:transparent;color:var(--sd-dark);font-weight:900;font-size:16px;padding:0 16px 0 2px}.smdoc-search input::placeholder{color:#6a7880;opacity:.9}.smdoc-toggle{height:56px;border-radius:18px;border:1px solid var(--sd-line);background:#fff;color:var(--sd-dark);padding:0 18px;font-weight:1000;display:inline-flex;align-items:center;gap:9px;cursor:pointer;transition:.18s ease}.smdoc-toggle:hover{transform:translateY(-2px);border-color:#b8d3d8;box-shadow:0 12px 24px rgba(7,56,77,.08)}.smdoc-file-layout{display:grid;grid-template-columns:minmax(360px,.75fr) minmax(0,1.25fr);gap:14px}.smdoc-filegroups{display:grid;gap:10px;max-height:690px;overflow:auto;padding-right:4px}.smdoc-dir{border:1px solid #dce9ec;border-radius:20px;background:#fff;overflow:hidden}.smdoc-dir-head{width:100%;border:0;background:#fbfefd;color:var(--sd-dark);padding:13px 15px;display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;font-weight:1000;text-align:left}.smdoc-dir-head .left{display:flex;align-items:center;gap:10px;min-width:0}.smdoc-dir-head .left span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.smdoc-dir-head small{color:#60747f;font-weight:900}.smdoc-dir-body{display:none;padding:9px}.smdoc-dir.open .smdoc-dir-body{display:grid;gap:8px}.smdoc-file{display:grid;grid-template-columns:26px minmax(0,1fr);gap:9px;align-items:center;border:1px solid #eef4f5;border-radius:14px;padding:10px;background:#fff;color:var(--sd-dark);transition:.16s ease;text-align:left;cursor:pointer}.smdoc-file:hover,.smdoc-file.active{background:#f3fbfa;border-color:#bcdce1;transform:translateX(3px)}.smdoc-file svg{width:18px;height:18px;color:var(--sd-teal)}.smdoc-file b{display:block;font-size:14px;font-weight:1000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.smdoc-file em{display:block;font-style:normal;color:#667b86;font-size:12px;font-weight:850;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.smdoc-preview{position:sticky;top:12px;border:1px solid #d8e9ee;border-radius:22px;background:linear-gradient(180deg,#fff 0%,#fbfefd 100%);padding:18px;min-height:280px;box-shadow:0 12px 30px rgba(7,56,77,.05)}.smdoc-preview-icon{width:56px;height:56px;border-radius:18px;background:var(--sd-dark);color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:12px}.smdoc-preview h3{margin:0 0 7px;font-size:25px;line-height:1.05;color:var(--sd-dark);word-break:break-word}.smdoc-preview p{font-weight:850;color:var(--sd-muted);line-height:1.45}.smdoc-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:15px}.smdoc-meta span{border:1px solid #dce9ed;border-radius:15px;background:#fff;padding:10px;color:var(--sd-muted);font-weight:850}.smdoc-meta b{display:block;color:var(--sd-dark);font-size:13px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}.smdoc-path{margin-top:12px;border:1px dashed #c9dfe3;border-radius:16px;background:#fff;padding:12px;font-weight:900;color:var(--sd-dark);word-break:break-all}.smdoc-footer{margin-top:18px;padding:16px;border-radius:22px;border:1px dashed #c7dfe1;background:rgba(255,255,255,.72);display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;color:#536b76;font-weight:850}.smdoc-footer strong{color:var(--sd-dark)}@keyframes smdocIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}@media(max-width:1180px){.smdoc-hero{grid-template-columns:auto 1fr}.smdoc-actions{grid-column:1/-1;justify-content:flex-start}.smdoc-stats,.smdoc-quick{grid-template-columns:repeat(2,minmax(0,1fr))}.smdoc-grid,.smdoc-file-layout{grid-template-columns:1fr}.smdoc-preview{position:relative;top:auto}.smdoc-manual{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.smdoc{padding:12px}.smdoc-hero{grid-template-columns:1fr;padding:18px;border-radius:24px}.smdoc-logo{width:120px;height:82px}.smdoc-logo img{max-width:120px;max-height:82px}.smdoc-title{font-size:42px}.smdoc-sub{font-size:15px}.smdoc-actions,.smdoc-files-top{display:grid;grid-template-columns:1fr;width:100%}.smdoc-btn,.smdoc-toggle{width:100%;min-width:0}.smdoc-stats,.smdoc-quick,.smdoc-manual,.smdoc-meta{grid-template-columns:1fr}.smdoc-card{padding:16px}.smdoc-card h2{font-size:25px}.smdoc-filegroups{max-height:none}.smdoc-file{grid-template-columns:24px minmax(0,1fr)}}
</style>
<style id="schoolmanager-docs-final-polish">
.smdoc .smdoc-btn.red,.smdoc .smdoc-btn.red span,.smdoc .smdoc-btn.red svg,.smdoc .smdoc-btn.red svg *{color:#fff!important;stroke:#fff!important;fill:none!important}
.smdoc .smdoc-btn.red{background:#b01f2a!important;border-color:#b01f2a!important}
.smdoc .smdoc-btn.red:hover{background:#c72b37!important;border-color:#c72b37!important;transform:translateY(-2px)}
.smdoc .smdoc-btn{min-width:150px}
.smdoc-credits{display:grid;grid-template-columns:1.1fr .9fr;gap:16px;margin-top:16px}.smdoc-credit-hero{border:1px solid #d8e9ee;border-radius:26px;background:linear-gradient(135deg,#fff 0%,#fbfefd 70%,#fff7e8 100%);padding:24px;box-shadow:0 12px 32px rgba(7,56,77,.055)}.smdoc-credit-hero h2{font-size:34px;margin:0 0 10px;color:var(--sd-dark);font-weight:1000}.smdoc-credit-list{display:grid;gap:12px}.smdoc-credit-person{border:1px solid #d8e9ee;background:#fff;border-radius:22px;padding:18px;display:grid;grid-template-columns:52px 1fr;gap:14px;align-items:center;box-shadow:0 10px 24px rgba(7,56,77,.045)}.smdoc-credit-person .avatar{width:52px;height:52px;border-radius:18px;background:var(--sd-dark);color:#fff;display:flex;align-items:center;justify-content:center}.smdoc-credit-person b{font-size:20px;color:var(--sd-dark)}.smdoc-credit-person span{display:block;color:var(--sd-muted);font-weight:850;margin-top:3px}.smdoc-credit-tags{display:flex;flex-wrap:wrap;gap:9px;margin-top:16px}.smdoc-credit-tags span{border:1px solid #d8e9ee;border-radius:999px;padding:8px 11px;background:#fff;font-weight:900;color:var(--sd-dark)}@media(max-width:900px){.smdoc-credits{grid-template-columns:1fr}.smdoc .smdoc-btn{min-width:0}}
</style>

<div class="smdoc">
  <section class="smdoc-hero">
    <div class="smdoc-logo"><img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del centro"></div>
    <div>
      <p class="smdoc-kicker">Gestión School Manager · Documentación del plugin</p>
      <h1 class="smdoc-title">Versiones</h1>
      <p class="smdoc-sub">Versión pública estable actualizada. Documentación interactiva para entender módulos, flujo de trabajo, asignación automática TIC, archivos importantes y mantenimiento.</p>
    </div>
    <div class="smdoc-actions">
      <a class="smdoc-btn red" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/formularios.php?v=<?= urlencode($pluginVersion) ?>"><?= schoolmanager_doc_svg('home') ?><span>Inicio</span></a>
      <a class="smdoc-btn dark" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/selector.php?building=<?= urlencode(plugin_schoolmanager_default_building_code()) ?>&floor=<?= urlencode(plugin_schoolmanager_default_floor_code(plugin_schoolmanager_default_building_code())) ?>&v=<?= urlencode($pluginVersion) ?>"><?= schoolmanager_doc_svg('map') ?><span>Plano</span></a>
      <a class="smdoc-btn" href="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>/plugins/schoolmanager/front/panel_tic.php?v=<?= urlencode($pluginVersion) ?>"><?= schoolmanager_doc_svg('panel') ?><span>Panel TIC</span></a>
    </div>
  </section>

  <nav class="smdoc-nav" aria-label="Secciones de documentacion">
    <button class="smdoc-tab active" type="button" data-doc-tab="overview"><?= schoolmanager_doc_svg('book') ?>Resumen</button>
    <button class="smdoc-tab" type="button" data-doc-tab="manual"><?= schoolmanager_doc_svg('users') ?>Uso del plugin</button>
    <button class="smdoc-tab" type="button" data-doc-tab="files"><?= schoolmanager_doc_svg('folder') ?>Archivos</button>
    <button class="smdoc-tab" type="button" data-doc-tab="maintenance"><?= schoolmanager_doc_svg('wrench') ?>Mantenimiento</button>
    <button class="smdoc-tab" type="button" data-doc-tab="credits"><?= schoolmanager_doc_svg('heart') ?>Créditos</button>
  </nav>

  <section class="smdoc-view active" data-doc-view="overview">
    <section class="smdoc-stats">
      <div class="smdoc-stat"><span class="ico"><?= schoolmanager_doc_svg('version') ?></span><strong><?= htmlspecialchars($pluginVersion, ENT_QUOTES, 'UTF-8') ?></strong><span>Versión pública</span></div>
      <div class="smdoc-stat"><span class="ico"><?= schoolmanager_doc_svg('glpi') ?></span><strong><?= htmlspecialchars($glpiVersion, ENT_QUOTES, 'UTF-8') ?></strong><span>GLPI detectado</span></div>
      <div class="smdoc-stat"><span class="ico"><?= schoolmanager_doc_svg('folder') ?></span><strong><?= (int)$folderCount ?></strong><span>Carpetas visibles</span></div>
      <div class="smdoc-stat"><span class="ico"><?= schoolmanager_doc_svg('file') ?></span><strong><?= (int)$fileCount ?></strong><span>Archivos del plugin</span></div>
    </section>

    <section class="smdoc-quick">
      <?php foreach ($quickLinks as $link): ?>
        <a href="<?= htmlspecialchars($link[2], ENT_QUOTES, 'UTF-8') ?>">
          <span class="qicon"><?= schoolmanager_doc_svg($link[1]) ?></span>
          <span><b><?= htmlspecialchars($link[0], ENT_QUOTES, 'UTF-8') ?></b><span><?= htmlspecialchars($link[3], ENT_QUOTES, 'UTF-8') ?></span></span>
        </a>
      <?php endforeach; ?>
    </section>

    <section class="smdoc-grid">
      <article class="smdoc-card">
        <h2><span class="mini"><?= schoolmanager_doc_svg('route') ?></span>Cómo funciona</h2>
        <div class="smdoc-note">El plugin no sustituye GLPI: lo simplifica para el centro educativo. Usa datos reales de GLPI, pero ofrece pantallas más claras para profesores, técnicos y administración.</div>
        <div class="smdoc-flow">
          <?php foreach ($flows as $flow): ?>
            <div class="smdoc-flow-row"><span class="num"><?= htmlspecialchars($flow[0], ENT_QUOTES, 'UTF-8') ?></span><div><b><?= htmlspecialchars($flow[1], ENT_QUOTES, 'UTF-8') ?></b><p><?= htmlspecialchars($flow[2], ENT_QUOTES, 'UTF-8') ?></p></div></div>
          <?php endforeach; ?>
        </div>
      </article>

      <aside class="smdoc-card">
        <h2><span class="mini"><?= schoolmanager_doc_svg('spark') ?></span>Módulos</h2>
        <div class="smdoc-modules">
          <?php foreach ($modules as $i => $module): ?>
            <div class="smdoc-module <?= $i === 0 ? 'open' : '' ?>" data-doc-module>
              <button type="button" class="smdoc-module-head" data-doc-toggle>
                <span class="micon"><?= schoolmanager_doc_svg($module['icon']) ?></span>
                <span><b><?= htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') ?></b><span><?= htmlspecialchars($module['goal'], ENT_QUOTES, 'UTF-8') ?></span></span>
              </button>
              <div class="smdoc-module-body">
                <p><?= htmlspecialchars($module['text'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="smdoc-filechips" style="margin-top:10px">
                  <?php foreach ($module['files'] as $f): ?>
                    <span class="smdoc-chip"><?= schoolmanager_doc_svg('file') ?><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </aside>
    </section>
  </section>

  <section class="smdoc-view" data-doc-view="manual">
    <article class="smdoc-card">
      <h2><span class="mini"><?= schoolmanager_doc_svg('users') ?></span>Guía rápida de uso</h2>
      <div class="smdoc-note">Esta sección explica qué hace cada parte sin entrar en código. Sirve para presentar el plugin, enseñarlo en la demo o recordar el flujo de trabajo.</div>
      <div class="smdoc-manual">
        <?php foreach ($manual as $item): ?>
          <div class="smdoc-step"><span class="sico"><?= schoolmanager_doc_svg($item['icon']) ?></span><h3><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') ?></p></div>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <section class="smdoc-view" data-doc-view="files">
    <section class="smdoc-card smdoc-files">
      <h2><span class="mini"><?= schoolmanager_doc_svg('folder') ?></span>Mapa de archivos</h2>
      <div class="smdoc-note">Explora la estructura real del plugin. Pulsa un archivo para ver para qué sirve, a qué módulo pertenece, tamaño, tipo y ruta aproximada en servidor.</div>
      <div class="smdoc-files-top">
        <label class="smdoc-search"><?= schoolmanager_doc_svg('search') ?><input type="search" id="smdocFileSearch" placeholder="Buscar archivo, carpeta o módulo..." autocomplete="off"></label>
        <button type="button" class="smdoc-toggle" id="smdocExpandAll"><?= schoolmanager_doc_svg('arrow') ?><span>Expandir todo</span></button>
      </div>
      <div class="smdoc-file-layout">
        <div class="smdoc-filegroups" id="smdocFileGroups">
          <?php foreach ($groups as $folder => $items): ?>
            <div class="smdoc-dir <?= in_array($folder, ['front','inc'], true) ? 'open' : '' ?>" data-doc-dir data-search="<?= htmlspecialchars(strtolower($folder), ENT_QUOTES, 'UTF-8') ?>">
              <button type="button" class="smdoc-dir-head" data-dir-toggle><span class="left"><?= schoolmanager_doc_svg('folder') ?><span><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></span></span><small><?= count($items) ?> archivos</small></button>
              <div class="smdoc-dir-body">
                <?php foreach ($items as $file):
                  $desc = $descriptions[$file['path']] ?? strtoupper($file['ext']) . ' · archivo interno del plugin';
                  $module = schoolmanager_doc_file_module($file['path']);
                  $searchText = strtolower($file['path'] . ' ' . $desc . ' ' . $file['ext'] . ' ' . $module);
                ?>
                  <button type="button" class="smdoc-file" data-doc-file data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars(basename($file['path']), ENT_QUOTES, 'UTF-8') ?>" data-path="<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>" data-desc="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>" data-size="<?= htmlspecialchars(schoolmanager_doc_bytes($file['size']), ENT_QUOTES, 'UTF-8') ?>" data-ext="<?= htmlspecialchars($file['ext'], ENT_QUOTES, 'UTF-8') ?>" data-module="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>">
                    <?= schoolmanager_doc_svg($file['ext'] === 'php' ? 'code' : 'file') ?>
                    <span><b><?= htmlspecialchars(basename($file['path']), ENT_QUOTES, 'UTF-8') ?></b><em><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></em></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <aside class="smdoc-preview" id="smdocPreview">
          <span class="smdoc-preview-icon"><?= schoolmanager_doc_svg('eye') ?></span>
          <h3>Selecciona un archivo</h3>
          <p>Al pulsar sobre cualquier archivo de la izquierda se mostrará aquí su función dentro del plugin.</p>
          <div class="smdoc-meta">
            <span><b>Módulo</b>Documentación</span>
            <span><b>Ruta</b>/plugins/schoolmanager</span>
          </div>
        </aside>
      </div>
    </section>
  </section>

  <section class="smdoc-view" data-doc-view="maintenance">
    <section class="smdoc-grid">
      <article class="smdoc-card">
        <h2><span class="mini"><?= schoolmanager_doc_svg('wrench') ?></span>Mantenimiento</h2>
        <div class="smdoc-maint">
          <?php foreach ($maintenance as $i => $item): ?>
            <details <?= $i === 0 ? 'open' : '' ?>><summary><?= schoolmanager_doc_svg('check') ?><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></summary><p><?= htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') ?></p></details>
          <?php endforeach; ?>
        </div>
      </article>
      <aside class="smdoc-card">
        <h2><span class="mini"><?= schoolmanager_doc_svg('shield') ?></span>Notas técnicas</h2>
        <div class="smdoc-note">El plugin trabaja sobre GLPI y mantiene accesos a la ficha nativa. La idea es que el usuario use pantallas simples, pero el administrador siempre pueda volver a GLPI real.</div>
        <h3>Rutas principales</h3>
        <p><b>Plugin:</b> /var/www/glpi/plugins/schoolmanager</p>
        <p><b>Planos públicos:</b> /var/www/glpi/maps</p>
        <p><b>Ubicaciones:</b> /front/location.form.php?id=ID</p>
      </aside>
    </section>
  </section>

  <section class="smdoc-view" data-doc-view="credits">
    <section class="smdoc-credits">
      <article class="smdoc-credit-hero">
        <h2><?= schoolmanager_doc_svg('heart') ?> Créditos del proyecto</h2>
        <p>Plugin desarrollado para el GLPI School Manager del Centro educativo. Esta página documenta la versión pública estable y deja visible cómo está organizado el trabajo para poder mantenerlo y presentarlo mejor.</p>
        <div class="smdoc-credit-tags">
          <span>GLPI School Manager</span><span>Equipo TIC</span><span>Proyecto educativo</span><span>Gestión School Manager</span><span>GLPI</span>
        </div>
      </article>
      <aside class="smdoc-card">
        <h2><span class="mini"><?= schoolmanager_doc_svg('users') ?></span>Autores</h2>
        <div class="smdoc-credit-list">
          <div class="smdoc-credit-person"><span class="avatar"><?= schoolmanager_doc_svg('users') ?></span><span><b>Equipo TIC</b><span>Desarrollo, diseño del plugin, documentación y pruebas.</span></span></div>
          <div class="smdoc-credit-person"><span class="avatar"><?= schoolmanager_doc_svg('users') ?></span><span><b>Equipo TIC</b><span>Desarrollo, documentación técnica, pruebas y despliegue.</span></span></div>
        </div>
      </aside>
    </section>
  </section>

  <div class="smdoc-footer">
    <span><strong>Versión pública:</strong> <?= htmlspecialchars($pluginVersion, ENT_QUOTES, 'UTF-8') ?></span>
    <span><strong>GLPI:</strong> <?= htmlspecialchars($glpiVersion, ENT_QUOTES, 'UTF-8') ?></span>
    <span><strong>Estructura:</strong> <?= (int)$fileCount ?> archivos · <?= htmlspecialchars(schoolmanager_doc_bytes($totalSize), ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</div>

<script id="schoolmanager-docs-101-js">
(function(){
  var root = document.querySelector('.smdoc');
  if (!root) return;
  root.querySelectorAll('[data-doc-tab]').forEach(function(tab){
    tab.addEventListener('click', function(){
      var id = tab.getAttribute('data-doc-tab');
      root.querySelectorAll('[data-doc-tab]').forEach(function(t){ t.classList.toggle('active', t === tab); });
      root.querySelectorAll('[data-doc-view]').forEach(function(v){ v.classList.toggle('active', v.getAttribute('data-doc-view') === id); });
    });
  });
  root.querySelectorAll('[data-doc-toggle]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var box = btn.closest('[data-doc-module]');
      if (box) box.classList.toggle('open');
    });
  });
  root.querySelectorAll('[data-dir-toggle]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var box = btn.closest('[data-doc-dir]');
      if (box) box.classList.toggle('open');
    });
  });
  var input = document.getElementById('smdocFileSearch');
  if (input) {
    input.addEventListener('input', function(){
      var q = input.value.trim().toLowerCase();
      root.querySelectorAll('[data-doc-dir]').forEach(function(dir){
        var any = !q || (dir.getAttribute('data-search') || '').indexOf(q) !== -1;
        dir.querySelectorAll('[data-doc-file]').forEach(function(file){
          var match = !q || (file.getAttribute('data-search') || '').indexOf(q) !== -1;
          file.style.display = match ? '' : 'none';
          if (match) any = true;
        });
        dir.style.display = any ? '' : 'none';
        if (q && any) dir.classList.add('open');
      });
    });
  }
  var expand = document.getElementById('smdocExpandAll');
  if (expand) {
    expand.addEventListener('click', function(){
      var dirs = Array.prototype.slice.call(root.querySelectorAll('[data-doc-dir]'));
      var allOpen = dirs.every(function(d){ return d.classList.contains('open'); });
      dirs.forEach(function(d){ d.classList.toggle('open', !allOpen); });
      var label = expand.querySelector('span');
      if (label) label.textContent = allOpen ? 'Expandir todo' : 'Contraer todo';
    });
  }
  var preview = document.getElementById('smdocPreview');
  root.querySelectorAll('[data-doc-file]').forEach(function(file){
    file.addEventListener('click', function(){
      root.querySelectorAll('[data-doc-file]').forEach(function(f){ f.classList.remove('active'); });
      file.classList.add('active');
      if (!preview) return;
      var name = file.getAttribute('data-name') || 'Archivo';
      var path = file.getAttribute('data-path') || '';
      var desc = file.getAttribute('data-desc') || 'Archivo interno del plugin.';
      var size = file.getAttribute('data-size') || '-';
      var ext = file.getAttribute('data-ext') || '-';
      var module = file.getAttribute('data-module') || 'Núcleo';
      preview.innerHTML = '<span class="smdoc-preview-icon"><?= str_replace("'", "\\'", schoolmanager_doc_svg('file')) ?></span>'+
        '<h3>'+escapeHtml(name)+'</h3>'+
        '<p>'+escapeHtml(desc)+'</p>'+
        '<div class="smdoc-meta"><span><b>Módulo</b>'+escapeHtml(module)+'</span><span><b>Tipo</b>'+escapeHtml(ext.toUpperCase())+'</span><span><b>Tamaño</b>'+escapeHtml(size)+'</span><span><b>Servidor</b>schoolmanager</span></div>'+
        '<div class="smdoc-path">/var/www/glpi/plugins/schoolmanager/'+escapeHtml(path)+'</div>';
    });
  });
  function escapeHtml(text){
    return String(text).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]; });
  }
})();
</script>
<?php Html::footer(); ?>
