<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PortadaService;
use Illuminate\View\View;

/**
 * La portada pública (RF01–RF07) y el detalle ampliado de cada escenario
 * clínico (RF03). Sin sesión: la ve cualquiera.
 */
final class PortadaController extends Controller
{
    public function __construct(private readonly PortadaService $portada) {}

    public function inicio(): View
    {
        return view('portada.inicio', ['contenido' => $this->portada->contenido()]);
    }

    public function escenario(int $escenario): View
    {
        $publicado = $this->portada->escenarioPublicado($escenario);

        return view('portada.escenario', [
            'escenario' => $publicado,
            'otros' => $this->portada->otrosEscenarios($publicado),
        ]);
    }
}
