<?php

declare(strict_types=1);

use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSala;
use App\Events\SalaAsignada;
use App\Exceptions\AsignacionDeSalasInvalida;
use App\Exceptions\EstadoDeSalaInvalido;
use App\Livewire\Sala\EstadoDeSalas;
use App\Livewire\Solicitud\ExpedienteDeSolicitud;
use App\Models\CasoClinico;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\PreparacionService;
use App\Services\RegistroPrevioService;
use App\Services\RevisionDeSolicitudService;
use App\Services\SalaService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/*
 * RF30: la revisión de una solicitud verifica cruces de horario y el estado
 * del espacio físico (aire acondicionado, filtraciones, escenario
 * deshabilitado). El estado de cada sala es el de hoy, sin historial
 * (decisión del 2026-11-14), y lo anota el administrativo.
 */

beforeEach(function (): void {
    Event::fake([SalaAsignada::class]);
    $this->seed(RolSeeder::class);
    $this->fecha = now()->addDays(3)->format('Y-m-d');
});

// ---------------------------------------------------------------------
// El estado de la sala
// ---------------------------------------------------------------------

it('una sala nueva está operativa y sin nota', function (): void {
    $sala = Sala::factory()->create()->fresh();

    expect($sala->estado)->toBe(EstadoSala::Operativa)
        ->and($sala->novedad)->toBeNull();
});

it('anota la novedad con quién y cuándo', function (): void {
    $administrativo = User::factory()->administrativo()->create();
    $sala = Sala::factory()->create();

    app(SalaService::class)->cambiarEstado($sala, EstadoSala::ConNovedad, '  El aire no enfría.  ', $administrativo);

    $sala->refresh();
    expect($sala->estado)->toBe(EstadoSala::ConNovedad)
        ->and($sala->novedad)->toBe('El aire no enfría.')
        ->and($sala->estado_actualizado_por)->toBe($administrativo->id)
        ->and($sala->estado_actualizado_at)->not->toBeNull();
});

it('no deja una sala con novedad o fuera de servicio sin decir qué pasa', function (EstadoSala $estado): void {
    $sala = Sala::factory()->create();

    expect(fn () => app(SalaService::class)->cambiarEstado($sala, $estado, '   ', User::factory()->administrativo()->create()))
        ->toThrow(EstadoDeSalaInvalido::class);
    expect($sala->fresh()->estado)->toBe(EstadoSala::Operativa);
})->with([EstadoSala::ConNovedad, EstadoSala::FueraDeServicio]);

it('al volver a operativa borra la nota, que ya no describe nada', function (): void {
    $sala = Sala::factory()->fueraDeServicio()->create();

    app(SalaService::class)->cambiarEstado($sala, EstadoSala::Operativa, 'Lo que sea', User::factory()->administrativo()->create());

    expect($sala->fresh()->novedad)->toBeNull();
});

it('la base de datos rechaza una sala fuera de servicio sin nota, aunque se salte el Service', function (): void {
    $sala = Sala::factory()->create();

    expect(fn () => $sala->forceFill(['estado' => EstadoSala::FueraDeServicio, 'novedad' => null])->save())
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

it('administrativos, coordinación y el ADMIN abren el estado de las salas', function (string $rol): void {
    $this->actingAs(User::factory()->{$rol}()->create())->get(route('panel.salas'))->assertOk();
})->with(['administrativo', 'coordinador', 'admin']);

it('docentes y estudiantes no abren el estado de las salas', function (string $rol): void {
    $this->actingAs(User::factory()->{$rol}()->create())->get(route('panel.salas'))->assertForbidden();
})->with(['docente', 'estudiante']);

it('el administrativo marca una sala fuera de servicio desde la pantalla', function (): void {
    $sala = Sala::factory()->create();

    Livewire::actingAs(User::factory()->administrativo()->create())
        ->test(EstadoDeSalas::class)
        ->call('editar', $sala->id)
        ->set('estado', EstadoSala::FueraDeServicio->value)
        ->set('novedad', 'Filtración sobre la camilla.')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($sala->fresh()->estado)->toBe(EstadoSala::FueraDeServicio)
        ->and($sala->fresh()->novedad)->toBe('Filtración sobre la camilla.');
});

it('la pantalla pide la nota si falta, sin guardar', function (): void {
    $sala = Sala::factory()->create();

    Livewire::actingAs(User::factory()->administrativo()->create())
        ->test(EstadoDeSalas::class)
        ->call('editar', $sala->id)
        ->set('estado', EstadoSala::ConNovedad->value)
        ->call('guardar')
        ->assertHasErrors('novedad');

    expect($sala->fresh()->estado)->toBe(EstadoSala::Operativa);
});

it('un estado que la pantalla no ofrece es un error de validación, no un 500', function (): void {
    $sala = Sala::factory()->create();

    Livewire::actingAs(User::factory()->administrativo()->create())
        ->test(EstadoDeSalas::class)
        ->call('editar', $sala->id)
        ->set('estado', 'demolida')
        ->call('guardar')
        ->assertHasErrors('estado');
});

// ---------------------------------------------------------------------
// La preparación
// ---------------------------------------------------------------------

function montajeEnLaFecha(string $fecha): Preparacion
{
    $solicitud = Solicitud::factory()->aprobada()->create([
        'fecha' => $fecha,
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'cantidad_estudiantes' => 10,
    ]);

    return Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'estado' => EstadoPreparacion::Pendiente]);
}

it('no ofrece al preparar una sala fuera de servicio, y sí una con novedad', function (): void {
    $operativa = Sala::factory()->create();
    $conNovedad = Sala::factory()->conNovedad()->create();
    $fuera = Sala::factory()->fueraDeServicio()->create();

    $libres = app(PreparacionService::class)->salasLibresPara(montajeEnLaFecha($this->fecha))->pluck('id')->all();

    expect($libres)->toContain($operativa->id)
        ->toContain($conNovedad->id)
        ->not->toContain($fuera->id);
});

it('no asigna una sala fuera de servicio aunque llegue su id', function (): void {
    $fuera = Sala::factory()->fueraDeServicio()->create();
    $montaje = montajeEnLaFecha($this->fecha);

    expect(fn () => app(PreparacionService::class)->asignarSalas($montaje, [$fuera->id]))
        ->toThrow(AsignacionDeSalasInvalida::class, 'fuera de servicio');
    expect($montaje->salas()->count())->toBe(0);
    Event::assertNotDispatched(SalaAsignada::class);
});

// ---------------------------------------------------------------------
// La revisión
// ---------------------------------------------------------------------

it('al revisar avisa de otra sesión que se cruza en la franja', function (): void {
    Sala::factory()->count(2)->create();
    $otra = Solicitud::factory()->aprobada()->create(['fecha' => $this->fecha, 'hora_inicio' => '09:00', 'hora_fin' => '11:00']);
    $solicitud = Solicitud::factory()->create(['fecha' => $this->fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']);

    $avisos = app(RevisionDeSolicitudService::class)->advertencias($solicitud);

    expect($avisos)->toHaveCount(1)
        ->and($avisos[0])->toContain('1 sesión')->toContain($otra->casoClinico->nombre);
});

it('no se cuenta a sí misma como cruce ni avisa sin nada que avisar', function (): void {
    Sala::factory()->create();
    $solicitud = Solicitud::factory()->create(['fecha' => $this->fecha]);

    expect(app(RevisionDeSolicitudService::class)->advertencias($solicitud))->toBe([]);
});

it('avisa de las salas con novedad y de las fuera de servicio', function (): void {
    Sala::factory()->create();
    Sala::factory()->conNovedad('El aire no enfría.')->create(['nombre' => 'SIM-01']);
    Sala::factory()->fueraDeServicio('Filtración en el techo.')->create(['nombre' => 'SIM-02']);
    Sala::factory()->fueraDeServicio()->inactiva()->create(['nombre' => 'Retirada']);
    $solicitud = Solicitud::factory()->create(['fecha' => $this->fecha]);

    expect(app(RevisionDeSolicitudService::class)->advertencias($solicitud))->toBe([
        'SIM-01 tiene una novedad: El aire no enfría.',
        'SIM-02 está fuera de servicio y no se asignará: Filtración en el techo.',
    ]);
});

it('avisa cuando el escenario está deshabilitado', function (): void {
    Sala::factory()->create();
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto', 'activo' => false]);
    $solicitud = Solicitud::factory()->create(['fecha' => $this->fecha, 'caso_clinico_id' => $caso->id]);

    expect(app(RevisionDeSolicitudService::class)->advertencias($solicitud))
        ->toBe(['El escenario «Atención de parto» está deshabilitado.']);
});

it('una sala fuera de servicio no cuenta entre las que quedan libres', function (): void {
    Sala::factory()->create();
    Sala::factory()->fueraDeServicio()->create();
    Solicitud::factory()->aprobada()->create(['fecha' => $this->fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']);

    expect(app(RegistroPrevioService::class)->advertencias($this->fecha, '08:00', '10:00'))
        ->toContain('No quedaría ninguna sala libre: las salas en servicio ya están tomadas por sesiones aprobadas.');
});

it('ya aprobada o rechazada no avisa: no queda nada por decidir', function (string $estado): void {
    Sala::factory()->conNovedad()->create();
    $solicitud = Solicitud::factory()->{$estado}()->create(['fecha' => $this->fecha]);

    expect(app(RevisionDeSolicitudService::class)->advertencias($solicitud))->toBe([]);
})->with(['aprobada', 'rechazada']);

it('el expediente le muestra los avisos a quien revisa', function (): void {
    Sala::factory()->conNovedad('El aire no enfría.')->create(['nombre' => 'SIM-01']);
    $solicitud = Solicitud::factory()->create(['fecha' => $this->fecha]);

    Livewire::actingAs(User::factory()->administrativo()->create())
        ->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud])
        ->assertSee('Antes de decidir, verifica')
        ->assertSee('SIM-01 tiene una novedad: El aire no enfría.');
});

it('el docente no ve los avisos de la revisión en su solicitud', function (): void {
    Sala::factory()->conNovedad('El aire no enfría.')->create();
    $docente = User::factory()->docente()->create();
    $solicitud = Solicitud::factory()->create(['fecha' => $this->fecha, 'docente_id' => $docente->id]);

    Livewire::actingAs($docente)
        ->test(ExpedienteDeSolicitud::class, ['solicitud' => $solicitud])
        ->assertDontSee('Antes de decidir, verifica')
        ->assertDontSee('El aire no enfría.');
});
