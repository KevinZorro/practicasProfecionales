<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Services\PreparacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(PreparacionService::class);
});

// preventLazyLoading es un interruptor estático: si queda encendido,
// contamina los tests que corran después de este archivo.
afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Montaje del día con sala, e items ya copiados de la solicitud. */
function montajeDelDia(string $fecha, string $inicio, ?Sala $sala = null, int $items = 2): Preparacion
{
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::Aprobada,
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => '23:00:00',
    ]);

    $preparacion = Preparacion::factory()->create([
        'solicitud_id' => $solicitud->id,
        'sala_id' => $sala?->id,
    ]);

    ItemInventario::factory()->count($items)->create()->each(
        fn (ItemInventario $item) => $preparacion->items()->attach($item->id, [
            'cantidad' => 1,
            'alistado' => false,
        ]),
    );

    return $preparacion;
}

it('devuelve solo los montajes de la fecha pedida', function (): void {
    montajeDelDia('2026-04-10', '07:00:00');
    montajeDelDia('2026-04-10', '10:00:00');
    montajeDelDia('2026-04-11', '07:00:00');

    expect($this->servicio->tableroDelDia('2026-04-10'))->toHaveCount(2);
});

it('trae todo lo que el administrativo necesita para montar', function (): void {
    $sala = Sala::factory()->create(['nombre' => 'Sala de partos']);
    montajeDelDia('2026-04-10', '07:00:00', $sala, items: 3);

    $montaje = $this->servicio->tableroDelDia('2026-04-10')->first();

    expect($montaje->sala->nombre)->toBe('Sala de partos')
        ->and($montaje->solicitud->docente)->not->toBeNull()
        ->and($montaje->solicitud->materia)->not->toBeNull()
        ->and($montaje->solicitud->casoClinico)->not->toBeNull()
        ->and($montaje->solicitud->cantidad_estudiantes)->toBeInt()
        ->and($montaje->items)->toHaveCount(3)
        ->and((bool) $montaje->items->first()->pivot->alistado)->toBeFalse();
});

it('ordena los montajes por la hora de inicio de la práctica', function (): void {
    montajeDelDia('2026-04-10', '14:00:00');
    montajeDelDia('2026-04-10', '07:00:00');
    montajeDelDia('2026-04-10', '10:00:00');

    $horas = $this->servicio->tableroDelDia('2026-04-10')
        ->map(fn (Preparacion $montaje): string => $montaje->solicitud->hora_inicio)
        ->all();

    expect($horas)->toBe(['07:00:00', '10:00:00', '14:00:00']);
});

it('muestra los montajes sin sala, que son los que faltan por asignar', function (): void {
    montajeDelDia('2026-04-10', '07:00:00');

    $montaje = $this->servicio->tableroDelDia('2026-04-10')->first();

    expect($montaje->sala)->toBeNull();
});

it('no genera consultas N+1 al recorrer el tablero', function (): void {
    Model::preventLazyLoading();
    $sala = Sala::factory()->create();
    foreach (['07:00:00', '09:00:00', '11:00:00', '14:00:00'] as $hora) {
        montajeDelDia('2026-04-10', $hora, $sala);
    }

    DB::enableQueryLog();
    $tablero = $this->servicio->tableroDelDia('2026-04-10');
    recorrerTablero($tablero);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($tablero)->toHaveCount(4)
        // 1 por las preparaciones + 1 por cada relación precargada.
        ->and($consultas)->toBeLessThanOrEqual(7);
});

it('mantiene constante el número de consultas al crecer el tablero', function (): void {
    Model::preventLazyLoading();
    $sala = Sala::factory()->create();

    $medir = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        recorrerTablero($this->servicio->tableroDelDia('2026-04-10'));
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    montajeDelDia('2026-04-10', '07:00:00', $sala);
    montajeDelDia('2026-04-10', '08:00:00', $sala);
    $conDos = $medir();

    foreach (['09:00:00', '10:00:00', '11:00:00', '12:00:00'] as $hora) {
        montajeDelDia('2026-04-10', $hora, $sala);
    }

    // Si hubiera N+1, triplicar los montajes dispararía el conteo.
    expect($medir())->toBe($conDos);
});

/** Toca todo lo que la vista diaria mostraría de cada montaje. */
function recorrerTablero(iterable $tablero): void
{
    foreach ($tablero as $montaje) {
        $montaje->solicitud->docente->nombre;
        $montaje->solicitud->materia->nombre;
        $montaje->solicitud->casoClinico->nombre;
        $montaje->solicitud->cantidad_estudiantes;
        $montaje->sala?->nombre;
        foreach ($montaje->items as $item) {
            $item->nombre;
            $item->pivot->alistado;
        }
    }
}
