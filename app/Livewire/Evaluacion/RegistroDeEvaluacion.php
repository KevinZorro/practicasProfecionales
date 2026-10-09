<?php

declare(strict_types=1);

namespace App\Livewire\Evaluacion;

use App\Enums\EstadoEvaluacion;
use App\Enums\ResultadoEvaluacion;
use App\Exceptions\EvaluacionInvalida;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\Evaluacion;
use App\Models\EvaluacionEstudiante;
use App\Models\EvaluacionItem;
use App\Models\User;
use App\Services\EvaluacionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Registro de una evaluación (RF43-RF48): el docente agrega a los
 * estudiantes de la sesión, marca el checklist y decide el resultado de
 * cada uno. El resultado no se deriva de los ítems (RF46). Finalizada, la
 * pantalla queda de solo lectura.
 */
final class RegistroDeEvaluacion extends Component
{
    use AutorizaEnCadaPeticion;

    #[Locked]
    public int $evaluacionId;

    /** @var array<int, string> id del registro => observaciones escritas */
    public array $observaciones = [];

    public ?string $errorDeRegla = null;

    public function mount(Evaluacion $evaluacion): void
    {
        $this->evaluacionId = $evaluacion->id;
        $this->observaciones = $evaluacion->estudiantes()->pluck('observaciones', 'id')
            ->map(static fn (?string $texto): string => (string) $texto)
            ->all();
    }

    public function agregar(int $estudianteId, EvaluacionService $evaluaciones): void
    {
        $evaluacion = $this->evaluacionParaRegistrar();
        $this->ejecutar(fn () => $evaluaciones->agregarEstudiante($evaluacion, User::findOrFail($estudianteId)));
    }

    /** Agrega de una vez a todos los de la sesión que pueden entrar. */
    public function agregarHabilitados(EvaluacionService $evaluaciones): void
    {
        $evaluacion = $this->evaluacionParaRegistrar();
        $candidatos = $evaluaciones->candidatosDeLaSesion($evaluacion);

        foreach ($candidatos['estudiantes'] as $estudiante) {
            if ($candidatos['impedimentos'][$estudiante->id] === []) {
                $this->ejecutar(fn () => $evaluaciones->agregarEstudiante($evaluacion, $estudiante));
            }
        }
    }

    public function quitar(int $registroId, EvaluacionService $evaluaciones): void
    {
        $evaluacion = $this->evaluacionParaRegistrar();
        $registro = $this->registro($registroId);
        $this->ejecutar(fn () => $evaluaciones->quitarEstudiante($evaluacion, $registro->estudiante));
    }

    public function alternarItem(int $registroId, int $itemId, EvaluacionService $evaluaciones): void
    {
        $evaluacion = $this->evaluacionParaRegistrar();
        $registro = $this->registro($registroId);
        $item = EvaluacionItem::query()->where('evaluacion_id', $evaluacion->id)->findOrFail($itemId);
        $cumplido = $registro->items()->whereKey($item->id)->wherePivot('cumplido', true)->exists();

        $this->ejecutar(fn () => $cumplido
            ? $evaluaciones->desmarcarItem($registro, $item)
            : $evaluaciones->marcarItem($registro, $item));
    }

    public function resultado(int $registroId, string $resultado, EvaluacionService $evaluaciones): void
    {
        $this->evaluacionParaRegistrar();
        $registro = $this->registro($registroId);
        $this->ejecutar(fn () => $evaluaciones->registrarResultado($registro, ResultadoEvaluacion::from($resultado)));
    }

    public function guardarObservaciones(int $registroId, EvaluacionService $evaluaciones): void
    {
        $this->evaluacionParaRegistrar();
        $this->validate(['observaciones.'.$registroId => 'nullable|string|max:1000']);
        $registro = $this->registro($registroId);
        $texto = trim($this->observaciones[$registroId] ?? '');

        $this->ejecutar(fn () => $evaluaciones->registrarObservaciones($registro, $texto === '' ? null : $texto));
    }

    public function finalizar(EvaluacionService $evaluaciones): void
    {
        $evaluacion = Evaluacion::findOrFail($this->evaluacionId);
        $this->authorize('finalizar', $evaluacion);

        if ($this->ejecutar(fn () => $evaluaciones->finalizar($evaluacion))) {
            session()->flash('estado', 'Evaluación finalizada. Los estudiantes ya pueden ver sus resultados.');
        }
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        $this->authorize('view', Evaluacion::findOrFail($this->evaluacionId));
    }

    public function render(EvaluacionService $evaluaciones): mixed
    {
        $evaluacion = $evaluaciones->paraRegistrar(Evaluacion::findOrFail($this->evaluacionId));
        $editable = $evaluacion->estado === EstadoEvaluacion::Borrador && Auth::user()->can('registrar', $evaluacion);

        return view('livewire.evaluacion.registro-de-evaluacion', [
            'evaluacion' => $evaluacion,
            'editable' => $editable,
            'candidatos' => $editable ? $evaluaciones->candidatosDeLaSesion($evaluacion) : null,
            'resultados' => ResultadoEvaluacion::cases(),
        ]);
    }

    private function evaluacionParaRegistrar(): Evaluacion
    {
        $evaluacion = Evaluacion::findOrFail($this->evaluacionId);
        $this->authorize('registrar', $evaluacion);

        return $evaluacion;
    }

    /** Solo registros de esta evaluación: un id ajeno no se toca. */
    private function registro(int $registroId): EvaluacionEstudiante
    {
        return EvaluacionEstudiante::query()
            ->where('evaluacion_id', $this->evaluacionId)
            ->with('estudiante')
            ->findOrFail($registroId);
    }

    /** Corre la acción y traduce la regla rota a un mensaje en pantalla. */
    private function ejecutar(callable $accion): bool
    {
        $this->errorDeRegla = null;

        try {
            $accion();
        } catch (EvaluacionInvalida $invalida) {
            $this->errorDeRegla = $invalida->getMessage();

            return false;
        }

        return true;
    }
}
