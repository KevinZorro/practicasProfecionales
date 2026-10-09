<?php

declare(strict_types=1);

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Enums\Rol;
use App\Mail\SincronizacionDetenidaMail;
use App\Models\AsignacionDeRol;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\FuenteInstitucional;
use App\Services\FuenteInstitucionalSimulada;
use App\Services\PersonaInstitucional;
use App\Services\ResultadoSincronizacion;
use App\Services\UsuarioSyncService;
use Database\Seeders\RolSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    Mail::fake();
    // Sin umbral, salvo en los tests que lo prueban.
    config()->set('laboratorio.sincronizacion.umbral_desactivacion', 100);
});

/**
 * Pone la fuente que verá la sincronización.
 *
 * @param  list<PersonaInstitucional>  $personas
 */
function laFuenteDice(array $personas): void
{
    app()->instance(FuenteInstitucional::class, new class($personas) implements FuenteInstitucional
    {
        /** @param  list<PersonaInstitucional>  $personas */
        public function __construct(private readonly array $personas) {}

        public function personas(): array
        {
            return $this->personas;
        }
    });
}

function personaInstitucional(
    string $documento,
    string $email,
    OrigenUsuario $vinculacion = OrigenUsuario::Matriculado,
    string $programa = 'Enfermería',
    bool $vigente = true,
    string $nombre = 'Persona de la institución',
): PersonaInstitucional {
    return new PersonaInstitucional($documento, 'C-'.$documento, $nombre, $email, $programa, $vinculacion, $vigente);
}

function sincronizar(): ResultadoSincronizacion
{
    return app(UsuarioSyncService::class)->sincronizar();
}

// ---------------------------------------------------------------------
// Altas y cambios
// ---------------------------------------------------------------------

it('crea las cuentas de las personas vigentes de los programas del laboratorio, con su rol permanente', function (): void {
    laFuenteDice([
        personaInstitucional('1001', 'Estudiante@Ejemplo.edu.co'),
        personaInstitucional('2002', 'docente@ejemplo.edu.co', OrigenUsuario::Contratado, 'Regencia en Farmacia'),
    ]);

    $resultado = sincronizar();

    $estudiante = User::where('documento', '1001')->firstOrFail();
    $docente = User::where('documento', '2002')->firstOrFail();

    expect($resultado->creadas)->toBe(2)
        ->and($estudiante->email)->toBe('estudiante@ejemplo.edu.co')
        ->and($estudiante->origen)->toBe(OrigenUsuario::Matriculado)
        ->and($estudiante->programa)->toBe('Enfermería')
        ->and($estudiante->hasRole(Rol::Estudiante->value))->toBeTrue()
        ->and($docente->hasRole(Rol::Docente->value))->toBeTrue()
        ->and($estudiante->roles()->first()->pivot->hasta)->toBeNull();
});

it('deja el rastro de la asignación sin nadie que la haya hecho', function (): void {
    laFuenteDice([personaInstitucional('1001', 'estudiante@ejemplo.edu.co')]);

    sincronizar();

    $asignacion = AsignacionDeRol::sole();
    expect($asignacion->asignado_por)->toBeNull()
        ->and($asignacion->motivo)->toContain('sincronización');
});

it('no trae a quien no está vigente ni a quien es de un programa que no usa el laboratorio', function (): void {
    laFuenteDice([
        personaInstitucional('1001', 'egresado@ejemplo.edu.co', vigente: false),
        personaInstitucional('1002', 'sistemas@ejemplo.edu.co', programa: 'Ingeniería de Sistemas'),
    ]);

    expect(sincronizar()->creadas)->toBe(0)
        ->and(User::count())->toBe(0);
});

it('reconoce el programa sin importar tildes ni mayúsculas', function (): void {
    laFuenteDice([personaInstitucional('1001', 'a@ejemplo.edu.co', programa: '  ENFERMERIA ')]);

    expect(sincronizar()->creadas)->toBe(1);
});

it('actualiza los datos de quien ya tenía cuenta, encontrándolo por documento', function (): void {
    $cuenta = User::factory()->estudiante()->create(['documento' => '1001', 'nombre' => 'Nombre viejo']);
    laFuenteDice([personaInstitucional('1001', 'nuevo@ejemplo.edu.co', nombre: 'Nombre nuevo')]);

    $resultado = sincronizar();

    expect($resultado->actualizadas)->toBe(1)
        ->and($resultado->creadas)->toBe(0)
        ->and($cuenta->fresh()->nombre)->toBe('Nombre nuevo')
        ->and($cuenta->fresh()->email)->toBe('nuevo@ejemplo.edu.co');
});

it('no vuelve a dar un rol que ya tiene, ni deja rastro de más', function (): void {
    laFuenteDice([personaInstitucional('1001', 'a@ejemplo.edu.co')]);

    sincronizar();
    sincronizar();

    expect(AsignacionDeRol::count())->toBe(1);
});

it('no revoca ni toca un rol temporal que dio el ADMIN', function (): void {
    $admin = User::factory()->admin()->create();
    $pasante = User::factory()->estudiante()->create(['documento' => '1001']);
    app(AsignacionDeRolService::class)->asignar($admin, $pasante, Rol::Administrativo, now()->addMonth()->toDateString());
    laFuenteDice([personaInstitucional('1001', $pasante->email), personaInstitucional((string) $admin->documento, $admin->email, OrigenUsuario::Contratado)]);

    sincronizar();

    expect($pasante->fresh()->hasRole(Rol::Administrativo->value))->toBeTrue();
});

// ---------------------------------------------------------------------
// Bajas: inactiva, nunca borra
// ---------------------------------------------------------------------

it('desactiva sin borrar a quien desaparece de la fuente o deja de estar vigente', function (): void {
    $sigue = User::factory()->estudiante()->create(['documento' => '1001']);
    $seFue = User::factory()->estudiante()->create(['documento' => '1002']);
    $egreso = User::factory()->estudiante()->create(['documento' => '1003']);
    laFuenteDice([
        personaInstitucional('1001', $sigue->email),
        personaInstitucional('1003', $egreso->email, vigente: false),
    ]);

    $resultado = sincronizar();

    expect($resultado->desactivadas)->toBe(2)
        ->and($sigue->fresh()->estado)->toBe(EstadoUsuario::Activo)
        ->and($seFue->fresh()->estado)->toBe(EstadoUsuario::Inactivo)
        ->and($egreso->fresh()->estado)->toBe(EstadoUsuario::Inactivo)
        ->and(User::count())->toBe(3);
});

it('reactiva a quien vuelve a estar vigente', function (): void {
    $cuenta = User::factory()->estudiante()->inactivo()->create(['documento' => '1001']);
    laFuenteDice([personaInstitucional('1001', $cuenta->email)]);

    expect(sincronizar()->reactivadas)->toBe(1)
        ->and($cuenta->fresh()->estado)->toBe(EstadoUsuario::Activo);
});

it('no toca las cuentas creadas en la plataforma, ni aunque coincida el correo', function (): void {
    $manual = User::factory()->manual()->create(['nombre' => 'Ingeniero externo', 'email' => 'ingeniero@ejemplo.edu.co']);
    $otraManual = User::factory()->manual()->create();
    laFuenteDice([personaInstitucional('1001', 'INGENIERO@ejemplo.edu.co', nombre: 'Otro nombre')]);

    $resultado = sincronizar();

    expect($resultado->creadas)->toBe(0)
        ->and($manual->fresh()->nombre)->toBe('Ingeniero externo')
        ->and($manual->fresh()->roles)->toBeEmpty()
        ->and($otraManual->fresh()->estado)->toBe(EstadoUsuario::Activo);
});

it('no levanta la deshabilitación que puso el ADMIN aunque la persona siga vigente', function (): void {
    $cuenta = User::factory()->estudiante()->deshabilitado()->create(['documento' => '1001']);
    laFuenteDice([personaInstitucional('1001', $cuenta->email)]);

    sincronizar();

    expect($cuenta->fresh()->estaDeshabilitado())->toBeTrue();
});

// ---------------------------------------------------------------------
// El freno (RF20)
// ---------------------------------------------------------------------

it('se frena sin cambiar nada y avisa a cada ADMIN si fuera a desactivar más del umbral', function (): void {
    config()->set('laboratorio.sincronizacion.umbral_desactivacion', 10);
    $estudiantes = User::factory()->estudiante()->count(4)->create();
    User::factory()->admin()->manual()->count(2)->create();
    User::factory()->admin()->manual()->deshabilitado()->create();
    // La vista llega a medio cargar: solo trae a uno de los cuatro.
    laFuenteDice([personaInstitucional((string) $estudiantes[0]->documento, $estudiantes[0]->email)]);

    $resultado = sincronizar();

    expect($resultado->detenida)->toBeTrue()
        ->and($resultado->resumen())->toContain('iba a desactivar 3 de 4')
        ->and(User::where('estado', EstadoUsuario::Inactivo)->count())->toBe(0);
    Mail::assertQueued(SincronizacionDetenidaMail::class, 2);
});

it('aplica los cambios si las desactivaciones no pasan del umbral', function (): void {
    config()->set('laboratorio.sincronizacion.umbral_desactivacion', 10);
    $estudiantes = User::factory()->estudiante()->count(10)->create();
    laFuenteDice($estudiantes->skip(1)->map(fn (User $u) => personaInstitucional((string) $u->documento, $u->email))->values()->all());

    $resultado = sincronizar();

    expect($resultado->detenida)->toBeFalse()
        ->and($resultado->desactivadas)->toBe(1);
    Mail::assertNothingQueued();
});

// ---------------------------------------------------------------------
// La fuente simulada y el comando
// ---------------------------------------------------------------------

it('lee la fuente simulada del archivo del repositorio', function (): void {
    $personas = (new FuenteInstitucionalSimulada(database_path('datos/institucional-simulada.json')))->personas();

    expect($personas)->not->toBeEmpty()
        ->each->toBeInstanceOf(PersonaInstitucional::class);
});

it('corre la sincronización con la fuente simulada desde el comando', function (): void {
    app()->forgetInstance(FuenteInstitucional::class);

    $this->artisan('usuarios:sincronizar')
        ->expectsOutputToContain('5 creadas')
        ->assertSuccessful();

    expect(User::where('email', 'gloriaev@ejemplo.edu.co')->firstOrFail()->hasRole(Rol::Docente->value))->toBeTrue()
        ->and(User::where('email', 'mariafo@ejemplo.edu.co')->exists())->toBeFalse();
});

it('falla con un mensaje claro si se pide una fuente que todavía no existe', function (): void {
    config()->set('laboratorio.sincronizacion.fuente', 'institucional');

    app(FuenteInstitucional::class);
})->throws(RuntimeException::class, 'solo está la simulada');

it('no programa la sincronización salvo que se active', function (bool $programada): void {
    config()->set('laboratorio.sincronizacion.programada', $programada);

    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'usuarios:sincronizar'));

    expect($evento)->not->toBeNull()
        ->and($evento->filtersPass(app()))->toBe($programada);
})->with([true, false]);
