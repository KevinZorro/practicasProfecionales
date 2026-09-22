<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\NecesidadDeReposicion;
use App\Models\User;

/**
 * Lo que hizo falta y no sale del historial (RF67).
 *
 * Lo anota quien está en el laboratorio cuando pasa: el administrativo, y
 * por herencia coordinación y el ADMIN. Es exactamente lo que hoy se apunta
 * en una hoja aparte.
 */
final class NecesidadDeReposicionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $this->anotaNecesidades($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->anotaNecesidades($usuario);
    }

    /**
     * Dar por atendida: llegó, ya no hace falta, o se resolvió de otra
     * forma. Lo hacen los mismos que la anotan, porque constatar que llegó
     * la pila es trabajo diario del laboratorio.
     */
    public function atender(User $usuario, NecesidadDeReposicion $necesidad): bool
    {
        return $this->anotaNecesidades($usuario);
    }

    public function delete(User $usuario, NecesidadDeReposicion $necesidad): bool
    {
        return $this->anotaNecesidades($usuario);
    }

    private function anotaNecesidades(User $usuario): bool
    {
        return $usuario->hasAnyRole([
            Rol::Admin->value,
            Rol::Coordinador->value,
            Rol::Administrativo->value,
        ]);
    }
}
