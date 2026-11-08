<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Solicitud;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Nadie está en dos sesiones a la vez (decisión del 2026-11-07): un docente
 * solo puede estar en un salón, y un estudiante en una sesión.
 */
final class SesionQueSeCruza extends DomainException
{
    public static function delDocente(User $docente, Solicitud $otra): self
    {
        return new self(sprintf(
            '%s ya tiene otra sesión que se cruza: %s. Un docente no puede estar en dos salones a la vez.',
            $docente->nombre,
            self::describir($otra),
        ));
    }

    /**
     * @param  Collection<int, Solicitud>  $otras  con estudiantesPresentes limitados a los que chocan
     */
    public static function deEstudiantes(Collection $otras): self
    {
        $choques = $otras->flatMap(static fn (Solicitud $otra): Collection => $otra->estudiantesPresentes
            ->map(static fn (User $estudiante): string => sprintf('%s (%s)', $estudiante->nombre, self::describir($otra))));

        return new self(sprintf(
            'Ya están en otra sesión que se cruza: %s. Un estudiante no puede estar en dos sesiones a la vez.',
            $choques->implode('; '),
        ));
    }

    private static function describir(Solicitud $otra): string
    {
        return sprintf(
            '%s, el %s de %s a %s',
            $otra->casoClinico->nombre,
            $otra->fecha->format('d/m/Y'),
            substr($otra->hora_inicio, 0, 5),
            substr($otra->hora_fin, 0, 5),
        );
    }
}
