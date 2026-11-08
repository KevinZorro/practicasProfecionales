<?php

declare(strict_types=1);

namespace App\Livewire\Preparacion;

use App\Enums\EstadoPreparacion;
use App\Exceptions\AsignacionDeSalasInvalida;
use App\Exceptions\SalaInsuficiente;
use App\Exceptions\SalaOcupada;
use App\Exceptions\TransicionDePreparacionInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Livewire\Concerns\AvisaDelResultado;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Services\ParticipacionService;
use App\Services\PreparacionService;
use App\Support\FiltroDeUrl;
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
    use AvisaDelResultado;

    #[Url(as: 'fecha', keep: false)]
    public string $fecha = '';

    /** Preparación cuyo panel de alistamiento está abierto. */
    public ?int $abierta = null;

    /** @var array<int, string> id de preparación => mensaje de conflicto */
    public array $conflictos = [];

    /** @var list<int> de una a tres salas, que se unen abriendo las cortinas (P16) */
    public array $salasElegidas = [];

    public string $observaciones = '';

    public function mount(): void
    {
        $this->fecha = FiltroDeUrl::fecha($this->fecha);

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
        $this->salasElegidas = $preparacion->salas->pluck('id')->all();
        $this->observaciones = (string) $preparacion->observaciones;
    }

    public function cerrar(): void
    {
        $this->abierta = null;
        $this->salasElegidas = [];
        $this->observaciones = '';
        $this->conflictos = [];
    }

    public function asignarSalas(int $preparacionId, PreparacionService $preparaciones): void
    {
        $preparacion = $this->preparacion($preparacionId);
        $this->authorize('asignarSala', $preparacion);
        $this->validate([
            'salasElegidas' => 'required|array|min:1|max:'.PreparacionService::MAXIMO_DE_SALAS,
            'salasElegidas.*' => 'integer|distinct|exists:salas,id',
        ]);

        unset($this->conflictos[$preparacionId]);

        try {
            $preparaciones->asignarSalas($preparacion, array_map('intval', $this->salasElegidas));
            $this->avisar(count($this->salasElegidas) === 1 ? 'Sala asignada.' : 'Salas asignadas: se unen abriendo la cortina.');
        } catch (SalaOcupada $choque) {
            $this->conflictos[$preparacionId] = $this->describir($choque);
        } catch (SalaInsuficiente|AsignacionDeSalasInvalida $invalida) {
            $this->conflictos[$preparacionId] = $invalida->getMessage();
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
        $nuevoEstado = EstadoPreparacion::tryFrom($destino);

        // Un estado que la pantalla no ofrece no cambia nada.
        if (! $nuevoEstado instanceof EstadoPreparacion) {
            return;
        }

        try {
            $preparaciones->cambiarEstado($preparacion, $nuevoEstado, Auth::user());
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
        $this->avisar('Observaciones guardadas.');
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
            // Por montaje, cuántos elementos esperan al ingeniero (RF72).
            'delIngeniero' => $montajes->mapWithKeys(static fn (Preparacion $m): array => [
                $m->id => $m->items->filter(static fn (ItemInventario $item): bool => $preparaciones->requiereIngeniero($item))->pluck('id')->all(),
            ])->all(),
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
        return Preparacion::with(['solicitud.docente', 'solicitud.materia', 'solicitud.casoClinico', 'salas', 'items'])
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
            '%s ya está ocupada de %s a %s por «%s», de %s.',
            $choque->sala->nombre ?? 'Esa sala',
            substr($otra->hora_inicio, 0, 5),
            substr($otra->hora_fin, 0, 5),
            $otra->casoClinico->nombre,
            $otra->docente->nombre,
        );
    }
}
