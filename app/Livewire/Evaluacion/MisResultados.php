<?php

declare(strict_types=1);

namespace App\Livewire\Evaluacion;

use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\EvaluacionEstudiante;
use App\Services\EvaluacionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * El estudiante consulta sus evaluaciones finalizadas, con el resultado, el
 * intento y el checklist evaluado (RF49). Solo las suyas: se buscan por el
 * usuario autenticado, nunca por un id que llegue de la petición.
 */
final class MisResultados extends Component
{
    use AutorizaEnCadaPeticion;

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', EvaluacionEstudiante::class);
    }

    public function render(EvaluacionService $evaluaciones): mixed
    {
        return view('livewire.evaluacion.mis-resultados', [
            'resultados' => $evaluaciones->historialDelEstudiante(Auth::user()),
        ]);
    }
}
