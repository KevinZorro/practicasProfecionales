<?php

declare(strict_types=1);

use App\Enums\AccionAuditada;
use App\Enums\EstadoEvaluacion;
use App\Enums\ResultadoEvaluacion;
use App\Exceptions\EvaluacionInvalida;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\RegistroDeBitacora;
use App\Models\TipoEvaluacion;
use App\Models\User;
use App\Services\EvaluacionService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;

/*
 * B3.11 y B3.3 de la auditoría, al finalizar una evaluación:
 *
 * - El intento se calcula contando las evaluaciones finalizadas del mismo
 *   tipo: dos que se finalizan a la vez cuentan lo mismo y le dan al
 *   estudiante el mismo número.
 * - Un doble «Finalizar» que llega a la vez (dos pestañas, un doble toque)
 *   pasa dos veces la comprobación de borrador y escribe dos entradas en la
 *   bitácora.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->tipo = TipoEvaluacion::factory()->create();
    $this->estudiante = User::factory()->estudiante()->create();
});

/** Una evaluación en borrador de ese tipo, con el estudiante ya calificado. */
function evaluacionConElEstudiante(TipoEvaluacion $tipo, User $estudiante): Evaluacion
{
    $evaluacion = Evaluacion::factory()->create([
        'tipo_evaluacion_id' => $tipo->id,
        'docente_id' => User::factory()->docente()->create()->id,
    ]);

    EvaluacionEstudiante::factory()->create([
        'evaluacion_id' => $evaluacion->id,
        'estudiante_id' => $estudiante->id,
        'resultado' => ResultadoEvaluacion::Aprobado,
    ]);

    return $evaluacion;
}

/** Lo que hace la otra persona, desde su propia sesión de base de datos. */
function finalizarDesdeOtraSesion(int $evaluacionId): void
{
    enOtraSesion(function () use ($evaluacionId): void {
        $evaluacion = Evaluacion::findOrFail($evaluacionId);
        app(EvaluacionService::class)->finalizar($evaluacion, User::findOrFail($evaluacion->docente_id));
    });
}

it('dos evaluaciones del mismo tipo que se finalizan a la vez no le repiten el número de intento al estudiante', function (): void {
    $primera = evaluacionConElEstudiante($this->tipo, $this->estudiante);
    $segunda = evaluacionConElEstudiante($this->tipo, $this->estudiante);

    $seInterpuso = false;

    // Justo cuando la primera ya contó las finalizadas, fijó los intentos y
    // va a marcarse como finalizada, se finaliza la segunda.
    Evaluacion::updating(function (Evaluacion $evaluacion) use (&$seInterpuso, $primera, $segunda): void {
        if ($seInterpuso || $evaluacion->id !== $primera->id) {
            return;
        }

        $seInterpuso = true;

        try {
            finalizarDesdeOtraSesion($segunda->id);
        } catch (QueryException) {
            // Si las finalizaciones del mismo tipo se turnan, la segunda se
            // cansa de esperar a la primera.
        }
    });

    app(EvaluacionService::class)->finalizar($primera, User::findOrFail($primera->docente_id));

    // Si la segunda quedó pendiente, se finaliza cuando la primera ya terminó.
    if ($segunda->fresh()->estado !== EstadoEvaluacion::Finalizada) {
        finalizarDesdeOtraSesion($segunda->id);
    }

    $intentos = EvaluacionEstudiante::query()
        ->where('estudiante_id', $this->estudiante->id)
        ->orderBy('intento')
        ->pluck('intento')
        ->all();

    expect($seInterpuso)->toBeTrue()
        ->and($intentos)->toBe([1, 2]);
});

it('un doble «Finalizar» que llega a la vez deja una sola entrada en la bitácora', function (): void {
    $evaluacion = evaluacionConElEstudiante($this->tipo, $this->estudiante);

    $seInterpuso = false;
    $lecturas = 0;

    // Justo cuando la primera petición relee la evaluación para comprobar que
    // sigue en borrador (la segunda vez que la carga: la primera es la fila
    // bloqueada), llega la segunda. Sin el bloqueo de la evaluación, las dos
    // la verían en borrador y las dos la cerrarían.
    Evaluacion::retrieved(function (Evaluacion $leida) use (&$seInterpuso, &$lecturas, $evaluacion): void {
        if ($seInterpuso || $leida->id !== $evaluacion->id || ++$lecturas < 2) {
            return;
        }

        $seInterpuso = true;

        try {
            finalizarDesdeOtraSesion($evaluacion->id);
        } catch (EvaluacionInvalida|QueryException) {
            // Si el borrador se comprueba con la evaluación bloqueada, la
            // segunda la ve finalizada o se cansa de esperar.
        }
    });

    app(EvaluacionService::class)->finalizar($evaluacion, User::findOrFail($evaluacion->docente_id));

    // La segunda petición vuelve a intentarlo cuando la primera ya terminó.
    try {
        finalizarDesdeOtraSesion($evaluacion->id);
    } catch (EvaluacionInvalida) {
        // Lo esperado: ya no es un borrador.
    }

    expect($seInterpuso)->toBeTrue()
        ->and(RegistroDeBitacora::query()->where('accion', AccionAuditada::EvaluacionFinalizada)->count())->toBe(1);
});
