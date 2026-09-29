<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cabeceras de seguridad
|--------------------------------------------------------------------------
|
| Las pone el middleware CabecerasDeSeguridad en todas las respuestas de la
| aplicación, del panel y de Filament.
|
*/

return [

    /*
    | La Content-Security-Policy es la única cabecera que puede romper una
    | pantalla si le falta un origen. Si pasa en producción, se apaga aquí
    | sin tocar código mientras se corrige la política.
    */
    'politica_de_contenido' => (bool) env('POLITICA_DE_CONTENIDO', true),

    /*
    | Cuánto recuerda el navegador que el sitio solo va por HTTPS (HSTS). Un
    | año es lo habitual. Solo se manda sobre HTTPS.
    */
    'hsts_segundos' => 31_536_000,

];
