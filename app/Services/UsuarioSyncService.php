<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Enums\Rol;
use App\Mail\SincronizacionDetenidaMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Copia de la base institucional a la propia (RF19, RF20).
 *
 * Lee las personas de la FuenteInstitucional y deja en `users` a quienes
 * pueden entrar: vigentes y de uno de los programas del laboratorio. Reglas
 * que no se pueden romper:
 *
 * - **No borra a nadie.** Quien deja de estar vigente o desaparece de la
 *   fuente queda inactivo (regla 8): sus solicitudes, evaluaciones y
 *   formatos siguen apuntando a él.
 * - **No toca las cuentas manuales** (origen "manual", RF22) ni la marca de
 *   deshabilitado que pone el ADMIN (D6).
 * - **Solo da roles permanentes**, según la vinculación, y nunca revoca: las
 *   filas con fecha de fin son del ADMIN (§3 del CLAUDE.md).
 * - **Se frena** si una pasada fuera a desactivar más del umbral
 *   configurado: no aplica nada y avisa al ADMIN.
 * - **No escribe en la fuente.**
 *
 * Las personas se reconocen por documento; si la cuenta se creó antes sin
 * documento, por el correo.
 */
final class UsuarioSyncService
{
    public function __construct(
        private readonly FuenteInstitucional $fuente,
        private readonly AsignacionDeRolService $roles,
    ) {}

    public function sincronizar(): ResultadoSincronizacion
    {
        $habilitadas = collect($this->fuente->personas())
            ->filter(fn (PersonaInstitucional $p): bool => $p->vigente && $this->esDeUnProgramaDelLaboratorio($p->programa))
            ->keyBy('documento');

        $sincronizadas = User::query()
            ->whereIn('origen', [OrigenUsuario::Matriculado, OrigenUsuario::Contratado])
            ->get();

        $porDesactivar = $sincronizadas->filter(
            static fn (User $u): bool => $u->estado === EstadoUsuario::Activo && ! $habilitadas->has((string) $u->documento),
        );
        $activas = $sincronizadas->where('estado', EstadoUsuario::Activo)->count();

        if ($this->superaElUmbral($porDesactivar->count(), $activas)) {
            return $this->detener($porDesactivar->count(), $activas);
        }

        return DB::transaction(function () use ($habilitadas, $sincronizadas, $porDesactivar): ResultadoSincronizacion {
            [$creadas, $actualizadas, $reactivadas] = $this->altasYCambios($habilitadas, $sincronizadas);

            User::query()->whereIn('id', $porDesactivar->modelKeys())->update([
                'estado' => EstadoUsuario::Inactivo,
                'ultima_sincronizacion' => now(),
            ]);

            return new ResultadoSincronizacion(
                creadas: $creadas,
                actualizadas: $actualizadas,
                desactivadas: $porDesactivar->count(),
                reactivadas: $reactivadas,
            );
        });
    }

    /**
     * @param  Collection<string, PersonaInstitucional>  $habilitadas
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $sincronizadas
     * @return array{0: int, 1: int, 2: int} creadas, actualizadas, reactivadas
     */
    private function altasYCambios(Collection $habilitadas, \Illuminate\Database\Eloquent\Collection $sincronizadas): array
    {
        $porDocumento = $sincronizadas->keyBy('documento');
        $creadas = $actualizadas = $reactivadas = 0;

        foreach ($habilitadas as $persona) {
            $cuenta = $porDocumento->get($persona->documento) ?? $this->cuentaPorCorreo($persona->email);

            // Una cuenta manual con el mismo correo es del ADMIN: no se toca.
            if ($cuenta !== null && $cuenta->origen === OrigenUsuario::Manual) {
                continue;
            }

            $atributos = [
                'nombre' => $persona->nombre,
                'email' => mb_strtolower($persona->email),
                'documento' => $persona->documento,
                'codigo_institucional' => $persona->codigo,
                'programa' => $persona->programa,
                'origen' => $persona->vinculacion,
                'estado' => EstadoUsuario::Activo,
                'ultima_sincronizacion' => now(),
            ];

            if ($cuenta === null) {
                $cuenta = User::create($atributos);
                $creadas++;
            } else {
                $estabaInactiva = $cuenta->estado === EstadoUsuario::Inactivo;
                $cuenta->update($atributos);
                $estabaInactiva ? $reactivadas++ : $actualizadas++;
            }

            $rol = $persona->vinculacion->rolPermanente();

            if ($rol instanceof Rol) {
                $this->roles->asignarPorSincronizacion($cuenta, $rol);
            }
        }

        return [$creadas, $actualizadas, $reactivadas];
    }

    private function cuentaPorCorreo(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    private function esDeUnProgramaDelLaboratorio(string $programa): bool
    {
        $normalizar = static fn (string $texto): string => Str::of($texto)->ascii()->lower()->squish()->toString();
        $programas = array_map($normalizar, (array) config('laboratorio.sincronizacion.programas'));

        return in_array($normalizar($programa), $programas, true);
    }

    private function superaElUmbral(int $porDesactivar, int $activas): bool
    {
        if ($porDesactivar === 0 || $activas === 0) {
            return false;
        }

        return $porDesactivar * 100 > $activas * $this->umbral();
    }

    private function detener(int $porDesactivar, int $activas): ResultadoSincronizacion
    {
        foreach (User::query()->activos()->role(Rol::Admin->value)->get() as $admin) {
            Mail::to($admin->email)->queue(new SincronizacionDetenidaMail($porDesactivar, $activas, $this->umbral()));
        }

        return new ResultadoSincronizacion(
            detenida: true,
            motivoDeLaDetencion: sprintf(
                'iba a desactivar %d de %d cuentas activas, más del %d %% permitido.',
                $porDesactivar,
                $activas,
                $this->umbral(),
            ),
        );
    }

    private function umbral(): int
    {
        return (int) config('laboratorio.sincronizacion.umbral_desactivacion');
    }
}
