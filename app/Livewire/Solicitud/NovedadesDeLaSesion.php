<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\NovedadInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\CasoClinico;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\DatosReprogramacion;
use App\Services\NovedadesDeSesionService;
use App\Services\RegistroPrevioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Novedades de una sesión aprobada: reprogramarla (RF61) y sustituir al
 * docente (RF73), con el historial de las dos.
 */
final class NovedadesDeLaSesion extends Component
{
    use AutorizaEnCadaPeticion;

    #[Locked]
    public int $solicitudId;

    public string $fecha = '';

    public string $horaInicio = '';

    public string $horaFin = '';

    public ?int $casoClinicoId = null;

    public string $motivo = '';

    public string $constancia = '';

    public ?int $reemplazoId = null;

    public string $motivoSustitucion = '';

    public ?string $errorDeRegla = null;

    public function mount(Solicitud $solicitud): void
    {
        $this->solicitudId = $solicitud->id;
        $this->llenarConLaSesion($solicitud);
    }

    public function reprogramar(NovedadesDeSesionService $novedades): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('reprogramar', $solicitud);
        $this->validate([
            'fecha' => 'required|date',
            'horaInicio' => 'required|date_format:H:i',
            'horaFin' => 'required|date_format:H:i|after:horaInicio',
            'casoClinicoId' => 'required|integer|exists:casos_clinicos,id',
            'motivo' => 'required|string|max:1000',
            'constancia' => 'required|string|max:1000',
        ]);

        $hecho = $this->ejecutar(fn () => $novedades->reprogramar($solicitud, new DatosReprogramacion(
            fecha: $this->fecha,
            horaInicio: $this->horaInicio,
            horaFin: $this->horaFin,
            casoClinicoId: (int) $this->casoClinicoId,
            motivo: $this->motivo,
            constanciaComunicacion: $this->constancia,
        ), Auth::user()), 'Sesión reprogramada. El docente recibirá un correo.');

        if ($hecho) {
            $this->reset('motivo', 'constancia');
            $this->llenarConLaSesion($solicitud->fresh());
        }
    }

    public function sustituir(NovedadesDeSesionService $novedades): void
    {
        $solicitud = $this->solicitud();
        $this->authorize('sustituirDocente', $solicitud);
        $this->validate([
            'reemplazoId' => 'required|integer|exists:users,id',
            'motivoSustitucion' => 'required|string|max:1000',
        ]);

        $hecho = $this->ejecutar(
            fn () => $novedades->sustituir($solicitud, User::findOrFail($this->reemplazoId), $this->motivoSustitucion, Auth::user()),
            'Sustitución registrada. El docente que reemplaza recibirá un correo.',
        );

        if ($hecho) {
            $this->reset('reemplazoId', 'motivoSustitucion');
        }
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('reprogramar', $this->solicitud());
    }

    public function render(RegistroPrevioService $registroPrevio): mixed
    {
        $solicitud = $this->solicitud()->load([
            'docente:id,nombre', 'docenteQueDicta:id,nombre', 'materia:id,nombre', 'casoClinico:id,nombre',
            'reprogramaciones.casoClinicoAnterior:id,nombre', 'reprogramaciones.casoClinicoNuevo:id,nombre', 'reprogramaciones.reprogramadaPor:id,nombre',
            'sustituciones.docenteAnterior:id,nombre', 'sustituciones.docenteNuevo:id,nombre', 'sustituciones.registradaPor:id,nombre',
        ]);
        $franjaCompleta = $this->fecha !== '' && preg_match('/^\d{2}:\d{2}$/', $this->horaInicio) === 1
            && preg_match('/^\d{2}:\d{2}$/', $this->horaFin) === 1 && $this->horaInicio < $this->horaFin;

        return view('livewire.solicitud.novedades-de-la-sesion', [
            'solicitud' => $solicitud,
            'casosClinicos' => CasoClinico::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'docentes' => $registroPrevio->docentes(),
            'advertencias' => $franjaCompleta
                ? $registroPrevio->advertencias($this->fecha, $this->horaInicio, $this->horaFin, $solicitud->idDelDocenteQueDicta(), $solicitud->id)
                : [],
        ]);
    }

    private function llenarConLaSesion(Solicitud $solicitud): void
    {
        $this->fecha = $solicitud->fecha->format('Y-m-d');
        $this->horaInicio = substr($solicitud->hora_inicio, 0, 5);
        $this->horaFin = substr($solicitud->hora_fin, 0, 5);
        $this->casoClinicoId = $solicitud->caso_clinico_id;
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
        } catch (NovedadInvalida|CapacidadDeEstudiantesExcedida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return false;
        }

        session()->flash('estado', $mensaje);

        return true;
    }
}
