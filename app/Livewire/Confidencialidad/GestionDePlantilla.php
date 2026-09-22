<?php

declare(strict_types=1);

namespace App\Livewire\Confidencialidad;

use App\Exceptions\FormatoConfidencialidadInvalido;
use App\Models\PlantillaConfidencialidad;
use App\Services\ConfidencialidadService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Carga de la plantilla del formato (RF51). Solo el ADMIN.
 */
final class GestionDePlantilla extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Validate('required|string|max:150')]
    public string $nombre = 'Formato de confidencialidad y autorización de captación de imágenes';

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
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:'.config('laboratorio.confidencialidad.tamano_maximo_kb')],
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

    public function cargar(ConfidencialidadService $confidencialidad): void
    {
        $this->authorize('create', PlantillaConfidencialidad::class);
        $this->validate();
        $this->errorDeRegla = null;

        try {
            $confidencialidad->cargarPlantilla(Auth::user(), $this->archivo, $this->nombre, $this->version);
        } catch (FormatoConfidencialidadInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->reset('archivo', 'version');
        session()->flash('estado', 'Plantilla cargada. Es la que se descargará desde ahora.');
    }

    public function render(ConfidencialidadService $confidencialidad): mixed
    {
        return view('livewire.confidencialidad.gestion-de-plantilla', [
            'plantillas' => $confidencialidad->plantillas(),
        ]);
    }
}
