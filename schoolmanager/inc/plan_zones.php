<?php
if (!defined('GLPI_ROOT')) { die('No direct access'); }

/**
 * Optional generated clickable zones for classroom maps.
 *
 * The public plugin intentionally ships without school-specific coordinates.
 * Administrators can upload their own HTML/SVG/PNG/JPG/WEBP maps from the
 * configuration page and connect rooms through the normal School Manager
 * building/floor/classroom configuration.
 */
function plugin_schoolmanager_plan_zones(): array {
    return [];
}
