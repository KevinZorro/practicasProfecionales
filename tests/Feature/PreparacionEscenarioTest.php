<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use App\Exceptions\SalaOcupada;
use App\Exceptions\TransicionDePreparacionInvalida;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use App\Services\PreparacionService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(PreparacionService::class);
    $this->administrativo = User::factory()->administrativo()->create();
});

/** Preparación de una práctica en una fecha y franja concretas. */
function preparacionEn(string $fecha, string $inicio, string $fin, ?Sala $sala = null): Preparacion
{
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada,
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
    ]);

    return Preparacion::factory()->create([
        'solicitud_id' => $solicitud->id,
        'sala_id' => $sala?->id,
        'estado' => EstadoPreparacion::Pendiente,
    ]);
}

it('copia a la preparación los items pedidos, sin alistar, al aprobar', function (): void {
    Mail::fake();
    $caso = CasoClinico::factory()->create();
    $maniqui = ItemInventario::factory()->simulador()->create();
    $gasas = ItemInventario::factory()->equipoBasico()->create();
    $caso->items()->attach($maniqui->id, ['cantidad' => 1]);
    $caso->items()->attach($gasas->id, ['cantidad' => 15]);

    $solicitudes = app(SolicitudService::class);
    $solicitud = $solicitudes->crear(User::factory()->docente()->create(), new DatosNuevaSolicitud(
        materiaId: Materia::factory()->create()->id,
        casoClinicoId: $caso->id,
        tipo: TipoSesion::Practica,
        fecha: now()->addWeek()->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        cantidadEstudiantes: 12,
    ));
    $solicitudes->marcarRevisada($solicitud, $this->administrativo);
    $solicitudes->aprobar($solicitud, User::factory()->coordinador()->create());

    $items = $solicitud->fresh()->preparacion->items;

    expect($items)->toHaveCount(2)
        ->and($items->firstWhere('id', $maniqui->id)->pivot->cantidad)->toBe(1)
        ->and($items->firstWhere('id', $gasas->id)->pivot->cantidad)->toBe(15)
        ->and($items->every(fn (ItemInventario $item): bool => (bool) $item->pivot->alistado === false))->toBeTrue();
});

it('asigna una sala libre', function (): void {
    $sala = Sala::factory()->create();
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00');

    $this->servicio->asignarSala($preparacion, $sala);

    expect($preparacion->fresh()->sala_id)->toBe($sala->id);
});

it('deja usar la misma sala el mismo día en franjas que no se pisan', function (): void {
    $sala = Sala::factory()->create();
    preparacionEn('2026-04-10', '07:00:00', '09:00:00', $sala);
    $siguiente = preparacionEn('2026-04-10', '09:00:00', '11:00:00');

    $this->servicio->asignarSala($siguiente, $sala);

    expect($siguiente->fresh()->sala_id)->toBe($sala->id);
});

it('deja usar la misma sala a la misma hora en días distintos', function (): void {
    $sala = Sala::factory()->create();
    preparacionEn('2026-04-10', '07:00:00', '09:00:00', $sala);
    $otroDia = preparacionEn('2026-04-11', '07:00:00', '09:00:00');

    $this->servicio->asignarSala($otroDia, $sala);

    expect($otroDia->fresh()->sala_id)->toBe($sala->id);
});

dataset('franjas que se solapan', [
    'idéntica' => ['07:00:00', '09:00:00'],
    'empieza durante la otra' => ['08:00:00', '10:00:00'],
    'termina durante la otra' => ['06:00:00', '08:00:00'],
    'contenida dentro de la otra' => ['07:30:00', '08:30:00'],
    'envuelve a la otra' => ['06:00:00', '10:00:00'],
]);

it('no asigna una sala ocupada en una franja que se solapa', function (string $inicio, string $fin): void {
    $sala = Sala::factory()->create(['nombre' => 'Sala de partos']);
    preparacionEn('2026-04-10', '07:00:00', '09:00:00', $sala);
    $nueva = preparacionEn('2026-04-10', $inicio, $fin);

    $this->servicio->asignarSala($nueva, $sala);
})->with('franjas que se solapan')->throws(SalaOcupada::class);

it('cuenta en el mensaje del conflicto con qué solicitud choca', function (): void {
    $sala = Sala::factory()->create(['nombre' => 'Sala de urgencias']);
    $ocupante = preparacionEn('2026-04-10', '07:00:00', '09:00:00', $sala);
    $nueva = preparacionEn('2026-04-10', '08:00:00', '10:00:00');

    expect(fn () => $this->servicio->asignarSala($nueva, $sala))
        ->toThrow(
            SalaOcupada::class,
            "La sala Sala de urgencias ya está ocupada el 10/04/2026 de 07:00:00 a 09:00:00 por la solicitud #{$ocupante->solicitud_id}.",
        );
});

it('deja reasignar la misma sala a la misma preparación', function (): void {
    // No debe chocar consigo misma.
    $sala = Sala::factory()->create();
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00', $sala);

    $this->servicio->asignarSala($preparacion, $sala);

    expect($preparacion->fresh()->sala_id)->toBe($sala->id);
});

it('avanza el montaje de pendiente a en preparación y a preparado', function (): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00', Sala::factory()->create());

    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);
    expect($preparacion->fresh()->estado)->toBe(EstadoPreparacion::EnPreparacion)
        ->and($preparacion->fresh()->preparado_at)->toBeNull();

    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $this->administrativo);
    $preparacion = $preparacion->fresh();

    expect($preparacion->estado)->toBe(EstadoPreparacion::Preparado)
        ->and($preparacion->preparado_por)->toBe($this->administrativo->id)
        ->and($preparacion->preparado_at)->not->toBeNull();
});

it('no da por preparado un escenario sin sala asignada', function (): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00');
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);

    expect(fn () => $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $this->administrativo))
        ->toThrow(TransicionDePreparacionInvalida::class, 'sin sala asignada');

    expect($preparacion->fresh()->estado)->toBe(EstadoPreparacion::EnPreparacion);
});

it('deja volver de preparado a en preparación', function (): void {
    // Se monta con prisa minutos antes de clase: marcar preparado por error
    // no puede dejar un estado sin salida.
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00', Sala::factory()->create());
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $this->administrativo);

    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);

    expect($preparacion->fresh()->estado)->toBe(EstadoPreparacion::EnPreparacion);
});

it('borra quién y cuándo terminó el montaje al volver atrás', function (): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00', Sala::factory()->create());
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $this->administrativo);
    expect($preparacion->fresh()->preparado_at)->not->toBeNull();

    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);

    expect($preparacion->fresh()->preparado_por)->toBeNull()
        ->and($preparacion->fresh()->preparado_at)->toBeNull();
});

it('vuelve a registrar el montaje al darlo por preparado de nuevo', function (): void {
    $otro = User::factory()->administrativo()->create();
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00', Sala::factory()->create());
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $this->administrativo);
    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $this->administrativo);

    $this->servicio->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $otro);

    expect($preparacion->fresh()->estado)->toBe(EstadoPreparacion::Preparado)
        ->and($preparacion->fresh()->preparado_por)->toBe($otro->id)
        ->and($preparacion->fresh()->preparado_at)->not->toBeNull();
});

dataset('transiciones de montaje inválidas', [
    'saltarse en preparación' => [EstadoPreparacion::Pendiente, EstadoPreparacion::Preparado],
    'volver a pendiente' => [EstadoPreparacion::EnPreparacion, EstadoPreparacion::Pendiente],
    'repetir pendiente' => [EstadoPreparacion::Pendiente, EstadoPreparacion::Pendiente],
]);

it('lanza excepción en las transiciones de montaje inválidas', function (
    EstadoPreparacion $desde,
    EstadoPreparacion $hasta,
): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00', Sala::factory()->create());
    $preparacion->update(['estado' => $desde]);

    $this->servicio->cambiarEstado($preparacion, $hasta, $this->administrativo);
})->with('transiciones de montaje inválidas')->throws(TransicionDePreparacionInvalida::class);

it('marca y desmarca items alistados', function (): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00');
    $item = ItemInventario::factory()->create();
    $preparacion->items()->attach($item->id, ['cantidad' => 2, 'alistado' => false]);

    $this->servicio->marcarItemAlistado($preparacion, $item);
    expect((bool) $preparacion->fresh()->items->first()->pivot->alistado)->toBeTrue();

    $this->servicio->desmarcarItemAlistado($preparacion, $item);
    expect((bool) $preparacion->fresh()->items->first()->pivot->alistado)->toBeFalse();
});

it('no toca los demás items al marcar uno', function (): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00');
    $marcado = ItemInventario::factory()->create();
    $intacto = ItemInventario::factory()->create();
    $preparacion->items()->attach($marcado->id, ['cantidad' => 1, 'alistado' => false]);
    $preparacion->items()->attach($intacto->id, ['cantidad' => 1, 'alistado' => false]);

    $this->servicio->marcarItemAlistado($preparacion, $marcado);

    $items = $preparacion->fresh()->items;
    expect((bool) $items->firstWhere('id', $marcado->id)->pivot->alistado)->toBeTrue()
        ->and((bool) $items->firstWhere('id', $intacto->id)->pivot->alistado)->toBeFalse();
});

it('registra y borra observaciones del montaje', function (): void {
    $preparacion = preparacionEn('2026-04-10', '07:00:00', '09:00:00');

    $this->servicio->registrarObservaciones($preparacion, 'Falta reponer gasas.');
    expect($preparacion->fresh()->observaciones)->toBe('Falta reponer gasas.');

    $this->servicio->registrarObservaciones($preparacion, null);
    expect($preparacion->fresh()->observaciones)->toBeNull();
});

it('lista la ocupación de una sala en un rango', function (): void {
    $sala = Sala::factory()->create();
    $otraSala = Sala::factory()->create();
    preparacionEn('2026-04-10', '07:00:00', '09:00:00', $sala);
    preparacionEn('2026-04-12', '10:00:00', '12:00:00', $sala);
    preparacionEn('2026-05-02', '07:00:00', '09:00:00', $sala);
    preparacionEn('2026-04-11', '07:00:00', '09:00:00', $otraSala);

    $ocupacion = $this->servicio->ocupacionDeSala($sala, '2026-04-01', '2026-04-30');

    expect($ocupacion)->toHaveCount(2);
});

it('filtra por scope las preparaciones pendientes de una fecha', function (): void {
    preparacionEn('2026-04-10', '07:00:00', '09:00:00');
    preparacionEn('2026-04-10', '10:00:00', '12:00:00');
    preparacionEn('2026-04-11', '07:00:00', '09:00:00');
    preparacionEn('2026-04-10', '14:00:00', '16:00:00', Sala::factory()->create())
        ->update(['estado' => EstadoPreparacion::Preparado]);

    expect(Preparacion::pendientesDeLaFecha('2026-04-10')->count())->toBe(2)
        ->and(Preparacion::deLaFecha('2026-04-10')->count())->toBe(3);
});
