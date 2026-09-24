<?php

declare(strict_types=1);

use App\Filament\Resources\SalaResource;
use App\Filament\Resources\SalaResource\Pages\CreateSala;
use App\Filament\Resources\SalaResource\Pages\EditSala;
use App\Filament\Resources\SalaResource\Pages\ListSalas;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\User;
use App\Services\PreparacionService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * Catálogo de salas en el panel del ADMIN. Asignar una sala a una
 * preparación es otra pantalla, del administrativo.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN ver, crear y editar salas', function (): void {
    $sala = Sala::factory()->create();

    expect($this->admin->can('viewAny', Sala::class))->toBeTrue()
        ->and($this->admin->can('create', Sala::class))->toBeTrue()
        ->and($this->admin->can('update', $sala))->toBeTrue();
});

it('no deja gestionar el catálogo de salas a ningún otro rol, tampoco al administrativo', function (string $estado): void {
    // El administrativo asigna salas, pero no da de alta ni edita el catálogo.
    $usuario = User::factory()->{$estado}()->create();
    $sala = Sala::factory()->create();

    expect($usuario->can('viewAny', Sala::class))->toBeFalse()
        ->and($usuario->can('create', Sala::class))->toBeFalse()
        ->and($usuario->can('update', $sala))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('no deja borrar salas a nadie, ni al ADMIN', function (): void {
    expect($this->admin->can('delete', Sala::factory()->create()))->toBeFalse()
        ->and($this->admin->can('deleteAny', Sala::class))->toBeFalse();
});

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $sala = Sala::factory()->create();
    $this->actingAs($this->admin);

    $this->get(SalaResource::getUrl('index'))->assertOk();
    $this->get(SalaResource::getUrl('create'))->assertOk();
    $this->get(SalaResource::getUrl('edit', ['record' => $sala]))->assertOk();
});

it('no abre el listado a otro rol', function (): void {
    $this->actingAs(User::factory()->administrativo()->create())
        ->get(SalaResource::getUrl('index'))
        ->assertForbidden();
});

it('lista las salas activas y las inactivas', function (): void {
    $activa = Sala::factory()->create();
    $inactiva = Sala::factory()->inactiva()->create();

    Livewire::actingAs($this->admin)
        ->test(ListSalas::class)
        ->assertCanSeeTableRecords([$activa, $inactiva]);
});

it('crea una sala', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateSala::class)
        ->fillForm(['codigo' => 'SIM-01', 'nombre' => 'Sala de partos', 'capacidad' => 12, 'activo' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sala::where('codigo', 'SIM-01')->first())
        ->nombre->toBe('Sala de partos')
        ->capacidad->toBe(12)
        ->activo->toBeTrue();
});

it('no crea una sala sin sus datos obligatorios', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateSala::class)
        ->fillForm(['codigo' => '', 'nombre' => '', 'capacidad' => null])
        ->call('create')
        ->assertHasFormErrors(['codigo' => 'required', 'nombre' => 'required', 'capacidad' => 'required']);

    expect(Sala::count())->toBe(0);
});

it('no repite un código de sala', function (): void {
    Sala::factory()->create(['codigo' => 'SIM-01']);

    Livewire::actingAs($this->admin)
        ->test(CreateSala::class)
        ->fillForm(['codigo' => 'SIM-01', 'nombre' => 'Otra', 'capacidad' => 8])
        ->call('create')
        ->assertHasFormErrors(['codigo' => 'unique']);

    expect(Sala::count())->toBe(1);
});

it('no acepta una capacidad que no sea un entero positivo', function (mixed $capacidad): void {
    Livewire::actingAs($this->admin)
        ->test(CreateSala::class)
        ->fillForm(['codigo' => 'SIM-01', 'nombre' => 'Sala de partos', 'capacidad' => $capacidad])
        ->call('create')
        ->assertHasFormErrors(['capacidad']);

    expect(Sala::count())->toBe(0);
})->with(['cero' => 0, 'negativo' => -4, 'decimal' => '7.5', 'sobre el tope de la columna' => 2_147_483_648]);

it('edita una sala sin chocar con su propio código', function (): void {
    $sala = Sala::factory()->create(['codigo' => 'SIM-01']);

    Livewire::actingAs($this->admin)
        ->test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->fillForm(['capacidad' => 20])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sala->fresh())
        ->codigo->toBe('SIM-01')
        ->capacidad->toBe(20);
});

it('deja de ofrecer al asignar la sala que el ADMIN desactiva', function (): void {
    $sala = Sala::factory()->create();
    $preparacion = Preparacion::factory()->create();
    $salasLibres = fn () => app(PreparacionService::class)->salasLibresPara($preparacion)->pluck('id')->all();

    expect($salasLibres())->toContain($sala->id);

    Livewire::actingAs($this->admin)
        ->test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->fillForm(['activo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($salasLibres())->not->toContain($sala->id);
});

it('no ofrece borrar salas en ninguna pantalla', function (): void {
    $sala = Sala::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListSalas::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::actingAs($this->admin)
        ->test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});
