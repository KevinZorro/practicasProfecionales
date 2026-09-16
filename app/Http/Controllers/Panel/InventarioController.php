<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ItemInventario;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas del inventario (RF38-RF40).
 *
 * Docentes y estudiantes no entran a ninguna: lo decide
 * ItemInventarioPolicy y aquí solo se consulta.
 */
final class InventarioController extends Controller
{
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', ItemInventario::class), 403);

        return view('panel.inventario.index');
    }

    public function nuevo(Request $peticion): View
    {
        abort_unless($peticion->user()->can('create', ItemInventario::class), 403);

        return view('panel.inventario.formulario', ['item' => null]);
    }

    public function editar(Request $peticion, ItemInventario $item): View
    {
        abort_unless($peticion->user()->can('update', $item), 403);

        return view('panel.inventario.formulario', ['item' => $item]);
    }

    /** RF40: la disponibilidad no la ve un docente por ninguna vía. */
    public function disponibilidad(Request $peticion): View
    {
        abort_unless($peticion->user()->can('viewAny', ItemInventario::class), 403);

        return view('panel.inventario.disponibilidad');
    }
}
