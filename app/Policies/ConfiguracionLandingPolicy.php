<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Configuración de la landing (RF11): textos del hero, video y contacto.
 * Contenido público: solo el ADMIN, sin herencia.
 *
 * No hay registros que ver o borrar uno a uno: es un conjunto fijo de
 * claves (ClaveConfiguracionLanding) que se edita en una sola página.
 */
final class ConfiguracionLandingPolicy
{
    public function update(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
