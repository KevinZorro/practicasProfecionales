<?php

declare(strict_types=1);

namespace App\Livewire\Inventario;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Models\ItemInventario;
use App\Services\InventarioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado del inventario (RF38) y cambio de estado funcional (RF66).
 *
 * Solo elige filtros y recoge el motivo; la consulta y las transiciones
 * viven en InventarioService, y quién puede cada una lo decide la Policy.
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

    /** Ítem cuyo cambio de estado se está redactando. */
    public ?int $cambiandoEstado = null;

    public string $estadoDestino = '';

    public string $motivo = '';

    public ?string $errorDeRegla = null;

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

    /**
     * Abre el formulario de cambio de estado. El destino se propone, pero
     * quien decide si la transición vale es el Service.
     */
    public function pedirCambioDeEstado(int $itemId, string $destino): void
    {
        $this->authorize('cambiarEstadoFuncional', ItemInventario::findOrFail($itemId));

        $this->cambiandoEstado = $itemId;
        $this->estadoDestino = $destino;
        $this->motivo = '';
        $this->errorDeRegla = null;
    }

    public function cancelarCambioDeEstado(): void
    {
        $this->reset('cambiandoEstado', 'estadoDestino', 'motivo', 'errorDeRegla');
    }

    public function confirmarCambioDeEstado(InventarioService $inventario): void
    {
        $item = ItemInventario::findOrFail($this->cambiandoEstado);
        $destino = EstadoItemInventario::from($this->estadoDestino);

        // La Policy vuelve a mirarse dentro del Service, que es donde vive la
        // diferencia entre dar de baja y el resto de transiciones.
        $this->authorize($destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional', $item);
        $this->validate(['motivo' => 'required|string|min:5|max:1000']);
        $this->errorDeRegla = null;

        try {
            $inventario->cambiarEstado(Auth::user(), $item, $destino, $this->motivo);
        } catch (InventarioInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->cancelarCambioDeEstado();
        session()->flash('estado', sprintf('«%s» queda en «%s».', $item->nombre, $destino->etiqueta()));
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
