<?php

declare(strict_types=1);

namespace App\Support;

/** La URL de la política de tratamiento de datos, o null si no hay una válida (QA-11). */
final class PoliticaDeDatos
{
    public static function url(): ?string
    {
        $url = trim((string) config('laboratorio.politica_de_datos_url'));

        return preg_match('#^https?://[^\s]+$#i', $url) === 1 ? $url : null;
    }
}
