<?php

declare(strict_types=1);

use App\Enums\ResultadoEvaluacion;
use App\Enums\Rol;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\ItemChecklist;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(EvaluacionService::class);
    $this->docente = User::factory()->docente()->create();
    $this->materia = Materia::factory()->create();
    $this->tipo = TipoEvaluacion::factory()->create();
    $this->tipo->materias()->attach($this->materia->id);
    ItemChecklist::factory()->count(2)->create(['tipo_evaluacion_id' => $this->tipo->id]);
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Evaluación finalizada con los estudiantes dados y su resultado. */
function evaluacionFinalizada(User $docente, TipoEvaluacion $tipo, Materia $materia, array $resultados): Evaluacion
{
    $servicio = app(EvaluacionService::class);
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $materia->id]);
    $evaluacion = $servicio->crear($solicitud, $tipo, $docente);

    foreach ($resultados as $par) {
        [$estudiante, $resultado] = $par;
        $registro = $servicio->agregarEstudiante($evaluacion, $estudiante);
        $servicio->marcarItem($registro, $evaluacion->items->first());
        $servicio->registrarResultado($registro, $resultado);
    }
    $servicio->finalizar($evaluacion);

    return $evaluacion;
}

it('devuelve el historial de un docente sin mezclar el de otros', function (): void {
    $otroDocente = User::factory()->docente()->create();
    $estudiante = User::factory()->estudiante()->create();
    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    evaluacionFinalizada($otroDocente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);

    expect($this->servicio->historialDelDocente($this->docente))->toHaveCount(2)
        ->and($this->servicio->historialDelDocente($otroDocente))->toHaveCount(1);
});

it('devuelve al estudiante sus resultados con intento y checklist marcado', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::NoAprobado]]);

    $historial = $this->servicio->historialDelEstudiante($estudiante);
    $registro = $historial->first();

    expect($historial)->toHaveCount(1)
        ->and($registro->resultado)->toBe(ResultadoEvaluacion::NoAprobado)
        ->and($registro->intento)->toBe(1)
        ->and($registro->items)->toHaveCount(1)
        ->and((bool) $registro->items->first()->pivot->cumplido)->toBeTrue()
        ->and($registro->evaluacion->tipoEvaluacion->nombre)->toBe($this->tipo->nombre);
});

it('no mezcla en el historial los resultados de otros estudiantes', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $companero = User::factory()->estudiante()->create();
    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [
        [$estudiante, ResultadoEvaluacion::Aprobado],
        [$companero, ResultadoEvaluacion::NoAprobado],
    ]);

    expect($this->servicio->historialDelEstudiante($estudiante))->toHaveCount(1)
        ->and($this->servicio->historialDelEstudiante($estudiante)->first()->resultado)
        ->toBe(ResultadoEvaluacion::Aprobado);
});

it('oculta al estudiante las evaluaciones que siguen en borrador', function (): void {
    // Mientras es borrador el docente todavía está calificando.
    $estudiante = User::factory()->estudiante()->create();
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $this->materia->id]);
    $borrador = $this->servicio->crear($solicitud, $this->tipo, $this->docente);
    $registro = $this->servicio->agregarEstudiante($borrador, $estudiante);
    $this->servicio->registrarResultado($registro, ResultadoEvaluacion::Aprobado);

    expect($this->servicio->historialDelEstudiante($estudiante))->toHaveCount(0);
});

it('no genera consultas N+1 en el historial del docente', function (): void {
    Model::preventLazyLoading();
    $estudiante = User::factory()->estudiante()->create();
    foreach (range(1, 4) as $ignorado) {
        evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    }

    DB::enableQueryLog();
    foreach ($this->servicio->historialDelDocente($this->docente) as $evaluacion) {
        $evaluacion->tipoEvaluacion->nombre;
        $evaluacion->solicitud->materia->nombre;
        $evaluacion->solicitud->casoClinico->nombre;
        foreach ($evaluacion->estudiantes as $registro) {
            $registro->estudiante->nombre;
        }
    }
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBeLessThanOrEqual(7);
});

it('mantiene constante el número de consultas al crecer el historial', function (): void {
    Model::preventLazyLoading();
    $estudiante = User::factory()->estudiante()->create();

    $medirDocente = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach ($this->servicio->historialDelDocente($this->docente) as $evaluacion) {
            $evaluacion->tipoEvaluacion->nombre;
            $evaluacion->solicitud->materia->nombre;
            $evaluacion->solicitud->casoClinico->nombre;
            foreach ($evaluacion->estudiantes as $registro) {
                $registro->estudiante->nombre;
            }
        }
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    $conDos = $medirDocente();

    foreach (range(1, 4) as $ignorado) {
        evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    }

    expect($medirDocente())->toBe($conDos);
});

it('no genera consultas N+1 en el historial del estudiante', function (): void {
    Model::preventLazyLoading();
    $estudiante = User::factory()->estudiante()->create();
    foreach (range(1, 4) as $ignorado) {
        evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [[$estudiante, ResultadoEvaluacion::Aprobado]]);
    }

    DB::enableQueryLog();
    foreach ($this->servicio->historialDelEstudiante($estudiante) as $registro) {
        $registro->evaluacion->tipoEvaluacion->nombre;
        $registro->evaluacion->docente->nombre;
        $registro->evaluacion->solicitud->materia->nombre;
        foreach ($registro->items as $item) {
            $item->descripcion;
            $item->pivot->cumplido;
        }
    }
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBeLessThanOrEqual(7);
});

it('no deja a un docente ver evaluaciones de otro docente', function (): void {
    $otroDocente = User::factory()->docente()->create();
    $ajena = evaluacionFinalizada($otroDocente, $this->tipo, $this->materia, [
        [User::factory()->estudiante()->create(), ResultadoEvaluacion::Aprobado],
    ]);

    expect($this->docente->can('view', $ajena))->toBeFalse()
        ->and($this->docente->can('registrar', $ajena))->toBeFalse()
        ->and($this->docente->can('finalizar', $ajena))->toBeFalse()
        ->and($otroDocente->can('view', $ajena))->toBeTrue();
});

it('deja a coordinación y al ADMIN ver cualquier evaluación, para reportes', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $evaluacion = evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [
        [User::factory()->estudiante()->create(), ResultadoEvaluacion::Aprobado],
    ]);

    expect($usuario->can('view', $evaluacion))->toBeTrue();
})->with([Rol::Admin, Rol::Coordinador]);

it('no deja a un administrativo ni a un estudiante ver la evaluación completa', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);
    $evaluacion = evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [
        [User::factory()->estudiante()->create(), ResultadoEvaluacion::Aprobado],
    ]);

    expect($usuario->can('view', $evaluacion))->toBeFalse();
})->with([Rol::Administrativo, Rol::Estudiante]);

it('deja a cada estudiante ver solo su propio resultado', function (): void {
    $estudiante = User::factory()->estudiante()->create();
    $companero = User::factory()->estudiante()->create();
    evaluacionFinalizada($this->docente, $this->tipo, $this->materia, [
        [$estudiante, ResultadoEvaluacion::Aprobado],
        [$companero, ResultadoEvaluacion::NoAprobado],
    ]);

    $suyo = EvaluacionEstudiante::where('estudiante_id', $estudiante->id)->firstOrFail();
    $ajeno = EvaluacionEstudiante::where('estudiante_id', $companero->id)->firstOrFail();

    expect($estudiante->can('view', $suyo))->toBeTrue()
        ->and($estudiante->can('view', $ajeno))->toBeFalse()
        ->and($this->docente->can('view', $ajeno))->toBeTrue()
        ->and(User::factory()->estudiante()->create()->can('view', $suyo))->toBeFalse();
});

it('solo deja crear evaluaciones al docente', function (): void {
    foreach ([Rol::Admin, Rol::Coordinador, Rol::Administrativo, Rol::Estudiante] as $rol) {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol->value);
        expect($usuario->can('create', Evaluacion::class))->toBeFalse();
    }

    expect($this->docente->can('create', Evaluacion::class))->toBeTrue();
});
