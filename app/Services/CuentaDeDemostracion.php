<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Rol;

/** Una cuenta de demo:preparar, con la contraseña que se muestra una vez. */
final readonly class CuentaDeDemostracion
{
    public function __construct(
        public Rol $rol,
        public string $nombre,
        public string $email,
        public string $contrasena,
    ) {}
}
