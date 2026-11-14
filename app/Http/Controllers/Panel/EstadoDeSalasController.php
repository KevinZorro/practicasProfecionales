<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Sala;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Estado del espacio físico de cada sala (RF30).
 */
final class EstadoDeSalasController extends Controller
{
    public function __invoke(Request $peticion): View
    {
        abort_unless($peticion->user()->can('cambiarEstado', Sala::class), 403);

        return view('panel.salas.index');
    }
}
