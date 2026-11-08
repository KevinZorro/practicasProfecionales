<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\RetencionDeDatosService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Depuración de lo que tiene más de dos semestres (retención de datos,
 * decisión del 2026-11-08). Solo el ADMIN.
 */
final class DepuracionController extends Controller
{
    public function index(Request $peticion): View
    {
        abort_unless($peticion->user()->can('depurarDatos'), 403);

        return view('panel.depuracion.index');
    }

    /** Los formatos firmados que se van a depurar, en un ZIP, para guardarlos antes. */
    public function formatos(Request $peticion, RetencionDeDatosService $retencion): BinaryFileResponse
    {
        abort_unless($peticion->user()->can('depurarDatos'), 403);

        return response()
            ->download($retencion->zipDeLosFormatosQueSeVan($peticion->user()), 'formatos-de-mas-de-dos-semestres-'.now()->format('Y-m-d').'.zip')
            ->deleteFileAfterSend();
    }
}
