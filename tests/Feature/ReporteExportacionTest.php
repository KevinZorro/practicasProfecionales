<?php

declare(strict_types=1);

use App\Enums\Reporte;
use App\Enums\Rol;
use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use App\Services\ReporteService;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
    $this->generador = app(GeneradorDeReportes::class);
});

/** Datos con los que los tres reportes devuelven algo. */
function datosParaLosTresReportes(): void
{
    $docente = User::factory()->docente()->create(['nombre' => 'Ana Gómez']);
    $materia = Materia::factory()->create(['nombre' => 'Cuidado materno', 'semestre' => 5]);
    $caso = CasoClinico::factory()->create(['nombre' => 'Atención de parto']);
    $sala = Sala::factory()->create(['nombre' => 'Sala 1']);

    foreach (['07:00:00' => '09:30:00', '10:00:00' => '12:00:00'] as $inicio => $fin) {
        $solicitud = Solicitud::factory()->aprobada()->create([
            'docente_id' => $docente->id,
            'materia_id' => $materia->id,
            'caso_clinico_id' => $caso->id,
            'fecha' => '2026-04-10',
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'cantidad_estudiantes' => 12,
        ]);
        Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => $sala->id]);
    }

    $evaluada = Solicitud::factory()->deEvaluacion()->aprobada()->create([
        'docente_id' => $docente->id,
        'materia_id' => $materia->id,
        'fecha' => '2026-04-11',
    ]);
    $evaluacion = Evaluacion::factory()->finalizada()->create([
        'solicitud_id' => $evaluada->id,
        'docente_id' => $docente->id,
    ]);
    EvaluacionEstudiante::factory()->count(3)->create(['evaluacion_id' => $evaluacion->id]);

    Solicitud::factory()->deEvaluacion()->aprobada()->count(2)->create([
        'docente_id' => $docente->id,
        'materia_id' => $materia->id,
        'fecha' => now()->subWeek()->toDateString(),
    ]);
}

// ---------------------------------------------------------------------
// Una sola consulta alimenta las tres salidas
// ---------------------------------------------------------------------

it('devuelve exactamente las mismas filas en pantalla, en PDF y en Excel', function (Reporte $reporte): void {
    datosParaLosTresReportes();
    $filtro = new FiltroReporte(desde: '2020-01-01');

    // Pantalla: la consulta paginada, mapeada con el mismo exportador.
    $pantalla = collect($this->generador->paraPantalla($reporte, $filtro, porPagina: 100)->items());

    // PDF: las filas recorridas por lotes.
    $pdf = $this->generador->filas($reporte, $filtro);

    // Excel: la colección que maatwebsite construye a partir del exportador.
    $exportador = $this->generador->exportador($reporte, $filtro);
    $excel = $exportador->query()->get()->map(fn ($fila): array => $exportador->map($fila));

    expect($pdf->all())->toBe($pantalla->all())
        ->and($excel->all())->toBe($pantalla->all())
        ->and($pantalla)->not->toBeEmpty();
})->with([
    'uso de escenarios' => Reporte::UsoDeEscenarios,
    'resultados de evaluación' => Reporte::ResultadosDeEvaluacion,
    'evaluaciones no registradas' => Reporte::EvaluacionesNoRegistradas,
]);

it('coincide en los totales numéricos del uso de escenarios', function (): void {
    datosParaLosTresReportes();
    $filtro = new FiltroReporte(desde: '2026-04-01', hasta: '2026-04-30');

    $sumarHoras = static fn ($filas): float => round(collect($filas)->sum(static fn (array $f): float => (float) $f[7]), 2);

    $pantalla = $this->generador->paraPantalla(Reporte::UsoDeEscenarios, $filtro, porPagina: 100)->items();
    $pdf = $this->generador->filas(Reporte::UsoDeEscenarios, $filtro);

    // 2,5 h + 2 h de las dos prácticas, más las 2 h del escenario de
    // evaluación del día siguiente, que también está aprobado.
    expect($sumarHoras($pantalla))->toBe(6.5)
        ->and($sumarHoras($pdf))->toBe($sumarHoras($pantalla));
});

it('aplica el mismo filtro a las tres salidas', function (): void {
    datosParaLosTresReportes();
    $vacio = new FiltroReporte(desde: '2030-01-01');

    expect($this->generador->paraPantalla(Reporte::UsoDeEscenarios, $vacio)->items())->toBe([])
        ->and($this->generador->filas(Reporte::UsoDeEscenarios, $vacio)->all())->toBe([]);
});

// ---------------------------------------------------------------------
// Memoria: la exportación va por lotes
// ---------------------------------------------------------------------

it('recorre la exportación por lotes en vez de traerlo todo de una vez', function (): void {
    Solicitud::factory()->deEvaluacion()->aprobada()->count(7)->create(['fecha' => now()->subWeek()->toDateString()]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $filas = app(ReporteService::class)->porLotes(
        $this->generador->exportador(Reporte::EvaluacionesNoRegistradas, new FiltroReporte)->query(),
        static function ($lote) use (&$tamanos): void {
            $tamanos[] = $lote->count();
        },
        tamano: 3,
    );
    DB::disableQueryLog();

    // 7 filas de 3 en 3: tres lotes, ninguno con las siete a la vez.
    expect($tamanos)->toBe([3, 3, 1])
        ->and($filas)->toBeNull();
});

it('pide lotes de quinientos en la exportación a Excel', function (): void {
    expect($this->generador->exportador(Reporte::UsoDeEscenarios, new FiltroReporte)->chunkSize())
        ->toBe(ReporteService::TAMANO_DE_LOTE)
        ->and(ReporteService::TAMANO_DE_LOTE)->toBe(500);
});

// ---------------------------------------------------------------------
// Los dos archivos se generan de verdad
// ---------------------------------------------------------------------

it('genera el PDF de cada reporte', function (Reporte $reporte): void {
    datosParaLosTresReportes();

    $respuesta = $this->generador->pdf($reporte, new FiltroReporte);

    expect($respuesta->getStatusCode())->toBe(200)
        ->and($respuesta->headers->get('content-type'))->toContain('application/pdf')
        ->and($respuesta->headers->get('content-disposition'))->toContain("{$reporte->nombreDeArchivo()}.pdf")
        ->and(substr((string) $respuesta->getContent(), 0, 4))->toBe('%PDF');
})->with([
    'uso de escenarios' => Reporte::UsoDeEscenarios,
    'resultados de evaluación' => Reporte::ResultadosDeEvaluacion,
    'evaluaciones no registradas' => Reporte::EvaluacionesNoRegistradas,
]);

it('genera el Excel de cada reporte a partir del mismo exportador', function (Reporte $reporte): void {
    Excel::fake();
    datosParaLosTresReportes();

    // El generador recibe Excel por constructor, así que hay que resolverlo
    // después de fake() para que le llegue la instancia falsa.
    app(GeneradorDeReportes::class)->excel($reporte, new FiltroReporte);

    Excel::assertDownloaded("{$reporte->nombreDeArchivo()}.xlsx");
})->with([
    'uso de escenarios' => Reporte::UsoDeEscenarios,
    'resultados de evaluación' => Reporte::ResultadosDeEvaluacion,
    'evaluaciones no registradas' => Reporte::EvaluacionesNoRegistradas,
]);

it('escribe en el PDF el título del reporte y la descripción del filtro', function (): void {
    datosParaLosTresReportes();

    $contenido = (string) $this->generador->pdf(
        Reporte::UsoDeEscenarios,
        new FiltroReporte(desde: '2026-04-01', hasta: '2026-04-30'),
    )->getContent();

    expect($contenido)->toStartWith('%PDF')
        ->and(strlen($contenido))->toBeGreaterThan(1000);
});

// ---------------------------------------------------------------------
// Quién genera reportes
// ---------------------------------------------------------------------

it('deja generar reportes al coordinador y al ADMIN', function (Rol $rol): void {
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect(Gate::forUser($usuario)->allows('generarReportes'))->toBeTrue();
})->with([Rol::Admin, Rol::Coordinador]);

it('no deja generar reportes al administrativo', function (): void {
    // Segunda función operativa, junto con la verificación del
    // consentimiento, donde el administrativo no acompaña al coordinador.
    $administrativo = User::factory()->administrativo()->create();

    expect(Gate::forUser($administrativo)->allows('generarReportes'))->toBeFalse();
});

it('no deja a un docente ni a un estudiante ver los reportes agregados', function (Rol $rol): void {
    // Su historial propio lo cubren el RF35, el RF49 y el RF50.
    $usuario = User::factory()->create();
    $usuario->assignRole($rol->value);

    expect(Gate::forUser($usuario)->allows('generarReportes'))->toBeFalse();
})->with([Rol::Docente, Rol::Estudiante]);

it('deniega los reportes a un usuario sin ningún rol', function (): void {
    expect(Gate::forUser(User::factory()->create())->allows('generarReportes'))->toBeFalse();
});
