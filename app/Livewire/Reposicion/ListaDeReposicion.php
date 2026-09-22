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

    /** Día en que arranca el borrador, cuando ya no se puede elegir. */
    public ?string $origenFijado = null;

    /** La última lista llegó hasta hoy: el periodo siguiente no ha empezado. */
    public bool $periodoSinEmpezar = false;

    /** Necesidad que se está dando por atendida. */
    public ?int $atendiendo = null;

    public string $motivoAtencion = '';

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

        $this->origenFijado = $reposicion->origenDelSiguienteBorrador();

        // En cuanto hay una lista cerrada, el borrador arranca donde terminó
        // aquella y deja de ser editable: así ningún movimiento puede quedar
        // en dos listas ni en ninguna. La primera vez sí se elige, porque el
        // historial completo arranca con la migración del RF66.2 y el
        // laboratorio decide desde cuándo cuenta.
        $this->desde = $this->origenFijado ?? ($this->desde !== '' ? $this->desde : now()->startOfYear()->toDateString());

        // Recién cerrada una lista hasta hoy, el periodo siguiente arranca
        // mañana y todavía no hay nada que cerrar. Sin esto el rango queda
        // al revés en pantalla y el cierre falla con un mensaje que no
        // explica lo que pasa.
        $this->periodoSinEmpezar = $this->desde > now()->toDateString();

        if ($this->hasta === '' || $this->hasta < $this->desde) {
            $this->hasta = max(now()->toDateString(), $this->desde);
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

    public function pedirAtencion(int $necesidadId): void
    {
        $this->authorize('atender', NecesidadDeReposicion::findOrFail($necesidadId));
        $this->atendiendo = $necesidadId;
        $this->motivoAtencion = '';
        $this->errorDeRegla = null;
    }

    public function cancelarAtencion(): void
    {
        $this->reset('atendiendo', 'motivoAtencion', 'errorDeRegla');
    }

    /**
     * La necesidad deja de pedirse. No repone unidades: eso es otro acto,
     * con su propia cantidad y su propio motivo (RF66).
     */
    public function atender(ReposicionService $reposicion): void
    {
        $necesidad = NecesidadDeReposicion::findOrFail($this->atendiendo);
        $this->authorize('atender', $necesidad);
        $this->validate(['motivoAtencion' => 'required|string|min:5|max:200']);
        $this->errorDeRegla = null;

        try {
            $reposicion->atenderNecesidad(Auth::user(), $necesidad, $this->motivoAtencion);
        } catch (ReposicionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->cancelarAtencion();
        session()->flash('estado', 'Necesidad atendida. Deja de aparecer en los borradores.');
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
        $this->origenFijado = $reposicion->origenDelSiguienteBorrador();
        session()->flash('estado', 'Lista cerrada. Ya se puede descargar como soporte de la solicitud de compra.');
    }

    public function render(ReposicionService $reposicion): mixed
    {
        $cerrada = $this->listaCerrada === null ? null : Lista::with('cerradaPor:id,nombre')->find($this->listaCerrada);

        return view('livewire.reposicion.lista-de-reposicion', [
            'lista' => $cerrada,
            'lineas' => $cerrada?->lineas()->orderBy('motivo')->orderBy('descripcion')->get(),
            'previsualizacion' => $cerrada === null ? $reposicion->previsualizar($this->desde, $this->hasta) : collect(),
            'necesidades' => $reposicion->necesidades(),
            'motivos' => MotivoReposicion::cases(),
            'items' => ItemInventario::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'listasAnteriores' => $reposicion->listas(porPagina: 5),
        ]);
    }
}
