<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\FormatoConfidencialidad;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\ParticipacionService;
use App\Services\SolicitudService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Quién va a una sesión y quién no puede entrar, con el motivo (RF70). Desde
 * aquí el docente o el laboratorio retiran a un estudiante (RF69) o
 * completan la lista (RF28).
 */
final class ParticipantesDeLaSesion extends Component
{
    use AutorizaEnCadaPeticion;

    #[Locked]
    public int $solicitudId;

    public ?int $retirando = null;

    public string $motivoRetiro = '';

    public string $busquedaEstudiante = '';

    public ?string $errorDeRegla = null;

    public function mount(Solicitud $solicitud): void
    {
        $this->solicitudId = $solicitud->id;
    }

    public function pedirMotivo(int $estudianteId): void
    {
        $this->retirando = $estudianteId;
        $this->motivoRetiro = '';
    }

    public function retirar(int $estudianteId, SolicitudService $solicitudes): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('gestionarParticipantes', $solicitud);
        $this->validate(['motivoRetiro' => 'required|string|max:1000']);

        if ($this->ejecutar(fn () => $solicitudes->retirarEstudiante($solicitud, User::findOrFail($estudianteId), $this->motivoRetiro, Auth::user()), 'Estudiante retirado de la sesión.')) {
            $this->reset('retirando', 'motivoRetiro');
        }
    }

    public function agregar(int $estudianteId, SolicitudService $solicitudes): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('gestionarParticipantes', $solicitud);

        if ($this->ejecutar(fn () => $solicitudes->agregarEstudiantes($solicitud, [$estudianteId], Auth::user()), 'Estudiante agregado a la sesión.')) {
            $this->busquedaEstudiante = '';
        }
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('view', $this->solicitud());
    }

    public function render(ParticipacionService $participacion, SolicitudService $solicitudes): mixed
    {
        $solicitud = $this->solicitud()->load(['materia', 'casoClinico', 'preparacion.sala']);
        $lista = $participacion->deLaSesion($solicitud);
        $puedeGestionar = Auth::user()->can('gestionarParticipantes', $solicitud);

        return view('livewire.solicitud.participantes-de-la-sesion', [
            ...$lista,
            'solicitud' => $solicitud,
            'puedeGestionar' => $puedeGestionar,
            'verificaFormatos' => Auth::user()->can('viewAny', FormatoConfidencialidad::class),
            'resultados' => $puedeGestionar
                ? $solicitudes->buscarEstudiantes($this->busquedaEstudiante, $lista['estudiantes']->pluck('id')->all())
                : collect(),
        ]);
    }

    private function solicitud(): Solicitud
    {
        return Solicitud::findOrFail($this->solicitudId);
    }

    /** Corre la acción y traduce la regla rota a un mensaje en pantalla. */
    private function ejecutar(callable $accion, string $mensaje): bool
    {
        $this->errorDeRegla = null;

        try {
            $accion();
        } catch (SolicitudInvalida|CapacidadDeEstudiantesExcedida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return false;
        }

        session()->flash('estado', $mensaje);

        return true;
    }
}
