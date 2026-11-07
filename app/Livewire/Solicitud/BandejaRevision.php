<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\EstadoSolicitud;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\Solicitud;
use App\Services\InventarioService;
use App\Services\SolicitudService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de solicitudes (RF31-RF33): la lista que lleva al expediente de
 * cada una. Revisar, aprobar y rechazar se hace dentro del expediente, con
 * la solicitud completa a la vista (ExpedienteDeSolicitud).
 */
final class BandejaRevision extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    /** Valor del filtro que enseña aparte las vencidas: no es un estado. */
    public const VENCIDAS = 'vencidas';

    #[Url(as: 'estado', keep: false)]
    public string $estado = '';

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('verBandeja', Solicitud::class);
    }

    public function render(SolicitudService $solicitudes): mixed
    {
        $viendoVencidas = $this->estado === self::VENCIDAS;

        return view('livewire.solicitud.bandeja-revision', [
            'solicitudes' => $viendoVencidas ? $solicitudes->vencidas() : $solicitudes->bandeja(EstadoSolicitud::tryFrom($this->estado)),
            'estados' => EstadoSolicitud::cases(),
            'viendoVencidas' => $viendoVencidas,
            'cuantasVencidas' => $solicitudes->cuantasVencidas(),
            // RF66: prácticas ya aprobadas cuyo inventario dejó de dar.
            // Solo se avisa; qué hacer con ellas lo decide el cliente.
            'sinCobertura' => app(InventarioService::class)->solicitudesAprobadasSinCobertura(),
        ]);
    }
}
