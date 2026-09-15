<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Preparacion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tablero de montaje del día (RF36-RF37).
 */
final class PreparacionController extends Controller
{
    public function __invoke(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', Preparacion::class), 403);

        return view('panel.preparaciones.index');
    }
}
