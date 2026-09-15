<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\EstadoSolicitud;
use App\Services\SolicitudService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Historial de solicitudes del docente (RF35).
 *
 * La consulta vive en el Service; aquí solo se elige el filtro.
 */
final class MisSolicitudes extends Component
{
    use WithPagination;

    #[Url(as: 'estado', keep: false)]
    public string $estado = '';

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function render(SolicitudService $solicitudes): mixed
    {
        return view('livewire.solicitud.mis-solicitudes', [
            'solicitudes' => $solicitudes->historialDelDocente(Auth::user(), EstadoSolicitud::tryFrom($this->estado)),
            'estados' => EstadoSolicitud::cases(),
        ]);
    }
}
