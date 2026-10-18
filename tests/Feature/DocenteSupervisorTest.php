<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\OrigenUsuario;
use App\Enums\Reporte;
use App\Enums\Rol;
use App\Exceptions\SupervisionInvalida;
use App\Livewire\Supervision\EvaluacionesDeMisDocentes;
use App\Livewire\Usuario\DocentesSupervisados;
use App\Models\AsignacionDeRol;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\FormatoConfidencialidad;
use App\Models\ItemInventario;
use App\Models\PeriodoAcademico;
use App\Models\RegistroDeBitacora;
use App\Models\Solicitud;
use App\Models\Supervision;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\GeneradorDeReportes;
use App\Services\SupervisionService;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/*
 * Docente supervisor (RF77): consulta los resultados de las evaluaciones
 * que hicieron los docentes a su cargo, y nada más.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(SupervisionService::class);
    $this->admin = User::factory()->admin()->create();
    $this->supervisor = User::factory()->supervisor()->create(['nombre' => 'Sara Supervisora']);
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Pone a un docente a cargo de un supervisor, como lo haría el ADMIN. */
function aCargoDe(User $supervisor, User $docente): void
{
    app(SupervisionService::class)->asignarDocente(User::factory()->admin()->create(), $supervisor, $docente);
}

/** Una evaluación finalizada que hizo un docente, con un estudiante y su resultado. */
function evaluacionDe(User $docente, string $estudiante, ?string $fecha = null): EvaluacionEstudiante
{
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => $fecha ?? now()->toDateString()]);
    $evaluacion = Evaluacion::factory()->finalizada()->create(['docente_id' => $docente->id, 'solicitud_id' => $solicitud->id]);

    return EvaluacionEstudiante::factory()->create([
        'evaluacion_id' => $evaluacion->id,
        'estudiante_id' => User::factory()->estudiante()->create(['nombre' => $estudiante])->id,
    ]);
}

/** Como en la petición siguiente de verdad: RolActivo vive una petición. */
function enLaSiguientePeticionDelSupervisor(User $usuario): void
{
    app()->forgetScopedInstances();
    test()->actingAs($usuario->fresh());
}

// ---------------------------------------------------------------------
// Lo que ve
// ---------------------------------------------------------------------

it('ve las evaluaciones de sus docentes y no las de otros', function (): void {
    $suyo = User::factory()->docente()->create(['nombre' => 'Docente Suyo']);
    $ajeno = User::factory()->docente()->create(['nombre' => 'Docente Ajeno']);
    aCargoDe($this->supervisor, $suyo);
    evaluacionDe($suyo, 'Estudiante Visible');
    evaluacionDe($ajeno, 'Estudiante Oculto');

    $this->actingAs($this->supervisor)->get(route('panel.supervision'))
        ->assertOk()
        ->assertSee('Estudiante Visible')
        ->assertSee('Docente Suyo')
        ->assertDontSee('Estudiante Oculto')
        ->assertDontSee('Docente Ajeno');
});

it('no ve las evaluaciones de los docentes de otro supervisor', function (): void {
    $otroSupervisor = User::factory()->supervisor()->create();
    $docenteDelOtro = User::factory()->docente()->create();
    aCargoDe($otroSupervisor, $docenteDelOtro);
    evaluacionDe($docenteDelOtro, 'Estudiante De Otro');

    $this->actingAs($this->supervisor)->get(route('panel.supervision'))
        ->assertOk()
        ->assertDontSee('Estudiante De Otro');
});

it('pedir el docente de otro supervisor por la URL no devuelve nada', function (): void {
    $suyo = User::factory()->docente()->create();
    $ajeno = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $suyo);
    evaluacionDe($suyo, 'Estudiante Suyo');
    evaluacionDe($ajeno, 'Estudiante Ajeno');

    $this->actingAs($this->supervisor)->get(route('panel.supervision', ['docente' => $ajeno->id]))
        ->assertOk()
        ->assertDontSee('Estudiante Ajeno')
        ->assertDontSee('Estudiante Suyo');

    $filtro = $this->servicio->filtroDe($this->supervisor->fresh(), null, $ajeno->id);
    expect($filtro->docenteIds)->toBe([]);
});

it('muestra el resultado, el intento, el checklist y las observaciones', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    $registro = evaluacionDe($docente, 'Ana Evaluada');
    $registro->update(['intento' => 2, 'observaciones' => 'Buena técnica de sutura.']);

    $this->actingAs($this->supervisor)->get(route('panel.supervision'))
        ->assertSee('Ana Evaluada')
        ->assertSee('intento 2')
        ->assertSee('Buena técnica de sutura.')
        ->assertSee($registro->resultado->etiqueta());
});

it('no muestra los borradores: solo evaluaciones finalizadas', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    $borrador = Evaluacion::factory()->create(['docente_id' => $docente->id]);
    EvaluacionEstudiante::factory()->create([
        'evaluacion_id' => $borrador->id,
        'estudiante_id' => User::factory()->estudiante()->create(['nombre' => 'Estudiante En Borrador'])->id,
    ]);

    $this->actingAs($this->supervisor)->get(route('panel.supervision'))->assertDontSee('Estudiante En Borrador');
});

it('en una sustitución ve la evaluación de quien dictó, no la del titular', function (): void {
    $titular = User::factory()->docente()->create();
    $reemplazo = User::factory()->docente()->create();
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $titular->id, 'docente_que_dicta_id' => $reemplazo->id]);
    $evaluacion = Evaluacion::factory()->finalizada()->create(['docente_id' => $reemplazo->id, 'solicitud_id' => $solicitud->id]);
    EvaluacionEstudiante::factory()->create([
        'evaluacion_id' => $evaluacion->id,
        'estudiante_id' => User::factory()->estudiante()->create(['nombre' => 'Estudiante Del Reemplazo'])->id,
    ]);
    $supervisorDelTitular = User::factory()->supervisor()->create();
    aCargoDe($supervisorDelTitular, $titular);
    aCargoDe($this->supervisor, $reemplazo);

    $this->actingAs($supervisorDelTitular)->get(route('panel.supervision'))->assertDontSee('Estudiante Del Reemplazo');
    $this->actingAs($this->supervisor->fresh())->get(route('panel.supervision'))->assertSee('Estudiante Del Reemplazo');
});

it('abre con el periodo vigente y deja consultar el historial', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    $anterior = PeriodoAcademico::factory()->create([
        'nombre' => '2026-1', 'abierto_at' => '2026-02-01', 'cerrado_at' => '2026-06-30',
    ]);
    PeriodoAcademico::factory()->create(['nombre' => '2026-2', 'abierto_at' => '2026-08-01']);
    evaluacionDe($docente, 'Estudiante Del Primero', '2026-03-10');
    evaluacionDe($docente, 'Estudiante Del Segundo', '2026-09-10');

    $this->actingAs($this->supervisor);

    $this->get(route('panel.supervision'))
        ->assertSee('Estudiante Del Segundo')
        ->assertDontSee('Estudiante Del Primero');

    $this->get(route('panel.supervision', ['periodo' => $anterior->id]))
        ->assertSee('Estudiante Del Primero')
        ->assertDontSee('Estudiante Del Segundo');

    $this->get(route('panel.supervision', ['periodo' => 'todos']))
        ->assertSee('Estudiante Del Primero')
        ->assertSee('Estudiante Del Segundo');
});

it('conserva el periodo y el docente en la URL', function (): void {
    Livewire::actingAs($this->supervisor)
        ->test(EvaluacionesDeMisDocentes::class)
        ->set('periodo', 'todos')
        ->assertSet('periodo', 'todos');

    $this->actingAs($this->supervisor)->get(route('panel.supervision', ['periodo' => 'todos']))
        ->assertSee('Todo el historial');
});

it('dice que no tiene docentes a cargo cuando todavía no se los asignan', function (): void {
    $this->actingAs($this->supervisor)->get(route('panel.supervision'))
        ->assertOk()
        ->assertSee('Todavía no tienes docentes a tu cargo');
});

it('no genera consultas N+1 al listar los resultados', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    foreach (range(1, 6) as $n) {
        evaluacionDe($docente, "Estudiante {$n}");
    }
    Model::preventLazyLoading();

    $this->actingAs($this->supervisor)->get(route('panel.supervision'))->assertOk();
});

// ---------------------------------------------------------------------
// Lo que no puede hacer
// ---------------------------------------------------------------------

it('no solicita escenarios, no evalúa y no ve el inventario ni nada más', function (): void {
    $supervisor = $this->supervisor;

    expect($supervisor->can('create', Solicitud::class))->toBeFalse()
        ->and($supervisor->can('verBandeja', Solicitud::class))->toBeFalse()
        ->and($supervisor->can('create', Evaluacion::class))->toBeFalse()
        ->and($supervisor->can('viewAny', Evaluacion::class))->toBeFalse()
        ->and($supervisor->can('viewAny', ItemInventario::class))->toBeFalse()
        ->and($supervisor->can('verCalendario', Solicitud::class))->toBeFalse()
        ->and($supervisor->can('generarReportes'))->toBeFalse();

    $this->actingAs($supervisor);

    foreach (['panel.mis-solicitudes', 'panel.solicitudes.nueva', 'panel.solicitudes', 'panel.evaluaciones', 'panel.inventario', 'panel.reportes', 'panel.calendario', 'panel.mi-formato', 'panel.usuarios'] as $ruta) {
        $this->get(route($ruta))->assertForbidden();
    }
});

it('no firma el formato de confidencialidad: no entra a la práctica', function (): void {
    expect(Rol::queFirmanElFormato())->not->toContain(Rol::Supervisor->value)
        ->and($this->supervisor->can('create', FormatoConfidencialidad::class))->toBeFalse();
});

it('no ve las evaluaciones de la pantalla del docente ni abre una', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    $registro = evaluacionDe($docente, 'Estudiante X');

    $this->actingAs($this->supervisor)->get(route('panel.evaluaciones.registro', $registro->evaluacion))->assertForbidden();
});

it('un docente normal no ve la pantalla del supervisor ni sus descargas', function (): void {
    $docente = User::factory()->docente()->create();
    $this->actingAs($docente);

    $this->get(route('panel.supervision'))->assertForbidden();
    $this->get(route('panel.supervision.excel'))->assertForbidden();
    $this->get(route('panel.supervision.pdf'))->assertForbidden();
});

it('solo el supervisor ve la entrada en el menú, y «Hoy» enseña su bloque', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    evaluacionDe($docente, 'Estudiante Contado');

    $hoy = $this->actingAs($this->supervisor)->get(route('panel.inicio'))->assertOk();

    $hoy->assertSee('Tus docentes supervisados')->assertSee('Evaluaciones de mis docentes');

    $this->actingAs(User::factory()->docente()->create())->get(route('panel.inicio'))
        ->assertDontSee('Tus docentes supervisados')
        ->assertDontSee('Evaluaciones de mis docentes');
});

// ---------------------------------------------------------------------
// Descargas
// ---------------------------------------------------------------------

it('descarga en Excel y PDF solo los registros de sus docentes', function (): void {
    $suyo = User::factory()->docente()->create(['nombre' => 'Docente Suyo']);
    $ajeno = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $suyo);
    evaluacionDe($suyo, 'Estudiante Suyo');
    evaluacionDe($ajeno, 'Estudiante Ajeno');
    Excel::fake();

    $this->actingAs($this->supervisor);
    $this->get(route('panel.supervision.excel'))->assertOk();
    $this->get(route('panel.supervision.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');

    Excel::assertDownloaded('resultados_de_evaluacion.xlsx');

    $filas = app(GeneradorDeReportes::class)->filas(Reporte::ResultadosDeEvaluacion, $this->servicio->filtroDe($this->supervisor->fresh()));
    expect($filas)->toHaveCount(1)
        ->and($filas->first()[3])->toBe('Docente Suyo')
        ->and($filas->first()[4])->toBe('Estudiante Suyo');
});

it('la descarga lleva los filtros de la pantalla y no deja pedir docentes ajenos', function (): void {
    $suyo = User::factory()->docente()->create();
    $ajeno = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $suyo);
    evaluacionDe($ajeno, 'Estudiante Ajeno');

    $filtro = $this->servicio->filtroDe($this->supervisor->fresh(), null, $ajeno->id);
    $filas = app(GeneradorDeReportes::class)->filas(Reporte::ResultadosDeEvaluacion, $filtro);

    expect($filas)->toHaveCount(0);
});

// ---------------------------------------------------------------------
// El ADMIN reparte
// ---------------------------------------------------------------------

it('el ADMIN asigna y retira docentes y queda en la bitácora', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Dora Docente']);

    $this->servicio->asignarDocente($this->admin, $this->supervisor, $docente);

    expect($this->supervisor->docentesSupervisados()->pluck('users.id')->all())->toBe([$docente->id])
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::DocenteSupervisadoAsignado)->count())->toBe(1);

    $this->servicio->retirarDocente($this->admin, $this->supervisor, $docente);

    expect($this->supervisor->docentesSupervisados()->count())->toBe(0)
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::DocenteSupervisadoRetirado)->count())->toBe(1);
});

it('solo el ADMIN reparte docentes: ni coordinación, ni administrativo, ni el supervisor', function (string $estado): void {
    $actor = User::factory()->{$estado}()->create();
    $docente = User::factory()->docente()->create();

    expect(fn () => $this->servicio->asignarDocente($actor, $this->supervisor, $docente))
        ->toThrow(AuthorizationException::class);

    expect(Supervision::count())->toBe(0);
})->with(['coordinador', 'administrativo', 'docente', 'supervisor']);

it('solo el ADMIN abre la pantalla de docentes a cargo', function (): void {
    $this->actingAs(User::factory()->coordinador()->create())
        ->get(route('panel.usuarios.supervision', $this->supervisor))->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('panel.usuarios.supervision', $this->supervisor))->assertOk()->assertSee('Sara Supervisora');
});

it('asigna y retira desde la pantalla del ADMIN, y ahí nadie más actúa', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Dora Docente']);

    Livewire::actingAs($this->admin)
        ->test(DocentesSupervisados::class, ['supervisor' => $this->supervisor])
        ->assertSee('Dora Docente')
        ->call('asignar', $docente->id)
        ->assertDispatched('aviso-de-la-accion', mensaje: 'Docente asignado.')
        ->call('retirar', $docente->id);

    expect(Supervision::count())->toBe(0);

    Livewire::actingAs(User::factory()->coordinador()->create())
        ->test(DocentesSupervisados::class, ['supervisor' => $this->supervisor])
        ->assertForbidden();
});

it('solo el ADMIN le da el rol de supervisor', function (): void {
    $docente = User::factory()->docente()->create();

    expect($docente->can('asignar', AsignacionDeRol::class))->toBeFalse()
        ->and(User::factory()->coordinador()->create()->can('asignar', AsignacionDeRol::class))->toBeFalse();

    app(AsignacionDeRolService::class)->asignar($this->admin, $docente, Rol::Supervisor);

    expect($docente->fresh()->hasRole(Rol::Supervisor->value))->toBeTrue();

    expect(fn () => app(AsignacionDeRolService::class)->asignar(User::factory()->coordinador()->create(), User::factory()->create(), Rol::Supervisor))
        ->toThrow(AuthorizationException::class);
});

it('un docente puede tener varios supervisores y un supervisor, varios docentes', function (): void {
    $otro = User::factory()->supervisor()->create();
    $docentes = User::factory()->docente()->count(7)->create();

    foreach ($docentes as $docente) {
        $this->servicio->asignarDocente($this->admin, $this->supervisor, $docente);
    }
    $this->servicio->asignarDocente($this->admin, $otro, $docentes->first());

    expect($this->supervisor->docentesSupervisados()->count())->toBe(7)
        ->and($docentes->first()->supervisores()->count())->toBe(2);
});

it('no asigna a quien no es docente, ni a un supervisor sin el rol, ni a uno mismo, ni dos veces', function (): void {
    $docente = User::factory()->docente()->create();
    $sinRol = User::factory()->create();

    expect(fn () => $this->servicio->asignarDocente($this->admin, $this->supervisor, User::factory()->estudiante()->create()))
        ->toThrow(SupervisionInvalida::class, 'rol de docente')
        ->and(fn () => $this->servicio->asignarDocente($this->admin, $sinRol, $docente))
        ->toThrow(SupervisionInvalida::class, 'supervisor')
        ->and(fn () => $this->servicio->asignarDocente($this->admin, $this->supervisor, $this->supervisor))
        ->toThrow(SupervisionInvalida::class, 'a sí mismo');

    $this->servicio->asignarDocente($this->admin, $this->supervisor, $docente);

    expect(fn () => $this->servicio->asignarDocente($this->admin, $this->supervisor, $docente))
        ->toThrow(SupervisionInvalida::class, 'ya está a cargo')
        ->and(fn () => $this->servicio->retirarDocente($this->admin, $this->supervisor, User::factory()->docente()->create()))
        ->toThrow(SupervisionInvalida::class, 'no está a cargo');
});

it('ofrece como candidatos solo docentes activos que todavía no están a su cargo', function (): void {
    $yaAsignado = User::factory()->docente()->create(['nombre' => 'Ya Asignado']);
    $libre = User::factory()->docente()->create(['nombre' => 'Libre Docente']);
    $inactivo = User::factory()->docente()->inactivo()->create(['nombre' => 'Inactivo Docente']);
    User::factory()->estudiante()->create(['nombre' => 'Un Estudiante']);
    aCargoDe($this->supervisor, $yaAsignado);

    $nombres = $this->servicio->candidatosPara($this->supervisor)->pluck('nombre')->all();

    expect($nombres)->toContain('Libre Docente')
        ->not->toContain('Ya Asignado')
        ->not->toContain('Inactivo Docente')
        ->not->toContain('Un Estudiante')
        ->not->toContain('Sara Supervisora');
});

// ---------------------------------------------------------------------
// La vigencia del rol (RF63, RF64)
// ---------------------------------------------------------------------

it('si el rol con fecha de fin vence, la Policy deniega sin que corra nada', function (): void {
    $docente = User::factory()->docente()->create();
    $temporal = User::factory()->create();
    app(AsignacionDeRolService::class)->asignar($this->admin, $temporal, Rol::Supervisor, now()->toDateString());
    aCargoDe($temporal, $docente);
    evaluacionDe($docente, 'Estudiante Temporal');

    expect($temporal->fresh()->can('supervisar', Evaluacion::class))->toBeTrue();
    $this->actingAs($temporal->fresh())->get(route('panel.supervision'))->assertOk()->assertSee('Estudiante Temporal');

    $this->travelTo(now()->addDay()->startOfDay());
    app()->forgetScopedInstances();

    expect($temporal->fresh()->can('supervisar', Evaluacion::class))->toBeFalse();
    $this->actingAs($temporal->fresh())->get(route('panel.supervision'))->assertForbidden();
});

it('sin el rol, las filas de agrupación que quedan no le dan acceso al servicio', function (): void {
    $docente = User::factory()->docente()->create();
    aCargoDe($this->supervisor, $docente);
    app(AsignacionDeRolService::class)->revocar($this->admin, $this->supervisor, Rol::Supervisor);

    expect(Supervision::count())->toBe(1)
        ->and(fn () => $this->servicio->filtroDe($this->supervisor->fresh()))
        ->toThrow(AuthorizationException::class);
});

it('una acción con el rol retirado y la pantalla abierta responde 403 por HTTP real', function (): void {
    // Con otro rol a mano: se corta la acción, no se cae a otro rol (RF21).
    $persona = User::factory()->supervisor()->docente()->create()->fresh();
    $docente = User::factory()->docente()->create();
    aCargoDe($persona, $docente);

    $this->actingAs($persona)->post(route('panel.rol-activo'), ['rol' => Rol::Supervisor->value]);
    $instantanea = instantaneaDeLaPantalla(route('panel.supervision'));

    // Control: con el rol vigente, la acción pasa.
    enLaSiguientePeticionDelSupervisor($persona);
    accionDeLivewire($instantanea)->assertOk();

    app(AsignacionDeRolService::class)->revocar($this->admin, $persona, Rol::Supervisor);
    enLaSiguientePeticionDelSupervisor($persona);

    accionDeLivewire($instantanea)
        ->assertForbidden()
        ->assertJsonPath('message', 'El rol con el que abriste esta pantalla ya no está vigente. Recarga la página.');
});

it('si el supervisor se queda sin ningún rol, la acción de la pantalla abierta también responde 403', function (): void {
    $this->actingAs($this->supervisor);
    $instantanea = instantaneaDeLaPantalla(route('panel.supervision'));

    app(AsignacionDeRolService::class)->revocar($this->admin, $this->supervisor, Rol::Supervisor);
    enLaSiguientePeticionDelSupervisor($this->supervisor);

    accionDeLivewire($instantanea)->assertForbidden();
});

it('el rol activo manda: como docente no ve la pantalla del supervisor', function (): void {
    $persona = User::factory()->supervisor()->docente()->create()->fresh();

    $this->actingAs($persona)->post(route('panel.rol-activo'), ['rol' => Rol::Docente->value]);
    $this->get(route('panel.supervision'))->assertForbidden();

    $this->post(route('panel.rol-activo'), ['rol' => Rol::Supervisor->value]);
    $this->get(route('panel.supervision'))->assertOk();
});

it('la sincronización institucional no da el rol de supervisor', function (): void {
    foreach (OrigenUsuario::cases() as $origen) {
        expect($origen->rolPermanente())->not->toBe(Rol::Supervisor);
    }
});
