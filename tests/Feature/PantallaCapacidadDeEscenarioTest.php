<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Livewire\CasoClinico\CapacidadDeEscenarios;
use App\Models\CasoClinico;
use App\Models\User;
use App\Support\MenuDelPanel;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

// ---------------------------------------------------------------------
// Quién entra
// ---------------------------------------------------------------------

it('deja entrar al ADMIN a la pantalla de capacidad', function (): void {
    $this->actingAs($this->admin)->get(route('panel.casos-clinicos'))->assertOk();
});

it('no deja entrar a nadie más, ni escribiendo la URL a mano', function (Rol $rol): void {
    // RF74: la capacidad es dato del ADMIN, como el resto de la gestión de
    // casos clínicos del §6.1.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $this->actingAs($usuario->fresh())->get(route('panel.casos-clinicos'))->assertForbidden();
})->with([Rol::Coordinador, Rol::Administrativo, Rol::Docente, Rol::Estudiante]);

it('enseña la sección solo en el menú del ADMIN', function (Rol $rol, bool $laVe): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $claves = array_map(
        static fn ($seccion): string => $seccion->clave,
        (new MenuDelPanel)->visiblesPara($usuario->fresh()),
    );

    expect(in_array('casos-clinicos', $claves, true))->toBe($laVe);
})->with([
    'admin' => [Rol::Admin, true],
    'coordinador' => [Rol::Coordinador, false],
    'administrativo' => [Rol::Administrativo, false],
]);

// ---------------------------------------------------------------------
// Edición
// ---------------------------------------------------------------------

it('deja al ADMIN registrar la capacidad de un escenario', function (): void {
    $caso = CasoClinico::factory()->create(['capacidad_maxima_estudiantes' => null]);

    Livewire::actingAs($this->admin)
        ->test(CapacidadDeEscenarios::class)
        ->set("capacidades.{$caso->id}", '15')
        ->call('guardar', $caso->id)
        ->assertHasNoErrors();

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(15);
});

it('deja al ADMIN corregir una capacidad ya registrada', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    Livewire::actingAs($this->admin)
        ->test(CapacidadDeEscenarios::class)
        ->set("capacidades.{$caso->id}", '10')
        ->call('guardar', $caso->id);

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(10);
});

it('no deja guardar una capacidad que no sea un entero positivo', function (string $valor): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();

    Livewire::actingAs($this->admin)
        ->test(CapacidadDeEscenarios::class)
        ->set("capacidades.{$caso->id}", $valor)
        ->call('guardar', $caso->id)
        ->assertHasErrors("capacidades.{$caso->id}");

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(7);
})->with(['cero' => '0', 'negativo' => '-3', 'vacío' => '', 'texto' => 'muchos', 'decimal' => '7.5']);

it('no deja a otro rol guardar aunque llame al método a mano', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(7)->create();
    $coordinadora = User::factory()->coordinador()->create();

    Livewire::actingAs($coordinadora)
        ->test(CapacidadDeEscenarios::class)
        ->set("capacidades.{$caso->id}", '30')
        ->call('guardar', $caso->id)
        ->assertForbidden();

    expect($caso->fresh()->capacidad_maxima_estudiantes)->toBe(7);
});

// ---------------------------------------------------------------------
// Listado
// ---------------------------------------------------------------------

it('marca como sin definir el escenario al que le falta la capacidad', function (): void {
    CasoClinico::factory()->create(['nombre' => 'Morfofisiología', 'capacidad_maxima_estudiantes' => null]);

    Livewire::actingAs($this->admin)
        ->test(CapacidadDeEscenarios::class)
        ->assertSee('Morfofisiología')
        ->assertSee('Sin definir');
});

it('busca escenarios por nombre', function (): void {
    CasoClinico::factory()->create(['nombre' => 'Atención de parto normal']);
    CasoClinico::factory()->create(['nombre' => 'Crisis convulsiva']);

    Livewire::actingAs($this->admin)
        ->test(CapacidadDeEscenarios::class)
        ->set('busqueda', 'parto')
        ->assertSee('Atención de parto normal')
        ->assertDontSee('Crisis convulsiva');
});
