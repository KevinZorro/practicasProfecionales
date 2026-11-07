<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\EstadoSolicitud;
use App\Exceptions\SolicitudVencida;
use App\Livewire\Solicitud\BandejaRevision;
use App\Livewire\Solicitud\ExpedienteDeSolicitud;
use App\Models\Preparacion;
use App\Models\RegistroDeBitacora;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Una solicitud pendiente o revisada cuya fecha ya pasó no se revisa ni se
 * aprueba: aprobarla pondría en el calendario una práctica que no ocurrió
 * (decisión del cliente del 2026-11-07, B3.4). Solo se rechaza, para
 * cerrarla. No hay un estado nuevo: «vencida» es pendiente o revisada con
 * la fecha pasada, y la bandeja la enseña aparte.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(SolicitudService::class);
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

function ayer(): string
{
    return now()->subDay()->toDateString();
}

it('no deja revisar una solicitud cuya fecha ya pasó', function (): void {
    $vencida = Solicitud::factory()->create(['fecha' => ayer()]);

    expect(fn () => $this->servicio->marcarRevisada($vencida, $this->administrativo))->toThrow(SolicitudVencida::class)
        ->and($vencida->fresh()->estado)->toBe(EstadoSolicitud::Pendiente);
});

it('no deja aprobar una revisada cuya fecha ya pasó, ni deja preparación ni bitácora', function (): void {
    $vencida = Solicitud::factory()->revisada()->create(['fecha' => ayer()]);

    expect(fn () => $this->servicio->aprobar($vencida, $this->coordinadora))->toThrow(SolicitudVencida::class)
        ->and($vencida->fresh()->estado)->toBe(EstadoSolicitud::Revisada)
        ->and(Preparacion::query()->where('solicitud_id', $vencida->id)->exists())->toBeFalse()
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::SolicitudAprobada)->exists())->toBeFalse();
});

it('deja rechazar una vencida para cerrarla', function (Closure $crear, string $quien): void {
    Mail::fake();
    $vencida = $crear();

    $this->servicio->rechazar($vencida, $this->{$quien}, 'No se dictó.');

    expect($vencida->fresh()->estado)->toBe(EstadoSolicitud::Rechazada);
})->with([
    'pendiente, en la revisión' => [fn (): Solicitud => Solicitud::factory()->create(['fecha' => ayer()]), 'administrativo'],
    'revisada, en la aprobación' => [fn (): Solicitud => Solicitud::factory()->revisada()->create(['fecha' => ayer()]), 'coordinadora'],
]);

it('trata la fecha de hoy como vigente', function (): void {
    Mail::fake();
    $deHoy = Solicitud::factory()->revisada()->create(['fecha' => now()->toDateString()]);

    $this->servicio->aprobar($deHoy, $this->coordinadora);

    expect($deHoy->fresh()->estado)->toBe(EstadoSolicitud::Aprobada);
});

it('separa las vencidas del resto de la bandeja', function (): void {
    $vigente = Solicitud::factory()->create(['fecha' => now()->addDay()->toDateString()]);
    $vencida = Solicitud::factory()->create(['fecha' => ayer()]);
    $aprobadaPasada = Solicitud::factory()->aprobada()->create(['fecha' => ayer()]);

    expect($this->servicio->bandeja()->pluck('id')->all())->toEqualCanonicalizing([$vigente->id, $aprobadaPasada->id])
        ->and($this->servicio->vencidas()->pluck('id')->all())->toBe([$vencida->id])
        ->and($this->servicio->cuantasVencidas())->toBe(1);
});

it('avisa en la bandeja cuántas hay vencidas y las enseña aparte', function (): void {
    $vencida = Solicitud::factory()->create(['fecha' => now()->subDays(3)->toDateString()]);

    Livewire::actingAs($this->administrativo)
        ->test(BandejaRevision::class)
        ->assertSee('1 solicitud vencida')
        ->assertDontSeeHtml(route('panel.solicitudes.ver', $vencida))
        ->set('estado', BandejaRevision::VENCIDAS)
        ->assertSeeHtml(route('panel.solicitudes.ver', $vencida));
});

it('en el expediente de una vencida solo ofrece rechazar', function (): void {
    $vencida = Solicitud::factory()->revisada()->create(['fecha' => ayer()]);

    Livewire::actingAs($this->coordinadora)
        ->test(ExpedienteDeSolicitud::class, ['solicitud' => $vencida])
        ->assertSee('La fecha de esta solicitud ya pasó')
        ->assertDontSeeHtml('wire:click="aprobar"')
        ->assertSeeHtml('wire:click="pedirMotivo"')
        ->call('aprobar');

    expect($vencida->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
});
