<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Proxies de confianza
|--------------------------------------------------------------------------
|
| Si delante de la aplicación hay un proxy que termina el HTTPS (el de la
| universidad, por ejemplo) y la reenvía por HTTP, Laravel solo sabe que la
| petición original era segura si confía en ese proxy: lee sus cabeceras
| X-Forwarded-*. Sin eso generaría enlaces http:// y no mandaría HSTS.
|
| Vacío: no se confía en nadie, que es lo correcto si el propio nginx del
| compose termina el HTTPS o si la aplicación no está detrás de ningún
| proxy. Si no, la IP del proxy, o varias separadas por comas. "*" confía
| en quien llame, y solo es seguro si nadie más puede llegar al nginx.
|
| Laravel lee esta clave en su middleware TrustProxies, sin nada propio.
|
*/

return [

    'proxies' => env('PROXIES_DE_CONFIANZA') ?: null,

];
