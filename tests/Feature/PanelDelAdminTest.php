<?php

declare(strict_types=1);

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use Database\Seeders\RolSeeder;

/*
 * Puerta del panel de Filament (/admin): solo el ADMIN, con el rol activo
 * aplicado, con la vigencia institucional comprobada y sin entrada propia
 * por contraseña. Qué puede hacer dentro de cada pantalla lo dicen las
 * Policies; eso lo prueba RecursosDelAdminTest.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

it('manda a la entrada del proyecto a quien llega sin sesión', function (): void {
    $this->get('/admin')->assertRedirect();
    $this->assertGuest();
});

it('deja entrar al ADMIN', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('no deja entrar a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();

    $this->actingAs($usuario)->get('/admin')->assertForbidden();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('respeta el rol activo: con otro rol puesto, el ADMIN no entra', function (): void {
    $adminDocente = User::factory()->admin()->docente()->create();
    $this->actingAs($adminDocente);

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);
    $this->get('/admin')->assertForbidden();

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Admin->value]);
    $this->get('/admin')->assertOk();
});

it('corta a un ADMIN que perdió la vigencia institucional y le cierra la sesión', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin')->assertOk();

    $admin->forceFill(['estado' => EstadoUsuario::Inactivo])->save();

    $this->get('/admin')->assertForbidden();
    $this->assertGuest();
});

it('no deja actuar en una pantalla ya abierta cuando le quitan el rol de ADMIN', function (): void {
    // Petición real a /livewire/update: Livewire::test() se salta los
    // middleware y daría esto por bueno aunque la acción quedara abierta.
    $adminDocente = User::factory()->admin()->docente()->create();
    $this->actingAs($adminDocente);
    $instantanea = instantaneaDeLaPantalla('/admin');

    app(AsignacionDeRolService::class)->revocar(User::factory()->admin()->create(), $adminDocente, Rol::Admin);
    app()->forgetScopedInstances();
    $this->actingAs($adminDocente->fresh());

    accionDeLivewire($instantanea)->assertForbidden();
});

it('no deja actuar en una pantalla ya abierta después de pasarse a otro rol en otra pestaña', function (): void {
    // El rol de docente sigue vigente, así que EstablecerRolActivo deja
    // pasar: quien corta es canAccessPanel(), en el Authenticate de
    // Filament, que por eso también es persistente.
    $adminDocente = User::factory()->admin()->docente()->create();
    $this->actingAs($adminDocente);
    $instantanea = instantaneaDeLaPantalla('/admin');

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);
    app()->forgetScopedInstances();
    $this->actingAs($adminDocente->fresh());

    accionDeLivewire($instantanea)->assertForbidden();
});

it('deja actuar en una pantalla ya abierta cuando nada cambió', function (): void {
    // Control del anterior.
    $this->actingAs(User::factory()->admin()->create());
    $instantanea = instantaneaDeLaPantalla('/admin');

    accionDeLivewire($instantanea)->assertOk();
});

it('sale por la ruta de salida del proyecto', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('action="'.route('salir').'"', escape: false)
        ->assertDontSee('action="'.route('filament.admin.auth.logout').'"', escape: false);
});

it('enseña el enlace a la administración solo a quien puede entrar', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.inicio'))
        ->assertSee('href="'.route('filament.admin.pages.dashboard').'"', escape: false);

    $this->actingAs(User::factory()->coordinador()->create())
        ->get(route('panel.inicio'))
        ->assertDontSee('href="'.route('filament.admin.pages.dashboard').'"', escape: false);
});
