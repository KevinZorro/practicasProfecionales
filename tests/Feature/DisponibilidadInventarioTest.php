<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\EstadoSolicitud;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Services\InventarioService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(InventarioService::class);
});

/** Compromete unidades de un ítem en una solicitud con el estado dado. */
function comprometer(
    ItemInventario $item,
    int $cantidad,
    string $fecha,
    string $inicio,
    string $fin,
    EstadoSolicitud $estado = EstadoSolicitud::Aprobada,
): Solicitud {
    $solicitud = Solicitud::factory()->create([
        'estado' => $estado,
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
    ]);
    $solicitud->items()->attach($item->id, ['cantidad' => $cantidad]);

    return $solicitud;
}

it('devuelve todas las unidades cuando no hay nada comprometido', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(6);
});

it('descuenta lo comprometido en una solicitud aprobada que se solapa', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);
    comprometer($item, 2, '2026-05-10', '07:00:00', '09:00:00');

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(4);
});

it('suma lo comprometido por varias solicitudes solapadas', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 10]);
    comprometer($item, 2, '2026-05-10', '07:00:00', '09:00:00');
    comprometer($item, 3, '2026-05-10', '08:00:00', '10:00:00');

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '08:00:00', '09:00:00'))->toBe(5);
});

it('no descuenta lo comprometido en franjas que no se pisan', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);
    comprometer($item, 4, '2026-05-10', '07:00:00', '09:00:00');

    // Franja contigua: empieza justo cuando la otra termina.
    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '09:00:00', '11:00:00'))->toBe(6);
});

it('no descuenta lo comprometido en otro día', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);
    comprometer($item, 4, '2026-05-10', '07:00:00', '09:00:00');

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-11', '07:00:00', '09:00:00'))->toBe(6);
});

it('no descuenta lo pedido en solicitudes que aún no se aprobaron ni en las rechazadas', function (EstadoSolicitud $estado): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6]);
    comprometer($item, 4, '2026-05-10', '07:00:00', '09:00:00', $estado);

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(6);
})->with([
    'pendiente' => EstadoSolicitud::Pendiente,
    'revisada' => EstadoSolicitud::Revisada,
    'rechazada' => EstadoSolicitud::Rechazada,
]);

it('no cuenta como disponible un ítem en mantenimiento o dado de baja', function (EstadoItemInventario $estado): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6, 'estado' => $estado]);

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(0);
})->with([
    'mantenimiento' => EstadoItemInventario::Mantenimiento,
    'baja' => EstadoItemInventario::Baja,
]);

it('no cuenta como disponible un ítem inactivo', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 6, 'activo' => false]);

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(0);
});

it('nunca devuelve disponibilidad negativa', function (): void {
    // Si el inventario mermó después de aprobar, no se informan unidades
    // negativas: se informa cero.
    $item = ItemInventario::factory()->create(['cantidad_total' => 2]);
    comprometer($item, 5, '2026-05-10', '07:00:00', '09:00:00');

    expect($this->servicio->disponibilidadEnFranja($item, '2026-05-10', '07:00:00', '09:00:00'))->toBe(0);
});

it('no mezcla lo comprometido de un ítem con el de otro', function (): void {
    $maniqui = ItemInventario::factory()->create(['cantidad_total' => 4]);
    $monitor = ItemInventario::factory()->create(['cantidad_total' => 4]);
    comprometer($maniqui, 3, '2026-05-10', '07:00:00', '09:00:00');

    expect($this->servicio->disponibilidadEnFranja($maniqui, '2026-05-10', '07:00:00', '09:00:00'))->toBe(1)
        ->and($this->servicio->disponibilidadEnFranja($monitor, '2026-05-10', '07:00:00', '09:00:00'))->toBe(4);
});

it('calcula la disponibilidad de varios ítems a la vez', function (): void {
    $maniqui = ItemInventario::factory()->create(['cantidad_total' => 4]);
    $monitor = ItemInventario::factory()->create(['cantidad_total' => 6]);
    $enMantenimiento = ItemInventario::factory()->enMantenimiento()->create(['cantidad_total' => 9]);
    comprometer($maniqui, 3, '2026-05-10', '07:00:00', '09:00:00');
    comprometer($monitor, 1, '2026-05-10', '08:00:00', '10:00:00');

    $items = ItemInventario::whereIn('id', [$maniqui->id, $monitor->id, $enMantenimiento->id])->get();
    $disponibilidad = $this->servicio->disponibilidadDeVarios($items, '2026-05-10', '07:00:00', '09:00:00');

    expect($disponibilidad[$maniqui->id])->toBe(1)
        ->and($disponibilidad[$monitor->id])->toBe(5)
        ->and($disponibilidad[$enMantenimiento->id])->toBe(0);
});

it('consulta la disponibilidad de varios ítems sin N+1', function (): void {
    $items = ItemInventario::factory()->count(6)->create(['cantidad_total' => 5]);
    foreach ($items as $item) {
        comprometer($item, 1, '2026-05-10', '07:00:00', '09:00:00');
    }

    DB::enableQueryLog();
    $disponibilidad = $this->servicio->disponibilidadDeVarios($items, '2026-05-10', '07:00:00', '09:00:00');
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Una sola consulta agrupada, sin importar cuántos ítems se pidan.
    expect($consultas)->toBe(1)
        ->and($disponibilidad)->toHaveCount(6)
        ->and(array_values($disponibilidad))->each->toBe(4);
});

it('mantiene una sola consulta al crecer la lista de ítems', function (): void {
    $medir = function (Collection $items): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->servicio->disponibilidadDeVarios($items, '2026-05-10', '07:00:00', '09:00:00');
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $dos = ItemInventario::factory()->count(2)->create();
    $conDos = $medir($dos);

    $muchos = ItemInventario::factory()->count(12)->create();
    expect($medir($muchos))->toBe($conDos);
});

it('devuelve una lista vacía sin consultar nada si no hay ítems', function (): void {
    DB::enableQueryLog();
    $disponibilidad = $this->servicio->disponibilidadDeVarios(new Collection, '2026-05-10', '07:00:00', '09:00:00');
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($disponibilidad)->toBe([])->and($consultas)->toBe(0);
});
