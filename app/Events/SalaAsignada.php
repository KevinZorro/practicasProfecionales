<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Preparacion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** El administrativo eligió o cambió la sala de un escenario (RF36). */
final class SalaAsignada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Preparacion $preparacion,
        public readonly bool $esCambio,
    ) {}
}
