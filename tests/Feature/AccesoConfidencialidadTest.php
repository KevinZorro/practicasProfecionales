<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->estudiante = User::factory()->estudiante()->create();
    $this->entrega = FormatoConfidencialidad::factory()->cargado()->create([
        'firmante_id' => $this->estudiante->id,
    ]);
});

it('reserva la carga de la plantilla al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('create', PlantillaConfidencialidad::class))->toBe($puede);
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

    expect($usuario->can('descargar', PlantillaConfidencialidad::factory()->create()))->toBeTrue();
})->with([Rol::Estudiante, Rol::Docente, Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

it('deja verificar al administrativo, al coordinador y al ADMIN', function (Rol $rol, bool $puede): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('verificar', $this->entrega))->toBe($puede)
        ->and($usuario->can('rechazar', $this->entrega))->toBe($puede);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, true],
    'administrativo' => [Rol::Administrativo, true],
    'docente' => [Rol::Docente, false],
    'estudiante' => [Rol::Estudiante, false],
]);

it('deja al estudiante ver y bajar su propio formato', function (): void {
    expect($this->estudiante->can('view', $this->entrega))->toBeTrue()
        ->and($this->estudiante->can('descargar', $this->entrega))->toBeTrue()
        ->and($this->estudiante->can('update', $this->entrega))->toBeTrue();
});

it('no deja a un estudiante acercarse al formato de otro', function (): void {
    $otro = User::factory()->estudiante()->create();

    expect($otro->can('view', $this->entrega))->toBeFalse()
        ->and($otro->can('descargar', $this->entrega))->toBeFalse()
        ->and($otro->can('update', $this->entrega))->toBeFalse();
});

it('no deja a un docente bajar el archivo firmado', function (): void {
    // Lleva datos personales: solo el dueño y quien lo verifica (RNF07).
    $usuario = User::factory()->create();
    $usuario->assignRole(Rol::Docente->value);

    expect($usuario->can('descargar', $this->entrega))->toBeFalse();
});

it('deja bajar el archivo firmado a quien lo verifica', function (Rol $rol): void {
    // El administrativo verifica, así que también lee el documento: no se
    // aprueba lo que no se ha leído.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect($usuario->can('descargar', $this->entrega))->toBeTrue();
})->with([Rol::Administrativo, Rol::Coordinador, Rol::Admin]);

// QA-18: la descarga de la plantilla

it('responde 404 con su mensaje si no hay plantilla activa, no 500', function (): void {
    // La entrega del beforeEach trae su propia plantilla: se desactiva.
    PlantillaConfidencialidad::query()->update(['activo' => false]);

    $this->actingAs($this->estudiante)
        ->get(route('panel.formatos-confidencialidad.plantilla'))
        ->assertNotFound()
        ->assertSee('No hay plantilla de confidencialidad activa', false);
});

it('baja la plantilla activa y una versión concreta del historial', function (): void {
    Storage::fake('local');
    $plantilla = PlantillaConfidencialidad::factory()->create(['version' => '3.0']);
    Storage::disk('local')->put($plantilla->archivo_path, '%PDF-1.4 plantilla');
    $admin = User::factory()->admin()->create();

    $this->actingAs($this->estudiante)
        ->get(route('panel.formatos-confidencialidad.plantilla'))
        ->assertOk()
        ->assertDownload('formato-de-confidencialidad.pdf');

    $this->actingAs($admin)
        ->get(route('panel.formatos-confidencialidad.version', $plantilla))
        ->assertOk()
        ->assertDownload('formato-confidencialidad-v3.0.pdf');
});

it('responde 404 si el archivo de la plantilla ya no está en el disco', function (): void {
    Storage::fake('local');
    $plantilla = PlantillaConfidencialidad::factory()->create();

    $this->actingAs($this->estudiante)
        ->get(route('panel.formatos-confidencialidad.plantilla'))
        ->assertNotFound();
    expect($plantilla->archivo_path)->not->toBeEmpty();
});
