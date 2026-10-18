<?php

declare(strict_types=1);

namespace App\Livewire\Supervision;

use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\Evaluacion;
use App\Services\SupervisionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * El docente supervisor consulta los resultados de las evaluaciones que
 * hicieron los docentes a su cargo (RF77). Solo lectura.
 *
 * Los filtros viven en la URL: el botón «volver» y un marcador conservan el
 * periodo y el docente que se estaban mirando. Vacío es el periodo vigente.
 * Los enlaces de descarga llevan los mismos filtros, así que lo que se baja
 * es lo que se ve.
 */
final class EvaluacionesDeMisDocentes extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    #[Url(except: '')]
    public string $periodo = '';

    #[Url(except: '')]
    public string $docente = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('supervisar', Evaluacion::class);
    }

    public function render(SupervisionService $supervision): mixed
    {
        $supervisor = Auth::user();
        $periodo = $supervision->periodoDe($this->periodo);
        $docenteId = $this->docente === '' ? null : (int) $this->docente;
        $filtrosDeDescarga = array_filter(['periodo' => $this->periodo, 'docente' => $this->docente], static fn (string $valor): bool => $valor !== '');

        return view('livewire.supervision.evaluaciones-de-mis-docentes', [
            'resultados' => $supervision->resultados($supervisor, $periodo, $docenteId)->paginate(SupervisionService::POR_PAGINA),
            'docentes' => $supervision->docentesDe($supervisor),
            'periodos' => $supervision->periodosConsultables(),
            'periodoVigente' => $supervision->periodoDe(null),
            'periodoElegido' => $periodo,
            'filtrosDeDescarga' => $filtrosDeDescarga,
        ]);
    }
}
