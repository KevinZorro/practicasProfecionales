<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Periodo académico
    |--------------------------------------------------------------------------
    |
    | El periodo se escribe como "2026-2": año y número de semestre. No está
    | definido con la institución cómo se calcula, así que se deriva del
    | calendario con una regla simple y se deja una salida manual.
    |
    | "vigente" fija el periodo a mano y gana sobre el cálculo. Es la válvula
    | de escape para las semanas de transición entre semestres, cuando el
    | calendario y la realidad académica no coinciden.
    |
    | "mes_inicio_segundo_semestre" es el primer mes que ya cuenta como
    | segundo semestre. Con 7, enero a junio son el semestre 1 y julio a
    | diciembre el 2.
    |
    */

    'periodo_academico' => [
        'vigente' => env('PERIODO_ACADEMICO_VIGENTE'),
        'mes_inicio_segundo_semestre' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consentimiento informado
    |--------------------------------------------------------------------------
    |
    | Los archivos firmados llevan datos personales de estudiantes, así que
    | viven en el disco privado y se sirven por ruta protegida con Policy,
    | nunca por enlace directo (RNF07).
    |
    */

    'consentimiento' => [
        'disco' => 'local',
        'directorio_plantillas' => 'consentimientos/plantillas',
        'directorio_firmados' => 'consentimientos/firmados',
        'tamano_maximo_kb' => 5120,
    ],

];
