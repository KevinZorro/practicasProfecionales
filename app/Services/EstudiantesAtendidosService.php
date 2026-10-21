<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClaveConfiguracionLanding;
use App\Enums\EstadoSolicitud;
use App\Enums\ModoCifraDeEstudiantes;
use Illuminate\Support\Facades\DB;

/**
 * Cuántos estudiantes han pasado por el laboratorio, para la cifra de la
 * landing. El sistema no toma asistencia: "asistió" es estar en la lista de
 * una sesión aprobada que ya se realizó y no haber sido retirado (RF69).
 *
 * Cuenta personas distintas por periodo académico y suma los periodos: quien
 * viene en dos semestres cuenta en los dos, quien viene a tres sesiones del
 * mismo semestre cuenta una vez. Una sesión se asigna al periodo cuyo rango
 * de apertura a cierre contiene su fecha; las anteriores al primer periodo no
 * cuentan (para eso está el modo manual).
 */
final class EstudiantesAtendidosService
{
    public function __construct(private readonly ConfiguracionLandingService $configuracion) {}

    public function modo(): ModoCifraDeEstudiantes
    {
        $valor = $this->configuracion->valores()[ClaveConfiguracionLanding::EstudiantesModo->value];

        return ModoCifraDeEstudiantes::tryFrom((string) $valor) ?? ModoCifraDeEstudiantes::Automatico;
    }

    /** Lo que se publica: la cifra escrita a mano o la calculada, según el modo. */
    public function cifraPublicada(): int
    {
        if ($this->modo() === ModoCifraDeEstudiantes::Manual) {
            $manual = $this->configuracion->valores()[ClaveConfiguracionLanding::EstudiantesManual->value];

            return max(0, (int) $manual);
        }

        return $this->calcular();
    }

    public function calcular(): int
    {
        $hoy = now()->toDateString();

        $porPeriodo = DB::table('estudiante_solicitud as es')
            ->join('solicitudes as s', 's.id', '=', 'es.solicitud_id')
            ->join('periodos_academicos as p', function ($union) use ($hoy): void {
                $union->whereRaw('s.fecha >= p.abierto_at::date')
                    ->whereRaw('s.fecha <= coalesce(p.cerrado_at::date, ?::date)', [$hoy]);
            })
            ->where('s.estado', EstadoSolicitud::Aprobada->value)
            ->where('s.fecha', '<=', $hoy)
            ->whereNull('es.retirado_at')
            ->groupBy('p.id')
            ->selectRaw('count(distinct es.estudiante_id) as estudiantes')
            ->pluck('estudiantes');

        return (int) $porPeriodo->sum();
    }
}
