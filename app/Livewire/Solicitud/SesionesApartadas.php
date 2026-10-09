<?php

declare(strict_types=1);

namespace App\Livewire\Solicitud;

use App\Enums\TipoSesion;
use App\Exceptions\CapacidadDeEstudiantesExcedida;
use App\Exceptions\SolicitudInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\CasoClinico;
use App\Models\Materia;
use App\Models\Solicitud;
use App\Services\DatosSesionApartada;
use App\Services\RegistroPrevioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Los administrativos pasan a la plataforma las sesiones apartadas antes del
 * semestre, desde el formato físico que les entrega coordinación (RF57).
 * Llegan aprobadas. Mientras se llenan los datos se avisa de cruces (RF58).
 */
final class SesionesApartadas extends Component
{
    use AutorizaEnCadaPeticion;
    use WithPagination;

    #[Validate('required|integer|exists:users,id')]
    public ?int $docenteId = null;

    #[Validate('required|integer|exists:materias,id')]
    public ?int $materiaId = null;

    #[Validate('required|integer|exists:casos_clinicos,id')]
    public ?int $casoClinicoId = null;

    #[Validate('required|string')]
    public string $tipo = TipoSesion::Practica->value;

    #[Validate('required|date')]
    public string $fecha = '';

    #[Validate('required|date_format:H:i')]
    public string $horaInicio = '';

    #[Validate('required|date_format:H:i|after:horaInicio')]
    public string $horaFin = '';

    #[Validate('required|integer|min:1|max:200')]
    public ?int $cantidadEstudiantes = null;

    #[Validate('nullable|string|regex:/^[A-Za-z]{1,2}$/')]
    public string $grupo = '';

    #[Url(as: 'pendientes', keep: false)]
    public bool $soloSinFormato = false;

    public ?string $errorDeRegla = null;

    public function updatedSoloSinFormato(): void
    {
        $this->resetPage();
    }

    public function registrar(RegistroPrevioService $registroPrevio): void
    {
        $this->authorize('registrarApartada', Solicitud::class);
        $datos = $this->validate();
        $this->errorDeRegla = null;

        try {
            $registroPrevio->registrar(new DatosSesionApartada(
                docenteId: (int) $datos['docenteId'],
                materiaId: (int) $datos['materiaId'],
                casoClinicoId: (int) $datos['casoClinicoId'],
                tipo: TipoSesion::from($datos['tipo']),
                fecha: $datos['fecha'],
                horaInicio: $datos['horaInicio'],
                horaFin: $datos['horaFin'],
                cantidadEstudiantes: (int) $datos['cantidadEstudiantes'],
                grupo: $datos['grupo'],
            ), Auth::user());
        } catch (SolicitudInvalida|CapacidadDeEstudiantesExcedida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return;
        }

        // Se conservan docente, materia y caso: el formato físico suele traer
        // varias sesiones seguidas del mismo docente.
        $this->reset('fecha', 'horaInicio', 'horaFin', 'grupo');
        session()->flash('estado', 'Sesión registrada y aprobada. Falta su formato intramural.');
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('registrarApartada', Solicitud::class);
    }

    public function render(RegistroPrevioService $registroPrevio): mixed
    {
        $franjaCompleta = $this->fecha !== '' && preg_match('/^\d{2}:\d{2}$/', $this->horaInicio) === 1
            && preg_match('/^\d{2}:\d{2}$/', $this->horaFin) === 1 && $this->horaInicio < $this->horaFin;

        return view('livewire.solicitud.sesiones-apartadas', [
            'docentes' => $registroPrevio->docentes(),
            'materias' => Materia::activas()->orderBy('nombre')->get(['id', 'nombre', 'semestre']),
            'casosClinicos' => CasoClinico::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'tiposDeSesion' => TipoSesion::cases(),
            'advertencias' => $franjaCompleta
                ? $registroPrevio->advertencias($this->fecha, $this->horaInicio, $this->horaFin, $this->docenteId)
                : [],
            'sesiones' => $registroPrevio->listado($this->soloSinFormato),
        ]);
    }
}
