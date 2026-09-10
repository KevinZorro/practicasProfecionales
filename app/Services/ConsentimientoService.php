<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoConsentimiento;
use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
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
        return $this->tieneConsentimientoVigente($estudiante);
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

    private function garantizarPdf(UploadedFile $archivo): void
    {
        if ($archivo->getClientMimeType() !== 'application/pdf') {
            throw ConsentimientoInvalido::soloSeAceptaPdf($archivo->getClientMimeType());
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
