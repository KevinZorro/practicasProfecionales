<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Preparacion;
use App\Models\User;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->preparacion = Preparacion::factory()->create();
});

/** Usuario con un solo rol. */
function conRol(Rol $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    return $usuario;
}

it('no deja a un docente asignar sala ni preparar', function (): void {
    $docente = conRol(Rol::Docente);

    expect($docente->can('asignarSala', $this->preparacion))->toBeFalse()
        ->and($docente->can('preparar', $this->preparacion))->toBeFalse()
        ->and($docente->can('viewAny', Preparacion::class))->toBeFalse();
});

it('no deja a un estudiante acercarse al montaje', function (): void {
    $estudiante = conRol(Rol::Estudiante);

    expect($estudiante->can('asignarSala', $this->preparacion))->toBeFalse()
        ->and($estudiante->can('preparar', $this->preparacion))->toBeFalse()
        ->and($estudiante->can('view', $this->preparacion))->toBeFalse();
});

it('deja al administrativo asignar sala y preparar', function (): void {
    $administrativo = conRol(Rol::Administrativo);

    expect($administrativo->can('asignarSala', $this->preparacion))->toBeTrue()
        ->and($administrativo->can('preparar', $this->preparacion))->toBeTrue()
        ->and($administrativo->can('viewAny', Preparacion::class))->toBeTrue();
});

it('deja al coordinador asignar sala y preparar, porque hereda del administrativo', function (): void {
    $coordinadora = conRol(Rol::Coordinador);

    expect($coordinadora->can('asignarSala', $this->preparacion))->toBeTrue()
        ->and($coordinadora->can('preparar', $this->preparacion))->toBeTrue()
        ->and($coordinadora->can('viewAny', Preparacion::class))->toBeTrue();
});

it('no deja al ADMIN meterse en el montaje', function (): void {
    // El §6.1 no le marca "asignar sala y preparar escenario".
    $admin = conRol(Rol::Admin);

    expect($admin->can('asignarSala', $this->preparacion))->toBeFalse()
        ->and($admin->can('preparar', $this->preparacion))->toBeFalse()
        ->and($admin->can('viewAny', Preparacion::class))->toBeFalse();
});
