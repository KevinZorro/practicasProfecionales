<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Valores de filtro que llegan en la URL, donde cualquiera puede escribir
 * cualquier cosa. Lo que no se puede interpretar se descarta (el filtro
 * queda sin aplicar) en vez de llegar a la base, que respondería con un
 * error 500 (QA-17).
 */
final class FiltroDeUrl
{
    /** Una fecha Y-m-d real, o '' si no lo es (2026-02-31 tampoco). */
    public static function fecha(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return '';
        }

        return $fecha !== null && $fecha->format('Y-m-d') === $valor ? $valor : '';
    }

    /** Un entero positivo, o null si es otra cosa. */
    public static function entero(int|string|null $valor): ?int
    {
        if (is_int($valor)) {
            return $valor > 0 ? $valor : null;
        }

        return is_string($valor) && ctype_digit($valor) && (int) $valor > 0 ? (int) $valor : null;
    }
}
