<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\ResultadoEvaluacion;
use App\Enums\Rol;
use App\Enums\TipoSesion;
use App\Livewire\Bitacora\ConsultaDeBitacora;
use App\Models\Evaluacion;
use App\Models\FormatoConfidencialidad;
use App\Models\ItemInventario;
use App\Models\PlantillaConfidencialidad;
use App\Models\RegistroDeBitacora;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\BloqueoService;
use App\Services\ConfidencialidadService;
use App\Services\DatosReprogramacion;
use App\Services\DatosSesionApartada;
use App\Services\EvaluacionService;
use App\Services\InventarioService;
use App\Services\NovedadesDeSesionService;
use App\Services\PeriodoAcademicoService;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Bitácora de auditoría (RF62): cada acción queda con quién, cuándo, qué y
 * por qué, escrita por el Service en la misma transacción.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Mail::fake();
    $this->administrativo = User::factory()->administrativo()->create(['nombre' => 'Admin Istrativo']);
    $this->coordinadora = User::factory()->coordinador()->create(['nombre' => 'Coordi Nadora']);
    $this->docente = User::factory()->docente()->create(['nombre' => 'Doc Ente']);
});

function ultimaEntrada(): RegistroDeBitacora
{
    return RegistroDeBitacora::query()->latest('id')->firstOrFail();
}

it('registra la aprobación y el rechazo, con su fase y motivo', function (): void {
    $servicio = app(SolicitudService::class);
    $aprobada = Solicitud::factory()->revisada()->create(['docente_id' => $this->docente->id]);
    $servicio->aprobar($aprobada, $this->coordinadora);

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::SolicitudAprobada)
        ->user_id->toBe($this->coordinadora->id)
        ->and(ultimaEntrada()->auditable->is($aprobada))->toBeTrue()
        ->and(ultimaEntrada()->descripcion)->toContain('Doc Ente');

    $rechazada = Solicitud::factory()->create(['docente_id' => $this->docente->id]);
    $servicio->rechazar($rechazada, $this->administrativo, 'Sala inundada');

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::SolicitudRechazada)
        ->motivo->toBe('Sala inundada')
        ->and(ultimaEntrada()->descripcion)->toContain('en la revisión');
});

it('registra la sesión apartada, la reprogramación y la sustitución', function (): void {
    $sesion = app(RegistroPrevioService::class)->registrar(new DatosSesionApartada(
        ...materiaYCaso(),
        docenteId: $this->docente->id,
        tipo: TipoSesion::Practica,
        fecha: now()->addDays(3)->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        cantidadEstudiantes: 5,
    ), $this->administrativo);

    expect(ultimaEntrada()->accion)->toBe(AccionAuditada::SesionApartadaRegistrada);

    $novedades = app(NovedadesDeSesionService::class);
    $novedades->reprogramar($sesion, new DatosReprogramacion(
        fecha: now()->addDays(4)->format('Y-m-d'),
        horaInicio: '07:00',
        horaFin: '09:00',
        casoClinicoId: $sesion->caso_clinico_id,
        motivo: 'Aire acondicionado dañado',
        constanciaComunicacion: 'Llamada del 9/10',
    ), $this->administrativo);

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::SesionReprogramada)
        ->motivo->toBe('Aire acondicionado dañado')
        ->and(ultimaEntrada()->descripcion)->toContain('Llamada del 9/10');

    $novedades->sustituir($sesion->fresh(), User::factory()->docente()->create(['nombre' => 'Otro Docente']), 'Incapacidad', $this->administrativo);

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::DocenteSustituido)
        ->and(ultimaEntrada()->descripcion)->toContain('dicta Otro Docente en lugar de Doc Ente');
});

it('registra el retiro de un participante y el de unidades de inventario', function (): void {
    $servicio = app(SolicitudService::class);
    $ids = idsDeEstudiantes(1);
    $sesion = $servicio->crear($this->docente, sesionCon($ids));
    $servicio->retirarEstudiante($sesion, User::findOrFail($ids[0]), 'Llegó tarde', $this->docente);

    expect(ultimaEntrada())->accion->toBe(AccionAuditada::EstudianteRetirado)->motivo->toBe('Llegó tarde');

    $item = ItemInventario::factory()->create(['nombre' => 'Gasas', 'cantidad_total' => 10, 'cantidad_operativa' => 10]);
    app(InventarioService::class)->retirarUnidades($this->administrativo, $item, 3, 'Gasto en práctica');

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::UnidadesRetiradas)
        ->descripcion->toBe('Retiró 3 unidades operativas de Gasas.');
});

it('registra los cambios de rol y los bloqueos', function (): void {
    $admin = User::factory()->admin()->create();
    $pasante = User::factory()->create(['nombre' => 'Pa Sante']);
    $roles = app(AsignacionDeRolService::class);
    $roles->asignar($admin, $pasante, Rol::Administrativo, now()->addMonth()->toDateString());

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::RolAsignado)
        ->and(ultimaEntrada()->descripcion)->toContain('Administrativo a Pa Sante hasta el');

    $roles->revocar($admin, $pasante, Rol::Administrativo, 'Terminó la pasantía');

    expect(ultimaEntrada())->accion->toBe(AccionAuditada::RolRevocado)->motivo->toBe('Terminó la pasantía');

    $bloqueo = app(BloqueoService::class)->bloquear(User::factory()->estudiante()->create(), 'Daño de simulador', $this->coordinadora);
    app(BloqueoService::class)->levantar($bloqueo, 'Pagó el daño', $this->coordinadora);

    expect(RegistroDeBitacora::query()->where('accion', AccionAuditada::BloqueoRegistrado)->count())->toBe(1)
        ->and(ultimaEntrada()->accion)->toBe(AccionAuditada::BloqueoLevantado);
});

it('no deja entrada si la acción falla', function (): void {
    $pendiente = Solicitud::factory()->create();

    expect(fn () => app(SolicitudService::class)->aprobar($pendiente, $this->coordinadora))->toThrow(Exception::class)
        ->and(RegistroDeBitacora::count())->toBe(0);
});

it('enseña la bitácora a coordinación con filtros', function (): void {
    app(SolicitudService::class)->rechazar(Solicitud::factory()->create(), $this->administrativo, 'Sin sala');
    app(SolicitudService::class)->aprobar(Solicitud::factory()->revisada()->create(), $this->coordinadora);

    Livewire::actingAs($this->coordinadora)
        ->test(ConsultaDeBitacora::class)
        ->assertSee('2 registros')
        ->set('accion', AccionAuditada::SolicitudRechazada->value)
        ->assertSee('1 registro')
        ->assertSee('Motivo: Sin sala')
        ->set('accion', '')
        ->set('persona', 'coordi')
        ->assertSee('1 registro')
        ->assertSee('Aprobó');
});

it('no enseña la bitácora al administrativo ni al docente', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    $this->actingAs($usuario)->get(route('panel.bitacora'))->assertForbidden();
})->with([Rol::Administrativo, Rol::Docente, Rol::Estudiante]);

// ---------------------------------------------------------------------
// QA-10: verificación de formatos, periodos y evaluaciones
// ---------------------------------------------------------------------

/** Una entrega cargada del estudiante, con plantilla y periodo abiertos. */
function entregaCargadaParaAuditar(User $estudiante): FormatoConfidencialidad
{
    Storage::fake('local');
    PlantillaConfidencialidad::factory()->create();

    return app(ConfidencialidadService::class)->registrarEntrega($estudiante, UploadedFile::fake()->create('formato.pdf', 100, 'application/pdf'));
}

it('registra la verificación del formato de confidencialidad, con quién y a quién', function (): void {
    abrirPeriodo('2026-2');
    $estudiante = User::factory()->estudiante()->create(['nombre' => 'Est Udiante']);
    $entrega = entregaCargadaParaAuditar($estudiante);

    app(ConfidencialidadService::class)->verificar($entrega, $this->administrativo);

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::FormatoVerificado)
        ->user_id->toBe($this->administrativo->id)
        ->and(ultimaEntrada()->auditable->is($entrega))->toBeTrue()
        ->and(ultimaEntrada()->descripcion)->toContain('Est Udiante')->toContain('2026-2');
});

it('registra el rechazo del formato con su motivo, y el archivo no queda en la bitácora', function (): void {
    abrirPeriodo('2026-2');
    $entrega = entregaCargadaParaAuditar(User::factory()->estudiante()->create());

    app(ConfidencialidadService::class)->rechazar($entrega, $this->administrativo, 'Sin firma');

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::FormatoRechazado)
        ->motivo->toBe('Sin firma')
        ->and(ultimaEntrada()->descripcion)->not->toContain('.pdf');
});

it('registra la entrega en físico recibida en la puerta', function (): void {
    abrirPeriodo('2026-2');
    PlantillaConfidencialidad::factory()->create();
    $estudiante = User::factory()->estudiante()->create(['nombre' => 'Est Udiante']);

    app(ConfidencialidadService::class)->registrarEntregaFisica($estudiante, $this->administrativo);

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::EntregaFisicaRecibida)
        ->user_id->toBe($this->administrativo->id)
        ->and(ultimaEntrada()->descripcion)->toContain('Est Udiante');
});

it('no deja entrada del formato si la verificación se rechaza por estado', function (): void {
    abrirPeriodo('2026-2');
    $entrega = entregaCargadaParaAuditar(User::factory()->estudiante()->create());
    $servicio = app(ConfidencialidadService::class);
    $servicio->verificar($entrega, $this->administrativo);

    expect(fn () => $servicio->verificar($entrega->fresh(), $this->administrativo))->toThrow(Exception::class)
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::FormatoVerificado)->count())->toBe(1);
});

it('registra la apertura, el cierre y la reapertura del periodo académico', function (): void {
    $periodos = app(PeriodoAcademicoService::class);

    $periodo = $periodos->abrir('2027-1', $this->administrativo);
    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::PeriodoAbierto)
        ->user_id->toBe($this->administrativo->id)
        ->and(ultimaEntrada()->descripcion)->toContain('2027-1');

    $periodos->cerrar($periodo, $this->coordinadora);
    expect(ultimaEntrada())->accion->toBe(AccionAuditada::PeriodoCerrado)->user_id->toBe($this->coordinadora->id);

    $periodos->reabrir($periodo->fresh(), $this->coordinadora);
    expect(ultimaEntrada())->accion->toBe(AccionAuditada::PeriodoReabierto)
        ->and(RegistroDeBitacora::count())->toBe(3);
});

it('registra la finalización de una evaluación, con sus cifras', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Doc Ente']);
    $evaluacion = Evaluacion::factory()->create(['docente_id' => $docente->id]);
    $evaluacion->estudiantes()->create(['estudiante_id' => User::factory()->estudiante()->create()->id, 'resultado' => ResultadoEvaluacion::Aprobado, 'intento' => 1]);
    $evaluacion->estudiantes()->create(['estudiante_id' => User::factory()->estudiante()->create()->id, 'resultado' => ResultadoEvaluacion::NoAprobado, 'intento' => 1]);

    app(EvaluacionService::class)->finalizar($evaluacion, $docente);

    expect(ultimaEntrada())
        ->accion->toBe(AccionAuditada::EvaluacionFinalizada)
        ->user_id->toBe($docente->id)
        ->and(ultimaEntrada()->descripcion)->toContain('2 estudiantes')->toContain('1 aprobado');
});

it('la bitácora ofrece las acciones nuevas en su filtro', function (): void {
    Livewire::actingAs($this->coordinadora)
        ->test(ConsultaDeBitacora::class)
        ->assertSee('Formato de confidencialidad verificado')
        ->assertSee('Periodo académico abierto')
        ->assertSee('Evaluación finalizada');
});
