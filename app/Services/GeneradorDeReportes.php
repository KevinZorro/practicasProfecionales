<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Reporte;
use App\Exports\EvaluacionesNoRegistradasExport;
use App\Exports\ReporteExport;
use App\Exports\ResultadosDeEvaluacionExport;
use App\Exports\UsoDeEscenariosExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Exporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une los tres formatos de salida de un reporte sobre una sola consulta.
 *
 * Pantalla, PDF y Excel pasan todos por aquí: la consulta la arma
 * ReporteService una vez, y el mapeo de cada fila lo define la clase Export
 * una vez. Ninguno de los tres formatos construye datos por su cuenta, así
 * que no pueden mostrar cifras distintas para el mismo filtro.
 */
final class GeneradorDeReportes
{
    public function __construct(
        private readonly ReporteService $reportes,
        private readonly Exporter $excel,
    ) {}

    /**
     * La consulta del reporte, sin ejecutar.
     *
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function consulta(Reporte $reporte, FiltroReporte $filtro): Builder
    {
        return match ($reporte) {
            Reporte::UsoDeEscenarios => $this->reportes->usoDeEscenarios($filtro),
            Reporte::ResultadosDeEvaluacion => $this->reportes->resultadosDeEvaluacion($filtro),
            Reporte::EvaluacionesNoRegistradas => $this->reportes->evaluacionesNoRegistradas($filtro),
        };
    }

    public function exportador(Reporte $reporte, FiltroReporte $filtro): ReporteExport
    {
        $consulta = $this->consulta($reporte, $filtro);

        return match ($reporte) {
            Reporte::UsoDeEscenarios => new UsoDeEscenariosExport($consulta),
            Reporte::ResultadosDeEvaluacion => new ResultadosDeEvaluacionExport($consulta),
            Reporte::EvaluacionesNoRegistradas => new EvaluacionesNoRegistradasExport($consulta),
        };
    }

    /**
     * Salida a pantalla: la misma consulta, paginada.
     */
    public function paraPantalla(Reporte $reporte, FiltroReporte $filtro, int $porPagina = ReporteService::POR_PAGINA): LengthAwarePaginator
    {
        $exportador = $this->exportador($reporte, $filtro);
        $pagina = $this->reportes->paginar($exportador->query(), $porPagina);

        return $pagina->through(static fn ($fila): array => $exportador->map($fila));
    }

    /**
     * Todas las filas del reporte, ya mapeadas, recorridas por lotes.
     *
     * Es lo que alimenta el PDF, y usa el mismo map() que el Excel.
     *
     * @return Collection<int, list<mixed>>
     */
    public function filas(Reporte $reporte, FiltroReporte $filtro): Collection
    {
        $exportador = $this->exportador($reporte, $filtro);
        $filas = new Collection;

        $this->reportes->porLotes(
            $exportador->query(),
            static function (Collection $lote) use ($exportador, $filas): void {
                $lote->each(static fn ($fila) => $filas->push($exportador->map($fila)));
            },
        );

        return $filas;
    }

    public function pdf(Reporte $reporte, FiltroReporte $filtro): Response
    {
        $exportador = $this->exportador($reporte, $filtro);

        return Pdf::loadView($reporte->plantillaPdf(), [
            'titulo' => $reporte->titulo(),
            'descripcionDelFiltro' => $filtro->descripcion(),
            'encabezados' => $exportador->headings(),
            'filas' => $this->filas($reporte, $filtro),
        ])->setPaper('a4', 'landscape')->download("{$reporte->nombreDeArchivo()}.pdf");
    }

    public function excel(Reporte $reporte, FiltroReporte $filtro): BinaryFileResponse
    {
        return $this->excel->download(
            $this->exportador($reporte, $filtro),
            "{$reporte->nombreDeArchivo()}.xlsx",
        );
    }
}
