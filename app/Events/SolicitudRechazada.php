<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Solicitud;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SolicitudRechazada
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Solicitud $solicitud) {}
}
