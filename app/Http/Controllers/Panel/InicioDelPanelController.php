<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\MenuDelPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /panel no tiene pantalla propia: lleva a la primera sección que el rol
 * activo puede ver (hoy, el calendario, que ven todos). Así la entrada con
 * Google y los enlaces de la portada siguen apuntando a un solo sitio.
 */
final class InicioDelPanelController extends Controller
{
    public function __construct(private readonly MenuDelPanel $menu) {}

    public function __invoke(Request $peticion): RedirectResponse
    {
        $primera = $this->menu->visiblesPara($peticion->user())[0] ?? null;

        abort_if($primera === null, 403);

        return redirect()->route($primera->ruta);
    }
}
