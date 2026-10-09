<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Exceptions\SolicitudInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Services\RegistroPrevioService;
use App\Services\SolicitudService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El administrativo digita el formato intramural de una sesión: los
 * insumos, equipos y simuladores que llegaron en papel (RF59). Se precargan
 * los del escenario y se ajustan. Al guardar se avisa de lo que no alcanza
 * en esa franja (RF58), sin impedirlo.
 */
final class FormatoIntramural extends Component
{
    use AutorizaEnCadaPeticion;

    #[Locked]
    public int $solicitudId;

    /** @var array<int, int> id del ítem => cantidad */
    public array $items = [];

    public ?int $itemAAgregar = null;

    /** @var array<int, int> id del ítem => unidades libres, de lo que no alcanza */
    public array $faltantes = [];

    public ?string $errorDeRegla = null;

    public function mount(Solicitud $solicitud, SolicitudService $solicitudes): void
    {
        $this->solicitudId = $solicitud->id;
        $pedidos = $solicitud->items()->get();

        // Sin formato todavía: se parte del inventario que el escenario necesita (RF29).
        $fuente = $pedidos->isEmpty() ? $solicitudes->itemsSugeridos($solicitud->casoClinico) : $pedidos;

        $this->items = $fuente
            ->mapWithKeys(static fn (ItemInventario $item): array => [$item->id => (int) data_get($item, 'pivot.cantidad')])
            ->all();
    }

    public function agregarItem(): void
    {
        if ($this->itemAAgregar !== null && ! array_key_exists($this->itemAAgregar, $this->items)) {
            $this->items[$this->itemAAgregar] = 1;
        }

        $this->itemAAgregar = null;
    }

    public function quitarItem(int $itemId): void
    {
        unset($this->items[$itemId]);
    }

    public function guardar(RegistroPrevioService $registroPrevio): void
    {
        $solicitud = Solicitud::findOrFail($this->solicitudId);
        $this->authorize('registrarFormatoIntramural', $solicitud);
        $this->validate(['items' => 'array', 'items.*' => 'integer|min:1|max:1000']);
        $this->errorDeRegla = null;

        try {
            $this->faltantes = $registroPrevio->registrarFormatoIntramural(
                $solicitud,
                array_map(static fn (mixed $cantidad): int => (int) $cantidad, $this->items),
                Auth::user(),
            );
        } catch (SolicitudInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        session()->flash('estado', 'Formato intramural registrado. La preparación ya tiene la lista para alistar.');
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('registrarFormatoIntramural', Solicitud::findOrFail($this->solicitudId));
    }

    public function render(): mixed
    {
        return view('livewire.solicitud.formato-intramural', [
            'solicitud' => Solicitud::with(['docente:id,nombre', 'materia:id,nombre', 'casoClinico:id,nombre', 'formatoIntramuralPor:id,nombre'])->findOrFail($this->solicitudId),
            'elegidos' => ItemInventario::query()->whereIn('id', array_keys($this->items))->orderBy('tipo')->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'catalogo' => ItemInventario::query()->where('activo', true)->whereNotIn('id', array_keys($this->items))
                ->orderBy('tipo')->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
        ]);
    }
}
