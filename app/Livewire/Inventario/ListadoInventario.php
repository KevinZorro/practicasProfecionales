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

    /** Valor del filtro de estado que busca lo que hay que mirar. */
    public const NO_OPERATIVAS = 'no_operativas';

    /** Ítem cuyo movimiento de unidades se está redactando. */
    public ?int $cambiandoEstado = null;

    public string $estadoOrigen = '';

    public string $estadoDestino = '';

    public int $cantidad = 1;

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
     * Abre el formulario de movimiento. Origen y destino se proponen, pero
     * quien decide si la transición vale es el Service.
     */
    public function pedirCambioDeEstado(int $itemId, string $origen, string $destino): void
    {
        $item = ItemInventario::findOrFail($itemId);
        $this->authorize('cambiarEstadoFuncional', $item);

        $this->cambiandoEstado = $itemId;
        $this->estadoOrigen = $origen;
        $this->estadoDestino = $destino;
        // Se propone mover todo lo que hay en el origen, que es el caso más
        // común en una pieza única; con ocho sondas la administrativa
        // corrige el número.
        $this->cantidad = max(1, $item->cantidadEn(EstadoItemInventario::from($origen)));
        $this->motivo = '';
        $this->errorDeRegla = null;
    }

    public function cancelarCambioDeEstado(): void
    {
        $this->reset('cambiandoEstado', 'estadoOrigen', 'estadoDestino', 'cantidad', 'motivo', 'errorDeRegla');
    }

    public function confirmarCambioDeEstado(InventarioService $inventario): void
    {
        $item = ItemInventario::findOrFail($this->cambiandoEstado);
        $origen = EstadoItemInventario::from($this->estadoOrigen);
        $destino = EstadoItemInventario::from($this->estadoDestino);

        // La Policy vuelve a mirarse dentro del Service, que es donde vive la
        // diferencia entre dar de baja y el resto de movimientos.
        $this->authorize($destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional', $item);
        $this->validate([
            'cantidad' => 'required|integer|min:1',
            'motivo' => 'required|string|min:5|max:1000',
        ]);
        $this->errorDeRegla = null;

        try {
            $inventario->cambiarEstado(Auth::user(), $item, $origen, $destino, $this->cantidad, $this->motivo);
        } catch (InventarioInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $cantidad = $this->cantidad;
        $this->cancelarCambioDeEstado();
        session()->flash('estado', sprintf(
            '%d unidad(es) de «%s» pasan a «%s».',
            $cantidad,
            $item->nombre,
            $destino->etiqueta(),
        ));
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
                soloConUnidadesNoOperativas: $this->estado === self::NO_OPERATIVAS,
            ),
            'tipos' => TipoItemInventario::cases(),
            'estados' => EstadoItemInventario::cases(),
            'nivelesFidelidad' => NivelFidelidad::cases(),
        ]);
    }
}
