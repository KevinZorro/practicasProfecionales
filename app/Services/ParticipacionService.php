<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImpedimentoDeIngreso;
use App\Models\Bloqueo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Quién puede entrar al laboratorio y quién no, y por qué (RF70).
 *
 * Dos cosas lo impiden: no tener el formato de confidencialidad al día
 * (verificado o entregado en físico, RF53) y estar bloqueado por
 * coordinación (RF68). Es el único sitio que las junta: la evaluación (RF45)
 * y la lista de cada sesión preguntan aquí.
 */
final class ParticipacionService
{
    public function __construct(
        private readonly ConfidencialidadService $confidencialidad,
        private readonly BloqueoService $bloqueos,
    ) {}

    /**
     * @return list<Impedimento>
     */
    public function impedimentosDe(User $persona): array
    {
        return $this->impedimentos([$persona->id])[$persona->id];
    }

    public function puedeIngresar(User $persona): bool
    {
        return $this->impedimentosDe($persona) === [];
    }

    /**
     * Impedimentos de varias personas en dos consultas, sin importar cuántas
     * sean: es lo que se pinta en la lista de una sesión.
     *
     * @param  list<int>  $personaIds
     * @return array<int, list<Impedimento>> vacío para quien sí puede entrar
     */
    public function impedimentos(array $personaIds): array
    {
        $habilitados = array_flip($this->confidencialidad->habilitadosParaPracticas($personaIds));
        $bloqueos = $this->bloqueos->vigentesDe($personaIds);

        $resultado = [];

        foreach ($personaIds as $id) {
            $resultado[$id] = [];

            if (! isset($habilitados[$id])) {
                $resultado[$id][] = new Impedimento(ImpedimentoDeIngreso::SinFormato);
            }

            $bloqueo = $bloqueos->get($id);

            if ($bloqueo instanceof Bloqueo) {
                $resultado[$id][] = new Impedimento(ImpedimentoDeIngreso::Bloqueado, $bloqueo->motivo);
            }
        }

        return $resultado;
    }

    /**
     * La lista de una sesión, lista para pintar (RF70): el docente, los
     * estudiantes —retirados incluidos, con su motivo— y qué impide entrar a
     * cada uno.
     *
     * @return array{docente: User, impedimentosDelDocente: list<Impedimento>, estudiantes: Collection<int, User>, impedimentos: array<int, list<Impedimento>>, responsablesDeRetiro: array<int, string>}
     */
    public function deLaSesion(Solicitud $solicitud): array
    {
        $solicitud->loadMissing([
            'docente',
            'estudiantes' => static fn ($consulta) => $consulta->orderBy('nombre'),
        ]);

        $ids = [$solicitud->docente_id, ...$solicitud->estudiantes->pluck('id')->all()];
        $impedimentos = $this->impedimentos(array_values(array_unique($ids)));

        // Quién retiró a cada uno, en una consulta y no una por fila.
        $responsables = User::query()
            ->whereIn('id', $solicitud->estudiantes->pluck('participacion.retirado_por')->filter()->unique()->all())
            ->pluck('nombre', 'id')
            ->all();

        return [
            'docente' => $solicitud->docente,
            'impedimentosDelDocente' => $impedimentos[$solicitud->docente_id],
            'estudiantes' => $solicitud->estudiantes,
            'impedimentos' => $impedimentos,
            'responsablesDeRetiro' => $responsables,
        ];
    }
}
