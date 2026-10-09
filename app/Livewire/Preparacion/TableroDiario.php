<?php

declare(strict_types=1);

namespace App\Livewire\Preparacion;

use App\Enums\EstadoPreparacion;
use App\Exceptions\SalaOcupada;
use App\Exceptions\TransicionDePreparacionInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Sala;
use App\Services\ParticipacionService;
use App\Services\PreparacionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tablero de montaje del día (RF36-RF37).
 *
 * Se usa de pie, con prisa y desde el celular, así que cada acción es un
 * toque y se aplica al momento: no hay formulario que enviar ni página que
 * recargar.
 *
 * Aquí no se decide nada. Las transiciones, la validación de solapamiento y
 * las marcas de montaje las hace PreparacionService; quién puede tocar qué,
 * la PreparacionPolicy.
 */
final class TableroDiario extends Component
{
    use AutorizaEnCadaPeticion;

    #[Url(as: 'fecha', keep: false)]
    public string $fecha = '';

    /** Preparación cuyo panel de alistamiento está abierto. */
    public ?int $abierta = null;

    /** @var array<int, string> id de preparación => mensaje de conflicto */
    public array $conflictos = [];

    public ?int $salaElegida = null;

    public string $observaciones = '';

    public function mount(): void
    {
        if ($this->fecha === '') {
            $this->fecha = now()->toDateString();
        }
    }

    public function updatedFecha(): void
    {
        $this->cerrar();
    }

    public function irA(string $fecha): void
    {
        $this->fecha = $fecha;
        $this->cerrar();
    }

    public function abrir(int $preparacionId): void
    {
        if ($this->abierta === $preparacionId) {
            $this->cerrar();

            return;
        }

        $preparacion = $this->preparacion($preparacionId);
        $this->authorize('view', $preparacion);

        $this->abierta = $preparacionId;
        $this->salaElegida = $preparacion->sala_id;
        $this->observaciones = (string) $preparacion->observaciones;
    }

    public function cerrar(): void
    {
        $this->abierta = null;
        $this->salaElegida = null;
        $this->observaciones = '';
        $this->conflictos = [];
    }

    public function asignarSala(int $preparacionId, PreparacionService $preparaciones): void
    {
        $preparacion = $this->preparacion($preparacionId);
        $this->authorize('asignarSala', $preparacion);
        $this->validate(['salaElegida' => 'required|integer|exists:salas,id']);

        unset($this->conflictos[$preparacionId]);

        try {
            $preparaciones->asignarSala($preparacion, Sala::findOrFail($this->salaElegida));
            session()->flash('estado', 'Sala asignada.');
        } catch (SalaOcupada $choque) {
            $this->conflictos[$preparacionId] = $this->describir($choque);
        }
    }

    public function alternarItem(int $preparacionId, int $itemId, PreparacionService $preparaciones): void
    {
        $preparacion = $this->preparacion($preparacionId);
        $this->authorize('preparar', $preparacion);

        $item = $preparacion->items->firstWhere('id', $itemId);

        if (! $item instanceof ItemInventario) {
            return;
        }

        $item->pivot->alistado
            ? $preparaciones->desmarcarItemAlistado($preparacion, $item)
            : $preparaciones->marcarItemAlistado($preparacion, $item);
    }

    public function cambiarEstado(int $preparacionId, string $destino, PreparacionService $preparaciones): void
    {
        $preparacion = $this->preparacion($preparacionId);
        $this->authorize('preparar', $preparacion);

        try {
            $preparaciones->cambiarEstado($preparacion, EstadoPreparacion::from($destino), Auth::user());
        } catch (TransicionDePreparacionInvalida $invalida) {
            $this->conflictos[$preparacionId] = $invalida->getMessage();
        }
    }

    public function guardarObservaciones(int $preparacionId, PreparacionService $preparaciones): void
    {
        $preparacion = $this->preparacion($preparacionId);
        $this->authorize('preparar', $preparacion);
        $this->validate(['observaciones' => 'nullable|string|max:1000']);

        $preparaciones->registrarObservaciones($preparacion, $this->observaciones ?: null);
        session()->flash('estado', 'Observaciones guardadas.');
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', Preparacion::class);
    }

    public function render(PreparacionService $preparaciones, ParticipacionService $participacion): mixed
    {
        $preparacion = $this->abierta === null ? null : $this->preparacion($this->abierta);
        $montajes = $preparaciones->tableroDelDia($this->fecha);

        return view('livewire.preparacion.tablero-diario', [
            'montajes' => $montajes,
            'noPuedenIngresar' => $this->noPuedenIngresar($montajes, $participacion),
            'detalle' => $preparacion,
            'salasLibres' => $preparacion === null ? collect() : $preparaciones->salasLibresPara($preparacion),
        ]);
    }

    /**
     * Por montaje: si el docente puede entrar y cuántos estudiantes no
     * pueden. Una sola pasada por ParticipacionService para todo el día.
     *
     * @param  Collection<int, Preparacion>  $montajes
     * @return array<int, array{docente: bool, estudiantes: int}>
     */
    private function noPuedenIngresar(Collection $montajes, ParticipacionService $participacion): array
    {
        $ids = $montajes->flatMap(static fn (Preparacion $m) => [
            $m->solicitud->idDelDocenteQueDicta(),
            ...$m->solicitud->estudiantesPresentes->pluck('id')->all(),
        ])->unique()->values()->all();

        $impedimentos = $participacion->impedimentos($ids);

        return $montajes->mapWithKeys(static fn (Preparacion $m): array => [$m->id => [
            'docente' => $impedimentos[$m->solicitud->idDelDocenteQueDicta()] !== [],
            'estudiantes' => $m->solicitud->estudiantesPresentes
                ->filter(static fn ($e): bool => $impedimentos[$e->id] !== [])
                ->count(),
        ]])->all();
    }

    /** Preparación del día en curso, con todo lo que la pantalla enseña. */
    private function preparacion(int $preparacionId): Preparacion
    {
        return Preparacion::with(['solicitud.docente', 'solicitud.materia', 'solicitud.casoClinico', 'sala', 'items'])
            ->findOrFail($preparacionId);
    }

    /**
     * El choque, con el caso clínico y el docente de la otra práctica: el
     * identificador de la solicitud no le dice nada a quien está montando.
     */
    private function describir(SalaOcupada $choque): string
    {
        $otra = $choque->conflicto?->solicitud;

        if ($otra === null) {
            return $choque->getMessage();
        }

        return sprintf(
            'Esa sala ya está ocupada de %s a %s por «%s», de %s.',
            substr($otra->hora_inicio, 0, 5),
            substr($otra->hora_fin, 0, 5),
            $otra->casoClinico->nombre,
            $otra->docente->nombre,
        );
    }
}
