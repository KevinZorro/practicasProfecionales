<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\EstadoSolicitud;
use App\Events\SolicitudAprobada;
use App\Events\SolicitudRechazada;
use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Exceptions\TransicionDeSolicitudInvalida;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\RegistroDeBitacora;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\ConfidencialidadService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/*
 * QA-02: dos personas con la misma bandeja abierta tienen dos instancias del
 * mismo registro, cada una con el estado que leyó. La segunda en actuar no
 * puede pisar a la primera: el estado se vuelve a leer con la fila bloqueada.
 */

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolSeeder::class);
    abrirPeriodo('2026-2');
    $this->coordinadora = User::factory()->coordinador()->create();
    $this->admin = User::factory()->admin()->create();
    $this->administrativo = User::factory()->administrativo()->create();
});

it('no deja rechazar una solicitud que otra persona ya aprobó', function (): void {
    Event::fake([SolicitudAprobada::class, SolicitudRechazada::class]);
    $servicio = app(SolicitudService::class);
    $solicitud = Solicitud::factory()->revisada()->create();
    $deLaCoordinadora = Solicitud::findOrFail($solicitud->id);
    $delAdmin = Solicitud::findOrFail($solicitud->id);

    $servicio->aprobar($deLaCoordinadora, $this->coordinadora);

    expect(fn () => $servicio->rechazar($delAdmin, $this->admin, 'Tarde'))
        ->toThrow(TransicionDeSolicitudInvalida::class);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Aprobada)
        ->and($solicitud->fresh()->preparacion)->not->toBeNull()
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::SolicitudRechazada)->count())->toBe(0)
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::SolicitudAprobada)->count())->toBe(1);
    Event::assertDispatched(SolicitudAprobada::class, 1);
    Event::assertNotDispatched(SolicitudRechazada::class);
});

it('no deja aprobar una solicitud que otra persona ya rechazó', function (): void {
    Event::fake([SolicitudAprobada::class, SolicitudRechazada::class]);
    $servicio = app(SolicitudService::class);
    $solicitud = Solicitud::factory()->revisada()->create();
    $deLaCoordinadora = Solicitud::findOrFail($solicitud->id);
    $delAdmin = Solicitud::findOrFail($solicitud->id);

    $servicio->rechazar($delAdmin, $this->admin, 'No hay cupo');

    expect(fn () => $servicio->aprobar($deLaCoordinadora, $this->coordinadora))
        ->toThrow(TransicionDeSolicitudInvalida::class);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Rechazada)
        ->and($solicitud->fresh()->preparacion)->toBeNull();
    Event::assertNotDispatched(SolicitudAprobada::class);
});

it('no deja revisar dos veces la misma solicitud', function (): void {
    $servicio = app(SolicitudService::class);
    $solicitud = Solicitud::factory()->create();
    $primera = Solicitud::findOrFail($solicitud->id);
    $segunda = Solicitud::findOrFail($solicitud->id);

    $servicio->marcarRevisada($primera, $this->administrativo);

    expect(fn () => $servicio->marcarRevisada($segunda, User::factory()->administrativo()->create()))
        ->toThrow(TransicionDeSolicitudInvalida::class)
        ->and($solicitud->fresh()->revisada_por)->toBe($this->administrativo->id);
});

/** Una entrega cargada con su archivo en el disco. */
function entregaCargadaConArchivo(): FormatoConfidencialidad
{
    PlantillaConfidencialidad::factory()->create();

    return app(ConfidencialidadService::class)->registrarEntrega(
        User::factory()->estudiante()->create(),
        UploadedFile::fake()->create('formato.pdf', 200, 'application/pdf'),
    );
}

it('no deja verificar un formato que otra persona ya rechazó y dejó sin documento', function (): void {
    $servicio = app(ConfidencialidadService::class);
    $entrega = entregaCargadaConArchivo();
    $ruta = $entrega->archivo_firmado_path;
    $quienRechaza = FormatoConfidencialidad::findOrFail($entrega->id);
    $quienVerifica = FormatoConfidencialidad::findOrFail($entrega->id);

    $servicio->rechazar($quienRechaza, $this->administrativo, 'Ilegible');

    expect(fn () => $servicio->verificar($quienVerifica, User::factory()->administrativo()->create()))
        ->toThrow(FormatoConfidencialidadInvalido::class);

    $guardada = $entrega->fresh();
    expect($guardada->estado)->toBe(EstadoFormatoConfidencialidad::Pendiente)
        ->and($guardada->archivo_firmado_path)->toBeNull()
        ->and($servicio->puedeParticiparEnPracticas($entrega->firmante))->toBeFalse();
    Storage::disk('local')->assertMissing($ruta);
});

it('no deja rechazar un formato que otra persona ya verificó, y no borra su documento', function (): void {
    $servicio = app(ConfidencialidadService::class);
    $entrega = entregaCargadaConArchivo();
    $ruta = $entrega->archivo_firmado_path;
    $quienVerifica = FormatoConfidencialidad::findOrFail($entrega->id);
    $quienRechaza = FormatoConfidencialidad::findOrFail($entrega->id);

    $servicio->verificar($quienVerifica, $this->administrativo);

    expect(fn () => $servicio->rechazar($quienRechaza, User::factory()->administrativo()->create(), 'Ilegible'))
        ->toThrow(FormatoConfidencialidadInvalido::class);

    expect($entrega->fresh()->estado)->toBe(EstadoFormatoConfidencialidad::Verificado)
        ->and($entrega->fresh()->archivo_firmado_path)->toBe($ruta);
    Storage::disk('local')->assertExists($ruta);
});
