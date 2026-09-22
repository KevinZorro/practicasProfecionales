<?php

declare(strict_types=1);

namespace App\Livewire\Confidencialidad;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Models\FormatoConfidencialidad;
use App\Services\ConfidencialidadService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Verificación de los formatos entregados (RF52).
 *
 * Solo coordinador y ADMIN. El administrativo queda fuera, y quien lo decide
 * es la Policy: aquí no se comprueba ningún rol.
 */
final class BandejaVerificacion extends Component
{
    use WithPagination;

    #[Url(as: 'estado', keep: false)]
    public string $estado = EstadoFormatoConfidencialidad::Cargado->value;

    #[Url(as: 'periodo', keep: false)]
    public string $periodo = '';

    public ?int $rechazando = null;

    public string $motivoRechazo = '';

    public ?string $errorDeRegla = null;

    public function mount(ConfidencialidadService $confidencialidad): void
    {
        if ($this->periodo === '') {
            $this->periodo = $confidencialidad->periodoVigente();
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

    public function verificar(int $entregaId, ConfidencialidadService $confidencialidad): void
    {
        $entrega = $this->entrega($entregaId);
        $this->authorize('verificar', $entrega);
        $this->errorDeRegla = null;

        try {
            $confidencialidad->verificar($entrega, Auth::user());
        } catch (FormatoConfidencialidadInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        session()->flash('estado', 'Formato verificado.');
    }

    public function rechazar(int $entregaId, ConfidencialidadService $confidencialidad): void
    {
        $entrega = $this->entrega($entregaId);
        $this->authorize('rechazar', $entrega);
        $this->validate(['motivoRechazo' => 'nullable|string|max:1000']);
        $this->errorDeRegla = null;

        try {
            $confidencialidad->rechazar($entrega, Auth::user(), $this->motivoRechazo ?: null);
        } catch (FormatoConfidencialidadInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->cancelar();
        session()->flash('estado', 'Formato devuelto para que lo vuelvan a subir.');
    }

    public function render(ConfidencialidadService $confidencialidad): mixed
    {
        return view('livewire.confidencialidad.bandeja-verificacion', [
            'entregas' => $confidencialidad->bandeja(
                EstadoFormatoConfidencialidad::tryFrom($this->estado),
                $this->periodo,
            ),
            'estados' => EstadoFormatoConfidencialidad::cases(),
        ]);
    }

    private function entrega(int $entregaId): FormatoConfidencialidad
    {
        return FormatoConfidencialidad::with('firmante')->findOrFail($entregaId);
    }
}
