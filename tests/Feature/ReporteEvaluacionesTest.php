<?php

declare(strict_types=1);

use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoSolicitud;
use App\Enums\ResultadoEvaluacion;
use App\Enums\TipoSesion;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\FiltroReporte;
use App\Services\ReporteService;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->servicio = app(ReporteService::class);
});

// preventLazyLoading es una bandera estática: si no se apaga, se filtra a
// los demás archivos de la suite según el orden en que corran.
afterEach(function (): void {
    Model::preventLazyLoading(false);
});

/** Una evaluación finalizada sobre su escenario aprobado. */
function evaluacionFinalizadaDeReporte(User $docente, Materia $materia, string $fecha = '2026-04-10'): Evaluacion
{
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create([
        'docente_id' => $docente->id,
        'materia_id' => $materia->id,
        'fecha' => $fecha,
    ]);

    return Evaluacion::factory()->finalizada()->create([
        'solicitud_id' => $solicitud->id,
        'docente_id' => $docente->id,
    ]);
}

// ---------------------------------------------------------------------
// RF55 — resultados de evaluación
// ---------------------------------------------------------------------

it('devuelve un renglón por estudiante evaluado con su intento', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $materia = Materia::factory()->create(['nombre' => 'Cuidado materno']);
    $evaluacion = evaluacionFinalizadaDeReporte($docente, $materia);
    $estudiante = User::factory()->estudiante()->create(['nombre' => 'Luis Pérez']);

    EvaluacionEstudiante::factory()->create([
        'evaluacion_id' => $evaluacion->id,
        'estudiante_id' => $estudiante->id,
        'intento' => 2,
        'resultado' => ResultadoEvaluacion::NoAprobado,
    ]);

    $filas = $this->servicio->resultadosDeEvaluacion(new FiltroReporte)->get();

    expect($filas)->toHaveCount(1);
    $fila = $filas->first();
    expect($fila->estudiante->nombre)->toBe('Luis Pérez')
        ->and($fila->intento)->toBe(2)
        ->and($fila->resultado)->toBe(ResultadoEvaluacion::NoAprobado)
        ->and($fila->evaluacion->docente->nombre)->toBe('Ana Gómez')
        ->and($fila->evaluacion->solicitud->materia->nombre)->toBe('Cuidado materno');
});

it('deja fuera los resultados de evaluaciones en borrador', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();

    $borrador = Evaluacion::factory()->create([
        'solicitud_id' => Solicitud::factory()->deEvaluacion()->aprobada()->create(['docente_id' => $docente->id])->id,
        'docente_id' => $docente->id,
    ]);
    EvaluacionEstudiante::factory()->create(['evaluacion_id' => $borrador->id]);

    expect($borrador->estado)->toBe(EstadoEvaluacion::Borrador)
        ->and($this->servicio->resultadosDeEvaluacion(new FiltroReporte)->get())->toBeEmpty();
});

it('incluye los intentos repetidos del mismo estudiante', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();
    $estudiante = User::factory()->estudiante()->create();

    foreach ([1, 2, 3] as $intento) {
        EvaluacionEstudiante::factory()->intento($intento)->create([
            'evaluacion_id' => evaluacionFinalizadaDeReporte($docente, $materia)->id,
            'estudiante_id' => $estudiante->id,
        ]);
    }

    $filas = $this->servicio->resultadosDeEvaluacion(new FiltroReporte)->get();

    expect($filas)->toHaveCount(3)
        ->and($filas->pluck('intento')->all())->toEqualCanonicalizing([1, 2, 3]);
});

it('filtra los resultados por docente, materia y rango de fechas', function (): void {
    $ana = User::factory()->docente()->create(['nombre' => 'Ana']);
    $beto = User::factory()->docente()->create(['nombre' => 'Beto']);
    $materiaUno = Materia::factory()->create();
    $materiaDos = Materia::factory()->create();

    EvaluacionEstudiante::factory()->create(['evaluacion_id' => evaluacionFinalizadaDeReporte($ana, $materiaUno, '2026-03-10')->id]);
    EvaluacionEstudiante::factory()->create(['evaluacion_id' => evaluacionFinalizadaDeReporte($beto, $materiaDos, '2026-05-10')->id]);

    expect($this->servicio->resultadosDeEvaluacion(new FiltroReporte(docenteId: $ana->id))->get())->toHaveCount(1)
        ->and($this->servicio->resultadosDeEvaluacion(new FiltroReporte(materiaId: $materiaDos->id))->get())->toHaveCount(1)
        ->and($this->servicio->resultadosDeEvaluacion(new FiltroReporte(desde: '2026-05-01'))->get())->toHaveCount(1)
        ->and($this->servicio->resultadosDeEvaluacion(new FiltroReporte)->get())->toHaveCount(2);
});

it('no genera consultas N+1 al recorrer los resultados', function (): void {
    $docente = User::factory()->docente()->create();
    $materia = Materia::factory()->create();

    foreach (range(1, 6) as $ignorado) {
        EvaluacionEstudiante::factory()->create(['evaluacion_id' => evaluacionFinalizadaDeReporte($docente, $materia)->id]);
    }

    Model::preventLazyLoading();

    $filas = $this->servicio->resultadosDeEvaluacion(new FiltroReporte)->get();

    // Tocar cada relación que el exportador usa: sin with(), esto revienta.
    $filas->each(function (EvaluacionEstudiante $fila): void {
        expect($fila->estudiante->nombre)->toBeString()
            ->and($fila->evaluacion->docente->nombre)->toBeString()
            ->and($fila->evaluacion->tipoEvaluacion->nombre)->toBeString()
            ->and($fila->evaluacion->solicitud->materia->nombre)->toBeString();
    });
});

// ---------------------------------------------------------------------
// RF56 — evaluaciones no registradas
// ---------------------------------------------------------------------

it('detecta el escenario de evaluación que ya pasó y nunca se registró', function (): void {
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => now()->subWeek()->toDateString()]);

    $faltantes = $this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get();

    expect($faltantes)->toHaveCount(1)
        ->and($faltantes->first()->id)->toBe($solicitud->id);
});

it('no marca como faltante un escenario cuya evaluación sí quedó finalizada', function (): void {
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => now()->subWeek()->toDateString()]);
    Evaluacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id]);

    expect($this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get())->toBeEmpty();
});

it('cuenta como faltante la evaluación que quedó en borrador', function (): void {
    // Un borrador no tiene resultados publicables: queda fuera del RF55.
    // Si además quedara fuera del RF56, no aparecería en ningún reporte.
    $solicitud = Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => now()->subWeek()->toDateString()]);
    Evaluacion::factory()->create(['solicitud_id' => $solicitud->id]);

    $faltantes = $this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get();

    expect($faltantes)->toHaveCount(1)
        ->and($faltantes->first()->id)->toBe($solicitud->id);
});

it('no mira escenarios de práctica ni solicitudes sin aprobar ni fechas futuras', function (): void {
    Solicitud::factory()->aprobada()->create(['tipo' => TipoSesion::Practica, 'fecha' => now()->subWeek()->toDateString()]);
    Solicitud::factory()->deEvaluacion()->create(['estado' => EstadoSolicitud::Pendiente, 'fecha' => now()->subWeek()->toDateString()]);
    Solicitud::factory()->deEvaluacion()->create(['estado' => EstadoSolicitud::Rechazada, 'fecha' => now()->subWeek()->toDateString()]);
    Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => now()->addWeek()->toDateString()]);

    expect($this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get())->toBeEmpty();
});

it('no reclama todavía el escenario de evaluación de hoy', function (): void {
    // El docente tiene el resto del día para registrarla.
    Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => now()->toDateString()]);

    expect($this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get())->toBeEmpty();
});

it('filtra las faltantes por docente, materia y rango de fechas', function (): void {
    $ana = User::factory()->docente()->create();
    $materia = Materia::factory()->create();

    Solicitud::factory()->deEvaluacion()->aprobada()->create([
        'docente_id' => $ana->id, 'materia_id' => $materia->id, 'fecha' => '2026-03-10',
    ]);
    Solicitud::factory()->deEvaluacion()->aprobada()->create(['fecha' => '2026-05-10']);

    expect($this->servicio->evaluacionesNoRegistradas(new FiltroReporte(docenteId: $ana->id))->get())->toHaveCount(1)
        ->and($this->servicio->evaluacionesNoRegistradas(new FiltroReporte(materiaId: $materia->id))->get())->toHaveCount(1)
        ->and($this->servicio->evaluacionesNoRegistradas(new FiltroReporte(desde: '2026-05-01'))->get())->toHaveCount(1)
        ->and($this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get())->toHaveCount(2);
});

it('no genera consultas N+1 al recorrer las faltantes', function (): void {
    Solicitud::factory()->deEvaluacion()->aprobada()->count(6)->create(['fecha' => now()->subWeek()->toDateString()]);

    Model::preventLazyLoading();

    $this->servicio->evaluacionesNoRegistradas(new FiltroReporte)->get()->each(function (Solicitud $fila): void {
        expect($fila->docente->nombre)->toBeString()
            ->and($fila->materia->nombre)->toBeString()
            ->and($fila->casoClinico->nombre)->toBeString()
            ->and($fila->evaluacion)->toBeNull();
    });
});
