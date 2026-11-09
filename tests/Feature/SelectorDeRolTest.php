<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Support\RolActivo;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Str;

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
    $respuesta = $this->actingAs(usuarioConRoles($rol))->get(route('panel.calendario'));

    $respuesta->assertOk()
        ->assertDontSee('Cambiar de rol')
        ->assertSee($rol->etiqueta());
})->with([Rol::Docente, Rol::Estudiante, Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

it('muestra el selector con los dos roles a quien tiene dos', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente)->get(route('panel.calendario'))
        ->assertOk()
        ->assertSee('Cambiar de rol')
        ->assertSee(Rol::Coordinador->etiqueta())
        ->assertSee(Rol::Docente->etiqueta());
});

it('arranca en el rol más amplio de los asignados', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente)->get(route('panel.calendario'));

    // Queda guardado aunque no lo haya elegido: es el rol con el que se abrió
    // la pantalla, y las acciones de Livewire lo necesitan para saber si
    // sigue vigente (RolActivoEnLivewireTest).
    expect(session(RolActivo::CLAVE_DE_SESION))->toBe(Rol::Coordinador->value)
        ->and(app(RolActivo::class)->actual($coordinadoraDocente))->toBe(Rol::Coordinador);
});

it('cambia de rol sin cerrar sesión y lo guarda en la sesión, no en la base', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    $this->actingAs($coordinadoraDocente)
        ->from(route('panel.calendario'))
        ->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value])
        ->assertRedirect(route('panel.inicio'));

    expect(session(RolActivo::CLAVE_DE_SESION))->toBe(Rol::Docente->value)
        ->and($this->isAuthenticated())->toBeTrue()
        ->and($coordinadoraDocente->fresh()->roles->pluck('name')->all())
        ->toEqualCanonicalizing([Rol::Coordinador->value, Rol::Docente->value]);
});

it('cambia la navegación al cambiar de rol', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    // Como coordinadora ve reportes y formatos de confidencialidad; no puede solicitar.
    $this->actingAs($coordinadoraDocente)->get(route('panel.calendario'))
        ->assertSee('Reportes')
        ->assertSee('Formatos de confidencialidad');

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);

    // Como docente desaparecen: la Policy se lo niega con ese rol puesto.
    $this->get(route('panel.calendario'))
        ->assertOk()
        ->assertDontSee('Reportes')
        ->assertDontSee('>Formatos de confidencialidad<', escape: false);
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

    expect(session(RolActivo::CLAVE_DE_SESION))->toBe(Rol::Docente->value);
});

it('ignora un rol metido a mano en la sesión y cae al que sí corresponde', function (): void {
    $docente = usuarioConRoles(Rol::Docente);

    // Sesión manipulada: se pone coordinador sin tenerlo.
    $this->actingAs($docente)
        ->withSession([RolActivo::CLAVE_DE_SESION => Rol::Coordinador->value]);

    $this->get(route('panel.calendario'))
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

it('carga Alpine también en las pantallas sin componentes, para que el aviso del cambio se pueda cerrar', function (string $ruta): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    // «Hoy» y el calendario no montan ningún componente de Livewire, que
    // es quien trae Alpine. Sin él, el aviso no se iba ni se cerraba.
    $this->actingAs($coordinadoraDocente)
        ->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);

    $this->get(route($ruta))
        ->assertOk()
        ->assertSee('Ahora estás viendo el sistema como')
        ->assertSee('/livewire/livewire', false);
})->with(['panel.inicio', 'panel.calendario']);

it('al cambiar de rol lleva a «Hoy», no a una pantalla que el rol nuevo no puede ver', function (): void {
    $coordinadoraDocente = usuarioConRoles(Rol::Coordinador, Rol::Docente);

    // La bandeja de solicitudes es de coordinación, no del docente: volver
    // a ella enseñaba «Prohibido» justo después de anunciar el cambio.
    $this->actingAs($coordinadoraDocente)
        ->from(route('panel.solicitudes'))
        ->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value])
        ->assertRedirect(route('panel.inicio'));

    $this->get(route('panel.inicio'))
        ->assertOk()
        ->assertSee('Ahora estás viendo el sistema como Docente.');
});

// ---------------------------------------------------------------------
// Dónde está: arriba a la derecha (RF21)
// ---------------------------------------------------------------------

/** La barra de tinta del celular, sin el riel ni el contenido. */
function barraDelCelular(string $html): string
{
    return Str::betweenFirst($html, '<header class="sticky', '</header>');
}

it('pone el rol activo arriba a la derecha, también en el celular, y no al pie del menú', function (): void {
    // Al pie del riel no se veía en el celular sin abrir el menú, y quien
    // tiene dos roles no sabía con cuál estaba mirando.
    $html = $this->actingAs(usuarioConRoles(Rol::Coordinador, Rol::Docente))
        ->get(route('panel.calendario'))
        ->assertOk()
        ->getContent();

    expect(barraDelCelular($html))->toContain('Rol activo:')->toContain(route('panel.rol-activo'))
        ->and(Str::betweenFirst($html, '<aside id="menu-del-panel"', '</aside>'))->not->toContain(route('panel.rol-activo'))
        // Una vez en la barra del celular y otra sobre el contenido en escritorio.
        ->and(substr_count($html, 'Rol activo:'))->toBe(2);
});

it('cambia de rol con un solo toque: cada rol es un botón y el actual no se ofrece', function (): void {
    $this->actingAs(usuarioConRoles(Rol::Coordinador, Rol::Docente))
        ->get(route('panel.calendario'))
        ->assertSee('name="rol" value="docente"', false)
        ->assertDontSee('name="rol" value="coordinador"', false)
        ->assertSee('Con este estás viendo el sistema');
});

it('dice en la cabecera, con la fecha, el rol prestado aunque sea el único', function (): void {
    // RF63: quien usa un rol prestado no lo descubre al fallar un permiso.
    $hasta = now()->addWeek();
    $pasante = User::factory()->create();
    app(AsignacionDeRolService::class)->asignar(User::factory()->admin()->create(), $pasante, Rol::Administrativo, $hasta->toDateString());

    $html = $this->actingAs($pasante->fresh())->get(route('panel.inicio'))->assertOk()->getContent();

    expect(barraDelCelular($html))
        ->toContain(Rol::Administrativo->etiqueta())
        ->toContain('hasta '.$hasta->format('d/m'))
        // Con un solo rol no hay nada que elegir.
        ->not->toContain(route('panel.rol-activo'));
});

it('no ocupa la barra de quien tiene un solo rol permanente', function (): void {
    $html = $this->actingAs(usuarioConRoles(Rol::Estudiante))->get(route('panel.inicio'))->assertOk()->getContent();

    expect($html)->not->toContain('Rol activo:')
        ->and(barraDelCelular($html))->toContain('Laboratorio de Simulación');
});
