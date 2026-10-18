<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\TipoSesion;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Livewire\Concerns\AvisaDelResultado;
use App\Models\CasoClinico;
use App\Models\ItemInventario;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\BloqueoService;
use App\Services\DatosNuevaSolicitud;
use App\Services\SolicitudService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Formulario con el que el docente pide un escenario (RF27-RF29), con el
 * grupo y los estudiantes que van a la sesión (RF28).
 *
 * No decide nada: valida formato, arma el objeto de datos y llama al
 * Service. Tampoco enseña disponibilidad de inventario, que el RF40 reserva
 * a administrativos, coordinadores y ADMIN.
 */
final class FormularioSolicitud extends Component
{
    use AutorizaEnCadaPeticion;
    use AvisaDelResultado;

    public string $tipo = TipoSesion::Practica->value;

    public ?int $materiaId = null;

    public ?int $casoClinicoId = null;

    #[Validate('required|date|after_or_equal:today')]
    public string $fecha = '';

    #[Validate('required|date_format:H:i')]
    public string $horaInicio = '';

    #[Validate('required|date_format:H:i|after:horaInicio')]
    public string $horaFin = '';

    #[Validate('required|string|regex:/^[A-Za-z]{1,2}$/')]
    public string $grupo = '';

    /** @var list<int> los estudiantes que van a la sesión */
    #[Validate(['estudianteIds' => 'required|array|min:1', 'estudianteIds.*' => 'integer'])]
    public array $estudianteIds = [];

    public string $busquedaEstudiante = '';

    /** Códigos institucionales pegados de la lista del grupo. */
    public string $codigosPegados = '';

    /** @var list<string> códigos pegados que no son de ningún estudiante activo */
    public array $codigosDesconocidos = [];

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
    /** Otra materia, otros escenarios: el elegido deja de valer si no es de ella. */
    public function updatedMateriaId(): void
    {
        if ($this->casoClinicoId !== null && ! app(SolicitudService::class)->casoPerteneceALaMateria($this->casoClinicoId, (int) $this->materiaId)) {
            $this->casoClinicoId = null;
            $this->updatedCasoClinicoId(null);
        }
    }

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

    public function agregarEstudiante(int $estudianteId): void
    {
        if (! in_array($estudianteId, $this->estudianteIds, true)) {
            $this->estudianteIds[] = $estudianteId;
        }

        $this->busquedaEstudiante = '';
    }

    public function quitarEstudiante(int $estudianteId): void
    {
        $this->estudianteIds = array_values(array_diff($this->estudianteIds, [$estudianteId]));
    }

    /** Agrega de una vez los códigos pegados, separados por comas, espacios o saltos de línea. */
    public function agregarPorCodigo(SolicitudService $solicitudes): void
    {
        $codigos = preg_split('/[\s,;]+/', $this->codigosPegados) ?: [];
        $resultado = $solicitudes->estudiantesPorCodigo($codigos);

        foreach ($resultado['encontrados'] as $estudiante) {
            $this->agregarEstudiante($estudiante->id);
        }

        $this->codigosDesconocidos = $resultado['desconocidos'];
        $this->codigosPegados = '';
    }

    /**
     * Materia, escenario y tipo se validan contra la base y los enums, no solo
     * por formato: la petición puede traer lo que la pantalla no ofrece
     * (materias o escenarios inactivos, un tipo inventado) (QA-15).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoSesion::class)],
            'materiaId' => ['required', 'integer', Rule::exists('materias', 'id')->where('activo', true)],
            'casoClinicoId' => ['required', 'integer', Rule::exists('casos_clinicos', 'id')->where('activo', true)],
            'items.*' => ['integer', 'min:1', 'max:'.SolicitudService::CANTIDAD_MAXIMA_POR_EQUIPO],
        ];
    }

    public function guardar(SolicitudService $solicitudes): void
    {
        $this->authorize('create', Solicitud::class);
        $datos = $this->validate();

        try {
            $solicitudes->crear(Auth::user(), $this->comoDatos($datos));
        } catch (CapacidadDeEstudiantesExcedida|SolicitudInvalida $invalida) {
            // La regla vive en el Service; aquí solo se traduce a un error
            // junto a la lista de estudiantes, no como un fallo del servidor.
            $this->addError('estudianteIds', $invalida->getMessage());

            return;
        }

        $this->avisar('Tu solicitud quedó registrada. El laboratorio la revisará antes de aprobarla.');
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
            grupo: (string) $datos['grupo'],
            estudianteIds: array_map('intval', $this->estudianteIds),
            observaciones: $datos['observaciones'] ?? null,
            // Una cantidad nunca baja de uno: si el docente la deja en cero,
            // lo que quiere es quitar el equipo, y para eso está "Quitar".
            items: array_map(static fn (mixed $c): int => max(1, (int) $c), $this->items),
        );
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('create', Solicitud::class);
    }

    public function render(SolicitudService $solicitudes, BloqueoService $bloqueos): mixed
    {
        return view('livewire.solicitud.formulario-solicitud', [
            'bloqueo' => $bloqueos->vigenteDe(Auth::user()),
            'seleccionados' => User::query()->whereIn('id', $this->estudianteIds)->orderBy('nombre')->get(['id', 'nombre', 'codigo_institucional']),
            'resultados' => $solicitudes->buscarEstudiantes($this->busquedaEstudiante, $this->estudianteIds),
            'materias' => Materia::activas()->orderBy('nombre')->get(['id', 'nombre', 'semestre']),
            'casosClinicos' => CasoClinico::activos()->deLaMateria($this->materiaId)->orderBy('nombre')->get(['id', 'nombre']),
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
