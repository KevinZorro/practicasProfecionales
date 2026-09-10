<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(SolicitudService::class);
});

// preventLazyLoading es un interruptor estático: si queda encendido,
// contamina los tests que corran después de este archivo.
afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Solicitud aprobada en una fecha, con sala asignada o sin ella. */
function reservaAprobada(string $fecha, ?Sala $sala = null): Solicitud
{
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada,
        'fecha' => $fecha,
    ]);
    Preparacion::factory()->create([
        'solicitud_id' => $solicitud->id,
        'sala_id' => $sala?->id,
    ]);

    return $solicitud;
}

it('devuelve solo las reservas aprobadas del rango', function (): void {
    reservaAprobada('2026-03-10');
    reservaAprobada('2026-03-25');
    reservaAprobada('2026-05-01');
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente, 'fecha' => '2026-03-15']);
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Rechazada, 'fecha' => '2026-03-18']);

    $reservas = $this->servicio->paraCalendario('2026-03-01', '2026-03-31');

    expect($reservas)->toHaveCount(2);
});

it('incluye el tipo de sesión y la sala cuando ya fue asignada', function (): void {
    $sala = Sala::factory()->create(['nombre' => 'Sala de partos']);
    reservaAprobada('2026-03-10', $sala);
    reservaAprobada('2026-03-11');

    $reservas = $this->servicio->paraCalendario('2026-03-01', '2026-03-31');

    $conSala = $reservas->firstWhere('fecha.day', 10);
    $sinSala = $reservas->firstWhere('fecha.day', 11);

    expect($conSala->tipo)->not->toBeNull()
        ->and($conSala->preparacion->sala->nombre)->toBe('Sala de partos')
        ->and($sinSala->preparacion->sala)->toBeNull();
});

it('ordena las reservas por fecha y hora', function (): void {
    Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada, 'fecha' => '2026-03-10', 'hora_inicio' => '14:00:00',
    ]);
    Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada, 'fecha' => '2026-03-10', 'hora_inicio' => '07:00:00',
    ]);

    $reservas = $this->servicio->paraCalendario('2026-03-01', '2026-03-31');

    expect($reservas->first()->hora_inicio)->toBe('07:00:00');
});

it('no genera consultas N+1 al recorrer el calendario', function (): void {
    // Con lazy loading prohibido, cualquier relación sin precargar revienta.
    Model::preventLazyLoading();

    $sala = Sala::factory()->create();
    foreach (['2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08'] as $fecha) {
        reservaAprobada($fecha, $sala);
    }

    DB::enableQueryLog();
    $reservas = $this->servicio->paraCalendario('2026-03-01', '2026-03-31');

    foreach ($reservas as $reserva) {
        $reserva->docente->nombre;
        $reserva->materia->nombre;
        $reserva->casoClinico->nombre;
        $reserva->preparacion?->sala?->nombre;
    }
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($reservas)->toHaveCount(4)
        // 1 por las solicitudes + 1 por cada relación precargada.
        ->and($consultas)->toBeLessThanOrEqual(6);
});

it('mantiene constante el número de consultas al crecer el calendario', function (): void {
    Model::preventLazyLoading();
    $sala = Sala::factory()->create();

    $medir = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach ($this->servicio->paraCalendario('2026-03-01', '2026-03-31') as $reserva) {
            $reserva->docente->nombre;
            $reserva->materia->nombre;
            $reserva->casoClinico->nombre;
            $reserva->preparacion?->sala?->nombre;
        }
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    reservaAprobada('2026-03-05', $sala);
    reservaAprobada('2026-03-06', $sala);
    $conDos = $medir();

    foreach (['2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10'] as $fecha) {
        reservaAprobada($fecha, $sala);
    }
    $conSeis = $medir();

    // Si hubiera N+1, triplicar las reservas dispararía el conteo.
    expect($conSeis)->toBe($conDos);
});
