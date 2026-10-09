<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\RegistroDeBitacora;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bitácora de auditoría (RF62).
 */
final class BitacoraController extends Controller
{
    public function __invoke(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', RegistroDeBitacora::class), 403);

        return view('panel.bitacora.index');
    }
}
