<?php

declare(strict_types=1);

use App\Enums\EstadoSolicitud;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\EvaluacionInvalida;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Confidencialidad\EstadoDeFirmantes;
use App\Livewire\Preparacion\TableroDiario;
use App\Livewire\Solicitud\ParticipantesDeLaSesion;
use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\BloqueoService;
use App\Services\DatosNuevaSolicitud;
use App\Services\EvaluacionService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
 * La lista de una sesión: quién no puede asistir y por qué (RF70), retirar
 * a alguien (RF69), completar la lista (RF28) y verificar el formato del
 * grupo (RF71).
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
    $this->servicio = app(SolicitudService::class);
    $this->docente = User::factory()->docente()->habilitado()->create();
    $this->administrativo = User::factory()->administrativo()->create();
    $this->coordinadora = User::factory()->coordinador()->create();
});

function sesionDe(User $docente, array $estudianteIds): Solicitud
{
    return app(SolicitudService::class)->crear($docente, sesionCon($estudianteIds));
}

// ---------------------------------------------------------------------
// Quién no puede asistir (RF70)
// ---------------------------------------------------------------------

it('enseña al docente quién de su sesión no puede ingresar y por qué', function (): void {
    $lista = User::factory()->estudiante()->habilitado()->create(['nombre' => 'Ana Lista']);
    $sinFormato = User::factory()->estudiante()->create(['nombre' => 'Beto Sinformato']);
    $bloqueado = User::factory()->estudiante()->habilitado()->create(['nombre' => 'Caro Bloqueada']);
    app(BloqueoService::class)->bloquear($bloqueado, 'Daño de un simulador', $this->coordinadora);
    $sesion = sesionDe($this->docente, [$lista->id, $sinFormato->id, $bloqueado->id]);

    Livewire::actingAs($this->docente)
        ->test(ParticipantesDeLaSesion::class, ['solicitud' => $sesion])
        ->assertSee('2 no pueden ingresar.')
        ->assertSee('No tiene el formato de confidencialidad al día')
        ->assertSee('Bloqueado por coordinación: Daño de un simulador')
        ->assertSee('Puede ingresar');
});

it('avisa si el docente mismo no puede ingresar, sin cancelar la sesión', function (): void {
    $docente = User::factory()->docente()->create();
    $sesion = sesionDe($docente, idsDeEstudiantes(1));

    Livewire::actingAs($docente)
        ->test(ParticipantesDeLaSesion::class, ['solicitud' => $sesion])
        ->assertSee('coordinación decide si se reprograma o se sustituye al docente');

    expect($sesion->fresh()->estado)->toBe(EstadoSolicitud::Pendiente);
});

it('no deja ver la lista de una sesión ajena a otro docente', function (): void {
    $sesion = sesionDe($this->docente, idsDeEstudiantes(1));

    $this->actingAs(User::factory()->docente()->create())
        ->get(route('panel.solicitudes.participantes', $sesion))
        ->assertForbidden();

    $this->actingAs($this->administrativo)
        ->get(route('panel.solicitudes.participantes', $sesion))
        ->assertOk();
});

it('enseña en el tablero del día cuántos no pueden ingresar', function (): void {
    $sesion = sesionDe($this->docente, [User::factory()->estudiante()->create()->id, ...idsDeEstudiantes(1)]);
    $sesion->update(['estado' => EstadoSolicitud::Aprobada]);
    Preparacion::factory()->create(['solicitud_id' => $sesion->id, 'sala_id' => null]);

    Livewire::actingAs($this->administrativo)
        ->test(TableroDiario::class)
        ->set('fecha', $sesion->fecha->format('Y-m-d'))
        ->assertSee('2 estudiantes no pueden ingresar.');
});

// ---------------------------------------------------------------------
// Retirar (RF69) y completar la lista (RF28)
// ---------------------------------------------------------------------

it('retira a un estudiante con motivo y responsable, sin borrarlo de la lista', function (): void {
    $ids = idsDeEstudiantes(3);
    $sesion = sesionDe($this->docente, $ids);
    $retirado = User::findOrFail($ids[0]);

    $this->servicio->retirarEstudiante($sesion, $retirado, 'Llegó sin uniforme', $this->administrativo);

    $fila = $sesion->estudiantes()->whereKey($retirado->id)->firstOrFail()->participacion;

    expect($fila->fueRetirado())->toBeTrue()
        ->and($fila->retirado_por)->toBe($this->administrativo->id)
        ->and($fila->motivo_retiro)->toBe('Llegó sin uniforme')
        ->and($sesion->estudiantes()->count())->toBe(3)
        ->and($sesion->fresh()->cantidad_estudiantes)->toBe(2);
});

it('no retira sin motivo, ni dos veces, ni a quien no está en la lista', function (): void {
    $ids = idsDeEstudiantes(1);
    $sesion = sesionDe($this->docente, $ids);
    $estudiante = User::findOrFail($ids[0]);

    expect(fn () => $this->servicio->retirarEstudiante($sesion, $estudiante, ' ', $this->docente))
        ->toThrow(SolicitudInvalida::class, 'motivo');

    $this->servicio->retirarEstudiante($sesion, $estudiante, 'Motivo', $this->docente);

    expect(fn () => $this->servicio->retirarEstudiante($sesion, $estudiante, 'Otra vez', $this->docente))
        ->toThrow(SolicitudInvalida::class, 'fue retirado')
        ->and(fn () => $this->servicio->retirarEstudiante($sesion, User::factory()->estudiante()->create(), 'X', $this->docente))
        ->toThrow(SolicitudInvalida::class, 'no está en la lista');
});

it('no vuelve a agregar a un retirado, porque el retiro queda registrado', function (): void {
    $ids = idsDeEstudiantes(1);
    $sesion = sesionDe($this->docente, $ids);
    $this->servicio->retirarEstudiante($sesion, User::findOrFail($ids[0]), 'Motivo', $this->docente);

    expect(fn () => $this->servicio->agregarEstudiantes($sesion, $ids, $this->docente))
        ->toThrow(SolicitudInvalida::class, 'fue retirado');
});

it('completa la lista sin pasar la capacidad del escenario', function (): void {
    $caso = CasoClinico::factory()->conCapacidad(3)->create();
    $datos = sesionCon(idsDeEstudiantes(2));
    $sesion = $this->servicio->crear($this->docente, new DatosNuevaSolicitud(
        materiaId: $datos->materiaId, casoClinicoId: $caso->id, tipo: $datos->tipo, fecha: $datos->fecha,
        horaInicio: $datos->horaInicio, horaFin: $datos->horaFin, grupo: 'A', estudianteIds: $datos->estudianteIds,
    ));

    $this->servicio->agregarEstudiantes($sesion, idsDeEstudiantes(1), $this->docente);

    expect($sesion->fresh()->cantidad_estudiantes)->toBe(3)
        ->and(fn () => $this->servicio->agregarEstudiantes($sesion, idsDeEstudiantes(1), $this->docente))
        ->toThrow(CapacidadDeEstudiantesExcedida::class);
});

it('no cambia la lista de una sesión que ya pasó o fue rechazada', function (): void {
    $ids = idsDeEstudiantes(1);
    $pasada = sesionDe($this->docente, $ids);
    $pasada->update(['fecha' => now()->subDay()->format('Y-m-d')]);
    $rechazada = sesionDe($this->docente, idsDeEstudiantes(1));
    $rechazada->update(['estado' => EstadoSolicitud::Rechazada]);

    expect(fn () => $this->servicio->retirarEstudiante($pasada, User::findOrFail($ids[0]), 'X', $this->docente))
        ->toThrow(SolicitudInvalida::class, 'ya no se puede cambiar')
        ->and(fn () => $this->servicio->agregarEstudiantes($rechazada, idsDeEstudiantes(1), $this->docente))
        ->toThrow(SolicitudInvalida::class, 'ya no se puede cambiar');
});

it('no deja a otro docente cambiar la lista ni llamando al Service', function (): void {
    $sesion = sesionDe($this->docente, idsDeEstudiantes(1));

    expect(fn () => $this->servicio->agregarEstudiantes($sesion, idsDeEstudiantes(1), User::factory()->docente()->create()))
        ->toThrow(AuthorizationException::class);
});

it('retira y agrega desde la pantalla', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana Ruiz']);
    $luis = User::factory()->estudiante()->create(['nombre' => 'Luis Mora']);
    $sesion = sesionDe($this->docente, [$ana->id]);

    Livewire::actingAs($this->docente)
        ->test(ParticipantesDeLaSesion::class, ['solicitud' => $sesion])
        ->call('pedirMotivo', $ana->id)
        ->call('retirar', $ana->id)
        ->assertHasErrors(['motivoRetiro' => 'required'])
        ->set('motivoRetiro', 'Incapacidad médica')
        ->call('retirar', $ana->id)
        ->assertHasNoErrors()
        ->assertSee('Incapacidad médica')
        ->set('busquedaEstudiante', 'Luis')
        ->call('agregar', $luis->id)
        ->assertSee('Luis Mora');

    expect($sesion->estudiantesPresentes()->pluck('users.id')->all())->toBe([$luis->id]);
});

// ---------------------------------------------------------------------
// Verificar el formato del grupo (RF71) y por materia (RF53)
// ---------------------------------------------------------------------

it('acota el estado del formato a los estudiantes y el docente de una sesión', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana De La Sesion']);
    $retirado = User::factory()->estudiante()->create(['nombre' => 'Retirado De La Sesion']);
    User::factory()->estudiante()->create(['nombre' => 'Otro Estudiante']);
    $sesion = sesionDe($this->docente, [$ana->id, $retirado->id]);
    $this->servicio->retirarEstudiante($sesion, $retirado, 'Motivo', $this->docente);

    Livewire::actingAs($this->administrativo)
        ->test(EstadoDeFirmantes::class, ['sesion' => $sesion->id])
        ->set('sesion', $sesion->id)
        ->assertSee('Ana De La Sesion')
        ->assertSee($this->docente->nombre)
        ->assertDontSee('Retirado De La Sesion')
        ->assertDontSee('Otro Estudiante');
});

it('acota el estado del formato a quienes van o dictan sesiones de una materia', function (): void {
    $ana = User::factory()->estudiante()->create(['nombre' => 'Ana De Esta Materia']);
    User::factory()->estudiante()->create(['nombre' => 'Otro Estudiante']);
    $sesion = sesionDe($this->docente, [$ana->id]);

    Livewire::actingAs($this->administrativo)
        ->test(EstadoDeFirmantes::class)
        ->set('materia', $sesion->materia_id)
        ->assertSee('Ana De Esta Materia')
        ->assertDontSee('Otro Estudiante');
});

// ---------------------------------------------------------------------
// Evaluación (RF45)
// ---------------------------------------------------------------------

it('no deja evaluar a quien no puede ingresar', function (): void {
    $evaluacion = Evaluacion::factory()->create();
    $sinFormato = User::factory()->estudiante()->create(['nombre' => 'Beto Sinformato']);

    expect(fn () => app(EvaluacionService::class)->agregarEstudiante($evaluacion, $sinFormato))
        ->toThrow(EvaluacionInvalida::class, 'No se puede evaluar a Beto Sinformato: no tiene el formato de confidencialidad al día.');
});
