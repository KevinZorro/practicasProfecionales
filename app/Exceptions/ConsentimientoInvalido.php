<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoConsentimiento;
use DomainException;

final class ConsentimientoInvalido extends DomainException
{
    public static function sinPlantillaActiva(): self
    {
        return new self('No hay plantilla de consentimiento activa: el ADMIN debe cargar una antes de que los estudiantes entreguen.');
    }

    public static function soloSeAceptaPdf(string $mime): self
    {
        return new self(sprintf('El consentimiento debe entregarse en PDF; se recibió "%s".', $mime));
    }

    public static function archivoDemasiadoGrande(int $maximoKb): self
    {
        return new self(sprintf('El archivo del consentimiento supera el máximo de %d KB.', $maximoKb));
    }

    public static function noEstaCargado(EstadoConsentimiento $estado): self
    {
        return new self(sprintf(
            'Solo se verifica o rechaza un consentimiento cargado; este está "%s".',
            $estado->value,
        ));
    }
}
