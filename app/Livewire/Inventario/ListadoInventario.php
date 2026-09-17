<?php

declare(strict_types=1);

namespace App\Livewire\Inventario;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Models\ItemInventario;
use App\Services\InventarioService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado del inventario (RF38).
 *
 * Solo elige filtros; la consulta vive en InventarioService.
 */
final class ListadoInventario extends Component
{
    use WithPagination;

    /** Valor del filtro de fidelidad que busca los simuladores sin asignar. */
    public const SIN_ASIGNAR = 'sin_asignar';

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'tipo', keep: false)]
    public string $tipo = '';

    #[Url(as: 'estado', keep: false)]
    public string $estado = '';

    #[Url(as: 'fidelidad', keep: false)]
    public string $fidelidad = '';

    public ?int $bajaPendiente = null;

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'tipo', 'estado', 'fidelidad'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'tipo', 'estado', 'fidelidad');
        $this->resetPage();
    }

    public function pedirConfirmacionDeBaja(int $itemId): void
    {
        $this->authorize('darDeBaja', ItemInventario::findOrFail($itemId));
        $this->bajaPendiente = $itemId;
    }

    public function cancelarBaja(): void
    {
        $this->bajaPendiente = null;
    }

    public function darDeBaja(int $itemId, InventarioService $inventario): void
    {
        $item = ItemInventario::findOrFail($itemId);
        $this->authorize('darDeBaja', $item);

        $inventario->darDeBaja($item);
        $this->bajaPendiente = null;
        session()->flash('estado', "«{$item->nombre}» quedó dado de baja. El registro se conserva porque el histórico de solicitudes lo referencia.");
    }

    public function render(InventarioService $inventario): mixed
    {
        return view('livewire.inventario.listado-inventario', [
            'items' => $inventario->listar(
                tipo: TipoItemInventario::tryFrom($this->tipo),
                estado: EstadoItemInventario::tryFrom($this->estado),
                nivelFidelidad: NivelFidelidad::tryFrom($this->fidelidad),
                busqueda: $this->busqueda,
                soloSinFidelidad: $this->fidelidad === self::SIN_ASIGNAR,
            ),
            'tipos' => TipoItemInventario::cases(),
            'estados' => EstadoItemInventario::cases(),
            'nivelesFidelidad' => NivelFidelidad::cases(),
        ]);
    }
}
