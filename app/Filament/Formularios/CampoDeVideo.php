<?php

declare(strict_types=1);

namespace App\Filament\Formularios;

use App\Services\ImagenPublicaService;
use App\Services\VideoPublicoService;
use Filament\Forms\Components\FileUpload;

/**
 * El campo de video de las pantallas del contenido público (RF76), pareja
 * de CampoDeImagen. No recodifica: exige MP4 o WebM ya comprimido y con
 * tope de tamaño. Una sola definición para que ninguna pantalla acepte
 * otro tipo ni otro tope.
 */
final class CampoDeVideo
{
    public static function make(string $campo, string $carpeta): FileUpload
    {
        return FileUpload::make($campo)
            ->label('Video')
            ->disk(ImagenPublicaService::DISCO)
            ->directory($carpeta)
            ->acceptedFileTypes(VideoPublicoService::TIPOS_ACEPTADOS)
            ->maxSize(VideoPublicoService::TAMANO_MAXIMO_KB)
            ->helperText(sprintf(
                'Opcional. MP4 o WebM ya comprimido, de hasta %d MB (unos 90 segundos a 720p). Si hay video y foto, la foto es la portada del video.',
                intdiv(VideoPublicoService::TAMANO_MAXIMO_KB, 1024),
            ));
    }
}
