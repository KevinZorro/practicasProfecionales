<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\EstadoSolicitud;
use App\Models\ItemInventario;
use App\Models\Solicitud;
use App\Services\InventarioService;
use App\Services\SolicitudService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de revisión y resolución de solicitudes (RF31-RF33).
 *
 * Qué controles aparecen lo decide la Policy, no este componente: aquí no se
 * comprueba ningún rol. Las transiciones las hace el Service.
 */
final class BandejaRevision extends Component
{
    use WithPagination;

    #[Url(as: 'estado', keep: false)]
    public string $estado = '';

    public ?int $abierta = null;

    public ?int $rechazando = null;

    public string $motivoRechazo = '';

    public function updatedEstado(): void
    {
        $this->resetPage();
        $this->cerrar();
    }

    public function abrir(int $solicitudId): void
    {
        $this->abierta = $this->abierta === $solicitudId ? null : $solicitudId;
        $this->rechazando = null;
    }

    public function cerrar(): void
    {
        $this->abierta = null;
        $this->rechazando = null;
        $this->motivoRechazo = '';
    }

    public function revisar(int $solicitudId, SolicitudService $solicitudes): void
    {
        $solicitud = Solicitud::findOrFail($solicitudId);
        $this->authorize('revisar', $solicitud);

        $solicitudes->marcarRevisada($solicitud, Auth::user());
        session()->flash('estado', 'Solicitud marcada como revisada.');
    }

    public function aprobar(int $solicitudId, SolicitudService $solicitudes): void
    {
        $solicitud = Solicitud::findOrFail($solicitudId);
        $this->authorize('aprobar', $solicitud);

        $solicitudes->aprobar($solicitud, Auth::user());
        session()->flash('estado', 'Solicitud aprobada. El docente recibirá la notificación.');
        $this->cerrar();
    }

    public function pedirMotivo(int $solicitudId): void
    {
        $this->rechazando = $solicitudId;
        $this->motivoRechazo = '';
    }

    public function rechazar(int $solicitudId, SolicitudService $solicitudes): void
    {
        $solicitud = Solicitud::findOrFail($solicitudId);
        $this->authorize('rechazar', $solicitud);
        $this->validate(['motivoRechazo' => 'nullable|string|max:1000']);

        $solicitudes->rechazar($solicitud, Auth::user(), $this->motivoRechazo ?: null);
        session()->flash('estado', 'Solicitud rechazada. El docente recibirá el motivo.');
        $this->cerrar();
    }

    public function render(SolicitudService $solicitudes): mixed
    {
        $detalle = $this->abierta === null ? null : $solicitudes->paraDetalle(Solicitud::findOrFail($this->abierta));

        return view('livewire.solicitud.bandeja-revision', [
            'solicitudes' => $solicitudes->bandeja(EstadoSolicitud::tryFrom($this->estado)),
            'estados' => EstadoSolicitud::cases(),
            'detalle' => $detalle,
            'faltantes' => $detalle === null ? [] : $this->faltantesDeInventario($detalle),
        ]);
    }

    /**
     * Equipos de los que se pidieron más unidades de las que quedan libres
     * en esa franja.
     *
     * Se calcula solo para la solicitud abierta, no para toda la lista: cada
     * solicitud tiene su propia franja horaria, así que hacerlo para todas
     * sería una consulta por fila. Es información para el administrativo, no
     * un bloqueo: aprobar sigue siendo decisión del coordinador.
     *
     * @return array<int, int> id del ítem => unidades libres
     */
    private function faltantesDeInventario(Solicitud $solicitud): array
    {
        $disponibles = app(InventarioService::class)->disponibilidadDeVarios(
            $solicitud->items,
            $solicitud->fecha->format('Y-m-d'),
            $solicitud->hora_inicio,
            $solicitud->hora_fin,
        );

        return $solicitud->items
            ->filter(static fn (ItemInventario $item): bool => ($disponibles[$item->id] ?? 0) < $item->pivot->cantidad)
            ->mapWithKeys(static fn (ItemInventario $item): array => [$item->id => $disponibles[$item->id] ?? 0])
            ->all();
    }
}
