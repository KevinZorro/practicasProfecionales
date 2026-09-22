<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\AsignacionDeRol;
use App\Models\User;

/**
 * Quién reparte roles (RF63, RF64).
 *
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                            | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Asignar o revocar roles           |   ✓   |             |                |         |            |
 *
 * Solo el ADMIN, y sin herencia: el RF63 dice que es el ADMIN quien eleva a
 * coordinador, así que si el coordinador pudiera asignar roles podría
 * ampliarse a sí mismo el suyo o repartir el de coordinador, que es lo que
 * el requerimiento reserva al administrador de la plataforma.
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la de
 * AsignacionDeRol tiene que llamarse AsignacionDeRolPolicy. Con cualquier
 * otro nombre no se descubre y el Gate deniega en silencio.
 */
final class AsignacionDeRolPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    public function view(User $usuario, AsignacionDeRol $asignacion): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    /**
     * El modelo es opcional para poder preguntar a nivel de clase: la
     * pantalla decide si enseña el formulario antes de que exista ninguna
     * asignación.
     */
    public function asignar(User $usuario, ?AsignacionDeRol $asignacion = null): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    public function revocar(User $usuario, ?AsignacionDeRol $asignacion = null): bool
    {
        return $this->administraLaPlataforma($usuario);
    }

    private function administraLaPlataforma(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }
}
