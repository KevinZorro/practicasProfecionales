<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\SolicitudInformacion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Un interesado pidió información de un taller desde la portada (RF09). */
final class SolicitudDeInformacionRecibida
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SolicitudInformacion $solicitud,
    ) {}
}
