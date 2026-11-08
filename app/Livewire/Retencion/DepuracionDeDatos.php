<?php

declare(strict_types=1);

namespace App\Livewire\Retencion;

use App\Exceptions\RetencionInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Livewire\Concerns\AvisaDelResultado;
use App\Services\RetencionDeDatosService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Lo que tiene más de dos semestres y la depuración (retención de datos,
 * decisión del 2026-11-08). La regla y el borrado viven en
 * RetencionDeDatosService; aquí se enseñan las cifras y se pide confirmar
 * que los formatos ya se descargaron, porque borrarlos no se deshace.
 */
final class DepuracionDeDatos extends Component
{
    use AutorizaEnCadaPeticion;
    use AvisaDelResultado;

    public bool $descargoLosFormatos = false;

    public ?string $errorDeRegla = null;

    public function depurar(RetencionDeDatosService $retencion): void
    {
        $this->authorize('depurarDatos');

        if ($retencion->porDepurar()->formatos > 0) {
            $this->validate(['descargoLosFormatos' => 'accepted']);
        }

        $this->errorDeRegla = null;

        try {
            $retencion->depurar(Auth::user());
        } catch (RetencionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        $this->reset('descargoLosFormatos');
        $this->avisar('Listo: se depuró lo de más de dos semestres. Quedó en la bitácora.');
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('depurarDatos');
    }

    public function render(RetencionDeDatosService $retencion): mixed
    {
        return view('livewire.retencion.depuracion-de-datos', [
            'pendiente' => $retencion->porDepurar(),
        ]);
    }
}
