<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->estudiante = User::factory()->estudiante()->create();
    $this->entrega = ConsentimientoEstudiante::factory()->cargado()->create([
        'estudiante_id' => $this->estudiante->id,
    ]);
});

it('reserva la carga de la plantilla al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('create', ConsentimientoPlantilla::class))->toBe($puede);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, false],
    'administrativo' => [Rol::Administrativo, false],
    'docente' => [Rol::Docente, false],
    'estudiante' => [Rol::Estudiante, false],
]);

it('deja bajar la plantilla en blanco a cualquiera que vaya a firmarla', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('descargar', ConsentimientoPlantilla::factory()->create()))->toBeTrue();
})->with([Rol::Estudiante, Rol::Docente, Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

it('deja verificar solo al coordinador y al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('verificar', $this->entrega))->toBe($puede)
        ->and($usuario->can('rechazar', $this->entrega))->toBe($puede);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, true],
    'administrativo' => [Rol::Administrativo, false],
    'docente' => [Rol::Docente, false],
    'estudiante' => [Rol::Estudiante, false],
]);

it('deja al estudiante ver y bajar su propio consentimiento', function (): void {
    expect($this->estudiante->can('view', $this->entrega))->toBeTrue()
        ->and($this->estudiante->can('descargar', $this->entrega))->toBeTrue()
        ->and($this->estudiante->can('update', $this->entrega))->toBeTrue();
});

it('no deja a un estudiante acercarse al consentimiento de otro', function (): void {
    $otro = User::factory()->estudiante()->create();

    expect($otro->can('view', $this->entrega))->toBeFalse()
        ->and($otro->can('descargar', $this->entrega))->toBeFalse()
        ->and($otro->can('update', $this->entrega))->toBeFalse();
});

it('no deja a un docente ni a un administrativo bajar el archivo firmado', function (Rol $rol): void {
    // Lleva datos personales: solo el dueño y quien lo verifica (RNF07).
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('descargar', $this->entrega))->toBeFalse();
})->with([Rol::Docente, Rol::Administrativo]);

it('deja bajar el archivo firmado a quien lo verifica', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('descargar', $this->entrega))->toBeTrue();
})->with([Rol::Coordinador, Rol::Admin]);
