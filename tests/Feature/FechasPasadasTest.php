<?php

declare(strict_types=1);

use App\Enums\TipoSesion;
use App\Exceptions\NovedadInvalida;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Solicitud\FormularioSolicitud;
use App\Livewire\Solicitud\NovedadesDeLaSesion;
use App\Livewire\Solicitud\SesionesApartadas;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use App\Services\DatosReprogramacion;
use App\Services\DatosSesionApartada;
use App\Services\NovedadesDeSesionService;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Livewire\Livewire;

/*
 * QA-05: una sesión no se pide, no se aparta ni se reprograma para un día que
 * ya pasó. Hoy sí vale: se compara por día, en la zona de la aplicación.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->travelTo('2026-10-18 10:00:00');
    $this->docente = User::factory()->docente()->create();
    $this->administrativo = User::factory()->administrativo()->create();
});

function solicitudParaElDia(string $fecha): DatosNuevaSolicitud
{
    return new DatosNuevaSolicitud(
        ...materiaYCaso(),
        tipo: TipoSesion::Practica,
        fecha: $fecha,
        horaInicio: '07:00',
        horaFin: '09:00',
        grupo: 'A',
        estudianteIds: idsDeEstudiantes(2),
    );
}

function apartadaParaElDia(User $docente, string $fecha): DatosSesionApartada
{
    return new DatosSesionApartada(
        ...materiaYCaso(),
        docenteId: $docente->id,
        tipo: TipoSesion::Practica,
        fecha: $fecha,
        horaInicio: '07:00',
        horaFin: '09:00',
        cantidadEstudiantes: 5,
    );
}

function sesionParaReprogramar(User $docente): Solicitud
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'fecha' => '2026-10-25',
        'hora_inicio' => '07:00:00',
        'hora_fin' => '09:00:00',
        'cantidad_estudiantes' => 4,
    ]);
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => Sala::factory()->create()->id]);

    return $solicitud;
}

function reprogramadaPara(Solicitud $solicitud, string $fecha): DatosReprogramacion
{
    return new DatosReprogramacion(
        fecha: $fecha,
        horaInicio: '07:00',
        horaFin: '09:00',
        casoClinicoId: $solicitud->caso_clinico_id,
        motivo: 'Mantenimiento',
        constanciaComunicacion: 'Llamada del 9/10',
    );
}

// ---------------------------------------------------------------------
// Servicios
// ---------------------------------------------------------------------

it('no crea una solicitud para un día pasado, pero sí para hoy', function (): void {
    $servicio = app(SolicitudService::class);

    expect(fn () => $servicio->crear($this->docente, solicitudParaElDia('2026-10-17')))
        ->toThrow(SolicitudInvalida::class, 'ya pasó');
    expect(Solicitud::query()->count())->toBe(0);

    expect($servicio->crear($this->docente, solicitudParaElDia('2026-10-18'))->fecha->toDateString())->toBe('2026-10-18');
});

it('no registra una sesión apartada en un día pasado, pero sí hoy', function (): void {
    $servicio = app(RegistroPrevioService::class);

    expect(fn () => $servicio->registrar(apartadaParaElDia($this->docente, '2025-01-15'), $this->administrativo))
        ->toThrow(SolicitudInvalida::class, 'ya pasó');
    expect(Solicitud::query()->count())->toBe(0);

    expect($servicio->registrar(apartadaParaElDia($this->docente, '2026-10-18'), $this->administrativo))->toBeInstanceOf(Solicitud::class);
});

it('no reprograma una sesión a un día pasado, pero sí a hoy', function (): void {
    $servicio = app(NovedadesDeSesionService::class);
    $sesion = sesionParaReprogramar($this->docente);

    expect(fn () => $servicio->reprogramar($sesion, reprogramadaPara($sesion, '2026-10-01'), $this->administrativo))
        ->toThrow(NovedadInvalida::class, 'ya pasó');
    expect($sesion->fresh()->fecha->toDateString())->toBe('2026-10-25');

    $servicio->reprogramar($sesion->fresh(), reprogramadaPara($sesion, '2026-10-18'), $this->administrativo);
    expect($sesion->fresh()->fecha->toDateString())->toBe('2026-10-18');
});

// ---------------------------------------------------------------------
// Formularios
// ---------------------------------------------------------------------

it('el formulario de solicitud rechaza una fecha pasada y ofrece solo desde hoy', function (): void {
    Livewire::actingAs($this->docente)
        ->test(FormularioSolicitud::class)
        ->assertSeeHtml('min="2026-10-18"')
        ->set('fecha', '2026-10-17')
        ->call('guardar')
        ->assertHasErrors(['fecha']);
});

it('el formulario de sesiones apartadas rechaza una fecha pasada', function (): void {
    Livewire::actingAs($this->administrativo)
        ->test(SesionesApartadas::class)
        ->assertSeeHtml('min="2026-10-18"')
        ->set('fecha', '2025-01-15')
        ->call('registrar')
        ->assertHasErrors(['fecha']);
});

it('el formulario de reprogramación rechaza una fecha pasada', function (): void {
    $sesion = sesionParaReprogramar($this->docente);

    Livewire::actingAs($this->administrativo)
        ->test(NovedadesDeLaSesion::class, ['solicitud' => $sesion])
        ->assertSeeHtml('min="2026-10-18"')
        ->set('fecha', '2026-10-01')
        ->set('motivo', 'x')
        ->set('constancia', 'y')
        ->call('reprogramar')
        ->assertHasErrors(['fecha']);
});
