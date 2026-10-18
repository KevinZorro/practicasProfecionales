<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Services\ImagenPublicaService;
use App\Services\VideoPublicoService;

/**
 * Para las páginas de edición de un recurso con imágenes o videos: al
 * reemplazar o quitar uno, borra el archivo anterior. Sin esto, cada cambio de
 * foto dejaría el archivo viejo en el servidor para siempre.
 *
 * El borrado espera a que se confirme la transacción del guardado: si algo
 * falla y se deshace, el registro sigue apuntando a su imagen y la imagen
 * sigue existiendo.
 *
 * Qué campos son imágenes y cuáles videos lo dice el recurso, en
 * camposDeImagen() y camposDeVideo().
 */
trait BorraLasImagenesReemplazadas
{
    /** @var array<string, ?string> campo => ruta antes de guardar */
    private array $imagenesAntesDeGuardar = [];

    /** @var array<string, ?string> campo => ruta antes de guardar */
    private array $videosAntesDeGuardar = [];

    protected function beforeSave(): void
    {
        foreach (static::getResource()::camposDeImagen() as $campo) {
            $this->imagenesAntesDeGuardar[$campo] = $this->getRecord()->getOriginal($campo);
        }

        foreach (static::getResource()::camposDeVideo() as $campo) {
            $this->videosAntesDeGuardar[$campo] = $this->getRecord()->getOriginal($campo);
        }
    }

    protected function afterSave(): void
    {
        $imagenes = app(ImagenPublicaService::class);

        foreach ($this->imagenesAntesDeGuardar as $campo => $anterior) {
            if ($anterior !== $this->getRecord()->getAttribute($campo)) {
                $imagenes->borrarAlConfirmar($anterior);
            }
        }

        $videos = app(VideoPublicoService::class);

        foreach ($this->videosAntesDeGuardar as $campo => $anterior) {
            if ($anterior !== $this->getRecord()->getAttribute($campo)) {
                $videos->borrarAlConfirmar($anterior);
            }
        }
    }
}
