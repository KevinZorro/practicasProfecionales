<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Sala;
use App\Models\User;

/**
 * Salas: el espacio físico donde se monta el escenario. El catálogo lo
 * gestiona solo el ADMIN, sin herencia. Asignar una sala a una preparación
 * es otra cosa, del administrativo, y la decide PreparacionPolicy; anotar
 * su estado de hoy (RF30), también.
 *
 * No se borran, se desactivan. Una sala con preparaciones no se puede borrar
 * (la llave es restrict) sin perder dónde se hizo cada práctica. Desactivarla
 * la saca de las salas que se ofrecen al asignar (PreparacionService).
 *
 * Lo que aquí no está definido —deleteAny, restore, replicate...— lo deniega
 * el Gate, porque las pantallas de Filament heredan de RecursoDelAdmin.
 */
final class SalaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, Sala $sala): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, Sala $sala): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function delete(User $usuario, Sala $sala): bool
    {
        return false;
    }

    /**
     * El estado del espacio físico (RF30) lo anota quien revisa solicitudes
     * y prepara escenarios: el administrativo, y coordinación y el ADMIN por
     * herencia. No es parte del catálogo, así que no va a Filament.
     */
    public function cambiarEstado(User $usuario, ?Sala $sala = null): bool
    {
        return $usuario->hasAnyRole([
            Rol::Admin->value,
            Rol::Coordinador->value,
            Rol::Administrativo->value,
        ]);
    }
}
