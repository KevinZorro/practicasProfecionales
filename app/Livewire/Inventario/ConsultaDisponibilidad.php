<?php

declare(strict_types=1);

namespace App\Livewire\Inventario;

use App\Models\ItemInventario;
use App\Services\InventarioService;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Cuántas unidades de un ítem quedan libres en una franja (RF40).
 *
 * Vedada a docentes y estudiantes: la Policy lo decide y el controlador que
 * sirve la pantalla lo comprueba antes de pintarla.
 */
final class ConsultaDisponibilidad extends Component
{
    #[Validate('required|integer|exists:items_inventario,id')]
    public ?int $itemId = null;

    #[Validate('required|date')]
    public string $fecha = '';

    #[Validate('required|date_format:H:i')]
    public string $horaInicio = '07:00';

    #[Validate('required|date_format:H:i|after:horaInicio')]
    public string $horaFin = '09:00';

    public ?int $libres = null;

    public ?ItemInventario $consultado = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
    }

    public function consultar(InventarioService $inventario): void
    {
        $this->authorize('viewAny', ItemInventario::class);
        $this->validate();

        $this->consultado = ItemInventario::findOrFail($this->itemId);
        $this->libres = $inventario->disponibilidadEnFranja(
            $this->consultado,
            $this->fecha,
            $this->horaInicio.':00',
            $this->horaFin.':00',
        );
    }

    public function render(): mixed
    {
        return view('livewire.inventario.consulta-disponibilidad', [
            'items' => ItemInventario::query()
                ->orderBy('tipo')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo', 'cantidad_total']),
        ]);
    }
}
