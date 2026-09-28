<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Services\ImagenPublicaService;

/**
 * Para las páginas de edición de un recurso con imágenes: al reemplazar o
 * quitar una imagen, borra el archivo anterior. Sin esto, cada cambio de
 * foto dejaría el archivo viejo en el servidor para siempre.
 *
 * El borrado espera a que se confirme la transacción del guardado: si algo
 * falla y se deshace, el registro sigue apuntando a su imagen y la imagen
 * sigue existiendo.
 *
 * Qué campos son imágenes lo dice el recurso, en camposDeImagen().
 */
trait BorraLasImagenesReemplazadas
{
    /** @var array<string, ?string> campo => ruta antes de guardar */
    private array $imagenesAntesDeGuardar = [];

    protected function beforeSave(): void
    {
        foreach (static::getResource()::camposDeImagen() as $campo) {
            $this->imagenesAntesDeGuardar[$campo] = $this->getRecord()->getOriginal($campo);
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
    }
}
