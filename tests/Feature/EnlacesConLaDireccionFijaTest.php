<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * Ningún enlace de la plataforma sale sin el puerto de APP_URL.
 *
 * En el servidor se entra por un túnel a localhost:8080 y nginx reenvía
 * `Host: localhost`, sin el puerto. Todo enlace armado con ese Host apunta
 * a otro sitio: la CSP lo bloquea o el navegador no encuentra el servidor.
 * Ya pasó con los assets, con la vuelta tras entrar y con la fuente y el
 * favicon de /admin. En vez de probar pantalla por pantalla, esto recorre
 * todas las páginas sin parámetros de la portada, la entrada, el panel y
 * la administración, con cada rol, y falla con cualquier dirección a
 * localhost que no sea exactamente APP_URL, en el HTML o en un redirect.
 */

const DIRECCION_CONFIGURADA = 'http://localhost:8080';

// No es un enlace: es el espacio de nombres de los SVG, que nadie descarga.
const ESPACIO_DE_NOMBRES_SVG = 'http://www.w3.org/';

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    config(['app.url' => DIRECCION_CONFIGURADA, 'app.url_fija' => true, 'laboratorio.acceso.con_contrasena' => true]);
});

/**
 * Las direcciones a localhost que aparecen en la respuesta y no empiezan
 * por APP_URL. Livewire escribe las suyas en JSON, con las barras escapadas.
 *
 * @return list<string>
 */
function direccionesSinElPuerto(TestResponse $respuesta): array
{
    $texto = str_replace('\\/', '/', (string) $respuesta->getContent()).' '.$respuesta->headers->get('Location', '');

    preg_match_all('#https?://[^\s"\'<>)]+#i', $texto, $coincidencias);

    return array_values(array_unique(array_filter(
        $coincidencias[0],
        static fn (string $direccion): bool => $direccion !== DIRECCION_CONFIGURADA
            && ! str_starts_with($direccion, DIRECCION_CONFIGURADA.'/')
            && ! str_starts_with($direccion, ESPACIO_DE_NOMBRES_SVG),
    )));
}

/**
 * Las páginas que se abren sin parámetros: las del panel y la
 * administración, sin las descargas (no son HTML).
 *
 * @return list<string>
 */
function paginasSinParametros(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(static fn (Ruta $ruta): bool => in_array('GET', $ruta->methods(), true)
            && $ruta->parameterNames() === []
            && preg_match('#^(panel|admin)(/|$)#', $ruta->uri()) === 1
            && preg_match('#(excel|pdf|formatos|plantilla|eventos|logout)$#', $ruta->uri()) === 0)
        ->map(static fn (Ruta $ruta): string => '/'.$ruta->uri())
        ->unique()
        ->values()
        ->all();
}

it('las páginas públicas y la entrada no arman enlaces con el Host de la petición', function (string $pagina): void {
    $respuesta = $this->get('http://localhost'.$pagina);

    expect($respuesta->status())->toBeLessThan(500)
        ->and(direccionesSinElPuerto($respuesta))->toBe([]);
})->with(['/', '/acceso', '/acceso/recuperar', '/acceso/restablecer/un-token', '/panel', '/admin']);

it('ninguna página del panel ni de la administración arma enlaces con el Host de la petición', function (string $rol): void {
    $usuario = User::factory()->{$rol}()->create();
    $revisadas = 0;

    foreach (paginasSinParametros() as $pagina) {
        $respuesta = $this->actingAs($usuario)->get('http://localhost'.$pagina);

        expect($respuesta->status())->toBeLessThan(500, "{$pagina} respondió {$respuesta->status()}")
            ->and(direccionesSinElPuerto($respuesta))->toBe([], "{$pagina} con el rol {$rol}");

        $revisadas += $respuesta->isOk() ? 1 : 0;
    }

    // Que el recorrido no pase en vacío: cada rol abre varias pantallas.
    expect($revisadas)->toBeGreaterThan(1);
})->with(['admin', 'coordinador', 'administrativo', 'docente', 'supervisor', 'estudiante']);
