<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use DomainException;

final class BloqueoInvalido extends DomainException
{
    public static function noEsParticipante(User $persona): self
    {
        return new self(sprintf('Solo se bloquea a estudiantes y docentes; %s no lo es.', $persona->nombre));
    }

    public static function yaEstaBloqueado(User $persona): self
    {
        return new self(sprintf('%s ya tiene un bloqueo vigente.', $persona->nombre));
    }

    public static function yaFueLevantado(): self
    {
        return new self('Ese bloqueo ya se había levantado.');
    }

    public static function sinMotivo(): self
    {
        return new self('Escriba el motivo.');
    }
}
