<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Enums\Rol;
use App\Enums\TipoSesion;
use App\Models\AsignacionDeRol;
use App\Models\Bloqueo;
use App\Models\CasoClinico;
use App\Models\Evaluacion;
use App\Models\FormatoConfidencialidad;
use App\Models\Materia;
use App\Models\Preparacion;
use App\Models\RegistroDeBitacora;
use App\Models\Reprogramacion;
use App\Models\Sala;
use App\Models\Solicitud;
use App\Models\Sustitucion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un juego pequeño de datos para probar a mano, en el sandbox, el flujo de
 * una solicitud y cada correo que dispara (demo:preparar). No es el
 * DatosPruebaSeeder: aquel llena una base vacía de desarrollo; este convive
 * con datos reales, así que crea lo justo y sabe borrar solo lo suyo.
 *
 * Todo lo de la demostración se reconoce por una marca que un dato real no
 * lleva: el código empieza por DEMO- (cuentas, materias y salas) y el
 * nombre del caso clínico por [Demo]. Las cuentas son de origen manual, así
 * que la sincronización institucional no las toca.
 *
 * Las solicitudes se crean con los mismos Services que usa la pantalla, para
 * que queden exactamente como las dejaría una persona. Ninguno de esos pasos
 * manda correo: los correos salen cuando alguien sigue el flujo con las
 * cuentas de la demostración.
 */
final class DemostracionService
{
    public const PREFIJO_CODIGO = 'DEMO-';

    public const ETIQUETA = '[Demo]';

    private const GRUPO = 'A';

    /** clave del alias => [código, nombre, rol] */
    private const CUENTAS = [
        'docente' => ['DEMO-DOC1', 'Docente titular', Rol::Docente],
        'docente2' => ['DEMO-DOC2', 'Docente de reemplazo', Rol::Docente],
        'admvo' => ['DEMO-ADM1', 'Administrativo', Rol::Administrativo],
        'coord' => ['DEMO-COO1', 'Coordinación', Rol::Coordinador],
    ];

    private const ESTUDIANTES = 4;

    /** código => nombre */
    private const MATERIAS = [
        'DEMO-MAT1' => 'Cuidado materno perinatal',
        'DEMO-MAT2' => 'Urgencias y trauma',
    ];

    /** nombre => código de la materia */
    private const CASOS = [
        'Atención de parto' => 'DEMO-MAT1',
        'Reanimación neonatal' => 'DEMO-MAT1',
        'Paro cardiorrespiratorio' => 'DEMO-MAT2',
        'Herida por arma de fuego' => 'DEMO-MAT2',
    ];

    private const CAPACIDAD_DE_LOS_CASOS = 10;

    private const SALAS = ['DEMO-S1' => '01', 'DEMO-S2' => '02'];

    private const CAPACIDAD_DE_LAS_SALAS = 12;

    /** Llave foránea violada (PostgreSQL): el registro lo usa un dato real. */
    private const LLAVE_FORANEA_VIOLADA = '23503';

    public function __construct(
        private readonly SolicitudService $solicitudes,
        private readonly RegistroPrevioService $registroPrevio,
        private readonly AsignacionDeRolService $roles,
    ) {}

    /**
     * Deja la demostración como recién hecha: borra la anterior, salvo las
     * cuentas, que se reutilizan con contraseña nueva, y vuelve a crearlo
     * todo. Repetirlo da el mismo resultado.
     *
     * @param  string  $correo  el buzón real; las cuentas son alias suyos (usuario+docente@dominio)
     */
    public function preparar(string $correo): DemostracionPreparada
    {
        return DB::transaction(function () use ($correo): DemostracionPreparada {
            $this->borrarTodoMenosLasCuentas();

            $cuentas = [];
            $usuarios = [];

            foreach (self::CUENTAS as $clave => [$codigo, $nombre, $rol]) {
                $contrasena = Str::password(16, symbols: false);
                $usuarios[$clave] = $this->cuenta($codigo, $nombre, $this->alias($correo, $clave), $rol, $contrasena);
                $cuentas[$clave] = new CuentaDeDemostracion($rol, $usuarios[$clave]->nombre, $usuarios[$clave]->email, $contrasena);
            }

            $estudiantes = collect(range(1, self::ESTUDIANTES))->map(fn (int $numero): User => $this->cuenta(
                sprintf('%sEST%d', self::PREFIJO_CODIGO, $numero),
                'Estudiante '.$numero,
                $this->alias($correo, 'est'.$numero),
                Rol::Estudiante,
                null,
            ));

            $materias = $this->crearMaterias();
            $casos = $this->crearCasos($materias);
            $this->crearSalas();

            $solicitudes = $this->crearSolicitudes($usuarios, $estudiantes, $materias, $casos);

            foreach ($solicitudes as $solicitud) {
                $solicitud->load('casoClinico');
            }

            return new DemostracionPreparada($cuentas, $solicitudes);
        });
    }

    /**
     * Borra todo lo de la demostración, cuentas incluidas, y nada más. Una
     * cuenta o una sala que ya quedó atada a un dato real (por ejemplo, la
     * cuenta de coordinación de la demo aprobó una solicitud real) no se
     * puede borrar sin romper ese dato: la cuenta queda deshabilitada y la
     * sala inactiva, y se informa.
     */
    public function limpiar(): DemostracionLimpia
    {
        return DB::transaction(function (): DemostracionLimpia {
            $borrado = $this->borrarTodoMenosLasCuentas();
            $cuentasBorradas = 0;
            $cuentasDeshabilitadas = [];

            foreach ($this->usuariosDeLaDemostracion()->get() as $usuario) {
                if ($this->intentarBorrar(fn () => $this->borrarCuenta($usuario))) {
                    $cuentasBorradas++;

                    continue;
                }

                $usuario->forceFill([
                    'deshabilitado_at' => now(),
                    'motivo_deshabilitacion' => 'Cuenta de demostración que quedó en datos reales (demo:preparar --limpiar).',
                ])->save();
                $cuentasDeshabilitadas[] = $usuario->email;
            }

            return new DemostracionLimpia(
                solicitudes: $borrado['solicitudes'],
                cuentasBorradas: $cuentasBorradas,
                cuentasDeshabilitadas: $cuentasDeshabilitadas,
                salasDesactivadas: $borrado['salasDesactivadas'],
            );
        });
    }

    /**
     * usuario@dominio → usuario+clave@dominio. Gmail y Google Workspace
     * entregan los alias en el mismo buzón. Si el correo ya trae un alias,
     * se cambia por el de la demostración.
     */
    public function alias(string $correo, string $clave): string
    {
        [$usuario, $dominio] = explode('@', mb_strtolower(trim($correo)), 2);

        return sprintf('%s+%s@%s', Str::before($usuario, '+'), $clave, $dominio);
    }

    /** @return Builder<User> */
    public function usuariosDeLaDemostracion(): Builder
    {
        return User::query()
            ->where('codigo_institucional', 'like', self::PREFIJO_CODIGO.'%')
            ->where('origen', OrigenUsuario::Manual);
    }

    private function cuenta(string $codigo, string $nombre, string $email, Rol $rol, ?string $contrasena): User
    {
        $usuario = User::query()->where('codigo_institucional', $codigo)->first() ?? new User;

        $usuario->fill([
            'nombre' => self::ETIQUETA.' '.$nombre,
            'email' => $email,
            'codigo_institucional' => $codigo,
            'estado' => EstadoUsuario::Activo,
            'origen' => OrigenUsuario::Manual,
        ]);

        // La contraseña se escribe aquí y no con UsuarioService::fijarContrasena,
        // que además avisa por correo del cambio: en la demostración serían
        // cuatro avisos de "tu contraseña cambió" cada vez que se prepara.
        $usuario->forceFill([
            'password' => $contrasena,
            'remember_token' => null,
            'email_verified_at' => now(),
            'deshabilitado_at' => null,
            'deshabilitado_por' => null,
            'motivo_deshabilitacion' => null,
        ])->save();

        $this->roles->asignarParaLaDemostracion($usuario, $rol);

        return $usuario;
    }

    /** @return Collection<string, Materia> */
    private function crearMaterias(): Collection
    {
        return collect(self::MATERIAS)->map(fn (string $nombre, string $codigo): Materia => Materia::create([
            'codigo' => $codigo,
            'nombre' => self::ETIQUETA.' '.$nombre,
            'semestre' => 1,
            'activo' => true,
        ]));
    }

    /**
     * Sin inventario a propósito: así no compromete unidades reales del
     * laboratorio ni hace falta justificar faltantes al aprobar. Y fuera de
     * la portada pública.
     *
     * @param  Collection<string, Materia>  $materias
     * @return Collection<string, CasoClinico>
     */
    private function crearCasos(Collection $materias): Collection
    {
        return collect(self::CASOS)->map(function (string $materia, string $nombre) use ($materias): CasoClinico {
            $caso = CasoClinico::create([
                'nombre' => self::ETIQUETA.' '.$nombre,
                'descripcion' => 'Escenario de demostración para probar el flujo de solicitudes.',
                'capacidad_maxima_estudiantes' => self::CAPACIDAD_DE_LOS_CASOS,
                'visible_publico' => false,
                'activo' => true,
            ]);
            $caso->materias()->attach($materias[$materia]->id);

            return $caso;
        });
    }

    private function crearSalas(): void
    {
        foreach (self::SALAS as $codigo => $numero) {
            Sala::create([
                'nombre' => self::ETIQUETA.' Sala '.$numero,
                'codigo' => $codigo,
                'capacidad' => self::CAPACIDAD_DE_LAS_SALAS,
                'activo' => true,
                'bloque' => 'DEMO',
                'piso' => '0',
                'numero' => $numero,
            ]);
        }
    }

    /**
     * Una solicitud por cada camino del flujo, en días y horas distintos
     * para que ninguna se cruce con otra (P9).
     *
     * @param  array<string, User>  $usuarios
     * @param  Collection<int, User>  $estudiantes
     * @param  Collection<string, Materia>  $materias
     * @param  Collection<string, CasoClinico>  $casos
     * @return array<string, Solicitud>
     */
    private function crearSolicitudes(array $usuarios, Collection $estudiantes, Collection $materias, Collection $casos): array
    {
        $docente = $usuarios['docente'];
        $primeros = $estudiantes->take(2)->pluck('id')->all();
        $ultimos = $estudiantes->skip(2)->pluck('id')->values()->all();

        $nueva = fn (string $materia, string $caso, int $dias, string $desde, string $hasta, array $grupo): DatosNuevaSolicitud => new DatosNuevaSolicitud(
            materiaId: $materias[$materia]->id,
            casoClinicoId: $casos[$caso]->id,
            tipo: TipoSesion::Practica,
            fecha: $this->enDias($dias),
            horaInicio: $desde,
            horaFin: $hasta,
            grupo: self::GRUPO,
            estudianteIds: $grupo,
        );

        $apartada = fn (string $materia, string $caso, int $dias, string $desde, string $hasta): DatosSesionApartada => new DatosSesionApartada(
            docenteId: $docente->id,
            materiaId: $materias[$materia]->id,
            casoClinicoId: $casos[$caso]->id,
            tipo: TipoSesion::Practica,
            fecha: $this->enDias($dias),
            horaInicio: $desde,
            horaFin: $hasta,
            cantidadEstudiantes: count($ultimos),
            grupo: self::GRUPO,
        );

        $revisada = $this->solicitudes->crear($docente, $nueva('DEMO-MAT2', 'Paro cardiorrespiratorio', 7, '10:00', '12:00', $primeros));
        $this->solicitudes->marcarRevisada($revisada, $usuarios['admvo']);

        return [
            'para_aprobar' => $this->solicitudes->crear($docente, $nueva('DEMO-MAT1', 'Atención de parto', 5, '08:00', '10:00', $primeros)),
            'para_rechazar' => $this->solicitudes->crear($docente, $nueva('DEMO-MAT2', 'Herida por arma de fuego', 6, '08:00', '10:00', $ultimos)),
            'revisada' => $revisada->refresh(),
            // Aprobada y sin sala: nace así por el registro previo (RF57).
            // Lejos del aviso del RF60 para que no se mezcle con la siguiente.
            'aprobada' => $this->registroPrevio->registrar($apartada('DEMO-MAT1', 'Reanimación neonatal', 9, '14:00', '16:00'), $usuarios['admvo']),
            // Aprobada, próxima y sin formato intramural: entra en el aviso.
            'sin_formato_intramural' => $this->registroPrevio->registrar($apartada('DEMO-MAT2', 'Herida por arma de fuego', 2, '14:00', '16:00'), $usuarios['admvo']),
        ];
    }

    private function enDias(int $dias): string
    {
        return CarbonImmutable::today()->addDays($dias)->toDateString();
    }

    /**
     * @return array{solicitudes: int, salasDesactivadas: list<string>}
     */
    private function borrarTodoMenosLasCuentas(): array
    {
        $usuarioIds = $this->usuariosDeLaDemostracion()->pluck('id')->all();
        $casoIds = CasoClinico::query()->where('nombre', 'like', self::ETIQUETA.'%')->pluck('id')->all();
        $materiaIds = Materia::query()->where('codigo', 'like', self::PREFIJO_CODIGO.'%')->pluck('id')->all();
        $salas = Sala::query()->where('codigo', 'like', self::PREFIJO_CODIGO.'%')->get();

        $solicitudIds = Solicitud::query()
            ->where(static function ($consulta) use ($usuarioIds, $casoIds, $materiaIds): void {
                $consulta->whereIn('docente_id', $usuarioIds)
                    ->orWhereIn('docente_que_dicta_id', $usuarioIds)
                    ->orWhereIn('caso_clinico_id', $casoIds)
                    ->orWhereIn('materia_id', $materiaIds);
            })
            ->pluck('id')
            ->all();

        $this->borrarBitacoraDe(Solicitud::class, $solicitudIds);
        $this->borrarBitacoraDe(Preparacion::class, Preparacion::query()->whereIn('solicitud_id', $solicitudIds)->pluck('id')->all());
        $evaluacionIds = Evaluacion::query()->whereIn('solicitud_id', $solicitudIds)->pluck('id')->all();
        $this->borrarBitacoraDe(Evaluacion::class, $evaluacionIds);
        $this->borrarBitacoraDe(CasoClinico::class, $casoIds);
        $this->borrarBitacoraDe(Materia::class, $materiaIds);
        $this->borrarBitacoraDe(Sala::class, $salas->modelKeys());

        Evaluacion::query()->whereKey($evaluacionIds)->delete();
        Reprogramacion::query()->whereIn('solicitud_id', $solicitudIds)->delete();
        Sustitucion::query()->whereIn('solicitud_id', $solicitudIds)->delete();
        // Preparaciones, salas de la preparación, insumos y estudiantes de la
        // sesión se van en cascada.
        Solicitud::query()->whereKey($solicitudIds)->delete();
        CasoClinico::query()->whereKey($casoIds)->delete();
        Materia::query()->whereKey($materiaIds)->delete();

        $salasDesactivadas = [];

        foreach ($salas as $sala) {
            $borrada = $this->intentarBorrar(static function () use ($sala): void {
                DB::table('ubicaciones_sala')->where('sala_id', $sala->id)->delete();
                $sala->delete();
            });

            if (! $borrada) {
                $sala->update(['activo' => false]);
                $salasDesactivadas[] = $sala->codigo;
            }
        }

        return ['solicitudes' => count($solicitudIds), 'salasDesactivadas' => $salasDesactivadas];
    }

    /**
     * Lo que la cuenta dejó y es solo suyo. Lo que hizo sobre datos reales
     * (una entrada de la bitácora sobre otra persona, una solicitud real
     * que revisó) no se borra: hace fallar el borrado y la cuenta se
     * deshabilita.
     */
    private function borrarCuenta(User $usuario): void
    {
        $formatos = FormatoConfidencialidad::query()->where('firmante_id', $usuario->id)->get();
        $this->borrarBitacoraDe(FormatoConfidencialidad::class, $formatos->modelKeys());
        $this->borrarBitacoraDe(User::class, [$usuario->id]);
        // Las suyas sin sujeto: accesos negados y exportaciones.
        RegistroDeBitacora::query()->where('user_id', $usuario->id)->whereNull('auditable_id')->delete();

        $disco = Storage::disk((string) config('laboratorio.confidencialidad.disco'));
        $archivos = $formatos->pluck('archivo_firmado_path')->filter()->all();
        FormatoConfidencialidad::query()->whereKey($formatos->modelKeys())->delete();
        Bloqueo::query()->where('user_id', $usuario->id)->delete();
        AsignacionDeRol::query()->where('user_id', $usuario->id)->delete();
        DB::table((string) config('permission.table_names.model_has_roles'))
            ->where('model_type', $usuario->getMorphClass())
            ->where((string) config('permission.column_names.model_morph_key'), $usuario->id)
            ->delete();
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();

        $usuario->delete();

        // Los archivos, solo después de confirmar: si algo revierte la
        // transacción, el formato sigue apuntando a su archivo.
        DB::afterCommit(static fn () => $disco->delete($archivos));
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<int>  $ids
     */
    private function borrarBitacoraDe(string $modelo, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        RegistroDeBitacora::query()
            ->where('auditable_type', (new $modelo)->getMorphClass())
            ->whereIn('auditable_id', $ids)
            ->delete();
    }

    /**
     * Borra dentro de un punto de guardado: si un dato real lo impide, se
     * deshace solo ese intento y la limpieza sigue.
     */
    private function intentarBorrar(callable $borrar): bool
    {
        try {
            DB::transaction(static function () use ($borrar): void {
                $borrar();
            });
        } catch (QueryException $error) {
            if ($error->getCode() !== self::LLAVE_FORANEA_VIOLADA) {
                throw $error;
            }

            return false;
        }

        return true;
    }
}
