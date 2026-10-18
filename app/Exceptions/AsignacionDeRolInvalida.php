<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Rol;
use DomainException;

final class AsignacionDeRolInvalida extends DomainException
{
    public static function elRolYaEstaVigente(Rol $rol): self
    {
        return new self(sprintf('Esta persona ya tiene el rol de %s vigente.', $rol->etiqueta()));
    }

    public static function noTieneEseRol(Rol $rol): self
    {
        return new self(sprintf('Esta persona no tiene vigente el rol de %s.', $rol->etiqueta()));
    }

    public static function laFechaDeFinYaPaso(string $hasta): self
    {
        // Un rol que nace vencido no da permisos ni un día, y deja una fila
        // que estorba al volver a asignarlo.
        return new self(sprintf('La fecha de fin (%s) ya pasó: elige hoy o una fecha posterior.', $hasta));
    }

    public static function nadieSeQuitaElRolDeAdmin(): self
    {
        // Solo un ADMIN revoca, así que el único ADMIN que se quita el rol
        // dejaría la plataforma sin nadie que pueda devolvérselo.
        return new self('Nadie se quita a sí mismo el rol de ADMIN: la plataforma podría quedarse sin administrador. Que otro ADMIN lo haga.');
    }

    public static function laElevacionExigeMotivo(): self
    {
        return new self('Elevar a coordinador exige escribir el motivo de la delegación.');
    }
}
