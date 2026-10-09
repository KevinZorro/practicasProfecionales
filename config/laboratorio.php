<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Formato de confidencialidad
    |--------------------------------------------------------------------------
    |
    | Es el mismo documento que el laboratorio rotula "formato de
    | confidencialidad" en el Drive, e incluye la autorización de captación
    | de imágenes. Lo firma todo el que entra a la práctica: estudiantes y
    | docentes (RF51-RF52).
    |
    | Los archivos firmados llevan datos personales, así que viven en el
    | disco privado y se sirven por ruta protegida con Policy, nunca por
    | enlace directo (RNF07).
    |
    */

    'confidencialidad' => [
        'disco' => 'local',
        'directorio_plantillas' => 'confidencialidad/plantillas',
        'directorio_firmados' => 'confidencialidad/firmados',
        'tamano_maximo_kb' => 5120,
    ],

];
