<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Bloqueo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bloqueos de acceso al laboratorio (RF68).
 */
final class BloqueoController extends Controller
{
    public function __invoke(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', Bloqueo::class), 403);

        return view('panel.bloqueos.index');
    }
}
