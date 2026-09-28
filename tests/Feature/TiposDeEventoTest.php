<?php

declare(strict_types=1);

use App\Filament\Resources\TipoEventoResource;
use App\Filament\Resources\TipoEventoResource\Pages\CreateTipoEvento;
use App\Filament\Resources\TipoEventoResource\Pages\EditTipoEvento;
use App\Filament\Resources\TipoEventoResource\Pages\ListTiposEvento;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 * El tipo de evento (RF05, RF14) es un catálogo que gestiona el ADMIN, no
 * una lista fija en el código.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function migracionDeTiposDeEvento(): Migration
{
    return require database_path('migrations/2026_09_28_100000_los_tipos_de_evento_pasan_a_una_tabla.php');
}

// ---------------------------------------------------------------------
// La migración
// ---------------------------------------------------------------------

it('convierte en catálogo los tipos que ya tenían los eventos, sin perder ninguno', function (): void {
    // El esquema de antes: la columna "tipo" con el valor del enum.
    $migracion = migracionDeTiposDeEvento();
    $migracion->down();

    $insertar = fn (string $titulo, string $tipo) => DB::table('eventos')->insertGetId([
        'titulo' => $titulo, 'descripcion' => 'x', 'fecha' => '2026-10-01', 'tipo' => $tipo,
        'abierto_publico' => true, 'orden' => 0, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $congreso = $insertar('Congreso regional', 'congreso');
    $jornada = $insertar('Jornada de simulación', 'jornada');
    $otraJornada = $insertar('Jornada de puertas abiertas', 'jornada');

    $migracion->up();

    expect(Schema::hasColumn('eventos', 'tipo'))->toBeFalse()
        ->and(TipoEvento::orderBy('nombre')->pluck('nombre')->all())->toBe(['Congreso', 'Jornada'])
        ->and(Evento::find($congreso)->tipoEvento->nombre)->toBe('Congreso')
        ->and(Evento::find($jornada)->tipo_evento_id)->toBe(Evento::find($otraJornada)->tipo_evento_id);
});

it('deshace la migración devolviendo el tipo a su columna', function (): void {
    $evento = Evento::factory()->create(['tipo_evento_id' => TipoEvento::factory()->create(['nombre' => 'Seminario'])->id]);

    migracionDeTiposDeEvento()->down();

    expect(Schema::hasTable('tipos_evento'))->toBeFalse()
        ->and(DB::table('eventos')->where('id', $evento->id)->value('tipo'))->toBe('seminario');
});

it('no deja borrar un tipo que tiene eventos', function (): void {
    $tipo = TipoEvento::factory()->create();
    Evento::factory()->create(['tipo_evento_id' => $tipo->id]);

    expect(fn () => $tipo->delete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------
// La Policy
// ---------------------------------------------------------------------

it('deja al ADMIN ver, crear y editar tipos de evento', function (): void {
    $tipo = TipoEvento::factory()->create();

    expect($this->admin->can('viewAny', TipoEvento::class))->toBeTrue()
        ->and($this->admin->can('create', TipoEvento::class))->toBeTrue()
        ->and($this->admin->can('update', $tipo))->toBeTrue();
});

it('no deja gestionar tipos de evento a ningún otro rol', function (string $estado): void {
    $usuario = User::factory()->{$estado}()->create();
    $tipo = TipoEvento::factory()->create();

    expect($usuario->can('viewAny', TipoEvento::class))->toBeFalse()
        ->and($usuario->can('create', TipoEvento::class))->toBeFalse()
        ->and($usuario->can('update', $tipo))->toBeFalse();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('no deja borrar tipos de evento a nadie, ni al ADMIN', function (): void {
    expect($this->admin->can('delete', TipoEvento::factory()->create()))->toBeFalse()
        ->and($this->admin->can('deleteAny', TipoEvento::class))->toBeFalse();
});

// ---------------------------------------------------------------------
// Las pantallas
// ---------------------------------------------------------------------

it('abre el listado, el alta y la edición al ADMIN', function (): void {
    $tipo = TipoEvento::factory()->create();
    $this->actingAs($this->admin);

    expect(TipoEventoResource::getUrl('index'))->toEndWith('/admin/tipos-de-evento');

    $this->get(TipoEventoResource::getUrl('index'))->assertOk();
    $this->get(TipoEventoResource::getUrl('create'))->assertOk();
    $this->get(TipoEventoResource::getUrl('edit', ['record' => $tipo]))->assertOk();
});

it('no abre el listado a otro rol', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(TipoEventoResource::getUrl('index'))
        ->assertForbidden();
});

it('crea un tipo de evento', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CreateTipoEvento::class)
        ->fillForm(['nombre' => 'Simposio', 'activo' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TipoEvento::where('nombre', 'Simposio')->first()?->activo)->toBeTrue();
});

it('no repite el nombre de un tipo de evento', function (): void {
    TipoEvento::factory()->create(['nombre' => 'Simposio']);

    Livewire::actingAs($this->admin)
        ->test(CreateTipoEvento::class)
        ->fillForm(['nombre' => 'Simposio'])
        ->call('create')
        ->assertHasFormErrors(['nombre' => 'unique']);

    expect(TipoEvento::count())->toBe(1);
});

it('desactiva un tipo sin tocar los eventos que ya lo tienen', function (): void {
    $tipo = TipoEvento::factory()->create();
    $evento = Evento::factory()->create(['tipo_evento_id' => $tipo->id]);

    Livewire::actingAs($this->admin)
        ->test(EditTipoEvento::class, ['record' => $tipo->getRouteKey()])
        ->fillForm(['activo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tipo->fresh()->activo)->toBeFalse()
        ->and($evento->fresh()->tipo_evento_id)->toBe($tipo->id);
});

it('no ofrece borrar tipos de evento en ninguna pantalla', function (): void {
    $tipo = TipoEvento::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ListTiposEvento::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::actingAs($this->admin)
        ->test(EditTipoEvento::class, ['record' => $tipo->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});
