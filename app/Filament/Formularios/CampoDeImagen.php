<?php

declare(strict_types=1);

namespace App\Filament\Formularios;

use App\Exceptions\ImagenInvalida;
use App\Services\ImagenPublicaService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * El campo de imagen de todas las pantallas del contenido público.
 *
 * Valida tipo y tamaño al subir, y no guarda el archivo tal como llega:
 * lo pasa por ImagenPublicaService, que lo endereza, lo reduce y lo guarda
 * en WebP (RNF10). Una sola definición para que ninguna pantalla se salte
 * ese paso.
 */
final class CampoDeImagen
{
    public static function make(string $campo, string $carpeta): FileUpload
    {
        return FileUpload::make($campo)
            ->image()
            ->disk(ImagenPublicaService::DISCO)
            ->directory($carpeta)
            ->acceptedFileTypes(ImagenPublicaService::TIPOS_ACEPTADOS)
            ->maxSize(ImagenPublicaService::TAMANO_MAXIMO_KB)
            ->helperText(sprintf(
                'JPEG, PNG o WebP de hasta %d MB. Se guarda reducida a %d px de lado mayor para que cargue rápido.',
                intdiv(ImagenPublicaService::TAMANO_MAXIMO_KB, 1024),
                ImagenPublicaService::LADO_MAYOR_MAXIMO,
            ))
            ->saveUploadedFileUsing(static function (TemporaryUploadedFile $file, FileUpload $component) use ($carpeta): string {
                try {
                    return app(ImagenPublicaService::class)->guardar($file, $carpeta);
                } catch (ImagenInvalida $error) {
                    // Que se vea junto al campo, como cualquier otro error del formulario.
                    throw ValidationException::withMessages([$component->getStatePath() => $error->getMessage()]);
                }
            });
    }
}
