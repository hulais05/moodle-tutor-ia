<?php
// Provisioning automático del curso demo via APIs internas de Moodle.
// Crea el curso "Monitoreo ambiental en minería" y carga el material como páginas.
// Uso: php _provision.php   (dentro del contenedor webserver)

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

global $DB, $CFG;

// ---------- 1) Crear el curso (idempotente) ----------
$shortname = 'monitoreo-amb';
$course = $DB->get_record('course', ['shortname' => $shortname]);

if ($course) {
    echo "Curso ya existe (id={$course->id}).\n";
} else {
    $data = new stdClass();
    $data->fullname     = 'Monitoreo ambiental en minería';
    $data->shortname    = $shortname;
    $data->category     = 1;
    $data->summary      = 'Curso introductorio sobre monitoreo ambiental en proyectos mineros: '
                        . 'línea de base, parámetros, métodos y del dato al informe. '
                        . 'Incluye un tutor con IA para resolver dudas.';
    $data->summaryformat = FORMAT_HTML;
    $data->format       = 'topics';
    $data->numsections  = 3;
    $data->startdate    = time();
    $data->visible      = 1;
    $course = create_course($data);
    echo "Curso creado (id={$course->id}).\n";
}

// ---------- 2) Cargar el material como páginas (idempotente) ----------
$pagemodule = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);

function agregar_pagina($course, $pagemodule, $section, $name, $mdfile) {
    global $DB;

    // Evitar duplicados: ¿ya hay una página con ese nombre en el curso?
    $existe = $DB->record_exists('page', ['course' => $course->id, 'name' => $name]);
    if ($existe) {
        echo "  Página '$name' ya existe, se omite.\n";
        return;
    }

    $md = file_get_contents($mdfile);
    if ($md === false) {
        echo "  ERROR: no se pudo leer $mdfile\n";
        return;
    }

    $mi = new stdClass();
    $mi->modulename        = 'page';
    $mi->module            = $pagemodule->id;
    $mi->course            = $course->id;
    $mi->section           = $section;
    $mi->visible           = 1;
    $mi->visibleoncoursepage = 1;
    $mi->name              = $name;
    $mi->intro             = '';
    $mi->introformat       = FORMAT_HTML;
    $mi->page              = ['text' => $md, 'format' => FORMAT_MARKDOWN];
    $mi->display           = 5; // RESOURCELIB_DISPLAY_OPEN
    $mi->printheading      = 1;
    $mi->printintro        = 0;
    $mi->printlastmodified = 1;
    $mi->cmidnumber        = '';

    $res = add_moduleinfo($mi, $course);
    echo "  Página '$name' creada (cmid={$res->coursemodule}).\n";
}

agregar_pagina($course, $pagemodule, 1,
    'Módulo 1 — Introducción al monitoreo ambiental',
    '/var/www/html/_provision/01-introduccion-monitoreo-ambiental.md');

agregar_pagina($course, $pagemodule, 2,
    'Módulo 2 — Parámetros y métodos de medición',
    '/var/www/html/_provision/02-parametros-y-metodos.md');

// Refrescar caché del curso para que se vea al instante.
rebuild_course_cache($course->id, true);

echo "Listo. Curso disponible en: http://localhost:8000/course/view.php?id={$course->id}\n";
