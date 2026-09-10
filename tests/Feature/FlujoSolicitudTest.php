<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Enums\TipoSesion;
use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Exceptions\TransicionDeSolicitudInvalida;
use App\Listeners\EnviarCorreoResultadoSolicitud;
use App\Mail\SolicitudAprobadaMail;
use App\Mail\SolicitudRechazadaMail;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(SolicitudService::class);
    $this->docente = User::factory()->docente()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

/** Datos mínimos de una solicitud, con la materia y el caso ya creados. */
function datosDeSolicitud(?CasoClinico $caso = null, array $items = []): DatosNuevaSolicitud
{
    return new DatosNuevaSolicitud(
        materiaId: Materia::factory()->create()->id,
        casoClinicoId: ($caso ?? CasoClinico::factory()->create())->id,
        tipo: TipoSesion::Practica,
        fecha: now()->addWeek()->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        cantidadEstudiantes: 12,
        items: $items,
    );
}

it('recorre el flujo completo hasta dejar el escenario listo para preparar', function (): void {
    Mail::fake();

    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud());
    expect($solicitud->estado)->toBe(EstadoSolicitud::Pendiente);

    $this->servicio->marcarRevisada($solicitud, $this->administrativo);
    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Revisada)
        ->and($solicitud->fresh()->revisada_por)->toBe($this->administrativo->id)
        ->and($solicitud->fresh()->revisada_at)->not->toBeNull();

    $this->servicio->aprobar($solicitud, $this->coordinadora);
    $solicitud = $solicitud->fresh();

    expect($solicitud->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->resuelta_por)->toBe($this->coordinadora->id)
        ->and($solicitud->resuelta_at)->not->toBeNull();

    // Al aprobar nace la preparación, sin sala: la asigna el administrativo.
    expect($solicitud->preparacion)->not->toBeNull()
        ->and($solicitud->preparacion->estado)->toBe(EstadoPreparacion::Pendiente)
        ->and($solicitud->preparacion->sala_id)->toBeNull();
});

it('no asigna sala al crear la solicitud', function (): void {
    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud());

    expect($solicitud->preparacion)->toBeNull()
        ->and(array_key_exists('sala_id', $solicitud->getAttributes()))->toBeFalse();
});

it('rechaza una solicitud registrando el motivo', function (): void {
    Mail::fake();
    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud());
    $this->servicio->marcarRevisada($solicitud, $this->administrativo);

    $this->servicio->rechazar($solicitud, $this->coordinadora, 'El simulador está en mantenimiento.');
    $solicitud = $solicitud->fresh();

    expect($solicitud->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->motivo_rechazo)->toBe('El simulador está en mantenimiento.')
        ->and($solicitud->resuelta_por)->toBe($this->coordinadora->id)
        ->and($solicitud->preparacion)->toBeNull();
});

it('rechaza una solicitud sin motivo', function (): void {
    Mail::fake();
    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud());
    $this->servicio->marcarRevisada($solicitud, $this->administrativo);

    $this->servicio->rechazar($solicitud, $this->coordinadora);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->motivo_rechazo)->toBeNull();
});

it('precarga en la solicitud los items del caso clínico', function (): void {
    $caso = CasoClinico::factory()->create();
    $maniqui = ItemInventario::factory()->simulador()->create();
    $gasas = ItemInventario::factory()->equipoBasico()->create();
    $caso->items()->attach($maniqui->id, ['cantidad' => 1]);
    $caso->items()->attach($gasas->id, ['cantidad' => 15]);

    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud($caso));

    $items = $solicitud->items;
    expect($items)->toHaveCount(2)
        ->and($items->firstWhere('id', $maniqui->id)->pivot->cantidad)->toBe(1)
        ->and($items->firstWhere('id', $gasas->id)->pivot->cantidad)->toBe(15);
});

it('respeta los items que el docente ajustó en lugar de los sugeridos', function (): void {
    $caso = CasoClinico::factory()->create();
    $sugerido = ItemInventario::factory()->create();
    $agregado = ItemInventario::factory()->create();
    $caso->items()->attach($sugerido->id, ['cantidad' => 1]);

    // El docente sube la cantidad del sugerido y añade otro equipo.
    $solicitud = $this->servicio->crear(
        $this->docente,
        datosDeSolicitud($caso, [$sugerido->id => 4, $agregado->id => 2]),
    );

    expect($solicitud->items)->toHaveCount(2)
        ->and($solicitud->items->firstWhere('id', $sugerido->id)->pivot->cantidad)->toBe(4)
        ->and($solicitud->items->firstWhere('id', $agregado->id)->pivot->cantidad)->toBe(2);
});

it('sugiere los items de un caso clínico con sus cantidades', function (): void {
    $caso = CasoClinico::factory()->create();
    $item = ItemInventario::factory()->create();
    $caso->items()->attach($item->id, ['cantidad' => 3]);

    $sugeridos = $this->servicio->itemsSugeridos($caso);

    expect($sugeridos)->toHaveCount(1)
        ->and($sugeridos->first()->id)->toBe($item->id)
        ->and((int) $sugeridos->first()->pivot->cantidad)->toBe(3);
});

dataset('transiciones inválidas', [
    'aprobar una pendiente sin revisar' => [EstadoSolicitud::Pendiente, 'aprobar'],
    'rechazar una pendiente sin revisar' => [EstadoSolicitud::Pendiente, 'rechazar'],
    'revisar una ya revisada' => [EstadoSolicitud::Revisada, 'marcarRevisada'],
    'revisar una aprobada' => [EstadoSolicitud::Aprobada, 'marcarRevisada'],
    'revisar una rechazada' => [EstadoSolicitud::Rechazada, 'marcarRevisada'],
    'aprobar una ya aprobada' => [EstadoSolicitud::Aprobada, 'aprobar'],
    'aprobar una rechazada' => [EstadoSolicitud::Rechazada, 'aprobar'],
    'rechazar una aprobada' => [EstadoSolicitud::Aprobada, 'rechazar'],
    'rechazar una ya rechazada' => [EstadoSolicitud::Rechazada, 'rechazar'],
]);

it('lanza excepción en las transiciones inválidas', function (EstadoSolicitud $desde, string $metodo): void {
    $solicitud = Solicitud::factory()->create(['estado' => $desde]);

    $this->servicio->{$metodo}($solicitud, $this->coordinadora);
})->with('transiciones inválidas')->throws(TransicionDeSolicitudInvalida::class);

it('no deja rastro cuando la transición es inválida', function (): void {
    Event::fake();
    $solicitud = Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente]);

    expect(fn () => $this->servicio->aprobar($solicitud, $this->coordinadora))
        ->toThrow(TransicionDeSolicitudInvalida::class);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Pendiente)
        ->and($solicitud->fresh()->preparacion)->toBeNull();

    Event::assertNotDispatched(SolicitudAprobada::class);
});

it('avisa al docente por correo cuando se aprueba', function (): void {
    Mail::fake();
    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud());
    $this->servicio->marcarRevisada($solicitud, $this->administrativo);

    $this->servicio->aprobar($solicitud, $this->coordinadora);

    Mail::assertSent(SolicitudAprobadaMail::class, fn (SolicitudAprobadaMail $correo): bool => $correo->hasTo($this->docente->email)
        && $correo->solicitud->is($solicitud));
    Mail::assertNotSent(SolicitudRechazadaMail::class);
});

it('avisa al docente por correo cuando se rechaza, con el motivo', function (): void {
    Mail::fake();
    $solicitud = $this->servicio->crear($this->docente, datosDeSolicitud());
    $this->servicio->marcarRevisada($solicitud, $this->administrativo);

    $this->servicio->rechazar($solicitud, $this->coordinadora, 'No hay sala disponible.');

    Mail::assertSent(SolicitudRechazadaMail::class, function (SolicitudRechazadaMail $correo): bool {
        $renderizado = $correo->render();

        return $correo->hasTo($this->docente->email)
            && str_contains($renderizado, 'No hay sala disponible.');
    });
    Mail::assertNotSent(SolicitudAprobadaMail::class);
});

it('despacha los eventos de resolución', function (): void {
    Event::fake([SolicitudAprobada::class, SolicitudRechazada::class]);

    $aprobada = Solicitud::factory()->create(['estado' => EstadoSolicitud::Revisada]);
    $rechazada = Solicitud::factory()->create(['estado' => EstadoSolicitud::Revisada]);

    $this->servicio->aprobar($aprobada, $this->coordinadora);
    $this->servicio->rechazar($rechazada, $this->coordinadora);

    Event::assertDispatched(SolicitudAprobada::class);
    Event::assertDispatched(SolicitudRechazada::class);
});

it('envía el correo en cola para no bloquear la respuesta', function (): void {
    expect(app(EnviarCorreoResultadoSolicitud::class))
        ->toBeInstanceOf(ShouldQueue::class);
});

it('deja las solicitudes de cada docente separadas por los scopes', function (): void {
    $otroDocente = User::factory()->docente()->create();
    Solicitud::factory()->count(2)->create(['docente_id' => $this->docente->id]);
    Solicitud::factory()->create(['docente_id' => $otroDocente->id]);

    expect(Solicitud::delDocente($this->docente)->count())->toBe(2)
        ->and(Solicitud::delDocente($otroDocente)->count())->toBe(1);
});

it('separa las pendientes de revisión de las pendientes de aprobación', function (): void {
    Solicitud::factory()->count(2)->create(['estado' => EstadoSolicitud::Pendiente]);
    Solicitud::factory()->count(3)->create(['estado' => EstadoSolicitud::Revisada]);
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Aprobada]);

    expect(Solicitud::pendientesDeRevision()->count())->toBe(2)
        ->and(Solicitud::pendientesDeAprobacion()->count())->toBe(3);
});

it('filtra las aprobadas por rango de fechas', function (): void {
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Aprobada, 'fecha' => '2026-03-10']);
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Aprobada, 'fecha' => '2026-03-20']);
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Aprobada, 'fecha' => '2026-04-05']);
    Solicitud::factory()->create(['estado' => EstadoSolicitud::Pendiente, 'fecha' => '2026-03-15']);

    expect(Solicitud::aprobadasEntre('2026-03-01', '2026-03-31')->count())->toBe(2);
});

it('permite a un docente que además coordina resolver, hasta que el cliente decida', function (): void {
    // PENDIENTE §11.1 de CLAUDE.md: sin decidir si puede aprobar la suya.
    $ambosRoles = User::factory()->create();
    $ambosRoles->assignRole([Rol::Docente->value, Rol::Coordinador->value]);

    expect($ambosRoles->can('aprobar', Solicitud::factory()->create()))->toBeTrue();
});
