<?php

declare(strict_types=1);

namespace App\Livewire\Consentimiento;

use App\Models\ConsentimientoEstudiante;
use App\Services\ConsentimientoService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Quién tiene el consentimiento al día antes de una práctica (RF52).
 */
final class EstadoDeEstudiantes extends Component
{
    use WithPagination;

    /** Valores del filtro de situación. */
    public const AL_DIA = 'al_dia';

    public const LE_FALTA = 'le_falta';

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'periodo', keep: false)]
    public string $periodo = '';

    #[Url(as: 'situacion', keep: false)]
    public string $situacion = '';

    public function mount(ConsentimientoService $consentimientos): void
    {
        $this->authorize('viewAny', ConsentimientoEstudiante::class);

        if ($this->periodo === '') {
            $this->periodo = $consentimientos->periodoVigente();
        }
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'periodo', 'situacion'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'situacion');
        $this->resetPage();
    }

    public function render(ConsentimientoService $consentimientos): mixed
    {
        return view('livewire.consentimiento.estado-de-estudiantes', [
            'estudiantes' => $consentimientos->estadoDeLosEstudiantes(
                periodo: $this->periodo,
                soloSinVigente: $this->soloSinVigente(),
                busqueda: $this->busqueda,
            ),
        ]);
    }

    /** null: todos. true: solo a quien le falta. false: solo quien está al día. */
    private function soloSinVigente(): ?bool
    {
        return match ($this->situacion) {
            self::LE_FALTA => true,
            self::AL_DIA => false,
            default => null,
        };
    }
}
