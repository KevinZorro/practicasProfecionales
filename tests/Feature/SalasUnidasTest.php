<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\Reporte;
use App\Events\SalaAsignada;
use App\Exceptions\AsignacionDeSalasInvalida;
use App\Exceptions\SalaInsuficiente;
use App\Exceptions\SalaOcupada;
use App\Livewire\Preparacion\TableroDiario;
use App\Mail\SalaAsignadaMail;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosReprogramacion;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use App\Services\NovedadesDeSesionService;
use App\Services\PreparacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Las salas se separan con cortinas de hospital: al abrir la cortina, dos o
 * tres salas son un solo salón con la capacidad sumada (decisión del
 * cliente del 2026-11-08). El administrativo le asigna a cada sesión de una
 * a tres salas, y lo que cuenta frente al grupo es la capacidad de todas
 * juntas.
 */

beforeEach(function (): void {
    Event::fake([SalaAsignada::class]);
    Mail::fake();
    $this->seed(RolSeeder::class);
    $this->servicio = app(PreparacionService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->fecha = now()->addDays(2)->toDateString();
});

/** El montaje de una sesión aprobada para un grupo de este tamaño. */
function montajeParaUnGrupoDe(int $estudiantes, string $inicio = '08:00', string $fin = '10:00'): Preparacion
{
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada,
        'fecha' => test()->fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
        'cantidad_estudiantes' => $estudiantes,
    ]);

    return Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'estado' => EstadoPreparacion::Pendiente]);
}

it('une dos salas para un grupo que no cabe en una sola', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 12]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 10]);
    $montaje = montajeParaUnGrupoDe(20);

    $this->servicio->asignarSalas($montaje, [$una->id, $otra->id]);

    expect($montaje->fresh()->salas->pluck('id')->all())->toEqualCanonicalizing([$una->id, $otra->id])
        ->and($montaje->fresh()->nombresDeLasSalas())->toBe('SIM-01 + SIM-02');
    Event::assertDispatched(SalaAsignada::class, 1);
});

it('cuenta la capacidad de todas las salas juntas', function (): void {
    [$a, $b, $c] = Sala::factory()->count(3)->create(['capacidad' => 10])->all();
    $montaje = montajeParaUnGrupoDe(25);

    expect(fn () => $this->servicio->asignarSalas($montaje, [$a->id, $b->id]))->toThrow(SalaInsuficiente::class, 'caben 20 personas');

    $this->servicio->asignarSalas($montaje, [$a->id, $b->id, $c->id]);

    expect($montaje->fresh()->salas)->toHaveCount(3);
});

it('no une más de tres salas', function (): void {
    $salas = Sala::factory()->count(4)->create(['capacidad' => 10]);
    $montaje = montajeParaUnGrupoDe(12);

    expect(fn () => $this->servicio->asignarSalas($montaje, $salas->pluck('id')->all()))->toThrow(AsignacionDeSalasInvalida::class)
        ->and($montaje->fresh()->salas)->toBeEmpty();
});

it('no une una sala ocupada en esa franja, y entonces no asigna ninguna', function (): void {
    $libre = Sala::factory()->create(['capacidad' => 10]);
    $ocupada = Sala::factory()->create(['nombre' => 'SIM-09', 'capacidad' => 10]);
    $this->servicio->asignarSalas(montajeParaUnGrupoDe(8, '09:00', '11:00'), [$ocupada->id]);
    $montaje = montajeParaUnGrupoDe(15);

    expect(fn () => $this->servicio->asignarSalas($montaje, [$libre->id, $ocupada->id]))->toThrow(SalaOcupada::class, 'SIM-09')
        ->and($montaje->fresh()->salas)->toBeEmpty();
});

it('ofrece todas las salas libres, aunque solas no alcancen, para poder unirlas', function (): void {
    $chica = Sala::factory()->create(['capacidad' => 6]);
    $grande = Sala::factory()->create(['capacidad' => 30]);

    expect($this->servicio->salasLibresPara(montajeParaUnGrupoDe(12))->pluck('id')->all())->toEqualCanonicalizing([$chica->id, $grande->id]);
});

it('suelta todas las salas al reprogramar la sesión a otra franja', function (): void {
    [$a, $b] = Sala::factory()->count(2)->create(['capacidad' => 10])->all();
    $montaje = montajeParaUnGrupoDe(15);
    $this->servicio->asignarSalas($montaje, [$a->id, $b->id]);
    $sesion = $montaje->solicitud;

    app(NovedadesDeSesionService::class)->reprogramar($sesion, new DatosReprogramacion(
        fecha: $this->fecha,
        horaInicio: '14:00',
        horaFin: '16:00',
        casoClinicoId: $sesion->caso_clinico_id,
        motivo: 'Se cruzó con un parcial.',
        constanciaComunicacion: 'Correo del 3 de noviembre.',
    ), $this->administrativo);

    expect($montaje->fresh()->salas)->toBeEmpty();
});

it('deja elegir varias salas en el tablero', function (): void {
    [$a, $b] = Sala::factory()->count(2)->create(['capacidad' => 10])->all();
    $montaje = montajeParaUnGrupoDe(15);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class, ['fecha' => $this->fecha])
        ->call('abrir', $montaje->id)
        ->set('salasElegidas', [$a->id, $b->id])
        ->call('asignarSalas', $montaje->id)
        ->assertHasNoErrors();

    expect($montaje->fresh()->salas)->toHaveCount(2);
});

it('le dice al docente todas las salas de su sesión', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 10]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 10]);
    $montaje = montajeParaUnGrupoDe(15);
    $this->servicio->asignarSalas($montaje, [$una->id, $otra->id]);

    expect((new SalaAsignadaMail($montaje->fresh(), esCambio: false))->render())->toContain('SIM-01')->toContain('SIM-02');
});

it('cuenta una sola vez en el reporte de uso la sesión que usa dos salas', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 10]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 10]);
    $this->servicio->asignarSalas(montajeParaUnGrupoDe(15), [$una->id, $otra->id]);
    $generador = app(GeneradorDeReportes::class);

    $todas = $generador->filas(Reporte::UsoDeEscenarios, new FiltroReporte);
    $deUna = $generador->filas(Reporte::UsoDeEscenarios, new FiltroReporte(salaId: $otra->id));

    expect($todas)->toHaveCount(1)
        ->and($todas->first()[4])->toBe('SIM-01 + SIM-02')
        ->and($todas->first()[6])->toBe(1)
        ->and($deUna)->toHaveCount(1);
});

it('enseña en el calendario las salas unidas', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 10]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 10]);
    $montaje = montajeParaUnGrupoDe(15);
    $this->servicio->asignarSalas($montaje, [$una->id, $otra->id]);

    $eventos = collect($this->actingAs($this->administrativo)
        ->getJson(route('panel.calendario.eventos', ['desde' => $this->fecha, 'hasta' => $this->fecha]))
        ->json())->keyBy('id');

    expect($eventos[(string) $montaje->solicitud_id]['extendedProps']['sala'])->toBe('SIM-01 + SIM-02');
});

// ---------------------------------------------------------------------
// La pantalla: que se vea que quedaron unidas
// ---------------------------------------------------------------------

/** El HTML del tablero con el montaje abierto y, si se dan, las salas ya elegidas y asignadas. */
function tableroConSalas(Preparacion $montaje, array $salaIds = []): Testable
{
    $tablero = Livewire::actingAs(test()->administrativo)
        ->test(TableroDiario::class, ['fecha' => test()->fecha])
        ->call('abrir', $montaje->id);

    if ($salaIds === []) {
        return $tablero;
    }

    return $tablero->set('salasElegidas', $salaIds)->call('asignarSalas', $montaje->id);
}

it('confirma la unión nombrando las salas, no con un aviso genérico', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 10]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 8]);
    $montaje = montajeParaUnGrupoDe(15);

    tableroConSalas($montaje, [$una->id, $otra->id])
        ->assertDispatched('aviso-de-la-accion', mensaje: 'Salas unidas abriendo la cortina: SIM-01 y SIM-02.')
        ->assertDispatched('salas-asignadas', preparacion: $montaje->id, salas: [$una->id, $otra->id]);
});

it('nombra la sala cuando es una sola', function (): void {
    $sala = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 20]);
    $montaje = montajeParaUnGrupoDe(15);

    tableroConSalas($montaje, [$sala->id])
        ->assertDispatched('aviso-de-la-accion', mensaje: 'Sala asignada: SIM-01.')
        ->assertDontSee('Unidas abriendo la cortina');
});

it('enseña en la tarjeta cada sala unida en su línea, con la capacidad sumada', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 10]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 8]);
    $montaje = montajeParaUnGrupoDe(15);

    $html = tableroConSalas($montaje, [$una->id, $otra->id])->html();

    // Cada una con su ubicación; entre una y otra, Livewire deja sus marcas de bloque.
    expect($html)->toMatch('/<span class="block font-semibold">SIM-01[^<]*<\/span>.{0,200}?<span class="block font-semibold">SIM-02[^<]*<\/span>/s')
        ->toContain('Unidas abriendo la cortina · caben 18')
        ->not->toContain('SIM-01 + SIM-02');
});

it('tiñe la tarjeta una sola vez, en la respuesta que asigna las salas', function (): void {
    $una = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 10]);
    $otra = Sala::factory()->create(['nombre' => 'SIM-02', 'capacidad' => 8]);
    $montaje = montajeParaUnGrupoDe(15);
    $recien = '/class="[^"]*\brecien\b[^"]*"/';

    $tablero = tableroConSalas($montaje, [$una->id, $otra->id]);
    expect($tablero->html())->toMatch($recien);

    $tablero->set('observaciones', 'Llega el ingeniero a las 7.');
    expect($tablero->html())->not->toMatch($recien);
});

it('no tiñe nada si la sala no se pudo asignar', function (): void {
    $chica = Sala::factory()->create(['nombre' => 'SIM-01', 'capacidad' => 6]);
    $montaje = montajeParaUnGrupoDe(15);

    tableroConSalas($montaje, [$chica->id])
        ->assertNotDispatched('salas-asignadas')
        ->assertNotDispatched('aviso-de-la-accion');

    expect($montaje->fresh()->salas)->toBeEmpty();
});

it('le da al salón de la pantalla el grupo, el tope de salas y las que ya tiene la sesión', function (): void {
    [$una, $otra] = Sala::factory()->count(2)->create(['capacidad' => 10])->all();
    $montaje = montajeParaUnGrupoDe(15);
    $this->servicio->asignarSalas($montaje, [$una->id, $otra->id]);

    $html = tableroConSalas($montaje)->html();

    expect($html)->toContain('salonDeLaSesion(')
        ->toContain('grupo: 15')
        ->toContain('maximo: '.PreparacionService::MAXIMO_DE_SALAS)
        ->toContain('x-on:salas-asignadas.window')
        ->toContain('aria-live="polite"')
        ->toContain('Así queda');
});

it('carga el salón solo en la pantalla de preparación', function (): void {
    // Sin el script, el salón queda vacío sin dar ningún error en el servidor.
    expect(file_get_contents(resource_path('views/panel/preparaciones/index.blade.php')))->toContain("@vite('resources/js/salon.js')")
        ->and(file_get_contents(base_path('vite.config.js')))->toContain("'resources/js/salon.js'")
        ->and(file_get_contents(resource_path('views/layouts/panel.blade.php')))->not->toContain('salon.js');
});
