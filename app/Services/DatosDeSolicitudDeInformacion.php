<?php

declare(strict_types=1);

namespace App\Services;

/** Lo que deja un interesado en el formulario de un taller (RF09), ya validado. */
final readonly class DatosDeSolicitudDeInformacion
{
    public function __construct(
        public string $nombre,
        public string $email,
        public ?string $telefono,
        public ?string $mensaje,
    ) {}
}
