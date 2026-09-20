<?php

declare(strict_types=1);

namespace App\Livewire\CasoClinico;

use App\Models\CasoClinico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Capacidad máxima de estudiantes de cada escenario (RF74).
 *
 * Pantalla del ADMIN: el resto de la ficha del caso clínico todavía no tiene
 * gestión, así que aquí solo se edita la capacidad. Quién entra lo decide
 * CasoClinicoPolicy; este componente no comprueba ningún rol.
 */
final class CapacidadDeEscenarios extends Component
{
    use WithPagination;

    public string $busqueda = '';

    /** @var array<int, string> id del caso => capacidad tecleada */
    public array $capacidades = [];

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function guardar(int $casoId): void
    {
        $caso = CasoClinico::findOrFail($casoId);
        $this->authorize('update', $caso);

        $validado = $this->validateOnly(
            "capacidades.{$casoId}",
            ["capacidades.{$casoId}" => 'required|integer|min:1|max:200'],
            [
                "capacidades.{$casoId}.required" => 'Escribe cuántos estudiantes admite el escenario.',
                "capacidades.{$casoId}.integer" => 'La capacidad es un número entero de estudiantes.',
                "capacidades.{$casoId}.min" => 'Un escenario admite al menos un estudiante.',
                "capacidades.{$casoId}.max" => 'La capacidad no puede pasar de 200 estudiantes.',
            ],
        );

        $caso->update([
            'capacidad_maxima_estudiantes' => (int) data_get($validado, "capacidades.{$casoId}"),
        ]);

        session()->flash('estado', sprintf('Capacidad de "%s" guardada.', $caso->nombre));
    }

    public function render(): mixed
    {
        $casos = $this->casos();

        foreach ($casos as $caso) {
            // Lo ya tecleado manda: si el ADMIN escribió algo y falló la
            // validación, no se lo pisamos al repintar.
            $this->capacidades[$caso->id] ??= (string) ($caso->capacidad_maxima_estudiantes ?? '');
        }

        return view('livewire.caso-clinico.capacidad-de-escenarios', ['casos' => $casos]);
    }

    /** @return LengthAwarePaginator<int, CasoClinico> */
    private function casos(): LengthAwarePaginator
    {
        return CasoClinico::query()
            ->when($this->busqueda !== '', fn ($consulta) => $consulta->where('nombre', 'ilike', '%'.$this->busqueda.'%'))
            ->orderBy('nombre')
            ->paginate(15);
    }
}
