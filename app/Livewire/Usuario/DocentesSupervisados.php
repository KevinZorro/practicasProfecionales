<?php

declare(strict_types=1);

namespace App\Livewire\Usuario;

use App\Exceptions\SupervisionInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Livewire\Concerns\AvisaDelResultado;
use App\Models\Supervision;
use App\Models\User;
use App\Services\SupervisionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Qué docentes tiene a su cargo un docente supervisor (RF77). Lo reparte
 * solo el ADMIN, desde /panel/usuarios: no es una pantalla de Filament
 * porque asignar y retirar deja rastro en la bitácora.
 *
 * Aquí no se comprueba ningún rol: lo decide SupervisionPolicy.
 */
final class DocentesSupervisados extends Component
{
    use AutorizaEnCadaPeticion;
    use AvisaDelResultado;

    public User $supervisor;

    public string $busqueda = '';

    public ?string $errorDeRegla = null;

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('gestionar', Supervision::class);
    }

    public function asignar(int $docenteId, SupervisionService $supervision): void
    {
        $this->authorize('gestionar', Supervision::class);
        $this->errorDeRegla = null;

        try {
            $supervision->asignarDocente(Auth::user(), $this->supervisor, User::findOrFail($docenteId));
        } catch (SupervisionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->reset('busqueda');
        $this->avisar('Docente asignado.');
    }

    public function retirar(int $docenteId, SupervisionService $supervision): void
    {
        $this->authorize('gestionar', Supervision::class);
        $this->errorDeRegla = null;

        try {
            $supervision->retirarDocente(Auth::user(), $this->supervisor, User::findOrFail($docenteId));
        } catch (SupervisionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->avisar('Docente retirado. Ya no ve sus evaluaciones.');
    }

    public function render(SupervisionService $supervision): mixed
    {
        return view('livewire.usuario.docentes-supervisados', [
            'docentes' => $supervision->docentesDe($this->supervisor),
            'candidatos' => $supervision->candidatosPara($this->supervisor, $this->busqueda),
        ]);
    }
}
