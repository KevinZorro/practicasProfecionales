<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Un archivo que llegó como imagen y no se puede publicar tal cual.
 */
final class ImagenInvalida extends DomainException
{
    public static function noSeReconoce(): self
    {
        return new self('El archivo no es una imagen JPEG, PNG o WebP que se pueda abrir.');
    }

    public static function demasiadosPixeles(int $ancho, int $alto, int $maximo): self
    {
        return new self(sprintf(
            'La imagen mide %d × %d píxeles: pasa del máximo de %d megapíxeles. Redúcela antes de subirla.',
            $ancho,
            $alto,
            intdiv($maximo, 1_000_000),
        ));
    }
}
