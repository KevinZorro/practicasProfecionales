<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\User;

/**
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                 | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Solicitar escenario    |       |             |                |    ✓    |            |
 * | Revisar solicitudes    |       |      ✓      |       ✓        |         |            |
 * | Aprobar o rechazar     |       |      ✓      |                |         |            |
 * | Ver calendario         |   ✓   |      ✓      |       ✓        |    ✓    |     ✓      |
 */
final class SolicitudPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Docente->value) || $this->revisaSolicitudes($usuario);
    }

    public function view(User $usuario, Solicitud $solicitud): bool
    {
        return $this->esSuya($usuario, $solicitud) || $this->revisaSolicitudes($usuario);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Docente->value);
    }

    public function revisar(User $usuario, Solicitud $solicitud): bool
    {
        return $this->revisaSolicitudes($usuario);
    }

    public function aprobar(User $usuario, Solicitud $solicitud): bool
    {
        // PENDIENTE (§11.1 de CLAUDE.md): está sin decidir con el cliente si
        // un usuario con rol docente y coordinador puede aprobar su propia
        // solicitud. Hoy sí puede. Cuando se resuelva, la restricción entra
        // aquí y en rechazar():
        //     if ($this->esSuya($usuario, $solicitud)) { return false; }
        return $usuario->hasRole(Rol::Coordinador->value);
    }

    public function rechazar(User $usuario, Solicitud $solicitud): bool
    {
        // Mismo pendiente que en aprobar().
        return $usuario->hasRole(Rol::Coordinador->value);
    }

    /**
     * El calendario de reservas aprobadas (RF34) es la única vista que
     * comparten los cinco roles.
     */
    public function verCalendario(User $usuario): bool
    {
        return $usuario->hasAnyRole(array_column(Rol::cases(), 'value'));
    }

    /**
     * El coordinador hereda todo lo del administrativo. La herencia se
     * expresa una sola vez, aquí, en lugar de repetirse en cada permiso.
     */
    private function revisaSolicitudes(User $usuario): bool
    {
        return $usuario->hasAnyRole([Rol::Administrativo->value, Rol::Coordinador->value]);
    }

    private function esSuya(User $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->docente_id === $usuario->id;
    }
}
