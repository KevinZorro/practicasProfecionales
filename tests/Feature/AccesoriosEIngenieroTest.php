<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Livewire\Inventario\FormularioItem;
use App\Livewire\Inventario\ListadoInventario;
use App\Livewire\Preparacion\TableroDiario;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosItemInventario;
use App\Services\InventarioService;
use App\Services\PreparacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
 * Accesorios y repuestos de un simulador (RF38) y lo que monta el ingeniero
 * en la preparación (RF72).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->servicio = app(InventarioService::class);
});

function accesorioDe(?int $simuladorId, string $nombre = 'Piel de reemplazo'): DatosItemInventario
{
    return new DatosItemInventario(
        nombre: $nombre,
        tipo: TipoItemInventario::Accesorio,
        cantidadTotal: 2,
        simuladorId: $simuladorId,
    );
}

// ---------------------------------------------------------------------
// Accesorios y repuestos (RF38)
// ---------------------------------------------------------------------

it('registra un accesorio ligado a su simulador', function (): void {
    $maniqui = ItemInventario::factory()->simulador()->create(['nombre' => 'Maniquí de parto']);

    $accesorio = $this->servicio->crear($this->administrativo, accesorioDe($maniqui->id));

    expect($accesorio->tipo)->toBe(TipoItemInventario::Accesorio)
        ->and($accesorio->simulador->is($maniqui))->toBeTrue()
        ->and($maniqui->accesorios()->pluck('id')->all())->toBe([$accesorio->id]);
});

it('no deja un accesorio sin simulador ni colgado de algo que no es simulador', function (): void {
    $equipo = ItemInventario::factory()->create();

    expect(fn () => $this->servicio->crear($this->administrativo, accesorioDe(null)))
        ->toThrow(InventarioInvalido::class, 'pertenecer a un simulador')
        ->and(fn () => $this->servicio->crear($this->administrativo, accesorioDe($equipo->id)))
        ->toThrow(InventarioInvalido::class, 'pertenecer a un simulador');
});

it('no deja colgar un simulador de otro aunque se salte el Service', function (): void {
    $maniqui = ItemInventario::factory()->simulador()->create();

    expect(fn () => ItemInventario::factory()->create(['simulador_id' => $maniqui->id]))
        ->toThrow(QueryException::class)
        ->and(fn () => ItemInventario::factory()->create(['tipo' => TipoItemInventario::Accesorio, 'simulador_id' => null]))
        ->toThrow(QueryException::class);
});

it('suelta el simulador si el ítem deja de ser accesorio', function (): void {
    $accesorio = ItemInventario::factory()->accesorio()->create();

    $this->servicio->actualizar($this->administrativo, $accesorio, new DatosItemInventario(
        nombre: $accesorio->nombre,
        tipo: TipoItemInventario::EquipoClinico,
        cantidadTotal: $accesorio->cantidad_total,
        simuladorId: $accesorio->simulador_id,
    ));

    expect($accesorio->fresh()->simulador_id)->toBeNull();
});

it('pide el simulador en el formulario solo cuando es accesorio, y lo muestra en el listado', function (): void {
    $maniqui = ItemInventario::factory()->simulador()->create(['nombre' => 'Maniquí de parto']);

    Livewire::actingAs($this->administrativo)
        ->test(FormularioItem::class)
        ->assertDontSee('De qué simulador')
        ->set('tipo', TipoItemInventario::Accesorio->value)
        ->assertSee('De qué simulador')
        ->set('nombre', 'Brazo de venopunción')
        ->set('cantidadTotal', 1)
        ->call('guardar')
        ->assertSee('Un accesorio o repuesto tiene que pertenecer a un simulador del inventario.')
        ->set('simuladorId', $maniqui->id)
        ->call('guardar')
        ->assertHasNoErrors();

    Livewire::actingAs($this->administrativo)
        ->test(ListadoInventario::class)
        ->assertSee('Brazo de venopunción')
        ->assertSee('De: Maniquí de parto')
        ->assertSee('Accesorio o repuesto');
});

// ---------------------------------------------------------------------
// El ingeniero (RF72)
// ---------------------------------------------------------------------

it('marca como del ingeniero solo los simuladores de alta fidelidad', function (): void {
    $preparaciones = app(PreparacionService::class);

    expect($preparaciones->requiereIngeniero(ItemInventario::factory()->simulador(NivelFidelidad::Alta)->make()))->toBeTrue()
        ->and($preparaciones->requiereIngeniero(ItemInventario::factory()->simulador(NivelFidelidad::Media)->make()))->toBeFalse()
        ->and($preparaciones->requiereIngeniero(ItemInventario::factory()->make()))->toBeFalse();
});

it('separa en la preparación lo que requiere al ingeniero', function (): void {
    $alta = ItemInventario::factory()->simulador(NivelFidelidad::Alta)->create(['nombre' => 'Simulador neonatal']);
    $gasas = ItemInventario::factory()->equipoBasico()->create(['nombre' => 'Gasas estériles']);
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Aprobada, 'fecha' => '2026-10-20']);
    $preparacion = Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => null, 'estado' => EstadoPreparacion::Pendiente]);
    $preparacion->items()->attach([$alta->id => ['cantidad' => 1, 'alistado' => false], $gasas->id => ['cantidad' => 10, 'alistado' => false]]);

    $html = Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', '2026-10-20')
        ->assertSee('1 elemento requiere al ingeniero (alta fidelidad).')
        ->call('abrir', $preparacion->id)
        ->html();

    // El grupo del ingeniero va primero, con el simulador de alta fidelidad dentro.
    $posiciones = array_map(static fn (string $texto): int|false => mb_strpos($html, $texto), ['Requiere al ingeniero (alta fidelidad)', 'Simulador neonatal', 'Equipo básico', 'Gasas estériles']);

    expect($posiciones)->not->toContain(false)
        ->and($posiciones)->toBe(collect($posiciones)->sort()->values()->all());
});
