<?php

declare(strict_types=1);

namespace App\Livewire\Usuario;

use App\Enums\Rol;
use App\Exceptions\AsignacionDeRolInvalida;
use App\Exceptions\UsuarioInvalido;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\AsignacionDeRol;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Services\UsuarioService;
use App\Support\RolActivo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Usuarios y roles: reparto de roles, con y sin vigencia (RF63, RF64), y
 * deshabilitar o volver a habilitar una cuenta (RF22). El alta y la edición
 * de los datos van en FormularioDeCuenta.
 *
 * Aquí no se comprueba ningún rol: lo deciden AsignacionDeRolPolicy y
 * UserPolicy.
 */
final class RolesDeUsuarios extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    /** Usuario cuyo formulario de asignación está abierto. */
    public ?int $asignandoA = null;

    public string $rolElegido = '';

    public string $hasta = '';

    public string $motivo = '';

    public ?string $errorDeRegla = null;

    /** Cuenta cuyo formulario para deshabilitar o habilitar está abierto. */
    public ?int $cambiandoAcceso = null;

    public string $motivoDeAcceso = '';

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function abrir(int $usuarioId): void
    {
        $this->authorize('asignar', AsignacionDeRol::class);

        if ($this->asignandoA === $usuarioId) {
            $this->cerrar();

            return;
        }

        $this->asignandoA = $usuarioId;
        $this->reset('rolElegido', 'hasta', 'motivo', 'errorDeRegla');
    }

    public function cerrar(): void
    {
        $this->reset('asignandoA', 'rolElegido', 'hasta', 'motivo', 'errorDeRegla');
    }

    public function asignar(AsignacionDeRolService $roles): void
    {
        $this->authorize('asignar', AsignacionDeRol::class);
        $this->validate([
            'rolElegido' => ['required', 'string'],
            'hasta' => ['nullable', 'date'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->errorDeRegla = null;

        $rol = Rol::tryFrom($this->rolElegido);

        if (! $rol instanceof Rol) {
            $this->errorDeRegla = 'Elige un rol de la lista.';

            return;
        }

        try {
            $usuario = User::findOrFail($this->asignandoA);
            $roles->asignar(
                Auth::user(),
                $usuario,
                $rol,
                $this->hasta !== '' ? $this->hasta : null,
                $this->motivo !== '' ? $this->motivo : null,
            );
            app(RolActivo::class)->olvidarAsignados($usuario);
        } catch (AsignacionDeRolInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->cerrar();
        session()->flash('estado', 'Rol asignado.');
    }

    public function revocar(int $usuarioId, string $rol, AsignacionDeRolService $roles): void
    {
        $this->authorize('revocar', AsignacionDeRol::class);
        $this->errorDeRegla = null;

        $elegido = Rol::tryFrom($rol);

        if (! $elegido instanceof Rol) {
            return;
        }

        try {
            $usuario = User::findOrFail($usuarioId);
            $roles->revocar(Auth::user(), $usuario, $elegido);
            app(RolActivo::class)->olvidarAsignados($usuario);
        } catch (AsignacionDeRolInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        session()->flash('estado', 'Rol revocado. Deja de tener efecto ahora mismo, no al final del día.');
    }

    public function abrirAcceso(int $usuarioId): void
    {
        $this->cambiandoAcceso = $this->cambiandoAcceso === $usuarioId ? null : $usuarioId;
        $this->reset('motivoDeAcceso', 'errorDeRegla');
    }

    public function deshabilitar(UsuarioService $usuarios): void
    {
        $this->cambiarAcceso(static fn (User $cuenta, string $motivo) => $usuarios->deshabilitar($cuenta, $motivo, Auth::user()), 'Cuenta deshabilitada. No podrá entrar hasta que se vuelva a habilitar.');
    }

    public function habilitar(UsuarioService $usuarios): void
    {
        $this->cambiarAcceso(static fn (User $cuenta, string $motivo) => $usuarios->habilitar($cuenta, $motivo, Auth::user()), 'Cuenta habilitada de nuevo.');
    }

    /** @param  callable(User, string): User  $accion */
    private function cambiarAcceso(callable $accion, string $mensaje): void
    {
        $this->validate(['motivoDeAcceso' => ['required', 'string', 'max:1000']]);
        $this->errorDeRegla = null;

        try {
            $accion(User::findOrFail($this->cambiandoAcceso), $this->motivoDeAcceso);
        } catch (UsuarioInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->reset('cambiandoAcceso', 'motivoDeAcceso');
        session()->flash('estado', $mensaje);
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', AsignacionDeRol::class);
    }

    public function render(AsignacionDeRolService $roles): mixed
    {
        return view('livewire.usuario.roles-de-usuarios', [
            'usuarios' => $roles->usuariosConSusRoles($this->busqueda),
            'proximosAVencer' => $roles->proximosAVencer(),
            'rolesAsignables' => Rol::cases(),
            'diasDeAviso' => AsignacionDeRolService::DIAS_DE_AVISO,
        ]);
    }
}
