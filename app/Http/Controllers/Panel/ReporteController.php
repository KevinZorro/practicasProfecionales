<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\Reporte;
use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroDeReporteRequest;
use App\Services\GeneradorDeReportes;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes agregados (RF54-RF56), para coordinación y ADMIN.
 *
 * Las descargas son enlaces normales y no acciones de Livewire: el navegador
 * baja el archivo por su cuenta, sin pasarlo entero por la respuesta JSON
 * del componente, que es lo que aguanta una conexión lenta.
 */
final class ReporteController extends Controller
{
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('generarReportes'), 403);

        return view('panel.reportes.index');
    }

    public function excel(FiltroDeReporteRequest $peticion, Reporte $reporte, GeneradorDeReportes $reportes): BinaryFileResponse
    {
        return $reportes->excel($reporte, $peticion->filtroPara($reporte));
    }

    public function pdf(FiltroDeReporteRequest $peticion, Reporte $reporte, GeneradorDeReportes $reportes): Response
    {
        return $reportes->pdf($reporte, $peticion->filtroPara($reporte));
    }
}
