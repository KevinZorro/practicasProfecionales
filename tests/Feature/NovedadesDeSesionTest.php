<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\NovedadInvalida;
use App\Livewire\Solicitud\MisSolicitudes;
use App\Livewire\Solicitud\NovedadesDeLaSesion;
use App\Mail\SesionReprogramadaMail;
use App\Mail\SustitucionDocenteMail;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosReprogramacion;
use App\Services\EvaluacionService;
use App\Services\FiltroReporte;
use App\Services\NovedadesDeSesionService;
use App\Services\ReporteService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Reprogramar una sesión aprobada (RF61), sustituir al docente (RF73) y
 * atribuir las horas a quien dictó (RF54).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Mail::fake();
    $this->servicio = app(NovedadesDeSesionService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->titular = User::factory()->docente()->create(['nombre' => 'Titular Original']);
    $this->reemplazo = User::factory()->docente()->create(['nombre' => 'Docente Reemplazo']);
});

function sesionAprobadaDe(User $docente, array $atributos = []): Solicitud
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'fecha' => now()->addDays(3)->format('Y-m-d'),
        'hora_inicio' => '07:00:00',
        'hora_fin' => '09:00:00',
        'cantidad_estudiantes' => 6,
        ...$atributos,
    ]);
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => Sala::factory()->create()->id]);

    return $solicitud;
}

function reprogramacion(Solicitud $solicitud, array $cambios = []): DatosReprogramacion
{
    return new DatosReprogramacion(...[
        'fecha' => $solicitud->fecha->format('Y-m-d'),
        'horaInicio' => '07:00',
        'horaFin' => '09:00',
        'casoClinicoId' => $solicitud->caso_clinico_id,
        'motivo' => 'Mantenimiento del aire acondicionado',
        'constanciaComunicacion' => 'Llamada al docente el 9/10, aceptó el cambio',
        ...$cambios,
    ]);
}

// ---------------------------------------------------------------------
// Reprogramar (RF61)
// ---------------------------------------------------------------------

it('reprograma la fecha, deja el rastro, libera la sala y avisa al docente', function (): void {
    $sesion = sesionAprobadaDe($this->titular);
    $nueva = now()->addDays(5)->format('Y-m-d');

    $cambio = $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['fecha' => $nueva, 'horaInicio' => '10:00', 'horaFin' => '12:00']), $this->administrativo);
    $sesion->refresh();

    expect($sesion->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($sesion->fecha->format('Y-m-d'))->toBe($nueva)
        ->and(substr($sesion->hora_inicio, 0, 5))->toBe('10:00')
        ->and($sesion->preparacion->sala_id)->toBeNull()
        ->and($cambio->fecha_anterior->format('Y-m-d'))->toBe(now()->addDays(3)->format('Y-m-d'))
        ->and($cambio->reprogramada_por)->toBe($this->administrativo->id)
        ->and($cambio->constancia_comunicacion)->toBe('Llamada al docente el 9/10, aceptó el cambio');

    Mail::assertQueued(SesionReprogramadaMail::class, 1);
    Mail::assertQueued(SesionReprogramadaMail::class, fn (SesionReprogramadaMail $correo): bool => $correo->hasTo($this->titular->email));
});

it('al cambiar el escenario borra el formato intramural y revalida la capacidad', function (): void {
    $sesion = sesionAprobadaDe($this->titular, ['formato_intramural_at' => now(), 'formato_intramural_por' => $this->titular->id]);
    $sesion->items()->attach(ItemInventario::factory()->create()->id, ['cantidad' => 2]);
    $otro = CasoClinico::factory()->create();

    $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['casoClinicoId' => $otro->id]), $this->administrativo);
    $sesion->refresh();

    expect($sesion->caso_clinico_id)->toBe($otro->id)
        ->and($sesion->tieneFormatoIntramural())->toBeFalse()
        ->and($sesion->items()->count())->toBe(0)
        ->and($sesion->preparacion->sala_id)->not->toBeNull();

    $pequeno = CasoClinico::factory()->conCapacidad(3)->create();

    expect(fn () => $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['casoClinicoId' => $pequeno->id]), $this->administrativo))
        ->toThrow(CapacidadDeEstudiantesExcedida::class);
});

it('exige motivo, constancia y algún cambio', function (): void {
    $sesion = sesionAprobadaDe($this->titular);

    expect(fn () => $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['fecha' => now()->addDays(4)->format('Y-m-d'), 'motivo' => ' ']), $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'motivo')
        ->and(fn () => $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['fecha' => now()->addDays(4)->format('Y-m-d'), 'constanciaComunicacion' => '']), $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'comunicó')
        ->and(fn () => $this->servicio->reprogramar($sesion, reprogramacion($sesion), $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'nada que reprogramar');

    Mail::assertNothingQueued();
});

it('no reprograma una sesión pasada ni una que no está aprobada', function (): void {
    $pasada = sesionAprobadaDe($this->titular, ['fecha' => now()->subDay()->format('Y-m-d')]);
    $pendiente = Solicitud::factory()->create(['fecha' => now()->addDays(3)->format('Y-m-d')]);

    expect(fn () => $this->servicio->reprogramar($pasada, reprogramacion($pasada, ['horaInicio' => '10:00', 'horaFin' => '11:00']), $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'no ha pasado')
        ->and(fn () => $this->servicio->reprogramar($pendiente, reprogramacion($pendiente, ['horaInicio' => '10:00', 'horaFin' => '11:00']), $this->administrativo))
        ->toThrow(AuthorizationException::class);
});

it('no deja reprogramar a un docente ni al ADMIN', function (): void {
    $sesion = sesionAprobadaDe($this->titular);

    expect(fn () => $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['horaInicio' => '10:00', 'horaFin' => '11:00']), $this->titular))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $this->servicio->reprogramar($sesion, reprogramacion($sesion, ['horaInicio' => '10:00', 'horaFin' => '11:00']), User::factory()->admin()->create()))
        ->toThrow(AuthorizationException::class);
});

it('reprograma desde la pantalla y enseña el historial', function (): void {
    $sesion = sesionAprobadaDe($this->titular);

    Livewire::actingAs($this->administrativo)
        ->test(NovedadesDeLaSesion::class, ['solicitud' => $sesion])
        ->assertSet('horaInicio', '07:00')
        ->set('horaInicio', '13:00')
        ->set('horaFin', '15:00')
        ->call('reprogramar')
        ->assertHasErrors(['motivo' => 'required', 'constancia' => 'required'])
        ->set('motivo', 'Cruce con otra práctica')
        ->set('constancia', 'Correo del 9/10')
        ->call('reprogramar')
        ->assertHasNoErrors()
        ->assertSee('Reprogramada el')
        ->assertSee('Correo del 9/10');

    expect(substr($sesion->fresh()->hora_inicio, 0, 5))->toBe('13:00');
});

// ---------------------------------------------------------------------
// Sustituir al docente (RF73)
// ---------------------------------------------------------------------

it('sustituye al docente, deja el rastro y avisa al reemplazo', function (): void {
    $sesion = sesionAprobadaDe($this->titular);

    $sustitucion = $this->servicio->sustituir($sesion, $this->reemplazo, 'Incapacidad médica', $this->administrativo);

    expect($sesion->fresh()->docente_que_dicta_id)->toBe($this->reemplazo->id)
        ->and($sesion->fresh()->idDelDocenteQueDicta())->toBe($this->reemplazo->id)
        ->and($sustitucion->docente_anterior_id)->toBe($this->titular->id)
        ->and($sustitucion->registrada_por)->toBe($this->administrativo->id);

    Mail::assertQueued(SustitucionDocenteMail::class, 1);
    Mail::assertQueued(SustitucionDocenteMail::class, fn (SustitucionDocenteMail $correo): bool => $correo->hasTo($this->reemplazo->email));
});

it('devuelve la sesión al titular al elegirlo, y no acepta al mismo que ya dicta', function (): void {
    $sesion = sesionAprobadaDe($this->titular);
    $this->servicio->sustituir($sesion, $this->reemplazo, 'Incapacidad', $this->administrativo);

    expect(fn () => $this->servicio->sustituir($sesion->fresh(), $this->reemplazo, 'Otra', $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'ya es quien dicta');

    $this->servicio->sustituir($sesion->fresh(), $this->titular, 'Volvió', $this->administrativo);

    expect($sesion->fresh()->docente_que_dicta_id)->toBeNull()
        ->and($sesion->sustituciones()->count())->toBe(2);
});

it('no sustituye por quien no es docente activo', function (): void {
    $sesion = sesionAprobadaDe($this->titular);

    expect(fn () => $this->servicio->sustituir($sesion, User::factory()->estudiante()->create(), 'X', $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'docente con cuenta activa')
        ->and(fn () => $this->servicio->sustituir($sesion, User::factory()->docente()->inactivo()->create(), 'X', $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'docente con cuenta activa');
});

it('deja al reemplazo ver la sesión, registrar su evaluación y verla en su historial', function (): void {
    $sesion = sesionAprobadaDe($this->titular, ['tipo' => TipoSesion::Evaluacion]);
    $this->servicio->sustituir($sesion, $this->reemplazo, 'Incapacidad', $this->administrativo);

    expect($this->reemplazo->can('view', $sesion->fresh()))->toBeTrue()
        ->and($this->reemplazo->can('gestionarParticipantes', $sesion->fresh()))->toBeTrue()
        ->and(app(EvaluacionService::class)->sesionesPorEvaluar($this->reemplazo)->pluck('id')->all())->toBe([$sesion->id])
        ->and(app(EvaluacionService::class)->sesionesPorEvaluar($this->titular))->toBeEmpty();

    Livewire::actingAs($this->reemplazo)
        ->test(MisSolicitudes::class)
        ->assertSee('La dictas en reemplazo de Titular Original');

    Livewire::actingAs($this->titular)
        ->test(MisSolicitudes::class)
        ->assertSee('La dicta Docente Reemplazo en tu reemplazo');
});

it('sustituye desde la pantalla', function (): void {
    $sesion = sesionAprobadaDe($this->titular);

    Livewire::actingAs($this->administrativo)
        ->test(NovedadesDeLaSesion::class, ['solicitud' => $sesion])
        ->set('reemplazoId', $this->reemplazo->id)
        ->set('motivoSustitucion', 'Calamidad doméstica')
        ->call('sustituir')
        ->assertHasNoErrors()
        ->assertSee('Dicta: <span class="font-medium">Docente Reemplazo</span>', escape: false)
        ->assertSee('Calamidad doméstica');
});

// ---------------------------------------------------------------------
// Horas del reporte de uso (RF54)
// ---------------------------------------------------------------------

it('atribuye las horas a quien dictó e indica las sesiones por sustitución', function (): void {
    $caso = CasoClinico::factory()->create();
    $propia = sesionAprobadaDe($this->titular, ['caso_clinico_id' => $caso->id]);
    $cedida = sesionAprobadaDe($this->titular, ['caso_clinico_id' => $caso->id, 'materia_id' => $propia->materia_id, 'fecha' => now()->addDays(4)->format('Y-m-d')]);
    $this->servicio->sustituir($cedida, $this->reemplazo, 'Incapacidad', $this->administrativo);

    $filas = app(ReporteService::class)->usoDeEscenarios(new FiltroReporte)->get()
        ->keyBy(static fn ($fila) => $fila->getAttribute('docente_nombre'));

    expect((int) $filas['Titular Original']->getAttribute('sesiones'))->toBe(1)
        ->and((int) $filas['Titular Original']->getAttribute('sesiones_por_sustitucion'))->toBe(0)
        ->and((int) $filas['Docente Reemplazo']->getAttribute('sesiones'))->toBe(1)
        ->and((int) $filas['Docente Reemplazo']->getAttribute('sesiones_por_sustitucion'))->toBe(1)
        ->and(app(ReporteService::class)->usoDeEscenarios(new FiltroReporte(docenteId: $this->reemplazo->id))->get())->toHaveCount(1);
});
