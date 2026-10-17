<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Models\Solicitud;
use App\Support\EnlaceDeVuelta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas del formato de confidencialidad (RF51-RF53).
 *
 * Quién entra a cada una lo deciden FormatoConfidencialidadPolicy y
 * PlantillaConfidencialidadPolicy: aquí no se comprueba ningún rol.
 */
final class ConfidencialidadController extends Controller
{
    /** RF52: quien firma ve el suyo. Estudiantes y docentes. */
    public function mio(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', FormatoConfidencialidad::class), 403);

        return view('panel.confidencialidad.mio');
    }

    /** RF52: bandeja de verificación. Administrativo, coordinación y ADMIN. */
    public function bandeja(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', FormatoConfidencialidad::class), 403);

        return view('panel.confidencialidad.bandeja');
    }

    /** RF52: quién tiene el formato al día antes de una práctica. */
    public function estado(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', FormatoConfidencialidad::class), 403);

        return view('panel.confidencialidad.estado', ['volver' => $this->vueltaDelEstado($peticion)]);
    }

    /**
     * Abierto desde los participantes de una sesión, se vuelve a esa sesión:
     * es donde estaba quien pulsó «Verificar formatos de esta sesión».
     */
    private function vueltaDelEstado(Request $peticion): EnlaceDeVuelta
    {
        $sesion = Solicitud::find($peticion->integer('sesion'));

        if ($sesion !== null && $peticion->user()->can('view', $sesion)) {
            return new EnlaceDeVuelta(route('panel.solicitudes.participantes', $sesion), 'Participantes de la sesión');
        }

        return new EnlaceDeVuelta(route('panel.formatos-confidencialidad'), 'Verificación de formatos');
    }

    /** RF51: la plantilla en blanco. Solo el ADMIN. */
    public function plantillas(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', PlantillaConfidencialidad::class), 403);

        return view('panel.confidencialidad.plantillas');
    }
}
