<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\User;

/**
 * Permisos del §6.1 del documento de arquitectura:
 *
 * | Acción                 | ADMIN | Coordinador | Administrativo | Docente | Estudiante |
 * | Solicitar escenario    |       |             |                |    ✓    |            |
 * | Revisar solicitudes    |       |      ✓      |       ✓        |         |            |
 * | Aprobar una revisada   |   ✓   |      ✓      |                |         |            |
 * | Rechazar               |       |      ✓      |                |         |            |
 * | Ver calendario         |   ✓   |      ✓      |       ✓        |    ✓    |     ✓      |
 *
 * El ADMIN aprueba cuando la coordinadora no está disponible, pero no revisa:
 * la revisión administrativa previa es condición para aprobar, así que quien
 * aprueba nunca es quien revisó (cliente, reunión del 2026-09).
 */
final class SolicitudPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Docente->value) || $this->accedeALaBandeja($usuario);
    }

    public function view(User $usuario, Solicitud $solicitud): bool
    {
        return $this->esSuya($usuario, $solicitud) || $this->accedeALaBandeja($usuario);
    }

    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Docente->value);
    }

    /**
     * Entrar a la bandeja de revisión y resolución.
     *
     * No coincide con revisar(): el ADMIN entra para aprobar, pero no marca
     * solicitudes como revisadas. Lo usan la navegación lateral y el
     * controlador de la pantalla.
     */
    public function verBandeja(User $usuario, ?Solicitud $solicitud = null): bool
    {
        return $this->accedeALaBandeja($usuario);
    }

    /**
     * El modelo es opcional para poder preguntar también a nivel de clase,
     * que es lo que necesita la navegación para decidir si enseña la bandeja
     * antes de tener ninguna solicitud delante. Mismo patrón que
     * ItemInventarioPolicy::editarNivelFidelidad().
     */
    public function revisar(User $usuario, ?Solicitud $solicitud = null): bool
    {
        return $this->revisaSolicitudes($usuario);
    }

    /**
     * Sin revisión administrativa previa no aprueba nadie: la condición es el
     * estado del modelo, no un campo derivado. El Service ya impide la
     * transición desde "pendiente"; aquí se repite para que el control ni
     * siquiera aparezca en pantalla.
     */
    public function aprobar(User $usuario, Solicitud $solicitud): bool
    {
        // PENDIENTE (§11.1 de CLAUDE.md): está sin decidir con el cliente si
        // un usuario con rol docente y coordinador puede aprobar su propia
        // solicitud. Hoy sí puede. Cuando se resuelva, la restricción entra
        // aquí y en rechazar():
        //     if ($this->esSuya($usuario, $solicitud)) { return false; }
        if ($solicitud->estado !== EstadoSolicitud::Revisada) {
            return false;
        }

        return $usuario->hasAnyRole([Rol::Coordinador->value, Rol::Admin->value]);
    }

    /**
     * PENDIENTE con el cliente: el ADMIN puede aprobar en ausencia de la
     * coordinadora, pero solo se habló de aprobar. Rechazar sigue siendo suyo
     * hasta que lo confirme, así que en la bandeja el ADMIN ve "Aprobar" y no
     * ve "Rechazar". La asimetría es intencional, no un olvido.
     */
    public function rechazar(User $usuario, Solicitud $solicitud): bool
    {
        // Mismo pendiente del §11.1 que en aprobar().
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

    /** Quien revisa, más el ADMIN, que entra solo a aprobar. */
    private function accedeALaBandeja(User $usuario): bool
    {
        return $this->revisaSolicitudes($usuario) || $usuario->hasRole(Rol::Admin->value);
    }

    private function esSuya(User $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->docente_id === $usuario->id;
    }
}
