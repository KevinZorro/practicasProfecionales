<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Enums\Reporte;
use App\Http\Controllers\Controller;
use App\Models\ListaDeReposicion;
use App\Services\FiltroReporte;
use App\Services\GeneradorDeReportes;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lista de insumos por pedir o reponer (RF67).
 *
 * Las descargas son de listas ya cerradas: en borrador las cifras todavía se
 * mueven, y un soporte de solicitud de compra que cambia después de
 * entregarlo no sirve de soporte.
 */
final class ReposicionController extends Controller
{
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', ListaDeReposicion::class), 403);

        return view('panel.reposicion.index');
    }

    public function excel(Request $peticion, ListaDeReposicion $lista, GeneradorDeReportes $reportes): BinaryFileResponse
    {
        $this->garantizarDescarga($peticion, $lista);

        return $reportes->excel(Reporte::ListaDeReposicion, $this->filtroDe($lista));
    }

    public function pdf(Request $peticion, ListaDeReposicion $lista, GeneradorDeReportes $reportes): Response
    {
        $this->garantizarDescarga($peticion, $lista);

        return $reportes->pdf(Reporte::ListaDeReposicion, $this->filtroDe($lista));
    }

    private function garantizarDescarga(Request $peticion, ListaDeReposicion $lista): void
    {
        abort_unless($peticion->user()->can('exportar', $lista), 403);
        abort_unless($lista->estaCerrada(), 404);
    }

    private function filtroDe(ListaDeReposicion $lista): FiltroReporte
    {
        return new FiltroReporte(
            desde: $lista->desde->format('Y-m-d'),
            hasta: $lista->hasta->format('Y-m-d'),
            listaDeReposicionId: $lista->id,
        );
    }
}
