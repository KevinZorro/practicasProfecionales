<?php

declare(strict_types=1);

namespace App\Livewire\Consentimiento;

use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoEstudiante;
use App\Models\User;
use App\Services\ConsentimientoService;
use Illuminate\Support\Facades\Auth;
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

    public ?string $errorDeRegla = null;

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

    /**
     * RF53: la administrativa recibe el formato firmado en la puerta y lo
     * registra para dejar entrar al estudiante. El escaneo llega después.
     */
    public function marcarEntregaFisica(int $estudianteId, ConsentimientoService $consentimientos): void
    {
        $this->authorize('marcarEntregaFisica', ConsentimientoEstudiante::class);
        $this->errorDeRegla = null;

        try {
            $consentimientos->registrarEntregaFisica(User::findOrFail($estudianteId), Auth::user());
        } catch (ConsentimientoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        session()->flash('estado', 'Entrega en físico registrada. El estudiante puede ingresar a la práctica.');
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
