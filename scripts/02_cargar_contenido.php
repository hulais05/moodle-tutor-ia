<?php
// Corrige el contenido de las páginas: lo escribe directo, en formato Markdown.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

global $DB;

$mapa = [
    'Módulo 1 — Introducción al monitoreo ambiental' => '/var/www/html/_provision/01-introduccion-monitoreo-ambiental.md',
    'Módulo 2 — Parámetros y métodos de medición'    => '/var/www/html/_provision/02-parametros-y-metodos.md',
];

foreach ($mapa as $name => $mdfile) {
    $page = $DB->get_record('page', ['name' => $name]);
    if (!$page) {
        echo "No encontré la página '$name'.\n";
        continue;
    }
    $md = file_get_contents($mdfile);
    $page->content = $md;
    $page->contentformat = FORMAT_MARKDOWN; // 4
    $DB->update_record('page', $page);
    echo "Contenido cargado en '$name' (" . strlen($md) . " chars, formato Markdown).\n";
}

// Refrescar cachés.
rebuild_course_cache(0, true);
echo "Listo.\n";
