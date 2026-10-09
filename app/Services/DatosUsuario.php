<?php

declare(strict_types=1);

namespace App\Services;

/** Datos de una cuenta que el ADMIN crea o edita (RF22). */
final readonly class DatosUsuario
{
    public function __construct(
        public string $nombre,
        public string $email,
        public ?string $documento = null,
        public ?string $codigoInstitucional = null,
        public ?string $programa = null,
    ) {}
}
