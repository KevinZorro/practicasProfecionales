<?php

declare(strict_types=1);

namespace App\Livewire\Reposicion;

use App\Enums\MotivoReposicion;
use App\Exceptions\ReposicionInvalida;
use App\Models\ItemInventario;
use App\Models\ListaDeReposicion as Lista;
use App\Models\NecesidadDeReposicion;
use App\Services\DatosNecesidad;
use App\Services\ReposicionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lista de insumos por pedir o reponer (RF67).
 *
 * En borrador enseña la previsualización, que se calcula en el momento desde
 * el historial y las necesidades anotadas. Al cerrarla, las líneas se
 * congelan y la pantalla pasa a leer esas, que son las que se exportan.
 */
final class ListaDeReposicion extends Component
{
    #[Url(as: 'desde', keep: false)]
    public string $desde = '';

    #[Url(as: 'hasta', keep: false)]
    public string $hasta = '';

    public ?int $listaCerrada = null;

    public string $observaciones = '';

    /** Formulario de necesidad. */
    public bool $anotando = false;

    public ?int $itemId = null;

    public string $descripcion = '';

    public int $cantidad = 1;

    public string $justificacion = '';

    public ?string $errorDeRegla = null;

    public function mount(ReposicionService $reposicion): void
    {
        $this->authorize('viewAny', Lista::class);

        if ($this->desde === '') {
            $this->desde = now()->startOfYear()->toDateString();
        }

        if ($this->hasta === '') {
            $this->hasta = now()->toDateString();
        }
    }

    public function abrirFormulario(): void
    {
        $this->authorize('create', NecesidadDeReposicion::class);
        $this->reset('itemId', 'descripcion', 'cantidad', 'justificacion', 'errorDeRegla');
        $this->cantidad = 1;
        $this->anotando = true;
    }

    public function cancelarFormulario(): void
    {
        $this->reset('anotando', 'itemId', 'descripcion', 'cantidad', 'justificacion', 'errorDeRegla');
        $this->cantidad = 1;
    }

    public function anotarNecesidad(ReposicionService $reposicion): void
    {
        $this->authorize('create', NecesidadDeReposicion::class);
        $this->validate([
            'cantidad' => 'required|integer|min:1',
            'justificacion' => 'required|string|min:5|max:1000',
            'descripcion' => 'nullable|string|max:200',
            'itemId' => 'nullable|integer|exists:items_inventario,id',
        ]);
        $this->errorDeRegla = null;

        try {
            $reposicion->registrarNecesidad(Auth::user(), new DatosNecesidad(
                cantidad: $this->cantidad,
                justificacion: $this->justificacion,
                itemInventarioId: $this->itemId,
                descripcion: $this->descripcion === '' ? null : $this->descripcion,
            ));
        } catch (ReposicionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->cancelarFormulario();
        session()->flash('estado', 'Necesidad anotada. Entrará en la lista del periodo.');
    }

    /** Congela la lista: a partir de aquí no cambia (RF67). */
    public function cerrar(ReposicionService $reposicion): void
    {
        $lista = new Lista(['desde' => $this->desde, 'hasta' => $this->hasta]);
        $this->authorize('cerrar', $lista);
        $this->errorDeRegla = null;

        $lista->save();

        try {
            $reposicion->cerrar(Auth::user(), $lista, $this->observaciones === '' ? null : $this->observaciones);
        } catch (ReposicionInvalida $invalida) {
            $lista->delete();
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->listaCerrada = $lista->id;
        session()->flash('estado', 'Lista cerrada. Ya se puede descargar como soporte de la solicitud de compra.');
    }

    public function render(ReposicionService $reposicion): mixed
    {
        $cerrada = $this->listaCerrada === null ? null : Lista::with('cerradaPor:id,nombre')->find($this->listaCerrada);

        return view('livewire.reposicion.lista-de-reposicion', [
            'lista' => $cerrada,
            'lineas' => $cerrada?->lineas()->orderBy('motivo')->orderBy('descripcion')->get(),
            'previsualizacion' => $cerrada === null ? $reposicion->previsualizar($this->desde, $this->hasta) : collect(),
            'necesidades' => $reposicion->necesidades($this->desde, $this->hasta),
            'motivos' => MotivoReposicion::cases(),
            'items' => ItemInventario::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'listasAnteriores' => $reposicion->listas(porPagina: 5),
        ]);
    }
}
