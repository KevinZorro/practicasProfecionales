<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoSala;
use DomainException;

final class EstadoDeSalaInvalido extends DomainException
{
    public static function sinNovedad(EstadoSala $estado): self
    {
        return new self(sprintf('Una sala «%s» lleva una nota que diga qué pasa.', mb_strtolower($estado->etiqueta())));
    }
}
