<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Resources\Resource;

/**
 * Base de todas las pantallas del ADMIN en Filament.
 *
 * Filament consulta las Policies de Laravel, pero por defecto hace al revés
 * que Laravel: si el modelo no tiene Policy, o la Policy no define el
 * método (deleteAny, restore, replicate...), PERMITE la acción. Con eso,
 * olvidar una Policy o nombrarla mal —que ya nos pasó dos veces— dejaría la
 * pantalla abierta en vez de cerrada.
 *
 * Con la comprobación de existencia apagada, Filament pregunta al Gate
 * siempre, y el Gate deniega lo que no está definido. Cada recurso tiene
 * que heredar de aquí; RecursosDelAdminTest falla si alguno no lo hace o si
 * su modelo no tiene Policy.
 */
abstract class RecursoDelAdmin extends Resource
{
    protected static bool $shouldCheckPolicyExistence = false;
}
