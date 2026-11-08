<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\EstadoSolicitud;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Solicitud\ExpedienteDeSolicitud;
use App\Mail\SolicitudAprobadaMail;
use App\Models\ItemInventario;
use App\Models\RegistroDeBitacora;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Se puede aprobar aunque falten unidades en esa franja, pero entonces las
 * observaciones de la aprobación son obligatorias: el docente tiene que
 * saber qué no va a encontrar (decisión del cliente del 2026-11-07). Sin
 * faltantes, siguen siendo opcionales.
 */

beforeEach(function (): void {
    Mail::fake();
    $this->seed(RolSeeder::class);
    $this->servicio = app(SolicitudService::class);
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->sondas = ItemInventario::factory()->create(['nombre' => 'Sonda vesical', 'cantidad_total' => 1]);
});

/** Una solicitud revisada que pide estas unidades de sondas. */
function revisadaQuePide(int $sondas): Solicitud
{
    $solicitud = Solicitud::factory()->revisada()->create(['fecha' => now()->addWeek()->toDateString()]);
    $solicitud->items()->attach(test()->sondas->id, ['cantidad' => $sondas]);

    return $solicitud;
}

it('no deja aprobar con faltantes sin decir qué falta', function (?string $observaciones): void {
    $solicitud = revisadaQuePide(3);

    expect(fn () => $this->servicio->aprobar($solicitud, $this->coordinadora, $observaciones))
        ->toThrow(SolicitudInvalida::class, 'qué falta')
        ->and($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada);
})->with([
    'sin observaciones' => [null],
    'en blanco' => ['   '],
]);

it('aprueba con faltantes cuando se dice qué falta, y se lo cuenta al docente', function (): void {
    $solicitud = revisadaQuePide(3);

    $this->servicio->aprobar($solicitud, $this->coordinadora, 'Solo hay una sonda: traer dos del servicio.');

    $aprobada = $solicitud->fresh();

    expect($aprobada->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($aprobada->observaciones_aprobacion)->toBe('Solo hay una sonda: traer dos del servicio.')
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::SolicitudAprobada)->value('motivo'))
        ->toBe('Solo hay una sonda: traer dos del servicio.')
        ->and((new SolicitudAprobadaMail($aprobada))->render())->toContain('Solo hay una sonda: traer dos del servicio.');
});

it('sin faltantes, las observaciones siguen siendo opcionales', function (): void {
    $solicitud = revisadaQuePide(1);

    $this->servicio->aprobar($solicitud, $this->coordinadora);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->observaciones_aprobacion)->toBeNull();
});

it('pide en el expediente las observaciones solo cuando faltan unidades', function (): void {
    $solicitud = revisadaQuePide(3);

    Livewire::actingAs($this->coordinadora)
        ->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud])
        ->assertSee('obligatorias')
        ->call('aprobar')
        ->assertHasErrors('observacionesAprobacion')
        ->set('observacionesAprobacion', 'Solo hay una sonda.')
        ->call('aprobar')
        ->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada);
});

it('enseña al docente las observaciones de la aprobación en su expediente', function (): void {
    $solicitud = revisadaQuePide(3);
    $this->servicio->aprobar($solicitud, $this->coordinadora, 'Solo hay una sonda.');

    Livewire::actingAs($solicitud->docente)
        ->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud->fresh()])
        ->assertSee('Solo hay una sonda.');
});
