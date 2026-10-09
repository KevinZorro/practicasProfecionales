<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrigenUsuario;

/**
 * Una persona tal como la entrega la fuente institucional (RF19, RF20). Es
 * el contrato entre la sincronización y la fuente: la real tendrá que
 * traducir sus columnas a esto.
 */
final readonly class PersonaInstitucional
{
    public function __construct(
        public string $documento,
        public ?string $codigo,
        public string $nombre,
        public string $email,
        public string $programa,
        public OrigenUsuario $vinculacion,
        public bool $vigente,
    ) {}
}
