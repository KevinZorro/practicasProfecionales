<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Route;

/*
 * QA-17: un valor escrito a mano en la URL (una fecha que no es fecha, un
 * identificador que no es número) es un «no existe» o un filtro ignorado,
 * nunca un error 500 ni una línea de ERROR en el registro.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
    $this->admin = User::factory()->coordinador()->create();
});

it('responde 404 a un identificador que no es número', function (string $ruta): void {
    expect($this->actingAs($this->admin)->get($ruta)->status())->toBe(404, $ruta);
})->with([
    '/panel/solicitudes/abc/participantes',
    '/panel/solicitudes/abc/novedades',
    '/panel/solicitudes/abc/formato-intramural',
    '/panel/evaluaciones/abc',
    '/panel/inventario/abc/editar',
    '/panel/reposicion/abc/pdf',
    '/panel/reposicion/abc/excel',
    '/panel/formatos-confidencialidad/abc/documento',
    '/panel/formatos-confidencialidad/plantilla/abc',
    '/panel/usuarios/abc/editar',
    '/panel/usuarios/abc/supervision',
    '/escenarios/abc',
]);

it('ignora un filtro de la URL que no se puede interpretar y abre la pantalla', function (string $ruta): void {
    expect($this->actingAs($this->admin)->get($ruta)->status())->toBe(200, $ruta);
})->with([
    '/panel/preparaciones?fecha=abc',
    '/panel/preparaciones?fecha=2026-13-45',
    '/panel/bitacora?desde=abc',
    '/panel/bitacora?hasta=2026-02-31',
    '/panel/reposicion?desde=abc',
    '/panel/reposicion?hasta=abc',
    '/panel/formatos-confidencialidad/estado?sesion=abc',
    '/panel/formatos-confidencialidad/estado?materia=abc',
    '/panel/reportes?desde=abc&hasta=zzz',
    '/panel/solicitudes?estado=abc',
    '/panel/formatos-confidencialidad?estado=abc&periodo=abc',
    '/panel/formatos-confidencialidad/estado?situacion=abc&programa=abc&periodo=abc',
    '/panel/inventario?tipo=abc&estado=abc&fidelidad=abc',
]);

it('en la pantalla del supervisor, un docente ilegible no filtra y un periodo que no existe da 404', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get('/panel/supervision?docente=abc')->assertOk();
    $this->actingAs($supervisor)->get('/panel/supervision?periodo=abc')->assertNotFound();
});

it('traduce a 404 un valor que PostgreSQL rechaza por su tipo, venga de donde venga', function (): void {
    Route::middleware('web')->get('/_prueba-tipo', static fn () => User::query()->whereRaw("id = 'abc'")->get());
    Route::middleware('web')->get('/_prueba-sql-roto', static fn () => User::query()->whereRaw('esto no es sql')->get());

    $this->get('/_prueba-tipo')->assertNotFound();
    // Un error de SQL de verdad sigue siendo un fallo del servidor.
    $this->get('/_prueba-sql-roto')->assertStatus(500);
});
