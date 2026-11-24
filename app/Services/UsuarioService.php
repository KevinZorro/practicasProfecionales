<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccionAuditada;
use App\Enums\EstadoUsuario;
use App\Enums\OrigenUsuario;
use App\Exceptions\UsuarioInvalido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas que gestiona el ADMIN a mano (RF22).
 *
 * La mayoría de cuentas las crea y actualiza la sincronización
 * institucional (RF20). Las que se crean aquí quedan con origen "manual" y
 * la sincronización no las toca: un pasante externo o personal que no
 * figura en la vista institucional.
 *
 * Deshabilitar no es cambiar "estado": esa columna es la vigencia
 * institucional y solo la escribe la sincronización (regla 8). Es una marca
 * aparte que la sincronización respeta (D6).
 */
final class UsuarioService
{
    public function __construct(
        private readonly BitacoraService $bitacora,
        private readonly AsignacionDeRolService $roles,
    ) {}

    public function crear(DatosUsuario $datos, User $actor): User
    {
        $this->garantizarPermiso($actor, 'create', User::class);

        return $this->crearCuentaManual($datos);
    }

    /**
     * El ADMIN que nombra quien tiene la consola del servidor, con
     * usuarios:crear-administrador: el primero de una instalación, que nace
     * sin cuentas, o uno nuevo si el último dejó la universidad.
     *
     * Si el correo ya tiene cuenta —la trajo la sincronización, o es de una
     * docente—, la usa tal como está: no la habilita ni le cambia los datos,
     * que son del ADMIN y de la sincronización. Si no, la crea con origen
     * "manual". Repetirlo no cambia nada, porque el instalador lo corre en
     * cada pasada.
     *
     * @throws UsuarioInvalido si hay que crear la cuenta y falta el nombre
     */
    public function nombrarAdministradorDesdeLaConsola(string $email, ?string $nombre): AdministradorNombrado
    {
        return DB::transaction(function () use ($email, $nombre): AdministradorNombrado {
            $cuenta = $this->cuentaConElCorreo($email);
            $creada = $cuenta === null;

            if ($cuenta === null) {
                if ($this->opcional($nombre) === null) {
                    throw UsuarioInvalido::faltaElNombre($email);
                }

                $cuenta = $this->crearCuentaManual(new DatosUsuario((string) $nombre, $email));
            }

            $asignacion = $this->roles->asignarAdministradorDesdeLaConsola($cuenta);

            return new AdministradorNombrado($cuenta, $creada, $asignacion !== null);
        });
    }

    /**
     * Fija o cambia la contraseña de una cuenta desde la consola del
     * servidor (usuarios:contrasena, ADR 0011). Sin persona que actúe, igual
     * que nombrar al ADMIN desde la consola: no pasa por Policy ni bitácora.
     * Cierra las sesiones con "recordarme" que tuviera abiertas.
     *
     * @throws UsuarioInvalido si no hay cuenta o la contraseña es corta
     */
    public function fijarContrasenaDesdeLaConsola(string $email, string $contrasena): User
    {
        $minimo = (int) config('laboratorio.acceso.longitud_minima_contrasena');

        if (mb_strlen($contrasena) < $minimo) {
            throw UsuarioInvalido::contrasenaCorta($minimo);
        }

        $cuenta = $this->cuentaConElCorreo($email) ?? throw UsuarioInvalido::sinCuentaParaElCorreo($email);

        $cuenta->forceFill([
            'password' => $contrasena,
            'remember_token' => null,
        ])->save();

        return $cuenta;
    }

    /**
     * Si la cuenta viene de la sincronización, sus datos los puede volver a
     * escribir la siguiente pasada: la fuente es la institución.
     */
    public function actualizar(User $cuenta, DatosUsuario $datos, User $actor): User
    {
        $this->garantizarPermiso($actor, 'update', $cuenta);
        $email = $this->garantizarCorreoLibre($datos->email, $cuenta);

        $cuenta->update([
            'nombre' => trim($datos->nombre),
            'email' => $email,
            'documento' => $this->opcional($datos->documento),
            'codigo_institucional' => $this->opcional($datos->codigoInstitucional),
            'programa' => $this->opcional($datos->programa),
        ]);

        return $cuenta;
    }

    public function deshabilitar(User $cuenta, string $motivo, User $actor): User
    {
        if ($actor->is($cuenta)) {
            throw UsuarioInvalido::noSeDeshabilitaASiMismo();
        }

        $this->garantizarPermiso($actor, 'deshabilitar', $cuenta);
        $motivo = $this->garantizarMotivo($motivo);

        if ($cuenta->estaDeshabilitado()) {
            throw UsuarioInvalido::yaEstaDeshabilitado();
        }

        return DB::transaction(function () use ($cuenta, $motivo, $actor): User {
            $cuenta->deshabilitado_at = now();
            $cuenta->deshabilitado_por = $actor->id;
            $cuenta->motivo_deshabilitacion = $motivo;
            $cuenta->save();

            $this->bitacora->registrar(AccionAuditada::UsuarioDeshabilitado, $actor, $cuenta, sprintf('Deshabilitó la cuenta de %s.', $cuenta->nombre), $motivo);

            return $cuenta;
        });
    }

    public function habilitar(User $cuenta, string $motivo, User $actor): User
    {
        $this->garantizarPermiso($actor, 'habilitar', $cuenta);
        $motivo = $this->garantizarMotivo($motivo);

        if (! $cuenta->estaDeshabilitado()) {
            throw UsuarioInvalido::noEstaDeshabilitado();
        }

        return DB::transaction(function () use ($cuenta, $motivo, $actor): User {
            $cuenta->deshabilitado_at = null;
            $cuenta->deshabilitado_por = null;
            $cuenta->motivo_deshabilitacion = null;
            $cuenta->save();

            $this->bitacora->registrar(AccionAuditada::UsuarioHabilitado, $actor, $cuenta, sprintf('Volvió a habilitar la cuenta de %s.', $cuenta->nombre), $motivo);

            return $cuenta;
        });
    }

    private function crearCuentaManual(DatosUsuario $datos): User
    {
        $email = $this->garantizarCorreoLibre($datos->email);

        return User::create([
            'nombre' => trim($datos->nombre),
            'email' => $email,
            'documento' => $this->opcional($datos->documento),
            'codigo_institucional' => $this->opcional($datos->codigoInstitucional),
            'programa' => $this->opcional($datos->programa),
            'estado' => EstadoUsuario::Activo,
            'origen' => OrigenUsuario::Manual,
        ]);
    }

    /** El correo es la llave con la que entra por Google: no se repite, sin distinguir mayúsculas. */
    private function garantizarCorreoLibre(string $email, ?User $salvo = null): string
    {
        $email = mb_strtolower(trim($email));

        $ocupado = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($salvo !== null, static fn ($c) => $c->whereKeyNot($salvo->id))
            ->exists();

        if ($ocupado) {
            throw UsuarioInvalido::correoRepetido($email);
        }

        return $email;
    }

    private function cuentaConElCorreo(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    private function garantizarMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw UsuarioInvalido::sinMotivo();
        }

        return $motivo;
    }

    private function opcional(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * @param  User|class-string  $sobre
     *
     * @throws AuthorizationException
     */
    private function garantizarPermiso(User $actor, string $accion, User|string $sobre): void
    {
        if ($actor->cannot($accion, $sobre)) {
            throw new AuthorizationException(sprintf('El usuario no tiene permiso para "%s" cuentas.', $accion));
        }
    }
}
