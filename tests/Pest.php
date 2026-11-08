<?php

declare(strict_types=1);

use App\Enums\TipoSesion;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\PeriodoAcademico;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
| Las pruebas de concurrencia abren una segunda sesión de PostgreSQL, como
| dos personas en dos peticiones. Esa sesión no ve lo que queda dentro de la
| transacción de RefreshDatabase, así que aquí los datos se confirman de
| verdad y la base se vacía antes de cada prueba.
*/
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(function (): void {
        config(['database.connections.segunda_sesion' => config('database.connections.'.config('database.default'))]);

        // La prueba corre en un solo proceso: si la segunda sesión espera un
        // candado que tiene la primera, la primera nunca lo soltaría. Con
        // este tope la espera termina en error en vez de colgar la suite.
        DB::connection('segunda_sesion')->statement("SET lock_timeout = '1s'");
    })
    ->afterEach(function (): void {
        DB::disconnect('segunda_sesion');

        // DatabaseTruncation vacía la base antes de cada prueba, no después:
        // sin esto, lo confirmado aquí lo verían las pruebas con
        // RefreshDatabase que corran después en el mismo proceso.
        $this->truncateTablesForAllConnections();
    })
    ->in('Concurrencia');

/**
 * Corre la acción como otra persona que actúa a la vez: con su propia
 * sesión de PostgreSQL, como otra petición en otro proceso de PHP-FPM.
 */
function enOtraSesion(callable $accion): mixed
{
    $principal = DB::getDefaultConnection();
    DB::setDefaultConnection('segunda_sesion');

    try {
        return $accion();
    } finally {
        DB::setDefaultConnection($principal);
    }
}

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

/**
 * Datos de una sesión de práctica con estos estudiantes, sobre un caso y una
 * materia nuevos.
 *
 * @param  list<int>  $estudianteIds
 */
function sesionCon(array $estudianteIds, string $grupo = 'A', ?string $fecha = null): DatosNuevaSolicitud
{
    return new DatosNuevaSolicitud(
        ...materiaYCaso(),
        tipo: TipoSesion::Practica,
        fecha: $fecha ?? now()->addWeek()->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        grupo: $grupo,
        estudianteIds: $estudianteIds,
    );
}

/*
|--------------------------------------------------------------------------
| Materia y escenario relacionados (RF24)
|--------------------------------------------------------------------------
|
| El Service no registra un escenario en una materia a la que no está
| asociado. Devuelve los dos ids con los nombres de los datos de una sesión,
| para esparcirlos en el constructor: new DatosNuevaSolicitud(...materiaYCaso(), …).
|
*/

/** @return array{materiaId: int, casoClinicoId: int} */
function materiaYCaso(?Materia $materia = null, ?CasoClinico $caso = null): array
{
    $materia ??= Materia::factory()->create();
    $caso ??= CasoClinico::factory()->create();
    $caso->materias()->syncWithoutDetaching([$materia->id]);

    return ['materiaId' => $materia->id, 'casoClinicoId' => $caso->id];
}
