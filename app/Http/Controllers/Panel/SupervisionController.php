<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\Reporte;
use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroDeSupervisionRequest;
use App\Models\Evaluacion;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evaluaciones de los docentes supervisados (RF77), para el docente
 * supervisor. Las descargas son enlaces normales, como las de los reportes,
 * y salen de la misma consulta que la pantalla.
 */
final class SupervisionController extends Controller
{
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('supervisar', Evaluacion::class), 403);

        return view('panel.supervision.index');
    }

    public function excel(FiltroDeSupervisionRequest $peticion, SupervisionService $supervision, GeneradorDeReportes $reportes): BinaryFileResponse
    {
        return $reportes->excel(Reporte::ResultadosDeEvaluacion, $this->filtro($peticion, $supervision));
    }

    public function pdf(FiltroDeSupervisionRequest $peticion, SupervisionService $supervision, GeneradorDeReportes $reportes): Response
    {
        return $reportes->pdf(Reporte::ResultadosDeEvaluacion, $this->filtro($peticion, $supervision));
    }

    private function filtro(FiltroDeSupervisionRequest $peticion, SupervisionService $supervision): FiltroReporte
    {
        $datos = $peticion->validated();

        return $supervision->filtroDe(
            $peticion->user(),
            $supervision->periodoDe($datos['periodo'] ?? null),
            isset($datos['docente']) && $datos['docente'] !== '' ? (int) $datos['docente'] : null,
        );
    }
}
