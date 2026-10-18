<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Videos del contenido público: galería, escenarios, talleres, eventos y
 * equipamiento destacado (RF76).
 *
 * Como el del hero, no se recodifican en el servidor (haría falta ffmpeg):
 * llegan ya comprimidos, MP4 o WebM, y el formulario valida tipo y tamaño al
 * subirlos. Van al disco "public" y los sirve nginx directamente.
 */
final class VideoPublicoService
{
    public const TIPOS_ACEPTADOS = ['video/mp4', 'video/webm'];

    /**
     * Tope por video, en KB. Un video de hasta un minuto y medio a 720p
     * (unos 2,5 Mbps, lo habitual en un MP4 H.264 listo para web) pesa
     * cerca de 28 MB; uno de 30 segundos, 10 MB. 4K no hace falta.
     * php.ini, nginx y las subidas temporales de Livewire tienen que
     * admitirlo: ConfiguracionLandingTest lo comprueba.
     */
    public const TAMANO_MAXIMO_KB = 30 * 1024;

    /** El tipo se deduce de la extensión, que el formulario ya validó al subir. */
    public function tipoDe(string $ruta): string
    {
        return str_ends_with(mb_strtolower($ruta), '.webm') ? 'video/webm' : 'video/mp4';
    }

    /**
     * Borra un video que dejó de usarse, cuando la transacción que lo
     * reemplazó o borró su registro ya se confirmó (igual que las imágenes).
     */
    public function borrarAlConfirmar(?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        DB::afterCommit(static fn () => Storage::disk(ImagenPublicaService::DISCO)->delete($ruta));
    }
}
