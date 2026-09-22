<?php

declare(strict_types=1);

namespace App\Livewire\Confidencialidad;

use App\Enums\EstadoFormatoConfidencialidad;
use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Models\FormatoConfidencialidad;
use App\Services\ConfidencialidadService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * El formato de confidencialidad propio en el periodo vigente (RF52).
 *
 * Lo firman estudiantes y docentes, y cada quien ve solo el suyo: la entrega
 * se busca siempre por el usuario autenticado, nunca por un identificador
 * que venga de la petición.
 */
final class MiFormato extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $documento = null;

    public ?string $errorDeRegla = null;

    /**
     * Solo PDF y con el tope que fija config/laboratorio.php. Se valida aquí
     * para avisar antes de subir, y el Service lo vuelve a comprobar.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'documento' => ['required', 'file', 'mimes:pdf', 'max:'.config('laboratorio.confidencialidad.tamano_maximo_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento.mimes' => 'El formato debe ser un archivo PDF. Si lo escaneaste como imagen, conviértelo a PDF antes de subirlo.',
            'documento.max' => 'El archivo pesa más de lo permitido. El máximo son '.round((int) config('laboratorio.confidencialidad.tamano_maximo_kb') / 1024, 1).' MB.',
            'documento.required' => 'Elige el archivo firmado antes de enviarlo.',
        ];
    }

    public function entregar(ConfidencialidadService $confidencialidad): void
    {
        $this->authorize('create', FormatoConfidencialidad::class);
        $this->validate();
        $this->errorDeRegla = null;

        try {
            $confidencialidad->registrarEntrega(Auth::user(), $this->documento);
        } catch (FormatoConfidencialidadInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->reset('documento');
        session()->flash('estado', 'Tu formato quedó cargado. El laboratorio lo revisará.');
    }

    public function render(ConfidencialidadService $confidencialidad): mixed
    {
        $periodo = $confidencialidad->periodoVigente();

        return view('livewire.confidencialidad.mi-formato', [
            'periodo' => $periodo,
            'entrega' => $confidencialidad->entregaDelPeriodo(Auth::user(), $periodo),
            'hayPlantilla' => $this->hayPlantilla($confidencialidad),
            'estados' => EstadoFormatoConfidencialidad::cases(),
        ]);
    }

    private function hayPlantilla(ConfidencialidadService $confidencialidad): bool
    {
        try {
            $confidencialidad->plantillaVigente();

            return true;
        } catch (FormatoConfidencialidadInvalido) {
            return false;
        }
    }
}
