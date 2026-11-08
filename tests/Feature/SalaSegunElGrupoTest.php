<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Exceptions\SalaInsuficiente;
use App\Livewire\Preparacion\TableroDiario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\PreparacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/*
 * El grupo tiene que caber en las salas que se le asignan: la capacidad de
 * la sala (cuánta gente cabe en el espacio físico) tiene que alcanzar para
 * los estudiantes de la sesión (P11, decisión del 2026-11-07). Desde que se
 * pueden unir salas abriendo la cortina (P16), lo que cuenta es la capacidad
 * sumada, y el tablero ofrece también las que solas no alcanzan: ver
 * SalasUnidasTest.
 */

beforeEach(function (): void {
    Event::fake();
    $this->seed(RolSeeder::class);
    $this->servicio = app(PreparacionService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->fecha = now()->addDays(2)->toDateString();
});

/** El montaje de una sesión aprobada para un grupo de este tamaño. */
function montajeDeUnGrupoDe(int $estudiantes): Preparacion
{
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada,
        'fecha' => test()->fecha,
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'cantidad_estudiantes' => $estudiantes,
    ]);

    return Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'estado' => EstadoPreparacion::Pendiente]);
}

it('no asigna una sala en la que no cabe el grupo aunque se pida a mano', function (): void {
    $chica = Sala::factory()->create(['capacidad' => 6]);
    $montaje = montajeDeUnGrupoDe(12);

    expect(fn () => $this->servicio->asignarSalas($montaje, [$chica->id]))->toThrow(SalaInsuficiente::class)
        ->and($montaje->fresh()->salas)->toBeEmpty();
});

it('dice en el tablero por qué no asigna una sala en la que no cabe el grupo', function (): void {
    $chica = Sala::factory()->create(['nombre' => 'Sala chica', 'capacidad' => 6]);
    $montaje = montajeDeUnGrupoDe(12);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class, ['fecha' => $this->fecha])
        ->call('abrir', $montaje->id)
        ->set('salasElegidas', [$chica->id])
        ->call('asignarSalas', $montaje->id)
        ->assertSee('caben 6 personas');

    expect($montaje->fresh()->salas)->toBeEmpty();
});
