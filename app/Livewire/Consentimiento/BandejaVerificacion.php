<?php

declare(strict_types=1);

namespace App\Livewire\Consentimiento;

use App\Enums\EstadoConsentimiento;
use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoEstudiante;
use App\Services\ConsentimientoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Verificación de los consentimientos entregados (RF52).
 *
 * Solo coordinador y ADMIN. El administrativo queda fuera, y quien lo decide
 * es la Policy: aquí no se comprueba ningún rol.
 */
final class BandejaVerificacion extends Component
{
    use WithPagination;

    #[Url(as: 'estado', keep: false)]
    public string $estado = EstadoConsentimiento::Cargado->value;

    #[Url(as: 'periodo', keep: false)]
    public string $periodo = '';

    public ?int $rechazando = null;

    public string $motivoRechazo = '';

    public ?string $errorDeRegla = null;

    public function mount(ConsentimientoService $consentimientos): void
    {
        if ($this->periodo === '') {
            $this->periodo = $consentimientos->periodoVigente();
        }
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['estado', 'periodo'], true)) {
            $this->resetPage();
            $this->cancelar();
        }
    }

    public function pedirMotivo(int $entregaId): void
    {
        $this->authorize('rechazar', $this->entrega($entregaId));
        $this->rechazando = $entregaId;
        $this->motivoRechazo = '';
    }

    public function cancelar(): void
    {
        $this->rechazando = null;
        $this->motivoRechazo = '';
        $this->errorDeRegla = null;
    }

    public function verificar(int $entregaId, ConsentimientoService $consentimientos): void
    {
        $entrega = $this->entrega($entregaId);
        $this->authorize('verificar', $entrega);
        $this->errorDeRegla = null;

        try {
            $consentimientos->verificar($entrega, Auth::user());
        } catch (ConsentimientoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        session()->flash('estado', 'Consentimiento verificado.');
    }

    public function rechazar(int $entregaId, ConsentimientoService $consentimientos): void
    {
        $entrega = $this->entrega($entregaId);
        $this->authorize('rechazar', $entrega);
        $this->validate(['motivoRechazo' => 'nullable|string|max:1000']);
        $this->errorDeRegla = null;

        try {
            $consentimientos->rechazar($entrega, Auth::user(), $this->motivoRechazo ?: null);
        } catch (ConsentimientoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->cancelar();
        session()->flash('estado', 'Consentimiento devuelto al estudiante para que lo vuelva a subir.');
    }

    public function render(ConsentimientoService $consentimientos): mixed
    {
        return view('livewire.consentimiento.bandeja-verificacion', [
            'entregas' => $consentimientos->bandeja(
                EstadoConsentimiento::tryFrom($this->estado),
                $this->periodo,
            ),
            'estados' => EstadoConsentimiento::cases(),
        ]);
    }

    private function entrega(int $entregaId): ConsentimientoEstudiante
    {
        return ConsentimientoEstudiante::with('estudiante')->findOrFail($entregaId);
    }
}
