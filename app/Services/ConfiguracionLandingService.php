<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClaveConfiguracionLanding;
use App\Models\ConfiguracionLanding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lee y guarda la configuración de la landing (RF02, RF11).
 *
 * El video del hero no pasa por ImagenPublicaService: no se recodifica en
 * el servidor (haría falta ffmpeg, una dependencia pesada para un solo
 * archivo). Llega ya comprimido y con tope de tamaño, y el formulario lo
 * valida al subir.
 */
final class ConfiguracionLandingService
{
    public const TIPOS_DE_VIDEO = ['video/mp4', 'video/webm'];

    /**
     * Tope del video del hero, en KB. Un video de fondo de 20 a 30 segundos
     * a 720p cabe holgado. php.ini, nginx y las subidas temporales de
     * Livewire tienen que admitirlo: ConfiguracionLandingTest lo comprueba.
     */
    public const TAMANO_MAXIMO_VIDEO_KB = 15 * 1024;

    /** @return array<string, ?string> clave => valor; las que faltan, en null */
    public function valores(): array
    {
        $guardados = ConfiguracionLanding::query()->pluck('valor', 'clave');

        $valores = [];
        foreach (ClaveConfiguracionLanding::cases() as $clave) {
            $valores[$clave->value] = $guardados[$clave->value] ?? null;
        }

        return $valores;
    }

    /**
     * Guarda las claves conocidas, todas o ninguna. Si el video del hero
     * cambió, el anterior se borra cuando la transacción se confirma.
     *
     * @param  array<string, mixed>  $valores
     */
    public function guardar(array $valores): void
    {
        $anteriores = $this->valores();

        DB::transaction(function () use ($valores, $anteriores): void {
            foreach (ClaveConfiguracionLanding::cases() as $clave) {
                if (! array_key_exists($clave->value, $valores)) {
                    continue;
                }

                $valor = $valores[$clave->value];

                ConfiguracionLanding::query()->updateOrCreate(
                    ['clave' => $clave->value],
                    ['valor' => $valor === null || $valor === '' ? null : (string) $valor],
                );
            }

            $clave = ClaveConfiguracionLanding::HeroVideo->value;
            $videoAnterior = $anteriores[$clave];

            // Que no venga la clave es "no se tocó"; que venga vacía es "se quitó".
            if (! array_key_exists($clave, $valores)) {
                return;
            }

            if ($videoAnterior !== null && $videoAnterior !== $valores[$clave]) {
                DB::afterCommit(static fn () => Storage::disk(ImagenPublicaService::DISCO)->delete($videoAnterior));
            }
        });
    }
}
