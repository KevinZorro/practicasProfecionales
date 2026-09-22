<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoFormatoConfidencialidad;
use DomainException;

final class FormatoConfidencialidadInvalido extends DomainException
{
    public static function sinPlantillaActiva(): self
    {
        return new self('No hay plantilla de confidencialidad activa: el ADMIN debe cargar una antes de que alguien entregue.');
    }

    public static function soloSeAceptaPdf(string $mime): self
    {
        return new self(sprintf('El formato debe entregarse en PDF; se recibió "%s".', $mime));
    }

    public static function archivoDemasiadoGrande(int $maximoKb): self
    {
        return new self(sprintf('El archivo del formato supera el máximo de %d KB.', $maximoKb));
    }

    public static function yaSeRecibioEnFisico(): self
    {
        // No se pisa: el registro de quién recibió el papel y cuándo es
        // justamente lo que hay que conservar (RF53).
        return new self('La entrega en físico de este formato ya estaba registrada.');
    }

    public static function noEstaCargado(EstadoFormatoConfidencialidad $estado): self
    {
        return new self(sprintf(
            'Solo se verifica o rechaza un formato cargado; este está "%s".',
            $estado->value,
        ));
    }
}
