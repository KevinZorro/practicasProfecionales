<?php

declare(strict_types=1);

use App\Enums\EstadoItemInventario;
use App\Enums\EstadoSolicitud;
use App\Livewire\Solicitud\BandejaRevision;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\InventarioService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(InventarioService::class);
    $this->administrativo = User::factory()->administrativo()->create();
});

/** Una práctica aprobada futura que compromete $cantidad unidades del ítem. */
function practicaAprobadaCon(ItemInventario $item, int $cantidad, string $fecha = '+10 days'): Solicitud
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'fecha' => now()->modify($fecha)->format('Y-m-d'),
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);
    $solicitud->items()->attach($item->id, ['cantidad' => $cantidad]);

    return $solicitud;
}

it('no avisa de nada mientras el inventario alcance', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    practicaAprobadaCon($item, 6);

    expect($this->servicio->solicitudesAprobadasSinCobertura())->toBe([]);
});

it('detecta la práctica aprobada que se quedó sin unidades suficientes', function (): void {
    // Se aprobó con ocho y después fallaron cuatro.
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    $solicitud = practicaAprobadaCon($item, 6);

    $this->servicio->cambiarEstado(
        $this->administrativo,
        $item,
        EstadoItemInventario::Operativo,
        EstadoItemInventario::EnRevision,
        4,
        'Cuatro dejaron de funcionar.',
    );

    expect($this->servicio->solicitudesAprobadasSinCobertura())->toBe([$solicitud->id]);
});

it('no avisa si lo que falla sigue dejando unidades de sobra', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    practicaAprobadaCon($item, 3);

    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 2, 'Dos raras.');

    expect($this->servicio->solicitudesAprobadasSinCobertura())->toBe([]);
});

it('suma lo comprometido por prácticas que se pisan en la misma franja', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    $primera = practicaAprobadaCon($item, 5);
    $segunda = Solicitud::factory()->aprobada()->create([
        'fecha' => $primera->fecha->format('Y-m-d'),
        'hora_inicio' => '09:00:00',
        'hora_fin' => '11:00:00',
    ]);
    $segunda->items()->attach($item->id, ['cantidad' => 5]);

    // Diez comprometidas sobre ocho operativas: las dos se quedan cortas.
    expect($this->servicio->solicitudesAprobadasSinCobertura())
        ->toEqualCanonicalizing([$primera->id, $segunda->id]);
});

it('no mira prácticas que ya pasaron', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    practicaAprobadaCon($item, 6, '-10 days');

    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 5, 'Cinco malas.');

    expect($this->servicio->solicitudesAprobadasSinCobertura())->toBe([]);
});

it('no mira solicitudes que todavía no están aprobadas', function (string $estado): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    $solicitud = Solicitud::factory()->create([
        'estado' => EstadoSolicitud::from($estado),
        'fecha' => now()->modify('+10 days')->format('Y-m-d'),
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
    ]);
    $solicitud->items()->attach($item->id, ['cantidad' => 8]);

    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 5, 'Cinco malas.');

    expect($this->servicio->solicitudesAprobadasSinCobertura())->toBe([]);
})->with(['pendiente', 'revisada', 'rechazada']);

it('avisa en la bandeja y marca la práctica afectada', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    practicaAprobadaCon($item, 6);
    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 4, 'Cuatro malas.');

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertSee('práctica aprobada sin unidades suficientes')
        ->assertSee('dejó de estar operativo después de aprobarla');
});

it('no ensucia la bandeja cuando no hay nada que avisar', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    practicaAprobadaCon($item, 2);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertDontSee('sin unidades suficientes');
});

it('no toca la práctica: solo avisa', function (): void {
    // Qué hacer con una práctica ya aprobada es decisión del cliente.
    $item = ItemInventario::factory()->create(['cantidad_total' => 8]);
    $solicitud = practicaAprobadaCon($item, 6);

    $this->servicio->cambiarEstado($this->administrativo, $item, EstadoItemInventario::Operativo, EstadoItemInventario::EnRevision, 4, 'Cuatro malas.');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->items)->toHaveCount(1);
});
