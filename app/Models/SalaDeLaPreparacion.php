<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Una de las salas (hasta tres) que ocupa una sesión: abriendo las
 * cortinas, varias salas son un solo salón con la capacidad sumada. Es un
 * modelo, y no solo una tabla, para que asignar dispare eventos de modelo,
 * como el que usa la prueba de concurrencia de asignar sala.
 */
class SalaDeLaPreparacion extends Pivot
{
    protected $table = 'preparacion_sala';

    public $timestamps = false;
}
