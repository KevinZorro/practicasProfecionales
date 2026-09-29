<?php

declare(strict_types=1);

namespace App\Livewire\Reportes;

use App\Enums\Reporte;
use App\Http\Requests\FiltroDeReporteRequest;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\Materia;
use App\Models\Sala;
use App\Services\GeneradorDeReportes;
use App\Services\ReporteService;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla de reportes (RF54-RF56).
 *
 * No arma consultas: la tabla sale de GeneradorDeReportes, igual que el PDF
 * y el Excel, y los enlaces de descarga llevan en la URL los mismos filtros
 * que se están viendo. Por eso lo que se descarga es lo que se ve.
 *
 * Los filtros viven en la URL: un reporte filtrado se puede guardar como
 * marcador o mandar por correo a otro coordinador.
 */
final class PantallaDeReportes extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    #[Url(except: 'uso_de_escenarios')]
    public string $reporte = 'uso_de_escenarios';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $docente = '';

    #[Url(except: '')]
    public string $materia = '';

    #[Url(except: '')]
    public string $sala = '';

    public function mount(): void
    {
        $this->reporte = $this->reporteElegido()->value;
        $this->validarFiltros();
    }

    public function updated(): void
    {
        $this->resetPage();
        $this->validarFiltros();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('desde', 'hasta', 'docente', 'materia', 'sala');
        $this->resetErrorBag();
        $this->resetPage();
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('generarReportes');
    }

    public function render(GeneradorDeReportes $generador, ReporteService $reportes): View
    {
        $reporte = $this->reporteElegido();
        $filtrosValidos = $this->getErrorBag()->isEmpty();
        $filtro = FiltroDeReporteRequest::filtro($reporte, $this->filtros());

        return view('livewire.reportes.pantalla-de-reportes', [
            'elegido' => $reporte,
            'reportes' => Reporte::agregados(),
            'encabezados' => $generador->exportador($reporte, $filtro)->headings(),
            'filas' => $filtrosValidos ? $generador->paraPantalla($reporte, $filtro) : null,
            'consultaDeDescarga' => $filtrosValidos ? array_filter($this->filtros(), static fn (string $valor): bool => $valor !== '') : null,
            'docentes' => $reportes->docentesConSolicitudes(),
            'materias' => Materia::query()->orderBy('nombre')->get(['id', 'nombre', 'activo']),
            'salas' => $reporte->filtraPorSala() ? Sala::query()->orderBy('nombre')->get(['id', 'nombre', 'activo']) : null,
        ]);
    }

    /** Un valor que no es de la pantalla —URL escrita a mano— cae al primero. */
    private function reporteElegido(): Reporte
    {
        $reporte = Reporte::tryFrom($this->reporte);

        return in_array($reporte, Reporte::agregados(), true) ? $reporte : Reporte::UsoDeEscenarios;
    }

    /**
     * Los mismos nombres que la URL de descarga. La sala se descarta en los
     * reportes que no saben de salas, igual que al descargar.
     *
     * @return array<string, string>
     */
    private function filtros(): array
    {
        return [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'docente' => $this->docente,
            'materia' => $this->materia,
            'sala' => $this->reporteElegido()->filtraPorSala() ? $this->sala : '',
        ];
    }

    /**
     * Las reglas de la descarga, sobre los filtros con los vacíos como nulos:
     * es lo que recibe el controlador, porque Laravel convierte así las
     * cadenas vacías de la URL. Livewire recoge la excepción y la pinta.
     */
    private function validarFiltros(): void
    {
        $this->resetErrorBag();

        $datos = array_map(static fn (string $valor): ?string => $valor === '' ? null : $valor, $this->filtros());

        Validator::make($datos, FiltroDeReporteRequest::reglas(), FiltroDeReporteRequest::mensajes())->validate();
    }
}
