<?php

declare(strict_types=1);

use App\Enums\EstadoEvaluacion;
use App\Enums\ResultadoEvaluacion;
use App\Livewire\Evaluacion\Evaluaciones;
use App\Livewire\Evaluacion\MisResultados;
use App\Livewire\Evaluacion\RegistroDeEvaluacion;
use App\Models\Evaluacion;
use App\Models\ItemChecklist;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/*
 * Pantallas de evaluación (RF41-RF50): el docente registra sobre su sesión
 * de evaluación aprobada, con los estudiantes de la lista de la sesión; el
 * estudiante consulta sus resultados.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    abrirPeriodo();
    $this->docente = User::factory()->docente()->create();
});

/**
 * Sesión de evaluación aprobada del docente, con su lista de estudiantes y
 * un tipo de evaluación de la materia con tres ítems.
 *
 * @param  list<User>  $estudiantes
 * @return array{0: Solicitud, 1: TipoEvaluacion}
 */
function sesionDeEvaluacion(User $docente, array $estudiantes): array
{
    $materia = Materia::factory()->create();
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create([
        'materia_id' => $materia->id,
        'docente_id' => $docente->id,
        'fecha' => now()->addDay()->format('Y-m-d'),
    ]);
    $solicitud->estudiantes()->attach(collect($estudiantes)->pluck('id')->all());

    $tipo = TipoEvaluacion::factory()->create(['nombre' => 'Canalización de vía periférica']);
    $tipo->materias()->attach($materia->id);
    foreach (['Lavado de manos', 'Asepsia del sitio', 'Fijación del catéter'] as $orden => $descripcion) {
        ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'orden' => $orden + 1, 'descripcion' => $descripcion]);
    }

    return [$solicitud, $tipo];
}

it('ofrece al docente registrar la evaluación de su sesión aprobada', function (): void {
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, []);

    $componente = Livewire::actingAs($this->docente)
        ->test(Evaluaciones::class)
        ->assertSee('Sesiones de evaluación por registrar')
        ->assertSee($tipo->nombre)
        ->call('registrar', $solicitud->id)
        ->assertHasErrors(['tipoElegido.'.$solicitud->id])
        ->set('tipoElegido.'.$solicitud->id, $tipo->id)
        ->call('registrar', $solicitud->id);

    $evaluacion = Evaluacion::firstOrFail();
    $componente->assertRedirect(route('panel.evaluaciones.registro', $evaluacion));

    expect($evaluacion->items()->count())->toBe(3);
});

it('no ofrece registrar sobre la sesión de otro docente', function (): void {
    [$solicitud, $tipo] = sesionDeEvaluacion(User::factory()->docente()->create(), []);

    Livewire::actingAs($this->docente)
        ->test(Evaluaciones::class)
        ->assertDontSee($tipo->nombre)
        ->set('tipoElegido.'.$solicitud->id, $tipo->id)
        ->call('registrar', $solicitud->id)
        ->assertForbidden();
});

it('registra checklist, resultado y observaciones, y finaliza', function (): void {
    $ana = User::factory()->estudiante()->habilitado()->create(['nombre' => 'Ana Ruiz']);
    $beto = User::factory()->estudiante()->create(['nombre' => 'Beto Sinformato']);
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, [$ana, $beto]);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);
    $item = $evaluacion->items()->first();

    $pantalla = Livewire::actingAs($this->docente)
        ->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion])
        ->assertSee('Ana Ruiz')
        ->assertSee('No tiene el formato de confidencialidad al día')
        ->call('agregarHabilitados');

    $registro = $evaluacion->estudiantes()->sole();
    expect($registro->estudiante_id)->toBe($ana->id);

    $pantalla->call('alternarItem', $registro->id, $item->id)
        ->call('resultado', $registro->id, ResultadoEvaluacion::NoAprobado->value)
        ->set('observaciones.'.$registro->id, 'Contaminó el campo estéril.')
        ->call('guardarObservaciones', $registro->id)
        ->call('finalizar')
        ->assertHasNoErrors()
        ->assertDontSee('Finalizar evaluación');

    $registro->refresh();
    expect($registro->resultado)->toBe(ResultadoEvaluacion::NoAprobado)
        ->and($registro->observaciones)->toBe('Contaminó el campo estéril.')
        ->and((bool) $registro->items()->whereKey($item->id)->first()->pivot->cumplido)->toBeTrue()
        ->and($evaluacion->fresh()->estado)->toBe(EstadoEvaluacion::Finalizada);
});

it('no deja finalizar mientras falte algún resultado', function (): void {
    $ana = User::factory()->estudiante()->habilitado()->create();
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, [$ana]);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);
    app(EvaluacionService::class)->agregarEstudiante($evaluacion, $ana);

    Livewire::actingAs($this->docente)
        ->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion])
        ->call('finalizar')
        ->assertSee('1 estudiante(s) sin resultado registrado');

    expect($evaluacion->fresh()->estado)->toBe(EstadoEvaluacion::Borrador);
});

it('no evalúa a quien no está en la lista de la sesión', function (): void {
    $ana = User::factory()->estudiante()->habilitado()->create();
    $ajeno = User::factory()->estudiante()->habilitado()->create(['nombre' => 'Ajeno Ala Sesion']);
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, [$ana]);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);

    Livewire::actingAs($this->docente)
        ->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion])
        ->call('agregar', $ajeno->id)
        ->assertSee('Ajeno Ala Sesion no está en la lista de estudiantes de esta sesión.');

    expect($evaluacion->estudiantes()->count())->toBe(0);
});

it('no deja tocar un registro de otra evaluación desde esta pantalla', function (): void {
    $ana = User::factory()->estudiante()->habilitado()->create();
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, [$ana]);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);
    $ajeno = Evaluacion::factory()->create(['docente_id' => $this->docente->id]);
    $registroAjeno = $ajeno->estudiantes()->create(['estudiante_id' => $ana->id, 'intento' => 1]);

    $pantalla = Livewire::actingAs($this->docente)->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion]);

    expect(fn () => $pantalla->call('resultado', $registroAjeno->id, ResultadoEvaluacion::Aprobado->value))
        ->toThrow(ModelNotFoundException::class)
        ->and($registroAjeno->fresh()->resultado)->toBeNull();
});

it('deja a coordinación ver la evaluación pero no registrar en ella', function (): void {
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, []);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);
    $coordinadora = User::factory()->coordinador()->create();

    Livewire::actingAs($coordinadora)
        ->test(Evaluaciones::class)
        ->assertSee('Todas las evaluaciones')
        ->assertSee($tipo->nombre);

    Livewire::actingAs($coordinadora)
        ->test(RegistroDeEvaluacion::class, ['evaluacion' => $evaluacion])
        ->assertDontSee('Finalizar evaluación')
        ->call('finalizar')
        ->assertForbidden();
});

it('no deja a otro docente abrir la evaluación', function (): void {
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, []);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);

    $this->actingAs(User::factory()->docente()->create())
        ->get(route('panel.evaluaciones.registro', $evaluacion))
        ->assertForbidden();
});

it('enseña al estudiante sus resultados con el checklist completo', function (): void {
    $ana = User::factory()->estudiante()->habilitado()->create();
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, [$ana]);
    $servicio = app(EvaluacionService::class);
    $evaluacion = $servicio->crear($solicitud, $tipo, $this->docente);
    $registro = $servicio->agregarEstudiante($evaluacion, $ana);
    $servicio->marcarItem($registro, $evaluacion->items()->first());
    $servicio->registrarResultado($registro, ResultadoEvaluacion::Aprobado);
    $servicio->finalizar($evaluacion);

    Livewire::actingAs($ana)
        ->test(MisResultados::class)
        ->assertSee('Canalización de vía periférica')
        ->assertSee('Aprobado')
        ->assertSee('intento 1')
        ->assertSeeInOrder(['Lavado de manos', '(cumplido)', 'Asepsia del sitio', '(no cumplido)', 'Fijación del catéter', '(no cumplido)']);
});

it('no enseña al estudiante una evaluación todavía en borrador', function (): void {
    $ana = User::factory()->estudiante()->habilitado()->create();
    [$solicitud, $tipo] = sesionDeEvaluacion($this->docente, [$ana]);
    $evaluacion = app(EvaluacionService::class)->crear($solicitud, $tipo, $this->docente);
    app(EvaluacionService::class)->agregarEstudiante($evaluacion, $ana);

    Livewire::actingAs($ana)
        ->test(MisResultados::class)
        ->assertSee('Todavía no tienes evaluaciones');
});

it('reserva Mis resultados al estudiante', function (): void {
    $this->actingAs($this->docente)->get(route('panel.mis-resultados'))->assertForbidden();
    $this->actingAs(User::factory()->estudiante()->create())->get(route('panel.mis-resultados'))->assertOk();
});
