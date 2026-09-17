<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas del consentimiento informado (RF51-RF53).
 *
 * Quién entra a cada una lo deciden ConsentimientoEstudiantePolicy y
 * ConsentimientoPlantillaPolicy. El administrativo queda fuera de la
 * verificación: es la única función operativa donde no acompaña al
 * coordinador.
 */
final class ConsentimientoController extends Controller
{
    /** RF52: el estudiante ve el suyo. */
    public function mio(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', ConsentimientoEstudiante::class), 403);

        return view('panel.consentimientos.mio');
    }

    /** RF52: bandeja de verificación. Coordinador y ADMIN. */
    public function bandeja(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', ConsentimientoEstudiante::class), 403);

        return view('panel.consentimientos.bandeja');
    }

    /** RF52: quién tiene el consentimiento al día antes de una práctica. */
    public function estado(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', ConsentimientoEstudiante::class), 403);

        return view('panel.consentimientos.estado');
    }

    /** RF51: la plantilla en blanco. Solo el ADMIN. */
    public function plantillas(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', ConsentimientoPlantilla::class), 403);

        return view('panel.consentimientos.plantillas');
    }
}
