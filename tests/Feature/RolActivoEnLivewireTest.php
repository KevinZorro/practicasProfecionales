<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use Database\Seeders\RolSeeder;

/*
 * RF21 en las acciones de Livewire. Los botones de una pantalla ya abierta
 * van a /livewire/update, fuera de las rutas del panel, así que el rol
 * activo solo se aplica ahí porque EstablecerRolActivo es middleware
 * persistente (AppServiceProvider).
 *
 * Todo se prueba con peticiones HTTP reales: Livewire::test() se salta los
 * middleware y daría por buenos estos casos aunque el rol no se aplicara.
 *
 * El usuario de estos tests es coordinador y administrativo a la vez, y la
 * pantalla es la bandeja de solicitudes, donde los dos roles actúan. Es la
 * combinación que hace visible el fallo: el coordinador hereda lo del
 * administrativo, así que si la acción se evaluara con todos los roles, el
 * que queda le dejaría hacer lo que el retirado ya no puede.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

function coordinadoraYAdministrativa(): User
{
    return User::factory()->coordinador()->administrativo()->create()->fresh();
}

/**
 * En producción cada petición arranca de cero. En un test HTTP el contenedor
 * se comparte entre peticiones, y RolActivo —que vive una petición— seguiría
 * con los roles que leyó en la anterior. Esto deja las cosas como las
 * encontraría la petición siguiente de verdad.
 */
function comoEnLaSiguientePeticion(User $usuario): void
{
    app()->forgetScopedInstances();
    test()->actingAs($usuario->fresh());
}

function retirarRol(User $usuario, Rol $rol): void
{
    app(AsignacionDeRolService::class)->revocar(User::factory()->admin()->create(), $usuario, $rol);
}

it('no deja actuar con el rol que le quitaron mientras tenía la pantalla abierta', function (): void {
    $usuaria = coordinadoraYAdministrativa();
    $solicitud = Solicitud::factory()->create();

    $this->actingAs($usuaria)->post(route('panel.rol-activo'), ['rol' => Rol::Administrativo->value]);
    $instantanea = instantaneaDeLaPantalla(route('panel.solicitudes'));

    retirarRol($usuaria, Rol::Administrativo);
    comoEnLaSiguientePeticion($usuaria);

    // Como coordinadora podría revisar, pero el botón se pulsó en una
    // pantalla abierta como administrativa, y ese rol ya no lo tiene.
    accionDeLivewire($instantanea, 'revisar', [$solicitud->id])
        ->assertForbidden()
        ->assertJsonPath('message', 'El rol con el que abriste esta pantalla ya no está vigente. Recarga la página.');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Pendiente);
});

it('corta igual a quien nunca eligió rol y le quitan el que tenía por defecto', function (): void {
    // Sin pasar por el selector, la pantalla se abre con el rol más amplio.
    // Si solo se comparara con lo elegido a mano, este caso se escaparía.
    $usuaria = coordinadoraYAdministrativa();
    $solicitud = Solicitud::factory()->create();

    $this->actingAs($usuaria);
    $instantanea = instantaneaDeLaPantalla(route('panel.solicitudes'));

    retirarRol($usuaria, Rol::Coordinador);
    comoEnLaSiguientePeticion($usuaria);

    accionDeLivewire($instantanea, 'revisar', [$solicitud->id])->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Pendiente);
});

it('al recargar la pantalla la lleva al rol que le queda', function (): void {
    $usuaria = coordinadoraYAdministrativa();

    $this->actingAs($usuaria)->post(route('panel.rol-activo'), ['rol' => Rol::Administrativo->value]);
    instantaneaDeLaPantalla(route('panel.solicitudes'));

    retirarRol($usuaria, Rol::Administrativo);
    comoEnLaSiguientePeticion($usuaria);

    // Navegar no se corta: cae al rol legítimo, como siempre.
    $this->get(route('panel.solicitudes'))->assertOk()->assertSee(Rol::Coordinador->etiqueta());
});

it('evalúa los botones con el rol activo, no con todos los que tiene', function (): void {
    // Abre la bandeja como coordinadora y, en otra pestaña, se pasa a
    // administrativa. El botón de aprobar que quedó en la primera ya no es
    // suyo: la administrativa no aprueba.
    $usuaria = coordinadoraYAdministrativa();
    $solicitud = Solicitud::factory()->revisada()->create();

    $this->actingAs($usuaria);
    $instantanea = instantaneaDeLaPantalla(route('panel.solicitudes'));

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Administrativo->value]);
    comoEnLaSiguientePeticion($usuaria);

    accionDeLivewire($instantanea, 'aprobar', [$solicitud->id])->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});

it('deja actuar con el rol activo cuando nada cambió', function (): void {
    // Control: sin esto, los casos anteriores pasarían también si la acción
    // fallara por cualquier otro motivo.
    $usuaria = coordinadoraYAdministrativa();
    $solicitud = Solicitud::factory()->create();

    $this->actingAs($usuaria)->post(route('panel.rol-activo'), ['rol' => Rol::Administrativo->value]);
    $instantanea = instantaneaDeLaPantalla(route('panel.solicitudes'));
    comoEnLaSiguientePeticion($usuaria);

    accionDeLivewire($instantanea, 'revisar', [$solicitud->id])->assertOk();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});
