<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
require_once(__DIR__ . '/../inc/permissions.php');
$title = trim((string)($_GET['title'] ?? 'Error en Gestión School Manager'));
$message = trim((string)($_GET['message'] ?? 'No se ha podido completar la operación solicitada.'));
plugin_schoolmanager_error_page($title, $message, 'Vuelve a la página principal de gestión y prueba de nuevo.');

