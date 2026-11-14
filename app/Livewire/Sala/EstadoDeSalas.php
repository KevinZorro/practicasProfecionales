<?php

declare(strict_types=1);

namespace App\Livewire\Sala;

use App\Enums\EstadoSala;
use App\Exceptions\EstadoDeSalaInvalido;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Livewire\Concerns\AvisaDelResultado;
use App\Models\Sala;
use App\Services\SalaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * El administrativo anota cómo está hoy cada sala (RF30): operativa, con
 * una novedad o fuera de servicio. Lo que anota aparece en la revisión de
 * las solicitudes y en el tablero de preparación.
 */
final class EstadoDeSalas extends Component
{
    use AutorizaEnCadaPeticion;
    use AvisaDelResultado;

    /** La sala cuyo estado se está cambiando. */
    public ?int $editando = null;

    public string $estado = '';

    public string $novedad = '';

    public ?string $errorDeRegla = null;

    public function editar(int $salaId): void
    {
        $sala = Sala::query()->activas()->findOrFail($salaId);
        $this->authorize('cambiarEstado', $sala);

        $this->editando = $sala->id;
        $this->estado = $sala->estado->value;
        $this->novedad = (string) $sala->novedad;
        $this->errorDeRegla = null;
        $this->resetValidation();
    }

    public function cancelar(): void
    {
        $this->reset('editando', 'estado', 'novedad', 'errorDeRegla');
        $this->resetValidation();
    }

    public function guardar(SalaService $salas): void
    {
        $sala = Sala::query()->activas()->findOrFail((int) $this->editando);
        $this->authorize('cambiarEstado', $sala);
        $this->validate([
            'estado' => ['required', Rule::enum(EstadoSala::class)],
            'novedad' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $salas->cambiarEstado($sala, EstadoSala::from($this->estado), $this->novedad, Auth::user());
        } catch (EstadoDeSalaInvalido $invalido) {
            $this->addError('novedad', $invalido->getMessage());

            return;
        }

        $this->cancelar();
        $this->avisar(sprintf('%s: %s.', $sala->nombre, mb_strtolower($sala->estado->etiqueta())));
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('cambiarEstado', Sala::class);
    }

    public function render(): mixed
    {
        // Pocas salas (una decena): sin paginar.
        return view('livewire.sala.estado-de-salas', [
            'salas' => Sala::query()->activas()->with('estadoActualizadoPor:id,nombre')->orderBy('nombre')->get(),
            'estados' => EstadoSala::cases(),
        ]);
    }
}
