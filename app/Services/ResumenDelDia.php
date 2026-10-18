<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EvaluacionEstudiante;
use App\Models\Preparacion;
use App\Models\Solicitud;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lo que enseña la pantalla «Hoy» a una persona, bloque por bloque.
 *
 * Un bloque en null no se pinta: la persona no puede hacer lo que el bloque
 * propone. Un bloque vacío sí se pinta, porque «nada por aprobar» también
 * es una respuesta. Las listas traen solo las primeras filas; el total dice
 * cuántas hay en su pantalla.
 */
final readonly class ResumenDelDia
{
    /**
     * @param  LengthAwarePaginator<int, Solicitud>|null  $porAprobar
     * @param  Collection<int, Preparacion>|null  $montajesDeHoy
     * @param  LengthAwarePaginator<int, Solicitud>|null  $porRevisar
     * @param  Collection<int, Solicitud>|null  $proximasSesiones
     * @param  LengthAwarePaginator<int, Solicitud>|null  $enTramite
     * @param  Collection<int, Solicitud>|null  $porEvaluar
     * @param  Collection<int, EvaluacionEstudiante>|null  $ultimosResultados
     */
    public function __construct(
        public ?LengthAwarePaginator $porAprobar = null,
        public ?Collection $montajesDeHoy = null,
        public ?LengthAwarePaginator $porRevisar = null,
        public ?Collection $proximasSesiones = null,
        public ?LengthAwarePaginator $enTramite = null,
        public ?Collection $porEvaluar = null,
        public ?Collection $ultimosResultados = null,
        public ?int $formatosPorVerificar = null,
        public ?EstadoDeMiFormato $miFormato = null,
        public ?ResumenDeSupervision $supervision = null,
    ) {}

    /** Si no hay ningún bloque que enseñar a esta persona. */
    public function vacio(): bool
    {
        return $this->porAprobar === null
            && $this->montajesDeHoy === null
            && $this->porRevisar === null
            && $this->proximasSesiones === null
            && $this->enTramite === null
            && $this->porEvaluar === null
            && $this->ultimosResultados === null
            && $this->formatosPorVerificar === null
            && $this->miFormato === null
            && $this->supervision === null;
    }
}
