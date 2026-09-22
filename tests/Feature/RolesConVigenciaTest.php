<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\Rol;
use App\Exceptions\AsignacionDeRolInvalida;
use App\Livewire\Usuario\RolesDeUsuarios;
use App\Models\AsignacionDeRol;
use App\Models\Preparacion;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\PreparacionService;
use App\Support\RolActivo;
use Database\Seeders\RolSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(AsignacionDeRolService::class);
    $this->admin = User::factory()->admin()->create();
});

/** Un usuario sin ningún rol, que es a quien se le asigna uno temporal. */
function pasante(string $nombre = 'Pau Pasante'): User
{
    return User::factory()->create(['nombre' => $nombre]);
}

/** Roles vigentes leídos de cero, como los leería una petición nueva. */
function tieneElRol(User $usuario, Rol $rol): bool
{
    return $usuario->fresh()->hasRole($rol->value);
}

// ---------------------------------------------------------------------
// El vencimiento, sin que corra nada
// ---------------------------------------------------------------------

it('deja de dar permisos al día siguiente sin que corra ningún job', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->toDateString());

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeTrue();

    // Ni scheduler, ni comando, ni listener: solo pasa el tiempo.
    $this->travelTo(now()->addDay()->startOfDay());

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeFalse()
        ->and($pasante->fresh()->can('viewAny', Preparacion::class))->toBeFalse();
});

it('vale el día entero de su fecha de fin', function (string $momento): void {
    $pasante = pasante();
    $hasta = now()->addDays(3);
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, $hasta->toDateString());

    $this->travelTo($hasta->copy()->setTimeFromTimeString($momento));

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeTrue();
})->with([
    'primer minuto' => '00:00:00',
    'media tarde' => '15:30:00',
    'ocho de la noche' => '20:00:00',
    'último segundo' => '23:59:59',
]);

it('corta la vigencia en la hora de Colombia', function (): void {
    // Si se pierde APP_TIMEZONE, config/app.php cae a UTC y la frontera del
    // día se mueve cinco horas: un rol asignado a las 8 de la noche nacería
    // valiendo desde mañana.
    expect(config('app.timezone'))->toBe('America/Bogota');
});

it('no vence un rol permanente', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo);

    $this->travelTo(now()->addYear());

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeTrue();
});

it('no da permisos antes de su fecha de inicio si la base los trae del pasado', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo);

    $this->travelTo(now()->subDay());

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeFalse();
});

// ---------------------------------------------------------------------
// Revocación anticipada
// ---------------------------------------------------------------------

it('revoca con efecto en la misma petición, no al final del día', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->addMonth()->toDateString());

    expect($pasante->can('viewAny', Preparacion::class))->toBeTrue();

    $this->servicio->revocar($this->admin, $pasante, Rol::Administrativo);

    // Misma instancia y mismo segundo: no hace falta esperar a medianoche
    // ni recargar al usuario.
    expect($pasante->hasRole(Rol::Administrativo->value))->toBeFalse()
        ->and($pasante->can('viewAny', Preparacion::class))->toBeFalse();
});

it('borra la fila del pivote al revocar y deja el rastro', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->addMonth()->toDateString());

    $this->servicio->revocar($this->admin, $pasante, Rol::Administrativo, 'Terminó la pasantía');

    $pivote = config('permission.table_names.model_has_roles');

    expect(DB::table($pivote)->where('model_id', $pasante->id)->count())->toBe(0);

    $rastro = AsignacionDeRol::where('user_id', $pasante->id)->first();

    expect($rastro->revocada_at)->not->toBeNull()
        ->and($rastro->revocada_por)->toBe($this->admin->id)
        ->and($rastro->motivo)->toBe('Terminó la pasantía')
        ->and($rastro->estaRevocada())->toBeTrue();
});

it('no revoca un rol que la persona no tiene', function (): void {
    expect(fn () => $this->servicio->revocar($this->admin, pasante(), Rol::Coordinador))
        ->toThrow(AsignacionDeRolInvalida::class);
});

it('deja rastro al revocar un rol repartido antes de que existiera el historial', function (): void {
    // Los roles del seeder se asignan con assignRole() y no pasan por el
    // Service, así que no tienen fila de historial.
    $administrativo = User::factory()->administrativo()->create();

    expect(AsignacionDeRol::where('user_id', $administrativo->id)->count())->toBe(0);

    $this->servicio->revocar($this->admin, $administrativo, Rol::Administrativo);

    $rastro = AsignacionDeRol::where('user_id', $administrativo->id)->sole();

    expect($rastro->revocada_por)->toBe($this->admin->id)
        ->and($rastro->asignado_por)->toBeNull();
});

// ---------------------------------------------------------------------
// Asignación
// ---------------------------------------------------------------------

it('asigna un rol temporal con su fecha de fin y su responsable', function (): void {
    $pasante = pasante();
    $hasta = now()->addWeeks(2)->toDateString();

    $asignacion = $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, $hasta);

    expect($asignacion->hasta->toDateString())->toBe($hasta)
        ->and($asignacion->asignado_por)->toBe($this->admin->id)
        ->and($asignacion->esTemporal())->toBeTrue()
        ->and($this->servicio->vigenciaDe($pasante, Rol::Administrativo)->toDateString())->toBe($hasta);
});

it('exige motivo al elevar a coordinador', function (): void {
    expect(fn () => $this->servicio->asignar($this->admin, pasante(), Rol::Coordinador, now()->addDay()->toDateString()))
        ->toThrow(AsignacionDeRolInvalida::class, 'motivo');
});

it('no exige motivo para los demás roles', function (): void {
    $asignacion = $this->servicio->asignar($this->admin, pasante(), Rol::Administrativo, now()->addDay()->toDateString());

    expect($asignacion->motivo)->toBeNull();
});

it('guarda el motivo de la delegación', function (): void {
    $delegada = User::factory()->docente()->create();

    $asignacion = $this->servicio->asignar(
        $this->admin,
        $delegada,
        Rol::Coordinador,
        now()->addDays(2)->toDateString(),
        'La coordinadora está en consejo de facultad',
    );

    expect($asignacion->motivo)->toBe('La coordinadora está en consejo de facultad');
});

it('no asigna un rol que ya está vigente', function (): void {
    $administrativo = User::factory()->administrativo()->create();

    expect(fn () => $this->servicio->asignar($this->admin, $administrativo, Rol::Administrativo))
        ->toThrow(AsignacionDeRolInvalida::class);
});

it('no asigna con una fecha de fin que ya pasó', function (): void {
    expect(fn () => $this->servicio->asignar($this->admin, pasante(), Rol::Administrativo, now()->subDay()->toDateString()))
        ->toThrow(AsignacionDeRolInvalida::class);
});

it('vuelve a asignar un rol que venció', function (): void {
    // La llave primaria del pivote es (role_id, model_id, model_type), así
    // que la fila vencida sigue ahí y un attach() reventaría contra ella.
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->toDateString());

    $this->travelTo(now()->addDays(30));

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeFalse();

    $this->servicio->asignar($this->admin, $pasante->fresh(), Rol::Administrativo, now()->addWeek()->toDateString());

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeTrue()
        ->and(AsignacionDeRol::where('user_id', $pasante->id)->count())->toBe(2);
});

it('solo el ADMIN reparte roles', function (string $rol): void {
    $otro = User::factory()->$rol()->create();

    expect(fn () => $this->servicio->asignar($otro, pasante(), Rol::Administrativo))
        ->toThrow(AuthorizationException::class);
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('solo el ADMIN revoca roles', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->addMonth()->toDateString());

    expect(fn () => $this->servicio->revocar(User::factory()->coordinador()->create(), $pasante, Rol::Administrativo))
        ->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Selector de vista (RF21)
// ---------------------------------------------------------------------

it('no ofrece en el selector un rol vencido', function (): void {
    $docente = User::factory()->docente()->create();
    $this->servicio->asignar($this->admin, $docente, Rol::Coordinador, now()->toDateString(), 'Consejo');

    expect(app(RolActivo::class)->disponibles($docente->fresh()))->toContain(Rol::Coordinador);

    $this->travelTo(now()->addDay());

    // RolActivo vive una petición (scoped): al día siguiente el usuario
    // llega con una nueva, así que se simula soltando la instancia.
    $this->app->forgetScopedInstances();

    expect(app(RolActivo::class)->disponibles($docente->fresh()))
        ->not->toContain(Rol::Coordinador)
        ->toContain(Rol::Docente);
});

it('no deja asumir un rol vencido aunque se arme la petición a mano', function (): void {
    $docente = User::factory()->docente()->create();
    $this->servicio->asignar($this->admin, $docente, Rol::Coordinador, now()->toDateString(), 'Consejo');

    $this->travelTo(now()->addDay());
    $this->app->forgetScopedInstances();

    expect(app(RolActivo::class)->establecer($docente->fresh(), Rol::Coordinador))->toBeFalse();
});

it('entra con el rol permanente, no con el temporal', function (): void {
    $docente = User::factory()->docente()->create();
    $this->servicio->asignar($this->admin, $docente, Rol::Coordinador, now()->addWeek()->toDateString(), 'Consejo');

    $rolActivo = app(RolActivo::class);
    $docente = $docente->fresh();

    // Coordinador va antes que docente en el enum, pero la elevación es
    // excepcional: la persona sigue entrando a su trabajo de siempre.
    expect($rolActivo->actual($docente))->toBe(Rol::Docente)
        ->and($rolActivo->esTemporal($docente, Rol::Coordinador))->toBeTrue()
        ->and($rolActivo->esTemporal($docente, Rol::Docente))->toBeFalse();
});

it('respeta el rol elegido en el selector aunque sea el temporal', function (): void {
    $docente = User::factory()->docente()->create();
    $this->servicio->asignar($this->admin, $docente, Rol::Coordinador, now()->addWeek()->toDateString(), 'Consejo');
    $docente = $docente->fresh();

    $rolActivo = app(RolActivo::class);
    $rolActivo->establecer($docente, Rol::Coordinador);

    expect($rolActivo->actual($docente))->toBe(Rol::Coordinador);
});

// ---------------------------------------------------------------------
// Pantalla
// ---------------------------------------------------------------------

it('avisa de los roles que vencen pronto y solo de esos', function (): void {
    $pronto = pasante('Pau Por Vencer');
    $this->servicio->asignar($this->admin, $pronto, Rol::Administrativo, now()->addDays(3)->toDateString());

    $lejano = pasante('Lola Lejana');
    $this->servicio->asignar($this->admin, $lejano, Rol::Administrativo, now()->addMonths(3)->toDateString());

    $permanente = pasante('Pedro Permanente');
    $this->servicio->asignar($this->admin, $permanente, Rol::Administrativo);

    $avisados = $this->servicio->proximosAVencer()->pluck('id');

    expect($avisados)->toContain($pronto->id)
        ->not->toContain($lejano->id)
        ->not->toContain($permanente->id);
});

it('enseña al ADMIN el aviso de vencimiento en la pantalla', function (): void {
    $pronto = pasante('Pau Por Vencer');
    $this->servicio->asignar($this->admin, $pronto, Rol::Administrativo, now()->addDays(3)->toDateString());

    Livewire::actingAs($this->admin)
        ->test(RolesDeUsuarios::class)
        ->assertSeeInOrder(['Vencen en los próximos', 'Pau Por Vencer']);
});

it('enseña quién tiene cada rol y cuál es temporal', function (): void {
    $pasante = pasante('Pau Pasante');
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->addWeek()->toDateString());

    Livewire::actingAs($this->admin)
        ->test(RolesDeUsuarios::class)
        ->assertSee('Pau Pasante')
        ->assertSee('temporal hasta');
});

it('deja al ADMIN asignar y revocar desde la pantalla', function (): void {
    $pasante = pasante();

    Livewire::actingAs($this->admin)
        ->test(RolesDeUsuarios::class)
        ->call('abrir', $pasante->id)
        ->set('rolElegido', Rol::Administrativo->value)
        ->set('hasta', now()->addWeek()->toDateString())
        ->call('asignar')
        ->assertHasNoErrors();

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test(RolesDeUsuarios::class)
        ->call('revocar', $pasante->id, Rol::Administrativo->value);

    expect(tieneElRol($pasante, Rol::Administrativo))->toBeFalse();
});

it('avisa en la pantalla si falta el motivo de la elevación', function (): void {
    $docente = User::factory()->docente()->create();

    Livewire::actingAs($this->admin)
        ->test(RolesDeUsuarios::class)
        ->call('abrir', $docente->id)
        ->set('rolElegido', Rol::Coordinador->value)
        ->set('hasta', now()->addDay()->toDateString())
        ->call('asignar')
        ->assertSet('errorDeRegla', fn (?string $error): bool => str_contains((string) $error, 'motivo'));

    expect(tieneElRol($docente, Rol::Coordinador))->toBeFalse();
});

it('no deja entrar a la pantalla de roles a nadie más que al ADMIN', function (string $rol): void {
    $this->actingAs(User::factory()->$rol()->create())
        ->get(route('panel.usuarios'))
        ->assertForbidden();
})->with(['coordinador', 'administrativo', 'docente', 'estudiante']);

it('deja entrar al ADMIN a la pantalla de roles', function (): void {
    $this->actingAs($this->admin)->get(route('panel.usuarios'))->assertOk();
});

// ---------------------------------------------------------------------
// Lo que queda a medias cuando vence un rol (RF37)
// ---------------------------------------------------------------------

it('deja la preparación a medias del pasante para que otro la continúe', function (): void {
    $pasante = pasante();
    $this->servicio->asignar($this->admin, $pasante, Rol::Administrativo, now()->toDateString());

    $preparacion = Preparacion::factory()->conSala()->create();
    app(PreparacionService::class)->cambiarEstado($preparacion, EstadoPreparacion::EnPreparacion, $pasante);

    $this->travelTo(now()->addDay());

    $otro = User::factory()->administrativo()->create();
    $preparacion = $preparacion->fresh();

    // El pasante ya no entra, pero la preparación sigue donde estaba y
    // cualquier otro administrativo la retoma: la Policy es por rol, no por
    // quién la empezó.
    expect(tieneElRol($pasante, Rol::Administrativo))->toBeFalse()
        ->and($preparacion->estado)->toBe(EstadoPreparacion::EnPreparacion)
        ->and($otro->can('preparar', $preparacion))->toBeTrue();

    app(PreparacionService::class)->cambiarEstado($preparacion, EstadoPreparacion::Preparado, $otro);

    expect($preparacion->fresh()->preparado_por)->toBe($otro->id);
});
