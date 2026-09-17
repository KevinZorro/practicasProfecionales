<?php

declare(strict_types=1);

namespace App\Livewire\Consentimiento;

use App\Enums\EstadoConsentimiento;
use App\Exceptions\ConsentimientoInvalido;
use App\Models\ConsentimientoEstudiante;
use App\Services\ConsentimientoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * El consentimiento del estudiante en el periodo vigente (RF52).
 *
 * El estudiante solo ve el suyo: la entrega se busca siempre por el usuario
 * autenticado, nunca por un identificador que venga de la petición.
 */
final class MiConsentimiento extends Component
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
            'documento' => ['required', 'file', 'mimes:pdf', 'max:'.config('laboratorio.consentimiento.tamano_maximo_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento.mimes' => 'El consentimiento debe ser un archivo PDF. Si lo escaneaste como imagen, conviértelo a PDF antes de subirlo.',
            'documento.max' => 'El archivo pesa más de lo permitido. El máximo son '.round((int) config('laboratorio.consentimiento.tamano_maximo_kb') / 1024, 1).' MB.',
            'documento.required' => 'Elige el archivo firmado antes de enviarlo.',
        ];
    }

    public function entregar(ConsentimientoService $consentimientos): void
    {
        $this->authorize('create', ConsentimientoEstudiante::class);
        $this->validate();
        $this->errorDeRegla = null;

        try {
            $consentimientos->registrarEntrega(Auth::user(), $this->documento);
        } catch (ConsentimientoInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        $this->reset('documento');
        session()->flash('estado', 'Tu consentimiento quedó cargado. La coordinación lo revisará.');
    }

    public function render(ConsentimientoService $consentimientos): mixed
    {
        $periodo = $consentimientos->periodoVigente();

        return view('livewire.consentimiento.mi-consentimiento', [
            'periodo' => $periodo,
            'entrega' => $consentimientos->entregaDelPeriodo(Auth::user(), $periodo),
            'hayPlantilla' => $this->hayPlantilla($consentimientos),
            'estados' => EstadoConsentimiento::cases(),
        ]);
    }

    private function hayPlantilla(ConsentimientoService $consentimientos): bool
    {
        try {
            $consentimientos->plantillaVigente();

            return true;
        } catch (ConsentimientoInvalido) {
            return false;
        }
    }
}
