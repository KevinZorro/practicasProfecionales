<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoSolicitud;
use App\Enums\ResultadoEvaluacion;
use App\Enums\TipoSesion;
use App\Exceptions\EvaluacionInvalida;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\EvaluacionItem;
use App\Models\ItemChecklist;
use App\Models\Solicitud;
use App\Models\TipoEvaluacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registro de evaluaciones de habilidades (RF41-RF50).
 *
 * Tres reglas del §6 del documento mandan sobre todo lo demás: ninguna
 * evaluación existe sin escenario aprobado de tipo evaluación; el resultado
 * lo decide el docente y no se deriva de los ítems; y el checklist se copia
 * de la plantilla, no se referencia.
 */
final class EvaluacionService
{
    public function crear(Solicitud $solicitud, TipoEvaluacion $tipo, User $docente): Evaluacion
    {
        $this->garantizarSolicitudApta($solicitud);
        $this->garantizarTipoDeLaMateria($tipo, $solicitud);

        return DB::transaction(function () use ($solicitud, $tipo, $docente): Evaluacion {
            $evaluacion = Evaluacion::create([
                'solicitud_id' => $solicitud->id,
                'tipo_evaluacion_id' => $tipo->id,
                'docente_id' => $docente->id,
                'estado' => EstadoEvaluacion::Borrador,
            ]);

            $this->copiarChecklist($evaluacion, $tipo);

            return $evaluacion;
        });
    }

    public function agregarEstudiante(Evaluacion $evaluacion, User $estudiante): EvaluacionEstudiante
    {
        $this->garantizarBorrador($evaluacion);

        // Nace sin resultado: lo decide el docente más adelante (RF46).
        return $evaluacion->estudiantes()->create([
            'estudiante_id' => $estudiante->id,
            'resultado' => null,
            'intento' => $this->calcularIntento($estudiante, $evaluacion->tipoEvaluacion),
        ]);
    }

    public function quitarEstudiante(Evaluacion $evaluacion, User $estudiante): void
    {
        $this->garantizarBorrador($evaluacion);

        $evaluacion->estudiantes()->where('estudiante_id', $estudiante->id)->delete();
    }

    /**
     * Número de vez que el estudiante presenta este tipo de evaluación
     * (RF48).
     *
     * Aprobadas y no aprobadas cuentan igual: "intento" es cuántas veces se
     * presenta, no cuántas veces falló. Filtrar por resultado haría que
     * quien aprobó y vuelve a presentar apareciera otra vez como intento 1.
     *
     * Solo cuentan las finalizadas: un borrador todavía no es una
     * presentación registrada.
     */
    public function calcularIntento(User $estudiante, TipoEvaluacion $tipo): int
    {
        return EvaluacionEstudiante::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereHas('evaluacion', static fn (Builder $consulta) => $consulta
                ->where('tipo_evaluacion_id', $tipo->id)
                ->where('estado', EstadoEvaluacion::Finalizada))
            ->count() + 1;
    }

    public function marcarItem(EvaluacionEstudiante $registro, EvaluacionItem $item): void
    {
        $this->garantizarBorrador($registro->evaluacion);

        // Marcar ítems no toca el resultado, ni lo sugiere (RF46).
        $registro->items()->syncWithoutDetaching([$item->id => ['cumplido' => true]]);
    }

    public function desmarcarItem(EvaluacionEstudiante $registro, EvaluacionItem $item): void
    {
        $this->garantizarBorrador($registro->evaluacion);

        $registro->items()->syncWithoutDetaching([$item->id => ['cumplido' => false]]);
    }

    /**
     * Fija el resultado que decidió el docente. El sistema no lo calcula a
     * partir de los ítems cumplidos, por más que la cuenta cuadre (RF46).
     */
    public function registrarResultado(
        EvaluacionEstudiante $registro,
        ResultadoEvaluacion $resultado,
    ): EvaluacionEstudiante {
        $this->garantizarBorrador($registro->evaluacion);

        $registro->update(['resultado' => $resultado]);

        return $registro;
    }

    public function registrarObservaciones(
        EvaluacionEstudiante $registro,
        ?string $observaciones,
    ): EvaluacionEstudiante {
        $this->garantizarBorrador($registro->evaluacion);

        $registro->update(['observaciones' => $observaciones]);

        return $registro;
    }

    public function finalizar(Evaluacion $evaluacion): Evaluacion
    {
        $this->garantizarBorrador($evaluacion);
        $this->garantizarResultadosCompletos($evaluacion);

        DB::transaction(function () use ($evaluacion): void {
            $this->fijarIntentos($evaluacion);
            $evaluacion->update(['estado' => EstadoEvaluacion::Finalizada]);
        });

        return $evaluacion;
    }

    /**
     * Historial de evaluaciones registradas por un docente (RF50).
     *
     * @return Collection<int, Evaluacion>
     */
    public function historialDelDocente(User $docente): Collection
    {
        return Evaluacion::query()
            ->where('docente_id', $docente->id)
            ->with(['tipoEvaluacion', 'solicitud.materia', 'solicitud.casoClinico', 'estudiantes.estudiante'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Resultados propios de un estudiante, con intento y checklist marcado
     * (RF49). Solo las finalizadas: mientras la evaluación sea un borrador
     * el docente aún está calificando.
     *
     * @return Collection<int, EvaluacionEstudiante>
     */
    public function historialDelEstudiante(User $estudiante): Collection
    {
        return EvaluacionEstudiante::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereHas('evaluacion', static fn (Builder $consulta) => $consulta
                ->where('estado', EstadoEvaluacion::Finalizada))
            ->with(['evaluacion.tipoEvaluacion', 'evaluacion.docente', 'evaluacion.solicitud.materia', 'items'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Copia congelada del checklist (RF44). Si el ADMIN edita la plantilla
     * después, esta evaluación conserva los ítems con los que se evaluó.
     */
    private function copiarChecklist(Evaluacion $evaluacion, TipoEvaluacion $tipo): void
    {
        $evaluacion->items()->createMany(
            $tipo->itemsChecklist->map(static fn (ItemChecklist $plantilla): array => [
                'descripcion' => $plantilla->descripcion,
                'orden' => $plantilla->orden,
            ])->all(),
        );
    }

    private function garantizarSolicitudApta(Solicitud $solicitud): void
    {
        if ($solicitud->tipo !== TipoSesion::Evaluacion) {
            throw EvaluacionInvalida::solicitudNoEsDeEvaluacion($solicitud);
        }

        if ($solicitud->estado !== EstadoSolicitud::Aprobada) {
            throw EvaluacionInvalida::solicitudNoAprobada($solicitud);
        }

        if ($solicitud->evaluacion()->exists()) {
            throw EvaluacionInvalida::solicitudYaEvaluada($solicitud);
        }
    }

    /**
     * El tipo de evaluación tiene que estar asociado a la materia de la
     * solicitud (RF43): no se evalúa canalización de vía en Semiología si
     * el ADMIN no lo habilitó ahí.
     */
    private function garantizarTipoDeLaMateria(TipoEvaluacion $tipo, Solicitud $solicitud): void
    {
        if (! $tipo->materias()->whereKey($solicitud->materia_id)->exists()) {
            throw EvaluacionInvalida::tipoAjenoALaMateria($tipo, $solicitud);
        }
    }

    private function garantizarBorrador(Evaluacion $evaluacion): void
    {
        if ($evaluacion->estado !== EstadoEvaluacion::Borrador) {
            throw EvaluacionInvalida::noEsBorrador();
        }
    }

    private function garantizarResultadosCompletos(Evaluacion $evaluacion): void
    {
        $sinResultado = $evaluacion->estudiantes()->whereNull('resultado')->count();

        if ($sinResultado > 0) {
            throw EvaluacionInvalida::faltanResultados($sinResultado);
        }
    }

    /**
     * Se recalcula al cerrar: entre que se agregó al estudiante y ahora
     * pudo finalizarse otra evaluación del mismo tipo. Corre antes de
     * marcar esta como finalizada, así que no se cuenta a sí misma.
     */
    private function fijarIntentos(Evaluacion $evaluacion): void
    {
        $tipo = $evaluacion->tipoEvaluacion;

        foreach ($evaluacion->estudiantes()->with('estudiante')->get() as $registro) {
            $registro->update(['intento' => $this->calcularIntento($registro->estudiante, $tipo)]);
        }
    }
}
