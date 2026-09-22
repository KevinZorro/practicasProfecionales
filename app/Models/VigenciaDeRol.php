<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Una fila del pivote de spatie, con sus fechas de vigencia (RF63, RF64).
 *
 * Existe solo para que "desde" y "hasta" lleguen como fechas y no como
 * cadenas: sin esto, cada vista que las pinta tendría que parsearlas por su
 * cuenta y acabarían saliendo en formatos distintos según la pantalla.
 *
 * No lleva lógica: quién tiene un rol vigente lo decide el filtro de
 * User::roles(), en SQL.
 */
class VigenciaDeRol extends MorphPivot
{
    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }
}
