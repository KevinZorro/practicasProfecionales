<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * El formulario de un taller no está disponible: la sección está oculta, no
 * hay correo de contacto adonde enviarlo, o el taller no está publicado o ya
 * pasó (RF09). Para el visitante es un 404.
 */
final class FormularioDeInformacionNoDisponible extends DomainException
{
    public static function paraEseTaller(): self
    {
        return new self('Ese taller no recibe solicitudes de información.');
    }
}
