<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Filament\Resources\SalaResource\Pages\CreateSala;
use App\Filament\Resources\SalaResource\Pages\EditSala;
use App\Mail\SalaAsignadaMail;
use App\Models\CasoClinico;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\PreparacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Salas ubicadas por bloque, piso y número, con su historial (RF65), y el
 * correo al docente con la sala asignada (RF36).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->administrativo = User::factory()->administrativo()->create();
});

function preparacionDe(CasoClinico $caso, string $inicio = '07:00:00', string $fin = '09:00:00'): Preparacion
{
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada,
        'caso_clinico_id' => $caso->id,
        'fecha' => '2026-10-20',
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
    ]);

    return Preparacion::factory()->create([
        'solicitud_id' => $solicitud->id,
        'sala_id' => null,
        'estado' => EstadoPreparacion::Pendiente,
    ]);
}

// ---------------------------------------------------------------------
// Ubicación e historial (RF65)
// ---------------------------------------------------------------------

it('crea una sala con su ubicación y deja la primera fila del historial', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateSala::class)
        ->fillForm([
            'codigo' => 'B-2-04',
            'nombre' => 'Sala de partos',
            'capacidad' => 12,
            'bloque' => 'B',
            'piso' => '2',
            'numero' => '04',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sala = Sala::query()->where('codigo', 'B-2-04')->firstOrFail();

    expect($sala->ubicacion())->toBe('Bloque B · piso 2 · sala 04')
        ->and($sala->ubicaciones()->count())->toBe(1)
        ->and($sala->ubicaciones()->first()->registrada_por)->toBe($this->admin->id);
});

it('conserva la ubicación anterior al reubicar la sala', function (): void {
    $this->actingAs($this->admin);
    $sala = Sala::factory()->create(['bloque' => 'A', 'piso' => '1', 'numero' => '3']);

    Livewire::test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->fillForm(['bloque' => 'C', 'piso' => '3', 'numero' => '1'])
        ->call('save')
        ->assertHasNoFormErrors();

    $ubicaciones = $sala->fresh()->ubicaciones()->get();

    expect($ubicaciones)->toHaveCount(1)
        ->and($ubicaciones->first()->descripcion())->toBe('Bloque C · piso 3 · sala 1');

    Livewire::test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->fillForm(['bloque' => 'D', 'piso' => '1', 'numero' => '2'])
        ->call('save');

    expect($sala->fresh()->ubicaciones()->pluck('bloque')->all())->toBe(['D', 'C']);
});

it('no anota una reubicación si solo cambia el nombre', function (): void {
    $this->actingAs($this->admin);
    $sala = Sala::factory()->create(['bloque' => 'A', 'piso' => '1', 'numero' => '3']);
    $sala->ubicaciones()->create(['bloque' => 'A', 'piso' => '1', 'numero' => '3', 'registrada_por' => $this->admin->id]);

    Livewire::test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->fillForm(['nombre' => 'Sala 3'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sala->fresh()->nombre)->toBe('Sala 3')
        ->and($sala->ubicaciones()->count())->toBe(1);
});

it('exige bloque, piso y número', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateSala::class)
        ->fillForm(['codigo' => 'X', 'nombre' => 'X', 'capacidad' => 5, 'bloque' => '', 'piso' => '', 'numero' => ''])
        ->call('create')
        ->assertHasFormErrors(['bloque' => 'required', 'piso' => 'required', 'numero' => 'required']);
});

it('no deja dos salas en el mismo bloque, piso y número', function (): void {
    $this->actingAs($this->admin);
    Sala::factory()->create(['bloque' => 'A', 'piso' => '1', 'numero' => '3']);

    Livewire::test(CreateSala::class)
        ->fillForm(['codigo' => 'OTRA', 'nombre' => 'Otra', 'capacidad' => 5, 'bloque' => 'A', 'piso' => '1', 'numero' => '3'])
        ->call('create')
        ->assertHasFormErrors(['numero' => 'unique']);

    expect(fn () => Sala::factory()->create(['bloque' => 'A', 'piso' => '1', 'numero' => '3']))
        ->toThrow(QueryException::class);
});

it('vincula la sala con los escenarios que se montan en ella', function (): void {
    $this->actingAs($this->admin);
    $parto = CasoClinico::factory()->create();
    $sala = Sala::factory()->create();

    Livewire::test(EditSala::class, ['record' => $sala->getRouteKey()])
        ->fillForm(['casosClinicos' => [$parto->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sala->fresh()->casosClinicos->pluck('id')->all())->toBe([$parto->id])
        ->and($parto->salas()->pluck('salas.id')->all())->toBe([$sala->id]);
});

// ---------------------------------------------------------------------
// Al preparar (RF36, D14)
// ---------------------------------------------------------------------

it('ofrece primero las salas vinculadas al escenario, sin esconder las demás', function (): void {
    $caso = CasoClinico::factory()->create();
    $otra = Sala::factory()->create(['nombre' => 'A primera por nombre']);
    $vinculada = Sala::factory()->create(['nombre' => 'Z última por nombre']);
    $vinculada->casosClinicos()->attach($caso);

    $libres = app(PreparacionService::class)->salasLibresPara(preparacionDe($caso));

    expect($libres->pluck('id')->all())->toBe([$vinculada->id, $otra->id])
        ->and((bool) $libres->first()->del_escenario)->toBeTrue()
        ->and((bool) $libres->last()->del_escenario)->toBeFalse();
});

it('no ofrece una sala ocupada en la misma franja, aunque esté vinculada', function (): void {
    $caso = CasoClinico::factory()->create();
    $sala = Sala::factory()->create();
    $sala->casosClinicos()->attach($caso);
    $servicio = app(PreparacionService::class);
    $servicio->asignarSala(preparacionDe($caso), $sala);

    expect($servicio->salasLibresPara(preparacionDe($caso, '08:00:00', '10:00:00'))->pluck('id'))
        ->not->toContain($sala->id);
});

// ---------------------------------------------------------------------
// Correo al docente (RF36)
// ---------------------------------------------------------------------

it('avisa al docente por correo cuando se le asigna la sala', function (): void {
    Mail::fake();
    $preparacion = preparacionDe(CasoClinico::factory()->create());
    $sala = Sala::factory()->create(['nombre' => 'Sala de partos', 'bloque' => 'B', 'piso' => '2', 'numero' => '4']);

    app(PreparacionService::class)->asignarSala($preparacion, $sala);

    Mail::assertSent(SalaAsignadaMail::class, 1);
    Mail::assertSent(SalaAsignadaMail::class, function (SalaAsignadaMail $correo) use ($preparacion): bool {
        $renderizado = $correo->render();

        return $correo->hasTo($preparacion->solicitud->docente->email)
            && ! $correo->esCambio
            && str_contains($renderizado, 'Sala de partos (Bloque B · piso 2 · sala 4)');
    });
});

it('avisa otra vez si la sala cambia, y no si se vuelve a elegir la misma', function (): void {
    Mail::fake();
    $preparacion = preparacionDe(CasoClinico::factory()->create());
    $servicio = app(PreparacionService::class);
    $primera = Sala::factory()->create();

    $servicio->asignarSala($preparacion, $primera);
    $servicio->asignarSala($preparacion->fresh(), $primera);
    $servicio->asignarSala($preparacion->fresh(), Sala::factory()->create());

    Mail::assertSent(SalaAsignadaMail::class, 2);
    Mail::assertSent(SalaAsignadaMail::class, static fn (SalaAsignadaMail $correo): bool => $correo->esCambio);
});
