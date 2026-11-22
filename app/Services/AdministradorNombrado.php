<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/** Lo que hizo usuarios:crear-administrador, para decírselo a quien lo corrió. */
final readonly class AdministradorNombrado
{
    public function __construct(
        public User $cuenta,
        public bool $cuentaCreada,
        public bool $rolAsignado,
    ) {}

    public function resumen(): string
    {
        if ($this->cuentaCreada) {
            return sprintf('Creó la cuenta de %s <%s> con el rol de ADMIN.', $this->cuenta->nombre, $this->cuenta->email);
        }

        if ($this->rolAsignado) {
            return sprintf('La cuenta de %s <%s> ya existía: ahora también es ADMIN.', $this->cuenta->nombre, $this->cuenta->email);
        }

        return sprintf('%s <%s> ya era ADMIN: no cambió nada.', $this->cuenta->nombre, $this->cuenta->email);
    }
}
