<?php
// Public media endpoint for School Manager assets.
// It is used for logos because some GLPI/Apache installs block direct static files under plugins/.
include('../../../inc/includes.php');
require_once(__DIR__ . '/../inc/config.php');

function smgr_media_send(string $path): void {
    if (!is_file($path) || filesize($path) <= 0) { http_response_code(404); exit; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml',
    ];
    if (!isset($types[$ext])) { http_response_code(415); exit; }
    header('Content-Type: ' . $types[$ext]);
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$asset = (string)($_GET['asset'] ?? 'logo');
if ($asset !== 'logo') { http_response_code(404); exit; }
$path = function_exists('plugin_schoolmanager_logo_path') ? plugin_schoolmanager_logo_path() : (__DIR__ . '/../logo.png');
if (is_file($path) && filesize($path) > 0) { smgr_media_send($path); }
http_response_code(404);
