<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccionAuditada;
use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Exceptions\NovedadInvalida;
use App\Mail\SesionReprogramadaMail;
use App\Mail\SustitucionDocenteMail;
use App\Models\Reprogramacion;
use App\Models\Solicitud;
use App\Models\Sustitucion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Novedades de una sesión aprobada: reprogramarla (RF61) y sustituir al
 * docente (RF73). Las dos dejan una fila de solo añadir con lo que había,
 * lo nuevo, el motivo y quién lo registró.
 */
final class NovedadesDeSesionService
{
    public function __construct(
        private readonly SolicitudService $solicitudes,
        private readonly BitacoraService $bitacora,
        private readonly BloqueoService $bloqueos,
        private readonly CrucesDeSesionService $cruces,
    ) {}

    /**
     * Lo que conviene saber antes de poner a este docente en la sesión: si
     * está bloqueado (RF68), que es un aviso y el laboratorio decide, o si
     * ya dicta otra sesión a esa hora, que impide la sustitución
     * (CrucesDeSesionService) y aquí se anticipa.
     *
     * @return list<string>
     */
    public function avisosDeSustitucion(Solicitud $solicitud, User $reemplazo): array
    {
        $avisos = [];
        $bloqueo = $this->bloqueos->vigenteDe($reemplazo);

        if ($bloqueo !== null) {
            $avisos[] = sprintf('%s está bloqueado para el uso del laboratorio: %s.', $reemplazo->nombre, $bloqueo->motivo);
        }

        $cruzadas = Solicitud::query()
            ->whereKeyNot($solicitud->id)
            ->aprobadas()
            ->queDicta($reemplazo)
            ->queSeSolapanCon($solicitud->fecha->format('Y-m-d'), $solicitud->hora_inicio, $solicitud->hora_fin)
            ->count();

        if ($cruzadas > 0) {
            $avisos[] = sprintf('%s ya dicta otra sesión que se cruza con esta franja: no puede dictar esta.', $reemplazo->nombre);
        }

        return $avisos;
    }

    /**
     * Cambia fecha, franja o escenario. La sesión sigue aprobada; se
     * revalida la capacidad del escenario y el docente recibe un correo
     * (D4 de docs/trazabilidad.md).
     *
     * Si cambia la fecha o la franja, la sala asignada se libera: estaba
     * elegida para otra franja y se vuelve a elegir al preparar. Si cambia
     * el escenario, el formato intramural se borra, porque los insumos eran
     * los del escenario anterior, y el aviso del RF60 lo volverá a pedir.
     */
    public function reprogramar(Solicitud $solicitud, DatosReprogramacion $datos, User $actor): Reprogramacion
    {
        $this->garantizarPermiso($actor, 'reprogramar', $solicitud);
        $this->garantizarSesionVigente($solicitud);
        $motivo = $this->texto($datos->motivo, NovedadInvalida::faltaMotivo());
        $constancia = $this->texto($datos->constanciaComunicacion, NovedadInvalida::faltaConstancia());

        $cambiaFranja = $solicitud->fecha->format('Y-m-d') !== $datos->fecha
            || substr($solicitud->hora_inicio, 0, 5) !== $datos->horaInicio
            || substr($solicitud->hora_fin, 0, 5) !== $datos->horaFin;
        $cambiaEscenario = $solicitud->caso_clinico_id !== $datos->casoClinicoId;

        if ($this->solicitudes->esFechaPasada($datos->fecha)) {
            throw NovedadInvalida::fechaPasada();
        }

        if (! $cambiaFranja && ! $cambiaEscenario) {
            throw NovedadInvalida::sinCambios();
        }

        if ($cambiaEscenario && ! $this->solicitudes->casoPerteneceALaMateria($datos->casoClinicoId, $solicitud->materia_id)) {
            throw NovedadInvalida::casoFueraDeLaMateria();
        }

        if ($cambiaEscenario) {
            $this->solicitudes->garantizarCapacidad($datos->casoClinicoId, $solicitud->cantidad_estudiantes);
        }

        $reprogramacion = DB::transaction(function () use ($solicitud, $datos, $motivo, $constancia, $actor, $cambiaFranja, $cambiaEscenario): Reprogramacion {
            if ($cambiaFranja) {
                $this->garantizarFranjaLibre($solicitud, new Franja($datos->fecha, $datos->horaInicio, $datos->horaFin));
            }

            $reprogramacion = $solicitud->reprogramaciones()->create([
                'fecha_anterior' => $solicitud->fecha->format('Y-m-d'),
                'hora_inicio_anterior' => $solicitud->hora_inicio,
                'hora_fin_anterior' => $solicitud->hora_fin,
                'caso_clinico_anterior_id' => $solicitud->caso_clinico_id,
                'fecha_nueva' => $datos->fecha,
                'hora_inicio_nueva' => $datos->horaInicio,
                'hora_fin_nueva' => $datos->horaFin,
                'caso_clinico_nuevo_id' => $datos->casoClinicoId,
                'motivo' => $motivo,
                'constancia_comunicacion' => $constancia,
                'reprogramada_por' => $actor->id,
            ]);

            $solicitud->update([
                'fecha' => $datos->fecha,
                'hora_inicio' => $datos->horaInicio,
                'hora_fin' => $datos->horaFin,
                'caso_clinico_id' => $datos->casoClinicoId,
                ...($cambiaEscenario ? ['formato_intramural_at' => null, 'formato_intramural_por' => null] : []),
            ]);

            $preparacion = $solicitud->preparacion;

            // Las salas se eligieron para la franja vieja: se sueltan todas y se
            // vuelven a elegir al preparar.
            if ($cambiaFranja && $preparacion !== null) {
                $preparacion->salas()->detach();
            }

            if ($cambiaEscenario) {
                $solicitud->items()->detach();
                $preparacion?->items()->detach();
            }

            $this->bitacora->registrar(
                AccionAuditada::SesionReprogramada,
                $actor,
                $solicitud,
                sprintf(
                    'Reprogramó %s: del %s %s al %s %s. Comunicación: %s',
                    $this->solicitudes->describir($solicitud->refresh()),
                    $reprogramacion->fecha_anterior->format('d/m/Y'),
                    substr($reprogramacion->hora_inicio_anterior, 0, 5),
                    $reprogramacion->fecha_nueva->format('d/m/Y'),
                    substr($reprogramacion->hora_inicio_nueva, 0, 5),
                    $constancia,
                ),
                $motivo,
            );

            return $reprogramacion;
        });

        $docente = User::findOrFail($solicitud->idDelDocenteQueDicta());
        Mail::to($docente->email)->queue(new SesionReprogramadaMail($reprogramacion->load(['solicitud.materia', 'casoClinicoAnterior', 'casoClinicoNuevo'])));

        return $reprogramacion;
    }

    /**
     * Otro docente dicta la sesión (RF73). Se puede sustituir otra vez, o
     * devolverla al titular: elegirlo a él quita el reemplazo.
     */
    public function sustituir(Solicitud $solicitud, User $nuevo, string $motivo, User $actor): Sustitucion
    {
        $this->garantizarPermiso($actor, 'sustituirDocente', $solicitud);
        $this->garantizarSesionVigente($solicitud);
        $motivo = $this->texto($motivo, NovedadInvalida::faltaMotivo());

        $esDocenteActivo = User::query()->whereKey($nuevo->id)->activos()->role(Rol::Docente->value)->exists();

        if (! $esDocenteActivo) {
            throw NovedadInvalida::noEsDocente();
        }

        $anterior = $solicitud->idDelDocenteQueDicta();

        if ($anterior === $nuevo->id) {
            throw NovedadInvalida::mismoDocente();
        }

        $sustitucion = DB::transaction(function () use ($solicitud, $nuevo, $motivo, $actor, $anterior): Sustitucion {
            // La sesión primero y el docente después, en el mismo orden que
            // completar la lista: así dos transacciones no se esperan en cruz.
            $bloqueada = Solicitud::query()->whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();
            $this->cruces->bloquearPersonas([$nuevo->id]);
            $this->cruces->garantizarDocenteLibre($nuevo, Franja::deSolicitud($bloqueada), $bloqueada);

            $sustitucion = $solicitud->sustituciones()->create([
                'docente_anterior_id' => $anterior,
                'docente_nuevo_id' => $nuevo->id,
                'motivo' => $motivo,
                'registrada_por' => $actor->id,
            ]);

            $solicitud->update([
                'docente_que_dicta_id' => $nuevo->id === $solicitud->docente_id ? null : $nuevo->id,
            ]);

            $this->bitacora->registrar(
                AccionAuditada::DocenteSustituido,
                $actor,
                $solicitud,
                sprintf('En %s, dicta %s en lugar de %s.', $this->solicitudes->describir($solicitud), $nuevo->nombre, User::findOrFail($anterior)->nombre),
                $motivo,
            );

            return $sustitucion;
        });

        Mail::to($nuevo->email)->queue(new SustitucionDocenteMail($sustitucion->load(['solicitud.materia', 'solicitud.casoClinico', 'docenteAnterior', 'docenteNuevo'])));

        return $sustitucion;
    }

    /**
     * Ni el docente que la dicta ni los estudiantes que siguen en ella pueden
     * estar en otra sesión de la franja nueva (CrucesDeSesionService).
     */
    private function garantizarFranjaLibre(Solicitud $solicitud, Franja $nueva): void
    {
        $bloqueada = Solicitud::query()->whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();
        $docente = User::findOrFail($bloqueada->idDelDocenteQueDicta());
        /** @var list<int> $presentes */
        $presentes = $bloqueada->estudiantesPresentes()->pluck('users.id')->all();

        $this->cruces->bloquearPersonas([$docente->id, ...$presentes]);
        $this->cruces->garantizarDocenteLibre($docente, $nueva, $bloqueada);
        $this->cruces->garantizarEstudiantesLibres($presentes, $nueva, $bloqueada);
    }

    private function garantizarSesionVigente(Solicitud $solicitud): void
    {
        $vigente = $solicitud->estado === EstadoSolicitud::Aprobada
            && $solicitud->fecha->toDateString() >= now()->toDateString();

        if (! $vigente) {
            throw NovedadInvalida::sesionNoReprogramable();
        }
    }

    private function texto(string $valor, NovedadInvalida $siFalta): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            throw $siFalta;
        }

        return $valor;
    }

    /**
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, Solicitud $solicitud): void
    {
        if ($actor->cannot($accion, $solicitud)) {
            throw new AuthorizationException(sprintf('El usuario no tiene permiso para "%s".', $accion));
        }
    }
}
