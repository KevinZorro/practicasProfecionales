<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoSesion;
use App\Models\EvaluacionEstudiante;
use App\Models\Solicitud;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as ConsultaCruda;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reportes agregados del laboratorio (RF54-RF56).
 *
 * Cada reporte es UNA consulta, devuelta sin ejecutar. Pantalla, PDF y Excel
 * la reciben y deciden cómo recorrerla —paginando o por lotes—, pero ninguno
 * la reescribe. Es la única forma de garantizar que lo que se ve en pantalla
 * y lo que se descarga digan lo mismo, que es lo que exige el §6 de
 * CLAUDE.md y el §7 del documento de arquitectura.
 *
 * Ninguno de los tres métodos ejecuta nada ni carga el conjunto completo en
 * memoria: eso lo hacen paginar() y porLotes(), los dos únicos puntos donde
 * la consulta llega a la base.
 */
final class ReporteService
{
    /**
     * Tamaño de lote de las exportaciones. El servidor institucional es
     * modesto (RNF10), así que el Excel y el PDF recorren los resultados de
     * quinientos en quinientos en vez de traerlos todos.
     */
    public const TAMANO_DE_LOTE = 500;

    public const POR_PAGINA = 25;

    /**
     * RF54. Uso de escenarios agrupado por docente, materia, semestre, caso
     * clínico, sala y tipo de sesión.
     *
     * Las horas salen de hora_inicio y hora_fin, no de un campo guardado, y
     * se suman en la base de datos: traer las filas para sumarlas en PHP
     * sería justo la carga en memoria que el RNF10 no admite.
     */
    public function usoDeEscenarios(FiltroReporte $filtro): Builder
    {
        return $this->solicitudesAprobadas($filtro)
            ->leftJoin('preparaciones', 'preparaciones.solicitud_id', '=', 'solicitudes.id')
            ->leftJoin('salas', 'salas.id', '=', 'preparaciones.sala_id')
            ->join('users', 'users.id', '=', 'solicitudes.docente_id')
            ->join('materias', 'materias.id', '=', 'solicitudes.materia_id')
            ->join('casos_clinicos', 'casos_clinicos.id', '=', 'solicitudes.caso_clinico_id')
            ->when($filtro->salaId !== null, fn (Builder $c) => $c->where('preparaciones.sala_id', $filtro->salaId))
            // Se agrupa por id, no por nombre: dos docentes homónimos o dos
            // casos clínicos con el mismo título son cosas distintas y no
            // pueden caer en el mismo renglón. PostgreSQL deja seleccionar
            // el resto de columnas de una tabla cuya clave primaria está
            // agrupada.
            ->groupBy('users.id', 'materias.id', 'casos_clinicos.id', 'salas.id', 'solicitudes.tipo')
            ->orderBy('users.nombre')->orderBy('materias.nombre')->orderBy('casos_clinicos.nombre')
            ->select($this->columnasDeUso());
    }

    /**
     * RF55. Un renglón por estudiante evaluado, con su intento.
     *
     * Solo evaluaciones finalizadas: un borrador es una evaluación a medio
     * llenar, y sus resultados todavía pueden cambiar. Publicarlos en un
     * reporte sería dar por definitivo algo que el docente no ha cerrado.
     */
    public function resultadosDeEvaluacion(FiltroReporte $filtro): Builder
    {
        return EvaluacionEstudiante::query()
            ->with([
                'estudiante:id,nombre,email',
                'evaluacion:id,solicitud_id,tipo_evaluacion_id,docente_id',
                'evaluacion.docente:id,nombre',
                'evaluacion.tipoEvaluacion:id,nombre',
                'evaluacion.solicitud:id,materia_id,fecha',
                'evaluacion.solicitud.materia:id,codigo,nombre,semestre',
            ])
            ->whereHas('evaluacion', fn (Builder $c) => $c->where('estado', EstadoEvaluacion::Finalizada)
                ->when($filtro->docenteId !== null, fn (Builder $e) => $e->where('docente_id', $filtro->docenteId))
                ->whereHas('solicitud', fn (Builder $s) => $this->aplicarFechaYMateria($s, $filtro)))
            ->orderBy('evaluacion_id')->orderBy('estudiante_id')->orderBy('intento');
    }

    /**
     * RF56. Escenarios de evaluación que ya pasaron y cuya evaluación nunca
     * quedó registrada.
     *
     * Un borrador cuenta como FALTANTE, no como registrada. Si contara como
     * registrada, una evaluación empezada y nunca cerrada desaparecería de
     * los dos reportes: del RF55 porque no está finalizada, y del RF56
     * porque existe la fila. Ese hueco es justo lo que el RF56 busca
     * evitar. La columna "motivo" distingue los dos casos para que el
     * coordinador sepa si hay que registrarla o solo terminarla.
     */
    public function evaluacionesNoRegistradas(FiltroReporte $filtro): Builder
    {
        return Solicitud::query()
            ->with(['docente:id,nombre,email', 'materia:id,codigo,nombre,semestre', 'casoClinico:id,nombre', 'evaluacion:id,solicitud_id,estado'])
            ->where('tipo', TipoSesion::Evaluacion)
            ->where('estado', EstadoSolicitud::Aprobada)
            ->whereDate('fecha', '<', now()->toDateString())
            ->where(fn (Builder $c) => $c->whereDoesntHave('evaluacion')
                ->orWhereHas('evaluacion', fn (Builder $e) => $e->where('estado', EstadoEvaluacion::Borrador)))
            ->tap(fn (Builder $c) => $this->aplicarFechaYMateria($c, $filtro))
            ->when($filtro->docenteId !== null, fn (Builder $c) => $c->where('docente_id', $filtro->docenteId))
            ->orderBy('fecha')->orderBy('id');
    }

    /**
     * @template T of BuilderContract
     *
     * @param  T  $consulta
     */
    public function paginar(BuilderContract $consulta, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return $consulta->paginate($porPagina);
    }

    /**
     * Recorre la consulta por lotes. Es lo que usan el PDF y el Excel: la
     * misma consulta de pantalla, sin traerla entera a memoria.
     *
     * @param  callable(Collection<int, mixed>): void  $porCadaLote
     */
    public function porLotes(BuilderContract $consulta, callable $porCadaLote, int $tamano = self::TAMANO_DE_LOTE): void
    {
        $consulta->chunk($tamano, static function ($lote) use ($porCadaLote): void {
            $porCadaLote($lote);
        });
    }

    /**
     * Base común del RF54: solo solicitudes aprobadas. El reporte mide uso
     * real del laboratorio, y una solicitud pendiente o rechazada no ocupó
     * ni sala ni equipo.
     */
    private function solicitudesAprobadas(FiltroReporte $filtro): Builder
    {
        return Solicitud::query()
            ->where('solicitudes.estado', EstadoSolicitud::Aprobada)
            ->when($filtro->desde !== null, fn (Builder $c) => $c->whereDate('solicitudes.fecha', '>=', $filtro->desde))
            ->when($filtro->hasta !== null, fn (Builder $c) => $c->whereDate('solicitudes.fecha', '<=', $filtro->hasta))
            ->when($filtro->docenteId !== null, fn (Builder $c) => $c->where('solicitudes.docente_id', $filtro->docenteId))
            ->when($filtro->materiaId !== null, fn (Builder $c) => $c->where('solicitudes.materia_id', $filtro->materiaId));
    }

    /**
     * Rango de fechas y materia sobre una consulta de solicitudes. Lo
     * comparten el RF55 (a través de la evaluación) y el RF56.
     *
     * @param  Builder<Solicitud>  $consulta
     */
    private function aplicarFechaYMateria(Builder $consulta, FiltroReporte $filtro): void
    {
        $consulta
            ->when($filtro->desde !== null, fn (Builder $c) => $c->whereDate('fecha', '>=', $filtro->desde))
            ->when($filtro->hasta !== null, fn (Builder $c) => $c->whereDate('fecha', '<=', $filtro->hasta))
            ->when($filtro->materiaId !== null, fn (Builder $c) => $c->where('materia_id', $filtro->materiaId));
    }

    /**
     * Columnas agregadas del RF54.
     *
     * Las horas se calculan en SQL sobre la diferencia entre las dos horas.
     * La expresión es de PostgreSQL, que es el motor del proyecto en
     * desarrollo, pruebas y producción.
     *
     * @return list<ConsultaCruda|string>
     */
    private function columnasDeUso(): array
    {
        return [
            // Los alias llevan sufijo para no pisar las relaciones del
            // modelo: un atributo "docente" ocultaría la relación docente().
            'users.nombre as docente_nombre',
            'materias.nombre as materia_nombre',
            'materias.semestre as materia_semestre',
            'casos_clinicos.nombre as caso_clinico_nombre',
            DB::raw("COALESCE(salas.nombre, 'Sin asignar') as sala_nombre"),
            // Sin alias, para que siga aplicando el cast a TipoSesion.
            'solicitudes.tipo',
            DB::raw('COUNT(solicitudes.id) as sesiones'),
            DB::raw('SUM(EXTRACT(EPOCH FROM (solicitudes.hora_fin - solicitudes.hora_inicio)) / 3600) as horas'),
            DB::raw('SUM(solicitudes.cantidad_estudiantes) as estudiantes'),
        ];
    }
}
