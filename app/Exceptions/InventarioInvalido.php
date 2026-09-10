<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TipoItemInventario;
use DomainException;

final class InventarioInvalido extends DomainException
{
    public static function nivelDeFidelidadReservadoAlAdmin(): self
    {
        return new self('El nivel de fidelidad de un simulador solo lo registra el ADMIN.');
    }

    public static function nivelDeFidelidadSoloEnSimuladores(TipoItemInventario $tipo): self
    {
        return new self(sprintf(
            'Un ítem de tipo "%s" no lleva nivel de fidelidad: solo los simuladores lo tienen.',
            $tipo->value,
        ));
    }
}
