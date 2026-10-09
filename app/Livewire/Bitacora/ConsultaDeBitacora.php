<?php

declare(strict_types=1);

namespace App\Livewire\Bitacora;

use App\Enums\AccionAuditada;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\RegistroDeBitacora;
use App\Services\BitacoraService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Consulta de la bitácora de auditoría (RF62): qué se hizo, quién, cuándo y
 * por qué. Solo lectura.
 */
final class ConsultaDeBitacora extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    #[Url(as: 'accion', keep: false)]
    public string $accion = '';

    #[Url(as: 'persona', keep: false)]
    public string $persona = '';

    #[Url(as: 'desde', keep: false)]
    public string $desde = '';

    #[Url(as: 'hasta', keep: false)]
    public string $hasta = '';

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['accion', 'persona', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('accion', 'persona', 'desde', 'hasta');
        $this->resetPage();
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', RegistroDeBitacora::class);
    }

    public function render(BitacoraService $bitacora): mixed
    {
        $this->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);

        return view('livewire.bitacora.consulta-de-bitacora', [
            'registros' => $bitacora->listado(
                AccionAuditada::tryFrom($this->accion),
                $this->persona,
                $this->desde,
                $this->hasta,
            ),
            'acciones' => AccionAuditada::cases(),
        ]);
    }
}
