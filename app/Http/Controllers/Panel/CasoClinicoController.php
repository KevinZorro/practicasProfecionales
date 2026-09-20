<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CasoClinico;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas de escenarios clínicos.
 *
 * De momento solo la capacidad máxima de estudiantes (RF74), que es el único
 * campo del caso clínico con gestión propia.
 */
final class CasoClinicoController extends Controller
{
    public function __invoke(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', CasoClinico::class), 403);

        return view('panel.casos-clinicos.capacidad');
    }
}
