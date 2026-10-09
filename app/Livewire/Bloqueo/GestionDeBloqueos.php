<?php

declare(strict_types=1);

namespace App\Livewire\Bloqueo;

use App\Exceptions\BloqueoInvalido;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\Bloqueo;
use App\Models\User;
use App\Services\BloqueoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Coordinación bloquea a un estudiante o docente y levanta el bloqueo, con
 * motivo en los dos casos (RF68).
 */
final class GestionDeBloqueos extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    /** A quién bloquear: búsqueda por nombre, correo o código. */
    public string $busquedaPersona = '';

    public ?int $personaElegida = null;

    public string $motivo = '';

    /** Filtro del listado. */
    #[Url(as: 'q', keep: false)]
    public string $busqueda = '';

    #[Url(as: 'historial', keep: false)]
    public bool $conHistorial = false;

    public ?int $levantando = null;

    public string $motivoLevantamiento = '';

    public ?string $errorDeRegla = null;

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'conHistorial'], true)) {
            $this->resetPage();
        }
    }

    public function elegir(int $personaId): void
    {
        $this->personaElegida = $personaId;
        $this->busquedaPersona = '';
    }

    public function bloquear(BloqueoService $bloqueos): void
    {
        $this->authorize('create', Bloqueo::class);
        $this->validate([
            'personaElegida' => 'required|integer|exists:users,id',
            'motivo' => 'required|string|max:1000',
        ]);

        if ($this->ejecutar(fn () => $bloqueos->bloquear(User::findOrFail($this->personaElegida), $this->motivo, Auth::user()), 'Bloqueo registrado. No podrá ingresar al laboratorio hasta que se levante.')) {
            $this->reset('personaElegida', 'motivo');
        }
    }

    public function pedirMotivo(int $bloqueoId): void
    {
        $this->levantando = $bloqueoId;
        $this->motivoLevantamiento = '';
    }

    public function levantar(int $bloqueoId, BloqueoService $bloqueos): void
    {
        $bloqueo = Bloqueo::findOrFail($bloqueoId);
        $this->authorize('levantar', $bloqueo);
        $this->validate(['motivoLevantamiento' => 'required|string|max:1000']);

        if ($this->ejecutar(fn () => $bloqueos->levantar($bloqueo, $this->motivoLevantamiento, Auth::user()), 'Bloqueo levantado.')) {
            $this->reset('levantando', 'motivoLevantamiento');
        }
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', Bloqueo::class);
    }

    public function render(BloqueoService $bloqueos): mixed
    {
        return view('livewire.bloqueo.gestion-de-bloqueos', [
            'candidatos' => $bloqueos->bloqueables($this->busquedaPersona),
            'elegida' => $this->personaElegida === null ? null : User::find($this->personaElegida),
            'bloqueos' => $bloqueos->listado($this->busqueda, soloVigentes: ! $this->conHistorial),
        ]);
    }

    /** Corre la acción y traduce la regla rota a un mensaje en pantalla. */
    private function ejecutar(callable $accion, string $mensaje): bool
    {
        $this->errorDeRegla = null;

        try {
            $accion();
        } catch (BloqueoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return false;
        }

        session()->flash('estado', $mensaje);

        return true;
    }
}
