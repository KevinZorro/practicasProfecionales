<?php

declare(strict_types=1);

namespace App\Livewire\Usuario;

use App\Enums\Rol;
use App\Exceptions\AsignacionDeRolInvalida;
use App\Models\AsignacionDeRol;
use App\Models\User;
use App\Services\AsignacionDeRolService;
use App\Support\RolActivo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reparto de roles, con y sin vigencia (RF63, RF64).
 *
 * Es lo mínimo para asignar, revocar y ver quién tiene qué; la gestión
 * completa de usuarios es el RF22 y no va aquí, porque los usuarios van a
 * llegar de la sincronización institucional.
 *
 * Aquí no se comprueba ningún rol: lo decide AsignacionDeRolPolicy.
 */
final class RolesDeUsuarios extends Component
{
    use WithPagination;

    #[Url(as: 'buscar', keep: false)]
    public string $busqueda = '';

    /** Usuario cuyo formulario de asignación está abierto. */
    public ?int $asignandoA = null;

    public string $rolElegido = '';

    public string $hasta = '';

    public string $motivo = '';

    public ?string $errorDeRegla = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AsignacionDeRol::class);
    }

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
