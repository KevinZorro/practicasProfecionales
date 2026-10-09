<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PeriodoAcademicoInvalido;
use App\Models\PeriodoAcademico;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Periodo académico que el laboratorio abre y cierra (RF75).
 *
 * El sistema no impone fechas: el periodo corre desde que alguien lo abre
 * hasta que alguien lo cierra. El formato de confidencialidad y los demás
 * procesos semestrales preguntan aquí cuál es el periodo, nunca al
 * calendario.
 */
final class PeriodoAcademicoService
{
    public const POR_PAGINA = 15;

    /**
     * El periodo que rige hoy: el abierto, o si no hay ninguno, el último
     * que se cerró (D3 de docs/trazabilidad.md). Entre el cierre de un
     * semestre y la apertura del siguiente sigue valiendo lo del anterior;
     * de lo contrario, olvidar abrir el periodo dejaría a todo el mundo sin
     * formato de un día para otro.
     *
     * Nulo solo si el laboratorio nunca ha abierto un periodo.
     */
    public function vigente(): ?PeriodoAcademico
    {
        return $this->abierto()
            ?? PeriodoAcademico::query()->cerrados()->latest('cerrado_at')->latest('id')->first();
    }

    public function abierto(): ?PeriodoAcademico
    {
        return PeriodoAcademico::query()->abiertos()->first();
    }

    /**
     * @return LengthAwarePaginator<int, PeriodoAcademico>
     */
    public function historial(int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return PeriodoAcademico::query()
            ->with(['abiertoPor:id,nombre', 'cerradoPor:id,nombre'])
            ->orderByDesc('abierto_at')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    public function abrir(string $nombre, User $actor): PeriodoAcademico
    {
        $this->garantizarPermiso($actor, 'create', PeriodoAcademico::class);
        $nombre = trim($nombre);

        return DB::transaction(function () use ($nombre, $actor): PeriodoAcademico {
            $this->garantizarQueNingunoEstaAbierto();

            if (PeriodoAcademico::query()->where('nombre', $nombre)->exists()) {
                throw PeriodoAcademicoInvalido::nombreRepetido($nombre);
            }

            $periodo = new PeriodoAcademico(['nombre' => $nombre]);
            $periodo->abierto_at = now();
            $periodo->abierto_por = $actor->id;
            $periodo->save();

            return $periodo;
        });
    }

    public function cerrar(PeriodoAcademico $periodo, User $actor): PeriodoAcademico
    {
        $this->garantizarPermiso($actor, 'cerrar', $periodo);

        if (! $periodo->estaAbierto()) {
            throw PeriodoAcademicoInvalido::yaEstaCerrado($periodo);
        }

        $periodo->cerrado_at = now();
        $periodo->cerrado_por = $actor->id;
        $periodo->save();

        return $periodo;
    }

    /**
     * Deshace un cierre hecho por error. Solo el último periodo cerrado y
     * solo si no hay otro abierto: reabrir uno viejo cambiaría a qué periodo
     * pertenecen las entregas de hoy.
     */
    public function reabrir(PeriodoAcademico $periodo, User $actor): PeriodoAcademico
    {
        $this->garantizarPermiso($actor, 'reabrir', $periodo);

        return DB::transaction(function () use ($periodo): PeriodoAcademico {
            $this->garantizarQueNingunoEstaAbierto();

            $ultimo = $this->vigente();

            if (! $ultimo instanceof PeriodoAcademico || $ultimo->isNot($periodo)) {
                throw PeriodoAcademicoInvalido::soloSeReabreElUltimo($periodo);
            }

            $periodo->cerrado_at = null;
            $periodo->cerrado_por = null;
            $periodo->save();

            return $periodo;
        });
    }

    private function garantizarQueNingunoEstaAbierto(): void
    {
        $abierto = $this->abierto();

        if ($abierto instanceof PeriodoAcademico) {
            throw PeriodoAcademicoInvalido::yaHayUnoAbierto($abierto);
        }
    }

    /**
     * La Policy decide; el Service la consulta para que la regla valga
     * también fuera de una petición HTTP.
     *
     * @param  PeriodoAcademico|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, PeriodoAcademico|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException(
                sprintf('El usuario no tiene permiso para "%s" el periodo académico.', $accion),
            );
        }
    }
}
