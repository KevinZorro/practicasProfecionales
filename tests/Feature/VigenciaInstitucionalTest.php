<?php

declare(strict_types=1);

use App\Enums\EstadoUsuario;
use App\Http\Controllers\Auth\AccesoDeDesarrolloController;
use App\Models\User;
use App\Services\AccesoService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Route;

/*
 * Regla 8 del CLAUDE.md: el acceso depende de la vigencia institucional
 * (users.estado), no del correo. Los egresados conservan su cuenta
 * institucional, así que tener el correo no basta.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

// ---------------------------------------------------------------------
// La regla
// ---------------------------------------------------------------------

it('deja entrar a quien tiene vigencia institucional', function (): void {
    expect(app(AccesoService::class)->puedeEntrar(User::factory()->create()))->toBeTrue();
});

it('no deja entrar a quien ya no la tiene, aunque conserve el correo', function (): void {
    expect(app(AccesoService::class)->puedeEntrar(User::factory()->inactivo()->create()))->toBeFalse();
});

// ---------------------------------------------------------------------
// El middleware
// ---------------------------------------------------------------------

it('deja pasar al panel a un usuario activo', function (): void {
    $this->actingAs(User::factory()->docente()->create())
        ->get(route('panel.inicio'))
        ->assertOk();
});

it('cierra la sesión de un usuario inactivo y no le enseña el panel', function (): void {
    $this->actingAs(User::factory()->docente()->inactivo()->create())
        ->get(route('panel.inicio'))
        ->assertForbidden()
        ->assertSee('vinculación con la institución no está vigente');

    $this->assertGuest();
});

it('corta a quien se desactiva con la sesión abierta, en la petición siguiente', function (): void {
    $docente = User::factory()->docente()->create();
    $this->actingAs($docente)->get(route('panel.inicio'))->assertOk();

    // Lo que hará la sincronización institucional (regla 8).
    $docente->forceFill(['estado' => EstadoUsuario::Inactivo])->save();

    $this->get(route('panel.inicio'))->assertForbidden();
    $this->assertGuest();
});

it('no deja actuar en una pantalla que ya estaba abierta cuando se desactivó', function (): void {
    // Las acciones de un componente abierto van a /livewire/update, fuera del
    // grupo de rutas del panel. Esto es una petición HTTP de verdad, no
    // Livewire::test(), que se salta los middleware.
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $instantanea = instantaneaDeLaPantalla(route('panel.usuarios'));

    $admin->forceFill(['estado' => EstadoUsuario::Inactivo])->save();

    accionDeLivewire($instantanea, 'cerrar')->assertForbidden();
    $this->assertGuest();
});

it('sí deja actuar en esa misma pantalla mientras sigue activo', function (): void {
    // Control del test anterior: prueba que la petición que arma el test es
    // válida, así que el 403 de arriba viene de la regla y no del armado.
    $this->actingAs(User::factory()->admin()->create());
    $instantanea = instantaneaDeLaPantalla(route('panel.usuarios'));

    accionDeLivewire($instantanea, 'cerrar')->assertOk();
});

// ---------------------------------------------------------------------
// La entrada
// ---------------------------------------------------------------------

it('no deja entrar a un usuario inactivo por el acceso de desarrollo', function (): void {
    // La ruta real solo existe en local; se registra aquí para probar el
    // controlador, que es la misma comprobación que hará el de Google.
    Route::middleware('web')->post('/prueba/entrar', [AccesoDeDesarrolloController::class, 'entrar']);
    $inactivo = User::factory()->docente()->inactivo()->create();

    $this->post('/prueba/entrar', ['usuario' => $inactivo->id])
        ->assertSessionHasErrors(['usuario' => app(AccesoService::class)->motivoDelRechazo()]);

    $this->assertGuest();
});

it('deja entrar a un usuario activo por el acceso de desarrollo', function (): void {
    Route::middleware('web')->post('/prueba/entrar', [AccesoDeDesarrolloController::class, 'entrar']);
    $activo = User::factory()->docente()->create();

    $this->post('/prueba/entrar', ['usuario' => $activo->id])->assertRedirect(route('panel.inicio'));

    $this->assertAuthenticatedAs($activo);
});
