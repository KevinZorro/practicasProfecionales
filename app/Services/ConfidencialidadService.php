<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Enums\Rol;
use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Models\FormatoConfidencialidad;
use App\Models\PlantillaConfidencialidad;
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
 * Formato de confidencialidad de prácticas (RF51-RF53).
 *
 * Es el documento que el laboratorio rotula así en el Drive, e incluye la
 * autorización de captación de imágenes. Lo firma todo el que entra a la
 * práctica —estudiantes y docentes—, y por eso la columna se llama
 * "firmante_id" y no "estudiante_id".
 *
 * Se entrega una vez por semestre y se renueva al siguiente: el índice
 * único sobre (firmante_id, periodo_academico) lo garantiza en la base, y
 * aquí se decide a qué periodo pertenece cada entrega.
 *
 * Los archivos firmados llevan datos personales, así que viven en el disco
 * privado y se sirven por ruta protegida con Policy, nunca por enlace
 * directo (RNF07).
 */
final class ConfidencialidadService
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
     * @return LengthAwarePaginator<int, FormatoConfidencialidad>
     */
    public function bandeja(
        ?EstadoFormatoConfidencialidad $estado = EstadoFormatoConfidencialidad::Cargado,
        ?string $periodo = null,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        return FormatoConfidencialidad::query()
            ->when($estado instanceof EstadoFormatoConfidencialidad, fn (Builder $c) => $c->where('estado', $estado))
            ->when($periodo !== null && $periodo !== '', fn (Builder $c) => $c->delPeriodo($periodo))
            ->with(['firmante:id,nombre,email,codigo_institucional', 'firmante.roles:id,name', 'plantilla:id,nombre,version'])
            ->orderBy('updated_at')
            ->paginate($porPagina);
    }

    /**
     * Quién tiene el formato al día y quién no, en un periodo (RF52).
     *
     * Es la vista que deja saber a quién le falta antes de una práctica. Se
     * listan las personas, no las entregas: quien todavía no ha entregado
     * nada no tiene fila en formatos_confidencialidad y es justo el que hay
     * que ver.
     *
     * Lista a estudiantes y docentes juntos, porque el formato lo firma todo
     * el que entra a la práctica y quien revisa en la puerta los revisa en
     * la misma pasada.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function estadoDeLosFirmantes(
        ?string $periodo = null,
        ?bool $soloSinVigente = null,
        ?string $busqueda = null,
        int $porPagina = self::POR_PAGINA,
    ): LengthAwarePaginator {
        $periodo ??= $this->periodoVigente();

        // La restricción de un with() recibe la relación, no un Builder.
        $delPeriodo = static fn (HasMany $relacion) => $relacion->delPeriodo($periodo);

        return User::query()
            ->role(Rol::queFirmanElFormato())
            ->with([
                'formatosDeConfidencialidad' => $delPeriodo,
                'formatosDeConfidencialidad.plantilla:id,nombre,version',
                'formatosDeConfidencialidad.recibidoFisicoPor:id,nombre',
                'roles:id,name',
            ])
            ->when($soloSinVigente === true, fn (Builder $c) => $c->whereDoesntHave(
                'formatosDeConfidencialidad',
                static fn (Builder $e) => $e->delPeriodo($periodo)->verificados(),
            ))
            ->when($soloSinVigente === false, fn (Builder $c) => $c->whereHas(
                'formatosDeConfidencialidad',
                static fn (Builder $e) => $e->delPeriodo($periodo)->verificados(),
            ))
            ->when(
                is_string($busqueda) && trim($busqueda) !== '',
                fn (Builder $c) => $c->where(
                    fn (Builder $o) => $this->buscarPersona($o, (string) $busqueda),
                ),
            )
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    /**
     * Búsqueda por nombre, correo o código institucional (RF71).
     *
     * El código es como la administrativa identifica a alguien cuando el
     * nombre se repite o viene mal escrito en la lista impresa, así que
     * entra al mismo cuadro de búsqueda y no a un filtro aparte.
     *
     * @param  Builder<User>  $consulta
     */
    private function buscarPersona(Builder $consulta, string $busqueda): void
    {
        $aguja = '%'.mb_strtolower(trim($busqueda)).'%';

        $consulta->whereRaw('LOWER(nombre) LIKE ?', [$aguja])
            ->orWhereRaw('LOWER(email) LIKE ?', [$aguja])
            ->orWhereRaw('LOWER(codigo_institucional) LIKE ?', [$aguja]);
    }

    /**
     * Historial de plantillas, la vigente primero (RF51).
     *
     * @return LengthAwarePaginator<int, PlantillaConfidencialidad>
     */
    public function plantillas(int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return PlantillaConfidencialidad::query()
            ->with('subidoPor:id,nombre')
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    /**
     * El ADMIN sube la plantilla en blanco (RF51). Solo una queda activa:
     * todos deben firmar siempre la vigente.
     */
    public function cargarPlantilla(
        User $admin,
        UploadedFile $archivo,
        string $nombre,
        string $version,
    ): PlantillaConfidencialidad {
        $this->garantizarPermiso($admin, 'create', PlantillaConfidencialidad::class);
        $this->garantizarPdf($archivo);

        $atributos = $this->atributosDePlantilla($admin, $archivo, $nombre, $version);

        return DB::transaction(static function () use ($atributos): PlantillaConfidencialidad {
            PlantillaConfidencialidad::activas()->update(['activo' => false]);

            return PlantillaConfidencialidad::create($atributos);
        });
    }

    /**
     * Quien firma sube el documento escaneado. Si ya había entregado en este
     * periodo, se reemplaza: el índice único impide un segundo registro.
     */
    public function registrarEntrega(User $firmante, UploadedFile $archivo): FormatoConfidencialidad
    {
        $this->garantizarPdf($archivo);
        $plantilla = $this->plantillaVigente();

        return DB::transaction(function () use ($firmante, $archivo, $plantilla): FormatoConfidencialidad {
            $entrega = $this->entregaDelPeriodo($firmante) ?? new FormatoConfidencialidad;
            $this->borrarArchivoFirmado($entrega);

            $entrega->fill($this->atributosDeEntrega($firmante, $plantilla, $archivo));
            $entrega->save();

            return $entrega;
        });
    }

    /**
     * Quien firma entrega el formato en físico, en la puerta (RF53). La
     * administrativa lo recibe, lo registra y lo deja entrar; el escaneo
     * llega después.
     *
     * No toca el estado: el estado es del documento escaneado, que sigue sin
     * llegar. Esto anota el hecho —cuándo y quién recibió el papel— al lado,
     * y es lo que habilita el ingreso hasta que suba el archivo.
     *
     * Vale igual para un docente: llega a la misma puerta, con el mismo
     * papel y ante la misma persona, así que no hay nada que distinguir.
     *
     * Si todavía no tenía fila del periodo, se crea: quien nunca entregó
     * nada no tiene registro, y es justo el caso de la puerta.
     */
    public function registrarEntregaFisica(User $firmante, User $administrativo): FormatoConfidencialidad
    {
        $this->garantizarPermiso($administrativo, 'marcarEntregaFisica', FormatoConfidencialidad::class);

        return DB::transaction(function () use ($firmante, $administrativo): FormatoConfidencialidad {
            $entrega = $this->entregaDelPeriodo($firmante);

            if ($entrega instanceof FormatoConfidencialidad && $entrega->entregadoEnFisico()) {
                throw FormatoConfidencialidadInvalido::yaSeRecibioEnFisico();
            }

            $entrega ??= new FormatoConfidencialidad([
                'firmante_id' => $firmante->id,
                'plantilla_id' => $this->plantillaVigente()->id,
                'periodo_academico' => $this->periodoVigente(),
                'estado' => EstadoFormatoConfidencialidad::Pendiente,
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
     * Verificar el documento escaneado (RF52). Lo ejerce el administrativo,
     * que es quien recibe las entregas a diario; coordinación y ADMIN lo
     * conservan para supervisar. Quién exactamente lo decide la Policy.
     */
    public function verificar(FormatoConfidencialidad $entrega, User $verificador): FormatoConfidencialidad
    {
        $this->garantizarPermiso($verificador, 'verificar', $entrega);
        $this->garantizarCargado($entrega);

        $entrega->update([
            'estado' => EstadoFormatoConfidencialidad::Verificado,
            'motivo_rechazo' => null,
            'verificado_por' => $verificador->id,
            'verificado_at' => now(),
        ]);

        return $entrega;
    }

    /**
     * Devuelve la entrega a pendiente para que quien firma vuelva a
     * subirla. El archivo rechazado se borra: son datos personales que ya no
     * hacen falta, y un registro pendiente no debe conservar documento.
     */
    public function rechazar(
        FormatoConfidencialidad $entrega,
        User $verificador,
        ?string $motivo = null,
    ): FormatoConfidencialidad {
        $this->garantizarPermiso($verificador, 'rechazar', $entrega);
        $this->garantizarCargado($entrega);
        $this->borrarArchivoFirmado($entrega);

        $entrega->update([
            'estado' => EstadoFormatoConfidencialidad::Pendiente,
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
    public function tieneFormatoVigente(User $firmante): bool
    {
        return FormatoConfidencialidad::query()
            ->where('firmante_id', $firmante->id)
            ->delPeriodo($this->periodoVigente())
            ->where('estado', EstadoFormatoConfidencialidad::Verificado)
            ->exists();
    }

    /**
     * Punto único de verificación del RF53: si alguien puede entrar a
     * prácticas.
     *
     * Dos caminos lo habilitan y no son el mismo: el documento verificado, o
     * el formato entregado en físico en la puerta mientras llega el escaneo.
     * Por eso no coincide con tieneFormatoVigente(), que sigue
     * respondiendo si el documento está verificado y es lo que persigue la
     * administrativa hasta cerrarlo.
     *
     * Se engancha donde se decida bloquear —al agregarlo a una evaluación,
     * al pasar lista, o en un middleware de las vistas de estudiante—, pero
     * la comprobación vive aquí y no se repite.
     *
     * PENDIENTE con el cliente, dos cosas: si esto debe impedir que un
     * docente agregue al estudiante a una evaluación o solo advertir; y qué
     * significa para un docente sin el formato al día, porque bloquearlo es
     * cancelar la clase, no dejar a alguien fuera. Hoy nadie lo llama:
     * EvaluacionService no lo consulta.
     */
    public function puedeParticiparEnPracticas(User $firmante): bool
    {
        return FormatoConfidencialidad::query()
            ->where('firmante_id', $firmante->id)
            ->delPeriodo($this->periodoVigente())
            ->queHabilitanPracticas()
            ->exists();
    }

    public function entregaDelPeriodo(User $firmante, ?string $periodo = null): ?FormatoConfidencialidad
    {
        return FormatoConfidencialidad::query()
            ->where('firmante_id', $firmante->id)
            ->delPeriodo($periodo ?? $this->periodoVigente())
            ->first();
    }

    public function plantillaVigente(): PlantillaConfidencialidad
    {
        $plantilla = PlantillaConfidencialidad::activas()->latest('id')->first();

        if (! $plantilla instanceof PlantillaConfidencialidad) {
            throw FormatoConfidencialidadInvalido::sinPlantillaActiva();
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
        User $firmante,
        PlantillaConfidencialidad $plantilla,
        UploadedFile $archivo,
    ): array {
        return [
            'firmante_id' => $firmante->id,
            'plantilla_id' => $plantilla->id,
            'periodo_academico' => $this->periodoVigente(),
            'archivo_firmado_path' => $this->guardar($archivo, 'directorio_firmados'),
            'estado' => EstadoFormatoConfidencialidad::Cargado,
            'motivo_rechazo' => null,
            'verificado_por' => null,
            'verificado_at' => null,
        ];
    }

    private function guardar(UploadedFile $archivo, string $claveDirectorio): string
    {
        return $this->disco()->putFile(
            config("laboratorio.confidencialidad.{$claveDirectorio}"),
            $archivo,
        );
    }

    private function borrarArchivoFirmado(FormatoConfidencialidad $entrega): void
    {
        if (is_string($entrega->archivo_firmado_path)) {
            $this->disco()->delete($entrega->archivo_firmado_path);
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('laboratorio.confidencialidad.disco'));
    }

    /**
     * La Policy decide; el Service la consulta para que la regla valga
     * también fuera de una petición HTTP (comandos, colas, seeders).
     *
     * @param  FormatoConfidencialidad|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, FormatoConfidencialidad|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException(
                sprintf('El usuario no tiene permiso para "%s" este formato.', $accion),
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
            throw FormatoConfidencialidadInvalido::soloSeAceptaPdf((string) $archivo->getMimeType());
        }

        $maximoKb = (int) config('laboratorio.confidencialidad.tamano_maximo_kb');

        if ($archivo->getSize() > $maximoKb * 1024) {
            throw FormatoConfidencialidadInvalido::archivoDemasiadoGrande($maximoKb);
        }
    }

    private function garantizarCargado(FormatoConfidencialidad $entrega): void
    {
        if ($entrega->estado !== EstadoFormatoConfidencialidad::Cargado) {
            throw FormatoConfidencialidadInvalido::noEstaCargado($entrega->estado);
        }
    }
}
