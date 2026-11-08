<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use App\Exceptions\SesionQueSeCruza;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosNuevaSolicitud;
use App\Services\DatosReprogramacion;
use App\Services\DatosSesionApartada;
use App\Services\NovedadesDeSesionService;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * Nadie está en dos sesiones a la vez (decisión del cliente del 2026-11-07):
 * un estudiante no puede quedar en dos sesiones que se cruzan, y un docente
 * no puede tener dos solicitudes que se cruzan, porque solo puede estar en
 * un salón. Cuentan las pendientes, las revisadas y las aprobadas; una
 * rechazada ya no ocupa a nadie. Dos sesiones seguidas no se cruzan.
 */

beforeEach(function (): void {
    Mail::fake();
    $this->seed(RolSeeder::class);
    $this->servicio = app(SolicitudService::class);
    $this->novedades = app(NovedadesDeSesionService::class);
    $this->docente = User::factory()->docente()->create(['nombre' => 'Dora Docente']);
    $this->otroDocente = User::factory()->docente()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->fecha = now()->addWeek()->toDateString();
});

/**
 * Una sesión de esta franja con estos estudiantes.
 *
 * @param  list<int>  $estudianteIds
 */
function sesionEnFranja(array $estudianteIds, string $horaInicio, string $horaFin): DatosNuevaSolicitud
{
    return new DatosNuevaSolicitud(...[
        ...get_object_vars(sesionCon($estudianteIds)),
        'fecha' => test()->fecha,
        'horaInicio' => $horaInicio,
        'horaFin' => $horaFin,
    ]);
}

// ---------------------------------------------------------------------
// El docente
// ---------------------------------------------------------------------

it('no deja a un docente pedir otra sesión que se cruza con una suya', function (): void {
    $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '08:00', '10:00'));

    expect(fn () => $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '09:00', '11:00')))
        ->toThrow(SesionQueSeCruza::class, 'Dora Docente')
        ->and(Solicitud::query()->where('docente_id', $this->docente->id)->count())->toBe(1);
});

it('deja pedir sesiones seguidas, que no se cruzan', function (): void {
    $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '08:00', '10:00'));
    $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '10:00', '12:00'));

    expect(Solicitud::query()->where('docente_id', $this->docente->id)->count())->toBe(2);
});

it('libera al docente de una solicitud rechazada', function (): void {
    $rechazada = $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '08:00', '10:00'));
    $this->servicio->rechazar($rechazada, $this->administrativo, 'No hay simulador.');

    $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '08:00', '10:00'));

    expect(Solicitud::query()->where('docente_id', $this->docente->id)->where('estado', '!=', EstadoSolicitud::Rechazada)->count())->toBe(1);
});

it('no deja registrar una sesión apartada a un docente que ya tiene otra que se cruza', function (): void {
    $this->servicio->crear($this->docente, sesionEnFranja(idsDeEstudiantes(1), '08:00', '10:00'));
    $caso = materiaYCaso();

    expect(fn () => app(RegistroPrevioService::class)->registrar(new DatosSesionApartada(
        docenteId: $this->docente->id,
        materiaId: $caso['materiaId'],
        casoClinicoId: $caso['casoClinicoId'],
        tipo: TipoSesion::Practica,
        fecha: $this->fecha,
        horaInicio: '09:00',
        horaFin: '11:00',
        cantidadEstudiantes: 10,
    ), $this->administrativo))->toThrow(SesionQueSeCruza::class);
});

it('no deja sustituir por un docente que ya dicta otra sesión que se cruza', function (): void {
    $suya = Solicitud::factory()->aprobada()->create(['docente_id' => $this->docente->id, 'fecha' => $this->fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']);
    $delOtro = Solicitud::factory()->aprobada()->create(['docente_id' => $this->otroDocente->id, 'fecha' => $this->fecha, 'hora_inicio' => '09:00', 'hora_fin' => '11:00']);

    expect(fn () => $this->novedades->sustituir($delOtro, $this->docente, 'Incapacidad.', $this->administrativo))
        ->toThrow(SesionQueSeCruza::class)
        ->and($delOtro->fresh()->docente_que_dicta_id)->toBeNull()
        ->and($suya->fresh()->docente_que_dicta_id)->toBeNull();
});

// ---------------------------------------------------------------------
// Los estudiantes
// ---------------------------------------------------------------------

it('no deja poner a un estudiante en dos sesiones que se cruzan', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Cruce']);
    $this->servicio->crear($this->docente, sesionEnFranja([$ana->id], '08:00', '10:00'));

    expect(fn () => $this->servicio->crear($this->otroDocente, sesionEnFranja([$ana->id], '09:00', '11:00')))
        ->toThrow(SesionQueSeCruza::class, 'Ana Cruce');
});

it('tampoco al completar la lista de una sesión ya registrada', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Cruce']);
    $this->servicio->crear($this->docente, sesionEnFranja([$ana->id], '08:00', '10:00'));
    $otra = $this->servicio->crear($this->otroDocente, sesionEnFranja(idsDeEstudiantes(1), '09:00', '11:00'));

    expect(fn () => $this->servicio->agregarEstudiantes($otra, [$ana->id], $this->otroDocente))
        ->toThrow(SesionQueSeCruza::class, 'Ana Cruce')
        ->and($otra->estudiantes()->whereKey($ana->id)->exists())->toBeFalse();
});

it('deja de contar a un estudiante retirado de la otra sesión', function (): void {
    $ana = User::factory()->estudiante()->create();
    $primera = $this->servicio->crear($this->docente, sesionEnFranja([$ana->id, ...idsDeEstudiantes(1)], '08:00', '10:00'));
    $this->servicio->retirarEstudiante($primera, $ana, 'Cambió de grupo.', $this->docente);

    $this->servicio->crear($this->otroDocente, sesionEnFranja([$ana->id], '09:00', '11:00'));

    expect(Solicitud::query()->where('docente_id', $this->otroDocente->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------
// Al reprogramar
// ---------------------------------------------------------------------

it('no deja reprogramar a una franja en la que el docente o un estudiante ya están en otra sesión', function (string $quien): void {
    $ana = User::factory()->estudiante()->create();
    $aMover = Solicitud::factory()->aprobada()->create(['docente_id' => $this->docente->id, 'fecha' => $this->fecha, 'hora_inicio' => '14:00', 'hora_fin' => '16:00']);
    $aMover->estudiantes()->attach($ana->id);

    $ocupada = Solicitud::factory()->aprobada()->create([
        'docente_id' => $quien === 'docente' ? $this->docente->id : $this->otroDocente->id,
        'fecha' => $this->fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00',
    ]);

    if ($quien === 'estudiante') {
        $ocupada->estudiantes()->attach($ana->id);
    }

    expect(fn () => $this->novedades->reprogramar($aMover, new DatosReprogramacion(
        fecha: $this->fecha,
        horaInicio: '09:00',
        horaFin: '11:00',
        casoClinicoId: $aMover->caso_clinico_id,
        motivo: 'Se cruzó con un parcial.',
        constanciaComunicacion: 'Correo del 3 de noviembre.',
    ), $this->administrativo))->toThrow(SesionQueSeCruza::class)
        ->and(substr($aMover->fresh()->hora_inicio, 0, 5))->toBe('14:00');
})->with(['docente', 'estudiante']);
