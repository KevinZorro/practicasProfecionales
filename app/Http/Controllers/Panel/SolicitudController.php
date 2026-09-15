<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas del módulo de solicitudes (RF27-RF35).
 *
 * Cada método comprueba el permiso con la Policy y devuelve la vista; toda
 * la interacción vive en los componentes Livewire que esas vistas montan.
 */
final class SolicitudController extends Controller
{
    /** Historial del docente (RF35). */
    public function mias(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', Solicitud::class), 403);

        return view('panel.solicitudes.mias');
    }

    /** Formulario de nueva solicitud (RF27-RF30). */
    public function nueva(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', Solicitud::class), 403);

        return view('panel.solicitudes.nueva');
    }

    /** Bandeja de revisión y resolución (RF31-RF33). */
    public function bandeja(Request $peticion): View
    {
        abort_unless($peticion->user()->can('revisar', Solicitud::class), 403);

        return view('panel.solicitudes.bandeja');
    }
}
