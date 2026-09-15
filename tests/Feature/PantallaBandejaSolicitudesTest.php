<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Livewire\Solicitud\BandejaRevision;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

// ---------------------------------------------------------------------
// Qué controles ve cada rol
// ---------------------------------------------------------------------

it('enseña al administrativo el control de revisar pero no el de aprobar', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertSee('Marcar como revisada')
        ->assertDontSee('Aprobar')
        ->assertDontSee('Rechazar');
});

it('enseña al coordinador los tres controles', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaRevision::class)
        ->assertSee('Marcar como revisada')
        ->assertSee('Aprobar')
        ->assertSee('Rechazar');
});

it('no deja al administrativo aprobar aunque llame al método a mano', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->call('aprobar', $solicitud->id)
        ->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});

it('no deja al administrativo rechazar aunque llame al método a mano', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->call('rechazar', $solicitud->id)
        ->assertForbidden();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});

it('no deja a un docente ni a un estudiante acercarse a la bandeja', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $solicitud = Solicitud::factory()->create();

    Livewire::actingAs($usuario->fresh())
        ->test(BandejaRevision::class)
        ->call('revisar', $solicitud->id)
        ->assertForbidden();
})->with([Rol::Docente, Rol::Estudiante]);

// ---------------------------------------------------------------------
// El flujo
// ---------------------------------------------------------------------

it('deja al administrativo marcar una solicitud como revisada', function (): void {
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->call('revisar', $solicitud->id);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada)
        ->and($solicitud->fresh()->revisada_por)->toBe($this->administrativo->id);
});

it('deja al coordinador aprobar y crea la preparación', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaRevision::class)
        ->call('aprobar', $solicitud->id);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->preparacion)->not->toBeNull();
});

it('pide el motivo antes de rechazar y lo guarda', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaRevision::class)
        ->call('pedirMotivo', $solicitud->id)
        ->assertSee('Motivo del rechazo')
        ->set('motivoRechazo', 'Coincide con otra práctica en la misma sala.')
        ->call('rechazar', $solicitud->id);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->motivo_rechazo)->toBe('Coincide con otra práctica en la misma sala.');
});

it('deja rechazar sin motivo, porque es opcional', function (): void {
    $solicitud = Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaRevision::class)
        ->call('rechazar', $solicitud->id)
        ->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->motivo_rechazo)->toBeNull();
});

// ---------------------------------------------------------------------
// Listado y detalle
// ---------------------------------------------------------------------

it('ordena la bandeja por la fecha de la práctica', function (): void {
    Solicitud::factory()->create(['fecha' => '2026-11-20']);
    Solicitud::factory()->create(['fecha' => '2026-10-05']);
    Solicitud::factory()->create(['fecha' => '2026-12-01']);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->pluck('fecha')
            ->map(fn ($f): string => $f->format('Y-m-d'))->all() === ['2026-10-05', '2026-11-20', '2026-12-01']);
});

it('muestra en el detalle los equipos pedidos con sus cantidades', function (): void {
    $solicitud = Solicitud::factory()->create();
    $item = ItemInventario::factory()->create(['nombre' => 'Maniquí de parto', 'cantidad_total' => 5]);
    $solicitud->items()->attach($item->id, ['cantidad' => 2]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->call('abrir', $solicitud->id)
        ->assertSee('Maniquí de parto')
        ->assertSee('×2', escape: false);
});

it('avisa cuando se piden más unidades de las que quedan libres', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 3]);

    $yaAprobada = Solicitud::factory()->aprobada()->create([
        'fecha' => '2026-10-05', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00',
    ]);
    $yaAprobada->items()->attach($item->id, ['cantidad' => 2]);

    $nueva = Solicitud::factory()->create([
        'fecha' => '2026-10-05', 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00',
    ]);
    $nueva->items()->attach($item->id, ['cantidad' => 3]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->call('abrir', $nueva->id)
        ->assertSee('más unidades de las que quedan libres')
        ->assertSee('Solo quedan 1 libres en esa franja');
});

it('no avisa nada cuando lo pedido cabe en lo disponible', function (): void {
    $item = ItemInventario::factory()->create(['cantidad_total' => 10]);
    $solicitud = Solicitud::factory()->create();
    $solicitud->items()->attach($item->id, ['cantidad' => 2]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->call('abrir', $solicitud->id)
        ->assertDontSee('más unidades de las que quedan libres');
});

it('no impide aprobar aunque falten unidades', function (): void {
    // Es información para el administrativo, no un bloqueo. Si el cliente
    // decide después que debe bloquear, la regla entra en el Service.
    $item = ItemInventario::factory()->create(['cantidad_total' => 1]);
    $solicitud = Solicitud::factory()->revisada()->create();
    $solicitud->items()->attach($item->id, ['cantidad' => 9]);

    Livewire::actingAs($this->coordinadora)
        ->test(BandejaRevision::class)
        ->call('aprobar', $solicitud->id)
        ->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada);
});

it('filtra la bandeja por estado', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->revisada()->create();

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 2)
        ->set('estado', EstadoSolicitud::Revisada->value)
        ->assertViewHas('solicitudes', fn ($p): bool => $p->total() === 1);
});

it('no genera consultas N+1 al recorrer la bandeja', function (): void {
    Solicitud::factory()->count(6)->aprobada()->create()
        ->each(fn (Solicitud $s) => Preparacion::factory()->conSala()->create(['solicitud_id' => $s->id]));

    Model::preventLazyLoading();

    Livewire::actingAs($this->administrativo)->test(BandejaRevision::class)->assertOk();
});

it('señala el pendiente de que un docente-coordinador apruebe lo suyo', function (): void {
    // §11.1 de CLAUDE.md. Hoy sí puede; queda avisado en la pantalla.
    $this->actingAs($this->coordinadora)->get(route('panel.solicitudes'))
        ->assertOk()
        ->assertSee('puede aprobar sus propias solicitudes');
});
