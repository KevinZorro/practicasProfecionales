<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\PeriodoAcademico;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Abrir y cerrar el periodo académico (RF75).
 */
final class PeriodoAcademicoController extends Controller
{
    public function __invoke(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', PeriodoAcademico::class), 403);

        return view('panel.periodo-academico.index');
    }
}
