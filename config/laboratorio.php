<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sincronización con la base institucional (RF19, RF20)
    |--------------------------------------------------------------------------
    |
    | "fuente" dice de dónde se leen las personas. Mientras la universidad no
    | entregue el motor, el acceso y la estructura de su base (pendiente 2
    | del CLAUDE.md), la única es "simulada": un archivo JSON con el mismo
    | formato que tendrá la real.
    |
    | "programas" son los que usan el laboratorio: solo sus personas
    | vigentes entran (RF19). Se comparan sin tildes ni mayúsculas.
    |
    | "umbral_desactivacion" es el freno del RF20: si una pasada fuera a
    | desactivar más de ese porcentaje de las cuentas sincronizadas activas,
    | no aplica nada y avisa al ADMIN. Protege de una vista institucional
    | vacía o a medio cargar.
    |
    | "frecuencia" es la expresión cron de la pasada automática.
    |
    */

    'sincronizacion' => [
        'fuente' => env('SINCRONIZACION_FUENTE', 'simulada'),
        'archivo_simulado' => env('SINCRONIZACION_ARCHIVO_SIMULADO', database_path('datos/institucional-simulada.json')),
        'programas' => [
            'Enfermería',
            'Licenciatura en Ciencias Naturales',
            'Seguridad y Salud en el Trabajo',
            'Regencia en Farmacia',
        ],
        'umbral_desactivacion' => (int) env('SINCRONIZACION_UMBRAL_DESACTIVACION', 10),
        'frecuencia' => env('SINCRONIZACION_FRECUENCIA', '0 2 * * *'),
        // Apagada por defecto: con la fuente simulada, una pasada programada
        // en producción desactivaría a todos los que no están en el archivo
        // de prueba (la frenaría el umbral, con un correo cada noche).
        'programada' => (bool) env('SINCRONIZACION_PROGRAMADA', false),
    ],

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
