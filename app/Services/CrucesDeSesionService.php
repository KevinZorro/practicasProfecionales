<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSolicitud;
use App\Exceptions\SesionQueSeCruza;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Nadie está en dos sesiones a la vez (decisión del 2026-11-07): ni un
 * estudiante en dos sesiones que se cruzan, ni un docente con dos
 * solicitudes que se cruzan, porque solo puede estar en un salón. Cuentan
 * las pendientes, las revisadas y las aprobadas; una rechazada ya no ocupa
 * a nadie, y un estudiante retirado de una sesión (RF69) tampoco.
 *
 * Lo llaman los cinco caminos que ponen a alguien en una franja: solicitar,
 * completar la lista, registrar una sesión apartada, reprogramar y
 * sustituir al docente. Siempre dentro de la transacción de quien escribe y
 * después de bloquearPersonas(): dos personas que ponen al mismo estudiante
 * en dos sesiones a la vez se turnan, y la segunda ve la primera (B3.11).
 */
final class CrucesDeSesionService
{
    /**
     * Bloquea las filas de esas personas hasta el final de la transacción.
     * En orden de id, para que dos transacciones con personas en común no
     * se esperen la una a la otra.
     *
     * @param  list<int>  $ids
     */
    public function bloquearPersonas(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
    }

    public function garantizarDocenteLibre(User $docente, Franja $franja, ?Solicitud $excepto = null): void
    {
        $otra = $this->queSeCruzan($franja, $excepto)
            ->queDicta($docente)
            ->with('casoClinico:id,nombre')
            ->first();

        if ($otra instanceof Solicitud) {
            throw SesionQueSeCruza::delDocente($docente, $otra);
        }
    }

    /**
     * @param  list<int>  $estudianteIds
     */
    public function garantizarEstudiantesLibres(array $estudianteIds, Franja $franja, ?Solicitud $excepto = null): void
    {
        if ($estudianteIds === []) {
            return;
        }

        $soloEllos = static fn ($estudiantes) => $estudiantes->whereIn('users.id', $estudianteIds);

        $otras = $this->queSeCruzan($franja, $excepto)
            ->whereHas('estudiantesPresentes', $soloEllos)
            ->with(['casoClinico:id,nombre', 'estudiantesPresentes' => $soloEllos])
            ->orderBy('hora_inicio')
            ->get();

        if ($otras->isNotEmpty()) {
            throw SesionQueSeCruza::deEstudiantes($otras);
        }
    }

    /** @return Builder<Solicitud> */
    private function queSeCruzan(Franja $franja, ?Solicitud $excepto): Builder
    {
        return Solicitud::query()
            ->whereIn('estado', [EstadoSolicitud::Pendiente, EstadoSolicitud::Revisada, EstadoSolicitud::Aprobada])
            ->queSeSolapanCon($franja->fecha, $franja->horaInicio, $franja->horaFin)
            ->when($excepto instanceof Solicitud, static fn (Builder $consulta) => $consulta->whereKeyNot($excepto?->id));
    }
}
