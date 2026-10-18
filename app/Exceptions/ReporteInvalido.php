<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class ReporteInvalido extends DomainException
{
    /** La descarga es un enlace: el error se ve como página con su mensaje. */
    public function render(): never
    {
        abort(422, $this->getMessage());
    }

    public static function elPdfNecesitaUnRango(int $diasMaximos): self
    {
        return new self(sprintf(
            'El PDF necesita un rango de fechas de hasta %d días. Para periodos más largos descarga el Excel.',
            $diasMaximos,
        ));
    }

    public static function elRangoDelPdfEsMuyLargo(int $diasMaximos): self
    {
        return new self(sprintf(
            'El PDF admite rangos de hasta %d días. Acorta el rango o descarga el Excel, que no tiene ese tope.',
            $diasMaximos,
        ));
    }

    public static function elPdfTieneDemasiadasFilas(int $filasMaximas): self
    {
        return new self(sprintf(
            'El reporte tiene más de %d filas y el PDF no las aguanta. Acorta el rango o descarga el Excel.',
            $filasMaximas,
        ));
    }
}
