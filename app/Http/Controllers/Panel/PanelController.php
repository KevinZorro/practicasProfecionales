<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\MenuDelPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Armazón de las pantallas internas.
 *
 * Cada sección de la navegación necesita una ruta a la que apuntar antes de
 * que exista su pantalla. Este controlador sirve ese hueco: comprueba el
 * permiso de la sección y muestra un marcador de posición. Conforme cada
 * módulo reciba su pantalla, se le quita aquí la entrada correspondiente.
 */
final class PanelController extends Controller
{
    public function __construct(private readonly MenuDelPanel $menu) {}

    public function __invoke(Request $peticion, string $seccion): View
    {
        $actual = collect($this->menu->todas())->firstWhere('clave', $seccion);

        abort_if($actual === null, 404);
        abort_unless($actual->visiblePara($peticion->user()), 403);

        return view('panel.pendiente', ['seccion' => $actual]);
    }
}
