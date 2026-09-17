<?php

declare(strict_types=1);

namespace App\Livewire\Consentimiento;

use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoPlantilla;
use App\Services\ConsentimientoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Carga de la plantilla del consentimiento (RF51). Solo el ADMIN.
 */
final class GestionDePlantilla extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Validate('required|string|max:150')]
    public string $nombre = 'Consentimiento informado de prácticas de simulación';

    #[Validate('required|string|max:20')]
    public string $version = '';

    public ?TemporaryUploadedFile $archivo = null;

    public ?string $errorDeRegla = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:150',
            'version' => 'required|string|max:20',
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:'.config('laboratorio.consentimiento.tamano_maximo_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.mimes' => 'La plantilla debe ser un archivo PDF.',
            'archivo.max' => 'El archivo pesa más de lo permitido.',
        ];
    }

    public function cargar(ConsentimientoService $consentimientos): void
    {
        $this->authorize('create', ConsentimientoPlantilla::class);
        $this->validate();
        $this->errorDeRegla = null;

        try {
            $consentimientos->cargarPlantilla(Auth::user(), $this->archivo, $this->nombre, $this->version);
        } catch (ConsentimientoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->reset('archivo', 'version');
        session()->flash('estado', 'Plantilla cargada. Es la que los estudiantes descargarán desde ahora.');
    }

    public function render(ConsentimientoService $consentimientos): mixed
    {
        return view('livewire.consentimiento.gestion-de-plantilla', [
            'plantillas' => $consentimientos->plantillas(),
        ]);
    }
}
