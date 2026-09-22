<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
use App\Services\ConfidencialidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Única puerta a los archivos del formato de confidencialidad (RNF07).
 *
 * Los documentos viven en el disco privado, así que no hay URL que los
 * sirva: se leen desde aquí, y solo después de que la Policy haya dicho que
 * sí. Quien adivine el identificador de otro recibe un 403, no el archivo.
 */
final class DescargaConfidencialidadController extends Controller
{
    /** La plantilla en blanco: no lleva datos de nadie, pero tampoco se enlaza directo. */
    public function plantilla(Request $peticion, ConfidencialidadService $confidencialidad): StreamedResponse
    {
        $plantilla = $confidencialidad->plantillaVigente();
        abort_unless($peticion->user()->can('descargar', $plantilla), 403);

        return $this->entregar($plantilla->archivo_path, 'formato-de-confidencialidad.pdf');
    }

    /** El documento firmado: lleva datos personales de quien lo firmó. */
    public function firmado(Request $peticion, FormatoConfidencialidad $entrega): StreamedResponse
    {
        abort_unless($peticion->user()->can('descargar', $entrega), 403);
        abort_if($entrega->archivo_firmado_path === null, 404);

        return $this->entregar(
            $entrega->archivo_firmado_path,
            sprintf('formato-confidencialidad-%s-%s.pdf', $entrega->firmante_id, $entrega->periodo_academico),
        );
    }

    /** Una versión concreta de la plantilla, desde el historial del ADMIN. */
    public function versionDePlantilla(Request $peticion, PlantillaConfidencialidad $plantilla): StreamedResponse
    {
        abort_unless($peticion->user()->can('view', $plantilla), 403);

        return $this->entregar($plantilla->archivo_path, "formato-confidencialidad-v{$plantilla->version}.pdf");
    }

    private function entregar(string $ruta, string $nombre): StreamedResponse
    {
        $disco = Storage::disk(config('laboratorio.confidencialidad.disco'));

        abort_unless($disco->exists($ruta), 404);

        return $disco->download($ruta, $nombre);
    }
}
