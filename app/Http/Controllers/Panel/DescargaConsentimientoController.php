<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Services\ConsentimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Única puerta a los archivos del consentimiento (RNF07).
 *
 * Los documentos viven en el disco privado, así que no hay URL que los
 * sirva: se leen desde aquí, y solo después de que la Policy haya dicho que
 * sí. Un estudiante que adivine el identificador de otro recibe un 403, no
 * el archivo.
 */
final class DescargaConsentimientoController extends Controller
{
    /** La plantilla en blanco: no lleva datos de nadie, pero tampoco se enlaza directo. */
    public function plantilla(Request $peticion, ConsentimientoService $consentimientos): StreamedResponse
    {
        $plantilla = $consentimientos->plantillaVigente();
        abort_unless($peticion->user()->can('descargar', $plantilla), 403);

        return $this->entregar($plantilla->archivo_path, 'consentimiento-informado.pdf');
    }

    /** El documento firmado: datos personales del estudiante. */
    public function firmado(Request $peticion, ConsentimientoEstudiante $entrega): StreamedResponse
    {
        abort_unless($peticion->user()->can('descargar', $entrega), 403);
        abort_if($entrega->archivo_firmado_path === null, 404);

        return $this->entregar(
            $entrega->archivo_firmado_path,
            sprintf('consentimiento-%s-%s.pdf', $entrega->estudiante_id, $entrega->periodo_academico),
        );
    }

    /** Una versión concreta de la plantilla, desde el historial del ADMIN. */
    public function versionDePlantilla(Request $peticion, ConsentimientoPlantilla $plantilla): StreamedResponse
    {
        abort_unless($peticion->user()->can('view', $plantilla), 403);

        return $this->entregar($plantilla->archivo_path, "consentimiento-v{$plantilla->version}.pdf");
    }

    private function entregar(string $ruta, string $nombre): StreamedResponse
    {
        $disco = Storage::disk(config('laboratorio.consentimiento.disco'));

        abort_unless($disco->exists($ruta), 404);

        return $disco->download($ruta, $nombre);
    }
}
