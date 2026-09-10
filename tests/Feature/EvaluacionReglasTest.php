<?php

declare(strict_types=1);

use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoSolicitud;
use App\Enums\ResultadoEvaluacion;
use App\Enums\TipoSesion;
use App\Exceptions\EvaluacionInvalida;
use App\Models\Evaluacion;
use App\Models\ItemChecklist;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(EvaluacionService::class);
    $this->docente = User::factory()->docente()->create();
});

/**
 * Escenario mínimo: una solicitud de evaluación aprobada y un tipo de
 * evaluación habilitado para su materia, con checklist.
 *
 * @return array{0: Solicitud, 1: TipoEvaluacion}
 */
function escenarioEvaluable(int $itemsChecklist = 3): array
{
    $materia = Materia::factory()->create();
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $materia->id]);

    $tipo = TipoEvaluacion::factory()->create();
    $tipo->materias()->attach($materia->id);
    for ($orden = 1; $orden <= $itemsChecklist; $orden++) {
        ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'orden' => $orden]);
    }

    return [$solicitud, $tipo];
}

// ---------------------------------------------------------------------
// Regla 1: ninguna evaluación existe sin escenario aprobado de evaluación
// ---------------------------------------------------------------------

it('crea la evaluación sobre una solicitud de evaluación aprobada', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();

    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);

    expect($evaluacion->solicitud_id)->toBe($solicitud->id)
        ->and($evaluacion->estado)->toBe(EstadoEvaluacion::Borrador)
        ->and($evaluacion->docente_id)->toBe($this->docente->id);
});

it('no evalúa sobre una solicitud de tipo práctica', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $solicitud->update(['tipo' => TipoSesion::Practica]);

    expect(fn () => $this->servicio->crear($solicitud, $tipo, $this->docente))
        ->toThrow(EvaluacionInvalida::class, 'no se puede evaluar sobre ella');
});

it('no evalúa sobre una solicitud que no está aprobada', function (EstadoSolicitud $estado): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $solicitud->update(['estado' => $estado]);

    expect(fn () => $this->servicio->crear($solicitud, $tipo, $this->docente))
        ->toThrow(EvaluacionInvalida::class, 'ninguna evaluación existe sin escenario aprobado');
})->with([
    'pendiente' => EstadoSolicitud::Pendiente,
    'revisada' => EstadoSolicitud::Revisada,
    'rechazada' => EstadoSolicitud::Rechazada,
]);

it('no registra dos evaluaciones sobre la misma solicitud', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $this->servicio->crear($solicitud, $tipo, $this->docente);

    expect(fn () => $this->servicio->crear($solicitud->fresh(), $tipo, $this->docente))
        ->toThrow(EvaluacionInvalida::class, 'ya tiene una evaluación registrada');
});

it('no acepta un tipo de evaluación ajeno a la materia de la solicitud', function (): void {
    [$solicitud] = escenarioEvaluable();
    $ajeno = TipoEvaluacion::factory()->create(['nombre' => 'Sondaje vesical']);
    $ajeno->materias()->attach(Materia::factory()->create()->id);

    expect(fn () => $this->servicio->crear($solicitud, $ajeno, $this->docente))
        ->toThrow(EvaluacionInvalida::class, 'no está asociado a la materia');
});

// ---------------------------------------------------------------------
// Regla 2: el resultado lo decide el docente, no se deriva del checklist
// ---------------------------------------------------------------------

it('deja sin resultado a los estudiantes recién agregados', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);

    $registro = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());

    expect($registro->resultado)->toBeNull();
});

it('no fija el resultado aunque se marquen todos los ítems', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $registro = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());

    foreach ($evaluacion->items as $item) {
        $this->servicio->marcarItem($registro, $item);
    }

    expect($registro->fresh()->resultado)->toBeNull()
        ->and($registro->fresh()->items)->toHaveCount(3);
});

it('acepta aprobar sin ningún ítem marcado', function (): void {
    // El docente manda: los ítems son apoyo, no aritmética.
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $registro = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());

    $this->servicio->registrarResultado($registro, ResultadoEvaluacion::Aprobado);

    expect($registro->fresh()->resultado)->toBe(ResultadoEvaluacion::Aprobado)
        ->and($registro->fresh()->items)->toHaveCount(0);
});

it('acepta no aprobar con todos los ítems marcados', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $registro = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());
    foreach ($evaluacion->items as $item) {
        $this->servicio->marcarItem($registro, $item);
    }

    $this->servicio->registrarResultado($registro, ResultadoEvaluacion::NoAprobado);

    expect($registro->fresh()->resultado)->toBe(ResultadoEvaluacion::NoAprobado);
});

// ---------------------------------------------------------------------
// Regla 3: el checklist se copia, no se referencia
// ---------------------------------------------------------------------

it('copia el checklist de la plantilla al crear la evaluación', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();

    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);

    expect($evaluacion->items)->toHaveCount(3)
        ->and($evaluacion->items->pluck('descripcion')->all())
        ->toBe($tipo->itemsChecklist->pluck('descripcion')->all());
});

it('no altera una evaluación registrada cuando el ADMIN edita la plantilla', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $originales = $evaluacion->items->pluck('descripcion')->all();

    // El ADMIN reescribe la plantilla después de evaluar.
    $tipo->itemsChecklist->first()->update(['descripcion' => 'Texto nuevo del ADMIN']);
    ItemChecklist::factory()->create(['tipo_evaluacion_id' => $tipo->id, 'descripcion' => 'Ítem añadido después']);
    $tipo->itemsChecklist->last()->delete();

    $items = $evaluacion->fresh()->items;

    expect($items->pluck('descripcion')->all())->toBe($originales)
        ->and($items->pluck('descripcion'))->not->toContain('Texto nuevo del ADMIN')
        ->and($items->pluck('descripcion'))->not->toContain('Ítem añadido después');
});

it('mantiene los ítems copiados aunque se borre el tipo de evaluación completo', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);

    $tipo->itemsChecklist()->delete();

    expect($evaluacion->fresh()->items)->toHaveCount(3);
});

// ---------------------------------------------------------------------
// Cierre
// ---------------------------------------------------------------------

it('no finaliza si algún estudiante se quedó sin resultado', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $conResultado = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());
    $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());
    $this->servicio->registrarResultado($conResultado, ResultadoEvaluacion::Aprobado);

    expect(fn () => $this->servicio->finalizar($evaluacion))
        ->toThrow(EvaluacionInvalida::class, '1 estudiante(s) sin resultado');

    expect($evaluacion->fresh()->estado)->toBe(EstadoEvaluacion::Borrador);
});

it('no modifica una evaluación ya finalizada', function (string $metodo): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $registro = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());
    $this->servicio->registrarResultado($registro, ResultadoEvaluacion::Aprobado);
    $this->servicio->finalizar($evaluacion);

    $accion = match ($metodo) {
        'agregarEstudiante' => fn () => $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create()),
        'quitarEstudiante' => fn () => $this->servicio->quitarEstudiante($evaluacion, $registro->estudiante),
        'marcarItem' => fn () => $this->servicio->marcarItem($registro->fresh(), $evaluacion->items->first()),
        'registrarResultado' => fn () => $this->servicio->registrarResultado($registro->fresh(), ResultadoEvaluacion::NoAprobado),
        'registrarObservaciones' => fn () => $this->servicio->registrarObservaciones($registro->fresh(), 'tarde'),
        'finalizar' => fn () => $this->servicio->finalizar($evaluacion->fresh()),
    };

    expect($accion)->toThrow(EvaluacionInvalida::class, 'no se modifica');
})->with([
    'agregarEstudiante', 'quitarEstudiante', 'marcarItem',
    'registrarResultado', 'registrarObservaciones', 'finalizar',
]);

it('recorre el flujo completo hasta dejar la evaluación finalizada', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $aprobada = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());
    $reprobado = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());

    $this->servicio->marcarItem($aprobada, $evaluacion->items->first());
    $this->servicio->registrarResultado($aprobada, ResultadoEvaluacion::Aprobado);
    $this->servicio->registrarResultado($reprobado, ResultadoEvaluacion::NoAprobado);
    $this->servicio->registrarObservaciones($reprobado, 'Debe repasar la técnica.');

    $this->servicio->finalizar($evaluacion);

    expect($evaluacion->fresh()->estado)->toBe(EstadoEvaluacion::Finalizada)
        ->and($reprobado->fresh()->observaciones)->toBe('Debe repasar la técnica.')
        ->and($aprobada->fresh()->intento)->toBe(1);
});

it('quita a un estudiante mientras la evaluación es un borrador', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $estudiante = User::factory()->estudiante()->create();
    $this->servicio->agregarEstudiante($evaluacion, $estudiante);

    $this->servicio->quitarEstudiante($evaluacion, $estudiante);

    expect($evaluacion->fresh()->estudiantes)->toHaveCount(0);
});

it('desmarca un ítem ya marcado', function (): void {
    [$solicitud, $tipo] = escenarioEvaluable();
    $evaluacion = $this->servicio->crear($solicitud, $tipo, $this->docente);
    $registro = $this->servicio->agregarEstudiante($evaluacion, User::factory()->estudiante()->create());
    $item = $evaluacion->items->first();
    $this->servicio->marcarItem($registro, $item);

    $this->servicio->desmarcarItem($registro, $item);

    expect((bool) $registro->fresh()->items->first()->pivot->cumplido)->toBeFalse();
});

it('guarda una evaluación con solicitud_id obligatorio', function (): void {
    expect(fn () => Evaluacion::create([
        'tipo_evaluacion_id' => TipoEvaluacion::factory()->create()->id,
        'docente_id' => $this->docente->id,
    ]))->toThrow(QueryException::class);
});
