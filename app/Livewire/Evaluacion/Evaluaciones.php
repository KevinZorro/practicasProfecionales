<?php

declare(strict_types=1);

namespace App\Livewire\Evaluacion;

use App\Exceptions\EvaluacionInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\Evaluacion;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Services\EvaluacionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Evaluaciones (RF41, RF50). El docente ve sus sesiones de evaluación
 * aprobadas que esperan registro y su historial; coordinación y el ADMIN,
 * todas las evaluaciones.
 */
final class Evaluaciones extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    /** @var array<int, int|string|null> id de la solicitud => tipo de evaluación elegido */
    public array $tipoElegido = [];

    public ?string $errorDeRegla = null;

    public function registrar(int $solicitudId, EvaluacionService $evaluaciones): void
    {
        $this->authorize('create', Evaluacion::class);
        $solicitud = Solicitud::findOrFail($solicitudId);
        abort_unless($solicitud->idDelDocenteQueDicta() === Auth::id(), 403);

        $tipoId = (int) ($this->tipoElegido[$solicitudId] ?? 0);

        if ($tipoId === 0) {
            $this->addError('tipoElegido.'.$solicitudId, 'Elige el tipo de evaluación.');

            return;
        }

        $this->errorDeRegla = null;

        try {
            $evaluacion = $evaluaciones->crear($solicitud, TipoEvaluacion::findOrFail($tipoId), Auth::user());
        } catch (EvaluacionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->redirectRoute('panel.evaluaciones.registro', $evaluacion, navigate: true);
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', Evaluacion::class);
    }

    public function render(EvaluacionService $evaluaciones): mixed
    {
        $usuario = Auth::user();
        $registra = $usuario->can('create', Evaluacion::class);
        $porEvaluar = $registra ? $evaluaciones->sesionesPorEvaluar($usuario) : collect();

        return view('livewire.evaluacion.evaluaciones', [
            'registra' => $registra,
            'porEvaluar' => $porEvaluar,
            'tiposPorSesion' => $porEvaluar->mapWithKeys(static fn (Solicitud $s): array => [$s->id => $evaluaciones->tiposParaLaSesion($s)]),
            'mias' => $registra ? $evaluaciones->historialDelDocente($usuario) : collect(),
            'todas' => $usuario->can('verTodas', Evaluacion::class) ? $evaluaciones->todas() : null,
        ]);
    }
}
