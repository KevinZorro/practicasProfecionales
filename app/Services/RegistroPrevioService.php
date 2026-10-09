<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoSolicitud;
use App\Enums\OrigenSolicitud;
use App\Enums\Rol;
use App\Exceptions\SolicitudInvalida;
use App\Mail\AvisoFormatoIntramuralMail;
use App\Models\ItemInventario;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sesiones apartadas antes del semestre (RF57-RF60).
 *
 * Coordinación entrega a los administrativos el formato físico con las
 * sesiones del semestre, y ellos las pasan a la plataforma. Llegan ya
 * aprobadas: no pasan por las dos fases de RF30-RF31, porque la aprobación
 * la dio coordinación al entregar el formato (regla 9 del CLAUDE.md). Los
 * insumos —el formato intramural— llegan después y también los digita un
 * administrativo.
 */
final class RegistroPrevioService
{
    public const POR_PAGINA = 15;

    public function __construct(
        private readonly SolicitudService $solicitudes,
        private readonly PreparacionService $preparaciones,
        private readonly InventarioService $inventario,
        private readonly AjustesService $ajustes,
    ) {}

    /**
     * Registra la sesión ya aprobada y con su preparación, como si se
     * hubiera aprobado en la plataforma, pero sin insumos.
     */
    public function registrar(DatosSesionApartada $datos, User $actor): Solicitud
    {
        $this->garantizarPermiso($actor, 'registrarApartada', Solicitud::class);
        $this->garantizarDocente($datos->docenteId);
        $this->solicitudes->garantizarCapacidad($datos->casoClinicoId, $datos->cantidadEstudiantes);
        $grupo = $this->normalizarGrupo($datos->grupo);

        return DB::transaction(function () use ($datos, $actor, $grupo): Solicitud {
            $solicitud = Solicitud::create([
                'docente_id' => $datos->docenteId,
                'materia_id' => $datos->materiaId,
                'caso_clinico_id' => $datos->casoClinicoId,
                'tipo' => $datos->tipo,
                'fecha' => $datos->fecha,
                'hora_inicio' => $datos->horaInicio,
                'hora_fin' => $datos->horaFin,
                'cantidad_estudiantes' => $datos->cantidadEstudiantes,
                'grupo' => $grupo,
                'observaciones' => $datos->observaciones,
                'estado' => EstadoSolicitud::Aprobada,
                'origen' => OrigenSolicitud::RegistroPrevio,
                'registrada_por' => $actor->id,
            ]);

            $this->preparaciones->crearDesdeSolicitud($solicitud);

            return $solicitud;
        });
    }

    /**
     * Cruces con lo ya registrado en esa franja (RF58). Son avisos, no
     * impedimentos: el tiempo entre sesiones y qué hacer con un cruce lo
     * decide el personal del laboratorio. La sala no se valida aquí, porque
     * se elige al preparar; solo se avisa si no quedaría ninguna libre
     * (D17 de docs/trazabilidad.md).
     *
     * @return list<string>
     */
    public function advertencias(string $fecha, string $horaInicio, string $horaFin, ?int $docenteId = null): array
    {
        $cruces = Solicitud::query()
            ->whereIn('estado', [EstadoSolicitud::Pendiente, EstadoSolicitud::Revisada, EstadoSolicitud::Aprobada])
            ->queSeSolapanCon($fecha, $horaInicio, $horaFin)
            ->with(['docente:id,nombre', 'casoClinico:id,nombre'])
            ->orderBy('hora_inicio')
            ->get();

        $avisos = [];

        if ($docenteId !== null && $cruces->contains('docente_id', $docenteId)) {
            $avisos[] = 'El docente ya tiene otra sesión en esa franja.';
        }

        if ($cruces->isNotEmpty()) {
            $avisos[] = sprintf(
                'En esa franja ya hay %d %s: %s.',
                $cruces->count(),
                $cruces->count() === 1 ? 'sesión' : 'sesiones',
                $cruces->map(static fn (Solicitud $s): string => $s->casoClinico->nombre.' ('.$s->docente->nombre.')')->implode(', '),
            );
        }

        $aprobadas = $cruces->where('estado', EstadoSolicitud::Aprobada)->count();

        if ($aprobadas >= Sala::query()->activas()->count()) {
            $avisos[] = 'No quedaría ninguna sala libre: las salas activas ya están tomadas por sesiones aprobadas.';
        }

        return $avisos;
    }

    /**
     * A nombre de quién se puede registrar una sesión.
     *
     * @return Collection<int, User>
     */
    public function docentes(): Collection
    {
        return User::query()->activos()->role(Rol::Docente->value)->orderBy('nombre')->get(['id', 'nombre']);
    }

    /**
     * Sesiones apartadas, las que no tienen formato intramural primero.
     *
     * @return LengthAwarePaginator<int, Solicitud>
     */
    public function listado(bool $soloSinFormato = false, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return Solicitud::query()
            ->apartadas()
            ->when($soloSinFormato, static fn (Builder $c) => $c->sinFormatoIntramural())
            ->with(['docente:id,nombre', 'materia:id,nombre', 'casoClinico:id,nombre'])
            ->orderByRaw('formato_intramural_at IS NOT NULL')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->paginate($porPagina);
    }

    /**
     * Registra el formato intramural de la sesión (RF59) y lo pasa a su
     * preparación. Devuelve lo que falta en el inventario para esa franja:
     * es un aviso para el laboratorio (RF58), no impide guardar.
     *
     * @param  array<int, int>  $items  id del ítem => cantidad
     * @return array<int, int> id del ítem => unidades libres, solo los que no alcanzan
     */
    public function registrarFormatoIntramural(Solicitud $solicitud, array $items, User $actor): array
    {
        $this->garantizarPermiso($actor, 'registrarFormatoIntramural', $solicitud);

        if ($items === []) {
            throw SolicitudInvalida::formatoIntramuralVacio();
        }

        $faltantes = $this->faltantes($solicitud, $items);

        DB::transaction(function () use ($solicitud, $items, $actor): void {
            $solicitud->items()->sync(array_map(static fn (int $cantidad): array => ['cantidad' => max(1, $cantidad)], $items));
            $solicitud->update([
                'formato_intramural_at' => now(),
                'formato_intramural_por' => $actor->id,
            ]);

            $preparacion = $solicitud->preparacion;

            if ($preparacion !== null) {
                $this->preparaciones->copiarItemsDesdeSolicitud($preparacion->setRelation('solicitud', $solicitud->load('items')));
            }
        });

        return $faltantes;
    }

    /**
     * Sesiones aprobadas de hoy a "dias" días que todavía no tienen formato
     * intramural (RF60).
     *
     * @return Collection<int, Solicitud>
     */
    public function proximasSinFormatoIntramural(?int $dias = null): Collection
    {
        $dias ??= $this->ajustes->diasDeAvisoIntramural();

        return Solicitud::query()
            ->aprobadas()
            ->sinFormatoIntramural()
            ->whereBetween('fecha', [now()->toDateString(), now()->addDays($dias)->toDateString()])
            ->with(['docente', 'materia:id,nombre', 'casoClinico:id,nombre'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * El aviso diario del RF60: un correo por persona con todas sus sesiones
     * pendientes, no uno por sesión (D16). Al docente, las suyas; a cada
     * administrativo, todas. Devuelve cuántos correos se enviaron.
     */
    public function avisarSinFormatoIntramural(): int
    {
        $sesiones = $this->proximasSinFormatoIntramural();

        if ($sesiones->isEmpty()) {
            return 0;
        }

        $enviados = 0;

        foreach ($sesiones->groupBy('docente_id') as $suyas) {
            $docente = $suyas->first()->docente;
            Mail::to($docente->email)->queue(new AvisoFormatoIntramuralMail($docente, $suyas, paraDocente: true));
            $enviados++;
        }

        $administrativos = User::query()->activos()->role(Rol::Administrativo->value)->get();

        foreach ($administrativos as $administrativo) {
            Mail::to($administrativo->email)->queue(new AvisoFormatoIntramuralMail($administrativo, $sesiones, paraDocente: false));
            $enviados++;
        }

        return $enviados;
    }

    /**
     * @param  array<int, int>  $items
     * @return array<int, int>
     */
    private function faltantes(Solicitud $solicitud, array $items): array
    {
        $catalogo = ItemInventario::query()->whereIn('id', array_keys($items))->get();
        $libres = $this->inventario->disponibilidadDeVarios(
            $catalogo,
            $solicitud->fecha->format('Y-m-d'),
            $solicitud->hora_inicio,
            $solicitud->hora_fin,
        );

        // La sesión ya está aprobada: lo que tenía pedido cuenta como
        // comprometido contra ella misma, así que se le devuelve.
        $propios = $solicitud->items()->pluck('solicitud_item.cantidad', 'items_inventario.id')->all();
        $faltantes = [];

        foreach ($items as $id => $cantidad) {
            $disponible = ($libres[$id] ?? 0) + (int) ($propios[$id] ?? 0);

            if ($disponible < $cantidad) {
                $faltantes[$id] = $disponible;
            }
        }

        return $faltantes;
    }

    private function garantizarDocente(int $docenteId): void
    {
        $esDocente = User::query()->whereKey($docenteId)->role(Rol::Docente->value)->exists();

        if (! $esDocente) {
            throw SolicitudInvalida::noEsDocente();
        }
    }

    /** En el formato físico el grupo puede no venir; si viene, con la misma regla de RF28. */
    private function normalizarGrupo(?string $grupo): ?string
    {
        return trim((string) $grupo) === '' ? null : $this->solicitudes->normalizarGrupo((string) $grupo);
    }

    /**
     * @param  Solicitud|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, Solicitud|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException(sprintf('El usuario no tiene permiso para "%s".', $accion));
        }
    }
}
