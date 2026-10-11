<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Services\SolicitudService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas del módulo de solicitudes (RF27-RF35).
 *
 * Cada método comprueba el permiso con la Policy y devuelve la vista; toda
 * la interacción vive en los componentes Livewire que esas vistas montan.
 *
 * Una solicitud se abre como expediente: una cabecera con su línea de
 * estados y cuatro pestañas (detalle, participantes, novedades y formato
 * intramural). Cada pestaña es su propia ruta, con su propio permiso.
 */
final class SolicitudController extends Controller
{
    public function __construct(private readonly SolicitudService $solicitudes) {}

    /** Historial del docente (RF35). */
    public function mias(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', Solicitud::class), 403);

        return view('panel.solicitudes.mias');
    }

    /** Formulario de nueva solicitud (RF27-RF29). */
    public function nueva(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', Solicitud::class), 403);

        return view('panel.solicitudes.nueva');
    }

    /** Bandeja de revisión y resolución (RF31-RF33). */
    public function bandeja(Request $peticion): View
    {
        abort_unless($peticion->user()->can('verBandeja', Solicitud::class), 403);

        return view('panel.solicitudes.bandeja');
    }

    /** El expediente de una solicitud: su detalle y la decisión que toca (RF30, RF31). */
    public function ver(Request $peticion, Solicitud $solicitud): View
    {
        abort_unless($peticion->user()->can('view', $solicitud), 403);

        return view('panel.solicitudes.expediente', ['solicitud' => $this->solicitudes->paraElExpediente($solicitud)]);
    }

    /** Reprogramar la sesión y sustituir al docente (RF61, RF73). */
    public function novedades(Request $peticion, Solicitud $solicitud): View
    {
        abort_unless($peticion->user()->can('reprogramar', $solicitud), 403);

        return view('panel.solicitudes.novedades', ['solicitud' => $this->solicitudes->paraElExpediente($solicitud)]);
    }

    /** Sesiones apartadas antes del semestre (RF57). */
    public function apartadas(Request $peticion): View
    {
        abort_unless($peticion->user()->can('registrarApartada', Solicitud::class), 403);

        return view('panel.solicitudes.apartadas');
    }

    /** Formato intramural de una sesión (RF59). */
    public function formatoIntramural(Request $peticion, Solicitud $solicitud): View
    {
        abort_unless($peticion->user()->can('registrarFormatoIntramural', $solicitud), 403);

        return view('panel.solicitudes.formato-intramural', ['solicitud' => $this->solicitudes->paraElExpediente($solicitud)]);
    }

    /**
     * Los participantes de una sesión: quién no puede asistir y por qué
     * (RF70), y retirar o agregar estudiantes (RF69, RF28).
     */
    public function participantes(Request $peticion, Solicitud $solicitud): View
    {
        abort_unless($peticion->user()->can('view', $solicitud), 403);

        return view('panel.solicitudes.participantes', ['solicitud' => $this->solicitudes->paraElExpediente($solicitud)]);
    }
}
