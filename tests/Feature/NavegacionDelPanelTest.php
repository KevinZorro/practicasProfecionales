<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use App\Support\MenuDelPanel;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/** Claves de las secciones que el usuario ve con el rol dado. */
function seccionesVisibles(Rol $rol): array
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    return array_map(
        static fn ($seccion): string => $seccion->clave,
        (new MenuDelPanel)->visiblesPara($usuario->fresh()),
    );
}

it('enseña a cada rol solo lo que su Policy le permite', function (Rol $rol, array $esperadas): void {
    expect(seccionesVisibles($rol))->toEqualCanonicalizing($esperadas);
})->with([
    'docente' => [Rol::Docente, ['inicio', 'calendario', 'mis-solicitudes', 'evaluaciones', 'mi-formato']],
    'estudiante' => [Rol::Estudiante, ['inicio', 'calendario', 'mi-formato']],
    'administrativo' => [Rol::Administrativo, ['inicio', 'calendario', 'solicitudes', 'preparaciones', 'inventario', 'reposicion', 'formatos-confidencialidad']],
    'coordinador' => [Rol::Coordinador, ['inicio', 'calendario', 'solicitudes', 'preparaciones', 'evaluaciones', 'inventario', 'reposicion', 'formatos-confidencialidad', 'reportes']],
    'admin' => [Rol::Admin, ['inicio', 'calendario', 'solicitudes', 'evaluaciones', 'inventario', 'reposicion', 'formatos-confidencialidad', 'plantillas-confidencialidad', 'casos-clinicos', 'reportes']],
]);

it('no enseña al docente el inventario ni los reportes', function (): void {
    // RF40 y §6.1: disponibilidad de inventario y reportes agregados le
    // están vedados, aunque use el laboratorio a diario.
    expect(seccionesVisibles(Rol::Docente))
        ->not->toContain('inventario')
        ->not->toContain('reportes')
        ->not->toContain('formatos-confidencialidad');
});

it('no enseña los reportes al administrativo', function (): void {
    // Única función del coordinador que no acompaña: los formatos sí
    // los verifica él.
    expect(seccionesVisibles(Rol::Administrativo))
        ->not->toContain('reportes')
        ->toContain('formatos-confidencialidad');
});

it('enseña la bandeja de solicitudes al ADMIN, que es donde aprueba', function (): void {
    // Entra a aprobar en ausencia de la coordinadora, pero no revisa.
    expect(seccionesVisibles(Rol::Admin))
        ->toContain('solicitudes')
        ->not->toContain('mis-solicitudes');
});

it('cierra también la ruta de una sección que el menú esconde', function (Rol $rol, string $ruta): void {
    // Que el enlace no aparezca no basta: escribir la URL a mano tampoco
    // puede funcionar.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $this->actingAs($usuario->fresh())->get(route($ruta))->assertForbidden();
})->with([
    'docente en inventario' => [Rol::Docente, 'panel.inventario'],
    'docente en reportes' => [Rol::Docente, 'panel.reportes'],
    'administrativo en reportes' => [Rol::Administrativo, 'panel.reportes'],
    'estudiante en solicitudes' => [Rol::Estudiante, 'panel.solicitudes'],
    'docente en la bandeja' => [Rol::Docente, 'panel.solicitudes'],
    'administrativo en mis solicitudes' => [Rol::Administrativo, 'panel.mis-solicitudes'],
    'coordinador en plantillas' => [Rol::Coordinador, 'panel.plantillas-confidencialidad'],
]);

it('pinta en el menú lateral solo las secciones permitidas', function (): void {
    $docente = User::factory()->create();
    $docente->assignRole(Rol::Docente->value);

    $this->actingAs($docente->fresh())->get(route('panel.inicio'))
        ->assertOk()
        ->assertSee('Mis solicitudes')
        ->assertSee('Calendario')
        ->assertDontSee('Inventario')
        ->assertDontSee('Reportes');
});

it('no deja entrar al panel a quien no ha iniciado sesión', function (): void {
    // En pruebas no hay ruta de acceso —la de desarrollo solo se registra en
    // local y la de Google todavía no existe—, así que el invitado sale a la
    // portada.
    $this->get(route('panel.inicio'))->assertRedirect('/');
});

it('no deja entrar al panel a una cuenta sin ningún rol asignado', function (): void {
    // La sincronización institucional puede crear la cuenta antes de que el
    // ADMIN le asigne rol. Sin rol no hay navegación que pintar.
    $this->actingAs(User::factory()->create())
        ->get(route('panel.inicio'))
        ->assertForbidden()
        ->assertSee('no tiene ningún rol asignado');
});
