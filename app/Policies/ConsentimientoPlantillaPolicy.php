<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;

/**
 * Plantilla en blanco del consentimiento informado (RF51).
 *
 * Según el §6.1 del documento de arquitectura, cargar la plantilla es
 * exclusivo del ADMIN. Descargarla, en cambio, la necesita cualquiera que
 * vaya a firmarla o a repartirla, así que basta con estar autenticado.
 *
 * El nombre importa: Laravel resuelve las Policies por modelo, así que la
 * de ConsentimientoPlantilla tiene que llamarse ConsentimientoPlantillaPolicy.
 * Con cualquier otro nombre no se descubre y el Gate deniega en silencio.
 */
final class ConsentimientoPlantillaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function view(User $usuario, ConsentimientoPlantilla $plantilla): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /** RF51: solo el ADMIN sube la plantilla. */
    public function create(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    public function update(User $usuario, ConsentimientoPlantilla $plantilla): bool
    {
        return $usuario->hasRole(Rol::Admin->value);
    }

    /**
     * La plantilla está en blanco: no lleva datos personales de nadie, y el
     * estudiante tiene que poder bajarla para firmarla. Aun así se sirve por
     * ruta protegida, nunca por enlace directo al disco.
     */
    public function descargar(User $usuario, ConsentimientoPlantilla $plantilla): bool
    {
        return true;
    }
}
