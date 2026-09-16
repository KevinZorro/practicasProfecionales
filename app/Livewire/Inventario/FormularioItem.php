<?php

declare(strict_types=1);

namespace App\Livewire\Inventario;

use App\Enums\EstadoItemInventario;
use App\Enums\NivelFidelidad;
use App\Enums\TipoItemInventario;
use App\Exceptions\InventarioInvalido;
use App\Models\ItemInventario;
use App\Services\DatosItemInventario;
use App\Services\InventarioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Alta y edición de un ítem del inventario (RF38-RF39).
 *
 * El nivel de fidelidad se muestra siempre que el ítem sea un simulador,
 * pero solo es editable para el ADMIN: ocultarlo a los demás haría creer
 * que el dato no existe. Quien decide es la Policy, y el Service vuelve a
 * comprobarlo antes de tocar la columna.
 */
final class FormularioItem extends Component
{
    public ?ItemInventario $item = null;

    #[Validate('required|string|max:150')]
    public string $nombre = '';

    #[Validate('required|string')]
    public string $tipo = '';

    #[Validate('required|integer|min:0|max:9999')]
    public ?int $cantidadTotal = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $descripcion = null;

    #[Validate('required|string')]
    public string $estado = EstadoItemInventario::Disponible->value;

    public bool $activo = true;

    public string $nivelFidelidad = '';

    public ?string $errorDeRegla = null;

    public function mount(?ItemInventario $item = null): void
    {
        if (! $item instanceof ItemInventario || $item->id === null) {
            $this->tipo = TipoItemInventario::Simulador->value;

            return;
        }

        $this->item = $item;
        $this->nombre = (string) $item->nombre;
        $this->tipo = $item->tipo->value;
        $this->cantidadTotal = $item->cantidad_total;
        $this->descripcion = $item->descripcion;
        $this->estado = $item->estado->value;
        $this->activo = $item->activo;
        $this->nivelFidelidad = $item->nivel_fidelidad?->value ?? '';
    }

    /** El nivel de fidelidad solo tiene sentido en simuladores. */
    public function updatedTipo(): void
    {
        if (! $this->esSimulador()) {
            $this->nivelFidelidad = '';
        }
    }

    public function guardar(InventarioService $inventario): void
    {
        $this->authorize($this->item instanceof ItemInventario ? 'update' : 'create', $this->item ?? ItemInventario::class);
        $this->validate();
        $this->errorDeRegla = null;

        try {
            $this->item instanceof ItemInventario
                ? $inventario->actualizar(Auth::user(), $this->item, $this->comoDatos())
                : $inventario->crear(Auth::user(), $this->comoDatos());
        } catch (InventarioInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        session()->flash('estado', 'Ítem guardado.');
        $this->redirectRoute('panel.inventario', navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.inventario.formulario-item', [
            'tipos' => TipoItemInventario::cases(),
            'estados' => EstadoItemInventario::cases(),
            'nivelesFidelidad' => NivelFidelidad::cases(),
            'esSimulador' => $this->esSimulador(),
            'puedeEditarFidelidad' => Auth::user()?->can('editarNivelFidelidad', $this->item ?? ItemInventario::class) ?? false,
        ]);
    }

    private function esSimulador(): bool
    {
        return TipoItemInventario::tryFrom($this->tipo)?->admiteNivelFidelidad() ?? false;
    }

    /**
     * El nivel de fidelidad que se manda al Service es el del formulario
     * solo si quien edita puede cambiarlo; si no, el que ya tenía el ítem.
     * Así una petición manipulada no lo cuela: el Service lo comprueba
     * igual, pero el formulario tampoco lo intenta.
     */
    private function comoDatos(): DatosItemInventario
    {
        $nivel = Auth::user()?->can('editarNivelFidelidad', $this->item ?? ItemInventario::class)
            ? NivelFidelidad::tryFrom($this->nivelFidelidad)
            : $this->item?->nivel_fidelidad;

        return new DatosItemInventario(
            nombre: $this->nombre,
            tipo: TipoItemInventario::from($this->tipo),
            cantidadTotal: (int) $this->cantidadTotal,
            descripcion: $this->descripcion,
            estado: EstadoItemInventario::from($this->estado),
            activo: $this->activo,
            nivelFidelidad: $this->esSimulador() ? $nivel : null,
        );
    }
}
