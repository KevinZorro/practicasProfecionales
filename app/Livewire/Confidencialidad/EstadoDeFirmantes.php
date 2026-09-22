<?php

declare(strict_types=1);

namespace App\Livewire\Confidencialidad;

use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Models\FormatoConfidencialidad;
use App\Models\User;
use App\Services\ConfidencialidadService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Quién tiene el formato de confidencialidad al día antes de una práctica
 * (RF52).
 *
 * Lista a estudiantes y docentes juntos, porque el formato lo firma todo el
 * que entra a la práctica y quien revisa lo revisa de una sola pasada.
 */
final class EstadoDeFirmantes extends Component
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

    public function mount(ConfidencialidadService $confidencialidad): void
    {
        $this->authorize('viewAny', FormatoConfidencialidad::class);

        if ($this->periodo === '') {
            $this->periodo = $confidencialidad->periodoVigente();
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
     * registra para dejar entrar a quien lo trae. El escaneo llega después.
     */
    public function marcarEntregaFisica(int $firmanteId, ConfidencialidadService $confidencialidad): void
    {
        $this->authorize('marcarEntregaFisica', FormatoConfidencialidad::class);
        $this->errorDeRegla = null;

        try {
            $confidencialidad->registrarEntregaFisica(User::findOrFail($firmanteId), Auth::user());
        } catch (FormatoConfidencialidadInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        session()->flash('estado', 'Entrega en físico registrada. Puede ingresar a la práctica.');
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'situacion');
        $this->resetPage();
    }

    public function render(ConfidencialidadService $confidencialidad): mixed
    {
        return view('livewire.confidencialidad.estado-de-firmantes', [
            'firmantes' => $confidencialidad->estadoDeLosFirmantes(
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
