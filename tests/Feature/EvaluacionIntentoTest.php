<?php

declare(strict_types=1);

use App\Enums\ResultadoEvaluacion;
use App\Models\Evaluacion;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use Database\Seeders\RolSeeder;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(EvaluacionService::class);
    $this->docente = User::factory()->docente()->create();
    $this->estudiante = User::factory()->estudiante()->create();
    $this->materia = Materia::factory()->create();
    $this->tipo = TipoEvaluacion::factory()->create();
    $this->tipo->materias()->attach($this->materia->id);
});

/** Presenta al estudiante una vez y cierra la evaluación con ese resultado. */
function presentar(
    User $estudiante,
    TipoEvaluacion $tipo,
    Materia $materia,
    User $docente,
    ResultadoEvaluacion $resultado,
): int {
    $servicio = app(EvaluacionService::class);
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $materia->id]);
    $evaluacion = $servicio->crear($solicitud, $tipo, $docente);
    $registro = $servicio->agregarEstudiante($evaluacion, $estudiante);
    $servicio->registrarResultado($registro, $resultado);
    $servicio->finalizar($evaluacion);

    return $registro->fresh()->intento;
}

it('numera la primera presentación como intento 1', function (): void {
    $intento = presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::NoAprobado);

    expect($intento)->toBe(1);
});

it('incrementa el intento en la segunda presentación', function (): void {
    presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::NoAprobado);

    $segundo = presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);

    expect($segundo)->toBe(2);
});

it('cuenta igual las presentaciones aprobadas que las no aprobadas', function (): void {
    // "Intento" es cuántas veces se presenta, no cuántas veces falló.
    presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);
    presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);

    $tercero = presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);

    expect($tercero)->toBe(3);
});

it('lleva la cuenta por separado en cada tipo de evaluación', function (): void {
    $otroTipo = TipoEvaluacion::factory()->create();
    $otroTipo->materias()->attach($this->materia->id);
    presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::NoAprobado);
    presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::NoAprobado);

    $enElOtro = presentar($this->estudiante, $otroTipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);

    expect($enElOtro)->toBe(1);
});

it('lleva la cuenta por separado en cada estudiante', function (): void {
    presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::NoAprobado);
    $otro = User::factory()->estudiante()->create();

    $suyo = presentar($otro, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);

    expect($suyo)->toBe(1);
});

it('no cuenta las evaluaciones que siguen en borrador', function (): void {
    // Un borrador todavía no es una presentación registrada.
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $this->materia->id]);
    $borrador = $this->servicio->crear($solicitud, $this->tipo, $this->docente);
    $this->servicio->agregarEstudiante($borrador, $this->estudiante);

    expect($this->servicio->calcularIntento($this->estudiante, $this->tipo))->toBe(1);
});

it('recalcula el intento al finalizar, no al agregar al estudiante', function (): void {
    // Dos evaluaciones abiertas a la vez: la que cierre después es la 2.
    $primera = $this->servicio->crear(
        Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $this->materia->id]),
        $this->tipo,
        $this->docente,
    );
    $segunda = $this->servicio->crear(
        Solicitud::factory()->deEvaluacion()->aprobada()->create(['materia_id' => $this->materia->id]),
        $this->tipo,
        $this->docente,
    );
    $enPrimera = $this->servicio->agregarEstudiante($primera, $this->estudiante);
    $enSegunda = $this->servicio->agregarEstudiante($segunda, $this->estudiante);

    // Ambas nacieron como intento 1 porque ninguna estaba finalizada.
    expect($enPrimera->intento)->toBe(1)->and($enSegunda->intento)->toBe(1);

    $this->servicio->registrarResultado($enPrimera, ResultadoEvaluacion::NoAprobado);
    $this->servicio->finalizar($primera);
    $this->servicio->registrarResultado($enSegunda, ResultadoEvaluacion::Aprobado);
    $this->servicio->finalizar($segunda);

    expect($enPrimera->fresh()->intento)->toBe(1)
        ->and($enSegunda->fresh()->intento)->toBe(2);
});

it('no se cuenta a sí misma al calcular el intento', function (): void {
    $intento = presentar($this->estudiante, $this->tipo, $this->materia, $this->docente, ResultadoEvaluacion::Aprobado);

    expect($intento)->toBe(1)
        ->and(Evaluacion::count())->toBe(1);
});
