<?php

declare(strict_types=1);

namespace App\Livewire\PeriodoAcademico;

use App\Exceptions\PeriodoAcademicoInvalido;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\PeriodoAcademico;
use App\Services\PeriodoAcademicoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Abrir, cerrar y, si hubo un error, reabrir el periodo académico (RF75).
 */
final class GestionDePeriodos extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    #[Validate('required|string|max:20')]
    public string $nombre = '';

    public ?string $errorDeRegla = null;

    public function abrir(PeriodoAcademicoService $periodos): void
    {
        $this->authorize('create', PeriodoAcademico::class);
        $this->validate();

        if ($this->ejecutar(fn () => $periodos->abrir($this->nombre, Auth::user()), 'Periodo abierto. Desde ahora las entregas del formato cuentan para él.')) {
            $this->reset('nombre');
        }
    }

    public function cerrar(int $periodoId, PeriodoAcademicoService $periodos): void
    {
        $periodo = PeriodoAcademico::findOrFail($periodoId);
        $this->authorize('cerrar', $periodo);

        $this->ejecutar(fn () => $periodos->cerrar($periodo, Auth::user()), 'Periodo cerrado. Sigue valiendo hasta que se abra el siguiente.');
    }

    public function reabrir(int $periodoId, PeriodoAcademicoService $periodos): void
    {
        $periodo = PeriodoAcademico::findOrFail($periodoId);
        $this->authorize('reabrir', $periodo);

        $this->ejecutar(fn () => $periodos->reabrir($periodo, Auth::user()), 'Periodo reabierto.');
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('viewAny', PeriodoAcademico::class);
    }

    public function render(PeriodoAcademicoService $periodos): mixed
    {
        return view('livewire.periodo-academico.gestion-de-periodos', [
            'abierto' => $periodos->abierto(),
            'vigente' => $periodos->vigente(),
            'historial' => $periodos->historial(),
        ]);
    }

    /** Corre la acción y traduce la regla rota a un mensaje en pantalla. */
    private function ejecutar(callable $accion, string $mensaje): bool
    {
        $this->errorDeRegla = null;

        try {
            $accion();
        } catch (PeriodoAcademicoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return false;
        }

        session()->flash('estado', $mensaje);

        return true;
    }
}
