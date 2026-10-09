<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Evaluación de habilidades (RF41-RF50).
 */
final class EvaluacionController extends Controller
{
    /** El docente registra las suyas (RF41, RF50); coordinación y ADMIN ven todas. */
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', Evaluacion::class), 403);

        return view('panel.evaluaciones.index');
    }

    /** Checklist, resultados y observaciones de cada estudiante (RF43-RF48). */
    public function registro(Request $peticion, Evaluacion $evaluacion): View
    {
        abort_unless($peticion->user()->can('view', $evaluacion), 403);

        return view('panel.evaluaciones.registro', ['evaluacion' => $evaluacion]);
    }

    /** El estudiante consulta sus resultados (RF49). */
    public function misResultados(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', EvaluacionEstudiante::class), 403);

        return view('panel.evaluaciones.mis-resultados');
    }
}
