<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use App\Support\RolActivo;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/** Un usuario con los roles indicados. */
function usuarioConRoles(Rol ...$roles): User
{
    $usuario = User::factory()->create();
    foreach ($roles as $rol) {
        $usuario->assignRole($rol->value);
    }

    return $usuario->fresh();
}

it('no muestra el selector a quien solo tiene un rol', function (Rol $rol): void {
    $respuesta = $this->actingAs(usuarioConRoles($rol))->get(route('panel.inicio'));

    $respuesta->assertOk()
        ->assertDontSee('Cambiar de rol')
        ->assertSee($rol->etiqueta());
})->with([Rol::Docente, Rol::Estudiante, Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

it('muestra el selector con los dos roles a quien tiene dos', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente)->get(route('panel.inicio'))
        ->assertOk()
        ->assertSee('Cambiar de rol')
        ->assertSee(Rol::Coordinador->etiqueta())
        ->assertSee(Rol::Docente->etiqueta());
});

it('arranca en el rol más amplio de los asignados', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente)->get(route('panel.inicio'));

    expect(session(RolActivo::CLAVE_DE_SESION))->toBeNull()
        ->and(app(RolActivo::class)->actual($coordinadoraDocente))->toBe(Rol::Coordinador);
});

it('cambia de rol sin cerrar sesión y lo guarda en la sesión, no en la base', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente)
        ->from(route('panel.inicio'))
        ->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value])
        ->assertRedirect(route('panel.inicio'));

    expect(session(RolActivo::CLAVE_DE_SESION))->toBe(Rol::Docente->value)
        ->and($this->isAuthenticated())->toBeTrue()
        ->and($coordinadoraDocente->fresh()->roles->pluck('name')->all())
        ->toEqualCanonicalizing([Rol::Coordinador->value, Rol::Docente->value]);
});

it('cambia la navegación al cambiar de rol', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    // Como coordinadora ve reportes y consentimientos; no puede solicitar.
    $this->actingAs($coordinadoraDocente)->get(route('panel.inicio'))
        ->assertSee('Reportes')
        ->assertSee('Consentimientos');

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);

    // Como docente desaparecen: la Policy se lo niega con ese rol puesto.
    $this->get(route('panel.inicio'))
        ->assertOk()
        ->assertDontSee('Reportes')
        ->assertDontSee('>Consentimientos<', escape: false);
});

it('cambia los permisos efectivos al cambiar de rol, no solo el menú', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente);

    $this->get(route('panel.reportes'))->assertOk();

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);

    $this->get(route('panel.reportes'))->assertForbidden();
});

it('no deja asumir un rol que no se tiene asignado', function (): void {
    $docente = usuarioConRoles(Rol::Docente);

    $this->actingAs($docente)
        ->post(route('panel.rol-activo'), ['rol' => Rol::Admin->value])
        ->assertForbidden();

    expect(session(RolActivo::CLAVE_DE_SESION))->toBeNull();
});

it('ignora un rol metido a mano en la sesión y cae al que sí corresponde', function (): void {
    $docente = usuarioConRoles(Rol::Docente);

    // Sesión manipulada: se pone coordinador sin tenerlo.
    $this->actingAs($docente)
        ->withSession([RolActivo::CLAVE_DE_SESION => Rol::Coordinador->value]);

    $this->get(route('panel.inicio'))
        ->assertOk()
        ->assertSee(Rol::Docente->etiqueta())
        ->assertDontSee('Reportes');

    // Y tampoco consigue entrar a lo que ese rol le habría abierto.
    $this->get(route('panel.reportes'))->assertForbidden();
});

it('no acepta un rol que no existe', function (): void {
    $this->actingAs(usuarioConRoles(Rol::Docente))
        ->post(route('panel.rol-activo'), ['rol' => 'rector'])
        ->assertSessionHasErrors('rol');
});

it('deja ir y volver entre los dos roles', function (): void {
    // Regresión: aplicar() recorta la relación de roles, así que si
    // disponibles() la leyera en vez de consultar la base, el segundo
    // cambio se quedaría sin roles entre los que elegir.
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);
    $this->actingAs($coordinadoraDocente);

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);
    $this->get(route('panel.reportes'))->assertForbidden();

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Coordinador->value]);
    $this->get(route('panel.reportes'))->assertOk();

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);
    $this->get(route('panel.reportes'))->assertForbidden();
});
