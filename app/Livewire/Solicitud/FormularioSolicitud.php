<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\TipoSesion;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Services\DatosNuevaSolicitud;
use App\Services\SolicitudService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Formulario con el que el docente pide un escenario (RF27-RF30).
 *
 * No decide nada: valida formato, arma el objeto de datos y llama al
 * Service. Tampoco enseña disponibilidad de inventario, que el RF40 reserva
 * a administrativos, coordinadores y ADMIN.
 */
final class FormularioSolicitud extends Component
{
    #[Validate('required|string')]
    public string $tipo = TipoSesion::Practica->value;

    #[Validate('required|integer|exists:materias,id')]
    public ?int $materiaId = null;

    #[Validate('required|integer|exists:casos_clinicos,id')]
    public ?int $casoClinicoId = null;

    #[Validate('required|date')]
    public string $fecha = '';

    #[Validate('required|date_format:H:i')]
    public string $horaInicio = '';

    #[Validate('required|date_format:H:i|after:horaInicio')]
    public string $horaFin = '';

    #[Validate('required|integer|min:1|max:200')]
    public ?int $cantidadEstudiantes = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $observaciones = null;

    /** @var array<int, int> id del ítem => cantidad pedida */
    public array $items = [];

    public ?int $itemAAgregar = null;

    /**
     * Capacidad del escenario elegido (RF74). Se enseña bajo el campo para
     * que el docente vea el tope antes de enviar, no después. Null mientras
     * el ADMIN no la registre.
     */
    public ?int $capacidadDelCaso = null;

    /**
     * Al elegir el caso clínico se precarga su inventario (RF29). Es un
     * punto de partida: a partir de aquí el docente ajusta.
     */
    public function updatedCasoClinicoId(mixed $valor): void
    {
        $caso = CasoClinico::find((int) $valor);
        $this->capacidadDelCaso = $caso?->capacidad_maxima_estudiantes;

        $this->items = $caso instanceof CasoClinico
            ? app(SolicitudService::class)->itemsSugeridos($caso)
                ->mapWithKeys(static fn (ItemInventario $item): array => [$item->id => (int) $item->pivot->cantidad])
                ->all()
            : [];
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

    public function guardar(SolicitudService $solicitudes): void
    {
        $this->authorize('create', Solicitud::class);
        $datos = $this->validate();

        try {
            $solicitudes->crear(Auth::user(), $this->comoDatos($datos));
        } catch (CapacidadDeEstudiantesExcedida $excedida) {
            // La regla vive en el Service; aquí solo se traduce a un error
            // del campo para que se lea junto al número, no como un fallo
            // del servidor.
            $this->addError('cantidadEstudiantes', $excedida->getMessage());

            return;
        }

        session()->flash('estado', 'Tu solicitud quedó registrada. El laboratorio la revisará antes de aprobarla.');
        $this->redirectRoute('panel.mis-solicitudes', navigate: true);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function comoDatos(array $datos): DatosNuevaSolicitud
    {
        return new DatosNuevaSolicitud(
            materiaId: (int) $datos['materiaId'],
            casoClinicoId: (int) $datos['casoClinicoId'],
            tipo: TipoSesion::from($datos['tipo']),
            fecha: $datos['fecha'],
            horaInicio: $datos['horaInicio'],
            horaFin: $datos['horaFin'],
            cantidadEstudiantes: (int) $datos['cantidadEstudiantes'],
            observaciones: $datos['observaciones'] ?? null,
            // Una cantidad nunca baja de uno: si el docente la deja en cero,
            // lo que quiere es quitar el equipo, y para eso está "Quitar".
            items: array_map(static fn (mixed $c): int => max(1, (int) $c), $this->items),
        );
    }

    public function render(): mixed
    {
        return view('livewire.solicitud.formulario-solicitud', [
            'materias' => Materia::activas()->orderBy('nombre')->get(['id', 'nombre', 'semestre']),
            'casosClinicos' => CasoClinico::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'tiposDeSesion' => TipoSesion::cases(),
            'seleccionadosPorTipo' => $this->seleccionadosPorTipo(),
            'catalogo' => $this->catalogoParaAgregar(),
        ]);
    }

    /**
     * Los equipos elegidos, agrupados por tipo. Sin existencias: el docente
     * no ve disponibilidad de inventario (RF40).
     *
     * @return array<string, Collection<int, ItemInventario>>
     */
    private function seleccionadosPorTipo(): array
    {
        if ($this->items === []) {
            return [];
        }

        return ItemInventario::query()
            ->whereIn('id', array_keys($this->items))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo'])
            ->groupBy(static fn (ItemInventario $item): string => $item->tipo->value)
            ->all();
    }

    /**
     * Catálogo para agregar equipos sueltos: solo nombre y tipo, nunca
     * cantidades ni estado de existencias.
     *
     * @return Collection<int, ItemInventario>
     */
    private function catalogoParaAgregar(): Collection
    {
        return ItemInventario::query()
            ->where('activo', true)
            ->whereNotIn('id', array_keys($this->items))
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);
    }
}
