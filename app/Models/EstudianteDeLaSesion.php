<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Un estudiante en la lista de una sesión (RF28). Si lo retiran (RF69), no
 * sale de la lista: queda marcado con el motivo y quién lo decidió.
 */
class EstudianteDeLaSesion extends Pivot
{
    protected $table = 'estudiante_solicitud';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retirado_at' => 'datetime',
        ];
    }

    public function fueRetirado(): bool
    {
        return $this->retirado_at !== null;
    }
}
