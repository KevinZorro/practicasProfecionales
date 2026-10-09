<?php

declare(strict_types=1);

use App\Models\PeriodoAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso base de las pruebas
|--------------------------------------------------------------------------
|
| Los tests de "Feature" arrancan la aplicación completa y migran una base de
| datos limpia en cada uno. Los de "Unit" no tocan Laravel: son para lógica
| aislada de los Services.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Los tests de vistas comprueban qué se pinta y quién puede verlo, no el
    // empaquetado de los assets: sin esto exigirían un "npm run build" previo
    // y fallarían en un entorno limpio. Que Vite compile de verdad lo prueba
    // el job de Docker del CI, que levanta el entorno completo.
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Acciones de Livewire por HTTP
|--------------------------------------------------------------------------
|
| Livewire::test() se salta los middleware, así que no sirve para probar
| quién puede pulsar un botón de una pantalla ya abierta. Estas dos funciones
| hacen lo que hace el navegador: abrir la página y reenviar la instantánea
| del componente a /livewire/update, donde Livewire vuelve a aplicar los
| middleware persistentes (AppServiceProvider).
|
*/

/**
 * Abre la pantalla con el usuario autenticado y devuelve la instantánea de
 * su primer componente de Livewire.
 */
function instantaneaDeLaPantalla(string $url): string
{
    $html = test()->get($url)->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]+)"/', (string) $html, $coincidencia);

    return html_entity_decode($coincidencia[1]);
}

/**
 * Lo que manda el navegador al pulsar un botón de un componente ya abierto.
 * Sin método, lo que manda al refrescarlo ($refresh): la instantánea sin
 * ninguna llamada.
 *
 * @param  list<mixed>  $parametros
 */
function accionDeLivewire(string $instantanea, ?string $metodo = null, array $parametros = []): TestResponse
{
    return test()->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
        'components' => [[
            'snapshot' => $instantanea,
            'updates' => [],
            'calls' => $metodo === null ? [] : [['path' => '', 'method' => $metodo, 'params' => $parametros]],
        ]],
    ]);
}

/*
|--------------------------------------------------------------------------
| Periodo académico
|--------------------------------------------------------------------------
|
| El formato de confidencialidad cuenta para el periodo que el laboratorio
| abrió (RF75). Las factories de formatos usan "2026-2", así que es el que se
| abre por defecto.
|
*/

function abrirPeriodo(string $nombre = '2026-2'): PeriodoAcademico
{
    return PeriodoAcademico::factory()->create(['nombre' => $nombre]);
}

/*
|--------------------------------------------------------------------------
| Estudiantes de una sesión
|--------------------------------------------------------------------------
|
| El docente dice qué estudiantes van a cada sesión (RF28), y la cantidad
| sale de esa lista.
|
*/

/**
 * @return list<int>
 */
function idsDeEstudiantes(int $cuantos): array
{
    return User::factory()->estudiante()->count($cuantos)->create()->pluck('id')->all();
}
