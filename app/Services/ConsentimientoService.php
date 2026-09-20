<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoConsentimiento;
use App\Enums\Rol;
use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Consentimiento informado de prácticas (RF51-RF53).
 *
 * El consentimiento se entrega una vez por semestre y se renueva al
 * siguiente: el índice único sobre (estudiante_id, periodo_academico) lo
 * garantiza en la base, y aquí se decide a qué periodo pertenece cada
 * entrega.
 *
 * Los archivos firmados llevan datos personales, así que viven en el disco
 * privado y se sirven por ruta protegida con Policy, nunca por enlace
 * directo (RNF07).
 */
final class ConsentimientoService
{
    public const POR_PAGINA = 15;

    /**
     * Periodo académico vigente, en formato "2026-2".
     *
     * Es el único sitio donde se decide qué periodo corre. Cómo se calcula
     * no está definido con la institución, así que se deriva del calendario
     * y config/laboratorio.php deja una salida manual para las semanas de
     * transición entre semestres.
     */
    public function periodoVigente(): string
    {
        $fijado = config('laboratorio.periodo_academico.vigente');

        if (is_string($fijado) && $fijado !== '') {
            return $fijado;
        }

        $corte = (int) config('laboratorio.periodo_academico.mes_inicio_segundo_semestre');
        $hoy = now();

        return sprintf('%d-%d', $hoy->year, $hoy->month >= $corte ? 2 : 1);
    }

    /**
     * Bandeja de verificación (RF52): lo que espera a que un coordinador o
     * el ADMIN lo revise. Por defecto, los cargados del periodo vigente.
     *
     * @return LengthAwarePaginator<int, ConsentimientoEstudiante>
     */
    public function bandeja(
        ?EstadoConsentimiento $estado = EstadoConsentimiento::Cargado,
        ?string $periodo = null,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        return ConsentimientoEstudiante::query()
            ->when($estado instanceof EstadoConsentimiento, fn (Builder $c) => $c->where('estado', $estado))
            ->when($periodo !== null && $periodo !== '', fn (Builder $c) => $c->delPeriodo($periodo))
            ->with(['estudiante:id,nombre,email,codigo_institucional', 'plantilla:id,nombre,version'])
            ->orderBy('updated_at')
            ->paginate($porPagina);
    }

    /**
     * Quién tiene el consentimiento al día y quién no, en un periodo (RF52).
     *
     * Es la vista que deja saber a quién le falta antes de una práctica. Se
     * listan los estudiantes, no las entregas: quien todavía no ha entregado
     * nada no tiene fila en consentimientos_estudiante y es justo el que hay
     * que ver.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function estadoDeLosEstudiantes(
        ?string $periodo = null,
        ?bool $soloSinVigente = null,
        ?string $busqueda = null,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        $periodo ??= $this->periodoVigente();

        // La restricción de un with() recibe la relación, no un Builder.
        $delPeriodo = static fn (HasMany $relacion) => $relacion->delPeriodo($periodo);

        return User::query()
            ->role(Rol::Estudiante->value)
            ->with([
                'consentimientos' => $delPeriodo,
                'consentimientos.plantilla:id,nombre,version',
                'consentimientos.recibidoFisicoPor:id,nombre',
            ])
            ->when($soloSinVigente === true, fn (Builder $c) => $c->whereDoesntHave(
                'consentimientos',
                static fn (Builder $e) => $e->delPeriodo($periodo)->verificados(),
            ))
            ->when($soloSinVigente === false, fn (Builder $c) => $c->whereHas(
                'consentimientos',
                static fn (Builder $e) => $e->delPeriodo($periodo)->verificados(),
            ))
            ->when(
                is_string($busqueda) && trim($busqueda) !== '',
                fn (Builder $c) => $c->where(static function (Builder $o) use ($busqueda): void {
                    $aguja = '%'.mb_strtolower(trim((string) $busqueda)).'%';
                    $o->whereRaw('LOWER(nombre) LIKE ?', [$aguja])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$aguja]);
                }),
            )
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    /**
     * Historial de plantillas, la vigente primero (RF51).
     *
     * @return LengthAwarePaginator<int, ConsentimientoPlantilla>
     */
    public function plantillas(int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return ConsentimientoPlantilla::query()
            ->with('subidoPor:id,nombre')
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    /**
     * El ADMIN sube la plantilla en blanco (RF51). Solo una queda activa:
     * los estudiantes deben firmar siempre la vigente.
     */
    public function cargarPlantilla(
        User $admin,
        UploadedFile $archivo,
        string $nombre,
        string $version,
    ): ConsentimientoPlantilla {
        $this->garantizarPermiso($admin, 'create', ConsentimientoPlantilla::class);
        $this->garantizarPdf($archivo);

        $atributos = $this->atributosDePlantilla($admin, $archivo, $nombre, $version);

        return DB::transaction(static function () use ($atributos): ConsentimientoPlantilla {
            ConsentimientoPlantilla::activas()->update(['activo' => false]);

            return ConsentimientoPlantilla::create($atributos);
        });
    }

    /**
     * El estudiante sube el documento firmado. Si ya había entregado en este
     * periodo, se reemplaza: el índice único impide un segundo registro.
     */
    public function registrarEntrega(User $estudiante, UploadedFile $archivo): ConsentimientoEstudiante
    {
        $this->garantizarPdf($archivo);
        $plantilla = $this->plantillaVigente();

        return DB::transaction(function () use ($estudiante, $archivo, $plantilla): ConsentimientoEstudiante {
            $entrega = $this->entregaDelPeriodo($estudiante) ?? new ConsentimientoEstudiante;
            $this->borrarArchivoFirmado($entrega);

            $entrega->fill($this->atributosDeEntrega($estudiante, $plantilla, $archivo));
            $entrega->save();

            return $entrega;
        });
    }

    /**
     * El estudiante entrega el formato firmado en físico, en la puerta
     * (RF53). La administrativa lo recibe, lo registra y lo deja entrar; el
     * escaneo llega después.
     *
     * No toca el estado: el estado es del documento escaneado, que sigue sin
     * llegar. Esto anota el hecho —cuándo y quién recibió el papel— al lado,
     * y es lo que habilita el ingreso hasta que suba el archivo.
     *
     * Si el estudiante todavía no tenía fila del periodo, se crea: quien
     * nunca entregó nada no tiene registro, y es justo el caso de la puerta.
     */
    public function registrarEntregaFisica(User $estudiante, User $administrativo): ConsentimientoEstudiante
    {
        $this->garantizarPermiso($administrativo, 'marcarEntregaFisica', ConsentimientoEstudiante::class);

        return DB::transaction(function () use ($estudiante, $administrativo): ConsentimientoEstudiante {
            $entrega = $this->entregaDelPeriodo($estudiante);

            if ($entrega instanceof ConsentimientoEstudiante && $entrega->entregadoEnFisico()) {
                throw ConsentimientoInvalido::yaSeRecibioEnFisico();
            }

            $entrega ??= new ConsentimientoEstudiante([
                'estudiante_id' => $estudiante->id,
                'plantilla_id' => $this->plantillaVigente()->id,
                'periodo_academico' => $this->periodoVigente(),
                'estado' => EstadoConsentimiento::Pendiente,
            ]);

            $entrega->fill([
                'recibido_fisico_at' => now(),
                'recibido_fisico_por' => $administrativo->id,
            ]);
            $entrega->save();

            return $entrega;
        });
    }

    /**
     * Solo coordinador o ADMIN (§6.1). El administrativo queda fuera: es la
     * única función operativa donde no acompaña al coordinador.
     */
    public function verificar(ConsentimientoEstudiante $entrega, User $verificador): ConsentimientoEstudiante
    {
        $this->garantizarPermiso($verificador, 'verificar', $entrega);
        $this->garantizarCargado($entrega);

        $entrega->update([
            'estado' => EstadoConsentimiento::Verificado,
            'motivo_rechazo' => null,
            'verificado_por' => $verificador->id,
            'verificado_at' => now(),
        ]);

        return $entrega;
    }

    /**
     * Devuelve la entrega a pendiente para que el estudiante vuelva a
     * subirla. El archivo rechazado se borra: son datos personales que ya no
     * hacen falta, y un registro pendiente no debe conservar documento.
     */
    public function rechazar(
        ConsentimientoEstudiante $entrega,
        User $verificador,
        ?string $motivo = null,
    ): ConsentimientoEstudiante {
        $this->garantizarPermiso($verificador, 'rechazar', $entrega);
        $this->garantizarCargado($entrega);
        $this->borrarArchivoFirmado($entrega);

        $entrega->update([
            'estado' => EstadoConsentimiento::Pendiente,
            'archivo_firmado_path' => null,
            'motivo_rechazo' => $motivo,
            'verificado_por' => null,
            'verificado_at' => null,
        ]);

        return $entrega;
    }

    /**
     * Verdadero solo si hay entrega verificada para el periodo que corre.
     * Lo entregado el semestre pasado no sirve para este (RF52).
     *
     * Una entrega en físico no cuenta aquí: habilita el ingreso
     * (puedeParticiparEnPracticas) pero deja el trámite sin cerrar, y esta
     * es la pregunta que dice qué falta por escanear y verificar.
     */
    public function tieneConsentimientoVigente(User $estudiante): bool
    {
        return ConsentimientoEstudiante::query()
            ->where('estudiante_id', $estudiante->id)
            ->delPeriodo($this->periodoVigente())
            ->where('estado', EstadoConsentimiento::Verificado)
            ->exists();
    }

    /**
     * Punto único de verificación del RF53: si un estudiante puede entrar a
     * prácticas.
     *
     * Dos caminos lo habilitan y no son el mismo: el documento verificado, o
     * el formato entregado en físico en la puerta mientras llega el escaneo.
     * Por eso no coincide con tieneConsentimientoVigente(), que sigue
     * respondiendo si el documento está verificado y es lo que persigue la
     * administrativa hasta cerrarlo.
     *
     * Se engancha donde se decida bloquear —al agregarlo a una evaluación,
     * al pasar lista, o en un middleware de las vistas de estudiante—, pero
     * la comprobación vive aquí y no se repite.
     *
     * PENDIENTE con el cliente: si esto debe impedir que un docente agregue
     * al estudiante a una evaluación o solo advertir. Hoy nadie lo llama:
     * EvaluacionService no lo consulta.
     */
    public function puedeParticiparEnPracticas(User $estudiante): bool
    {
        return ConsentimientoEstudiante::query()
            ->where('estudiante_id', $estudiante->id)
            ->delPeriodo($this->periodoVigente())
            ->queHabilitanPracticas()
            ->exists();
    }

    public function entregaDelPeriodo(User $estudiante, ?string $periodo = null): ?ConsentimientoEstudiante
    {
        return ConsentimientoEstudiante::query()
            ->where('estudiante_id', $estudiante->id)
            ->delPeriodo($periodo ?? $this->periodoVigente())
            ->first();
    }

    public function plantillaVigente(): ConsentimientoPlantilla
    {
        $plantilla = ConsentimientoPlantilla::activas()->latest('id')->first();

        if (! $plantilla instanceof ConsentimientoPlantilla) {
            throw ConsentimientoInvalido::sinPlantillaActiva();
        }

        return $plantilla;
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosDePlantilla(
        User $admin,
        UploadedFile $archivo,
        string $nombre,
        string $version,
    ): array {
        return [
            'nombre' => $nombre,
            'version' => $version,
            'archivo_path' => $this->guardar($archivo, 'directorio_plantillas'),
            'activo' => true,
            'subido_por' => $admin->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosDeEntrega(
        User $estudiante,
        ConsentimientoPlantilla $plantilla,
        UploadedFile $archivo,
    ): array {
        return [
            'estudiante_id' => $estudiante->id,
            'plantilla_id' => $plantilla->id,
            'periodo_academico' => $this->periodoVigente(),
            'archivo_firmado_path' => $this->guardar($archivo, 'directorio_firmados'),
            'estado' => EstadoConsentimiento::Cargado,
            'motivo_rechazo' => null,
            'verificado_por' => null,
            'verificado_at' => null,
        ];
    }

    private function guardar(UploadedFile $archivo, string $claveDirectorio): string
    {
        return $this->disco()->putFile(
            config("laboratorio.consentimiento.{$claveDirectorio}"),
            $archivo,
        );
    }

    private function borrarArchivoFirmado(ConsentimientoEstudiante $entrega): void
    {
        if (is_string($entrega->archivo_firmado_path)) {
            $this->disco()->delete($entrega->archivo_firmado_path);
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('laboratorio.consentimiento.disco'));
    }

    /**
     * La Policy decide; el Service la consulta para que la regla valga
     * también fuera de una petición HTTP (comandos, colas, seeders).
     *
     * @param  ConsentimientoEstudiante|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, ConsentimientoEstudiante|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException(
                sprintf('El usuario no tiene permiso para "%s" este consentimiento.', $accion),
            );
        }
    }

    /**
     * Se mira getMimeType(), no getClientMimeType().
     *
     * Por dos razones. La de corrección: los archivos temporales de Livewire
     * pierden la cabecera del cliente y devuelven "application/octet-stream",
     * así que con getClientMimeType() ninguna subida real pasaba. La de
     * seguridad: la cabecera del cliente la escribe quien sube el archivo,
     * mientras que getMimeType() sale del archivo guardado.
     */
    private function garantizarPdf(UploadedFile $archivo): void
    {
        if ($archivo->getMimeType() !== 'application/pdf') {
            throw ConsentimientoInvalido::soloSeAceptaPdf((string) $archivo->getMimeType());
        }

        $maximoKb = (int) config('laboratorio.consentimiento.tamano_maximo_kb');

        if ($archivo->getSize() > $maximoKb * 1024) {
            throw ConsentimientoInvalido::archivoDemasiadoGrande($maximoKb);
        }
    }

    private function garantizarCargado(ConsentimientoEstudiante $entrega): void
    {
        if ($entrega->estado !== EstadoConsentimiento::Cargado) {
            throw ConsentimientoInvalido::noEstaCargado($entrega->estado);
        }
    }
}
