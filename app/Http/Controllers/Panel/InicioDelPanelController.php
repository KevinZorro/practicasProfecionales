<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\ResumenDelDiaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /panel es «Hoy»: lo que espera a quien entra, con el rol que tiene puesto.
 * La entrada con Google y los enlaces de la portada apuntan aquí.
 */
final class InicioDelPanelController extends Controller
{
    public function __construct(private readonly ResumenDelDiaService $resumen) {}

    public function __invoke(Request $peticion): View
    {
        return view('panel.hoy', [
            'hoy' => now(),
            'resumen' => $this->resumen->para($peticion->user(), now()),
        ]);
    }
}
