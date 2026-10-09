<?php

declare(strict_types=1);

namespace App\Livewire\Usuario;

use App\Exceptions\UsuarioInvalido;
use App\Livewire\Concerns\AutorizaEnCadaPeticion;
use App\Models\User;
use App\Services\DatosUsuario;
use App\Services\UsuarioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Alta y edición de una cuenta (RF22). Solo el ADMIN, por UserPolicy.
 *
 * Las cuentas creadas aquí quedan con origen "manual" y la sincronización
 * no las toca. Las que vienen de la institución se pueden corregir, pero la
 * siguiente pasada vuelve a escribir sus datos: la fuente es la institución.
 * Los roles no se dan aquí, sino en la lista de usuarios (RF63, RF64).
 */
final class FormularioDeCuenta extends Component
{
    use AutorizaEnCadaPeticion;

    public ?User $cuenta = null;

    #[Validate('required|string|max:150')]
    public string $nombre = '';

    #[Validate('required|email|max:150')]
    public string $correo = '';

    #[Validate('nullable|string|max:30')]
    public string $documento = '';

    #[Validate('nullable|string|max:30')]
    public string $codigoInstitucional = '';

    #[Validate('nullable|string|max:150')]
    public string $programa = '';

    public ?string $errorDeRegla = null;

    public function mount(?User $cuenta = null): void
    {
        if (! $cuenta instanceof User || ! $cuenta->exists) {
            return;
        }

        $this->cuenta = $cuenta;
        $this->nombre = $cuenta->nombre;
        $this->correo = $cuenta->email;
        $this->documento = (string) $cuenta->documento;
        $this->codigoInstitucional = (string) $cuenta->codigo_institucional;
        $this->programa = (string) $cuenta->programa;
    }

    public function guardar(UsuarioService $usuarios): void
    {
        $this->autorizarPantalla();
        $this->validate();
        $this->errorDeRegla = null;

        $datos = new DatosUsuario(
            nombre: $this->nombre,
            email: $this->correo,
            documento: $this->documento,
            codigoInstitucional: $this->codigoInstitucional,
            programa: $this->programa,
        );

        try {
            $this->cuenta instanceof User
                ? $usuarios->actualizar($this->cuenta, $datos, Auth::user())
                : $usuarios->crear($datos, Auth::user());
        } catch (UsuarioInvalido $invalido) {
            $this->errorDeRegla = $invalido->getMessage();

            return;
        }

        session()->flash('estado', $this->cuenta instanceof User ? 'Cuenta actualizada.' : 'Cuenta creada. Ahora asígnale un rol.');
        $this->redirectRoute('panel.usuarios', navigate: true);
    }

    /** El permiso que exige el controlador de la página. */
    protected function autorizarPantalla(): void
    {
        if ($this->cuenta instanceof User) {
            $this->authorize('update', $this->cuenta);

            return;
        }

        $this->authorize('create', User::class);
    }

    public function render(): mixed
    {
        return view('livewire.usuario.formulario-de-cuenta', [
            'esAlta' => ! $this->cuenta instanceof User,
            'programas' => $this->programasElegibles(),
        ]);
    }

    /**
     * Los del laboratorio, más el que ya tenga la cuenta aunque no esté en la
     * lista: si no, guardar sin tocar el campo se lo borraría.
     *
     * @return list<string>
     */
    private function programasElegibles(): array
    {
        $programas = array_map('strval', (array) config('laboratorio.sincronizacion.programas'));

        if ($this->programa !== '' && ! in_array($this->programa, $programas, true)) {
            $programas[] = $this->programa;
        }

        return $programas;
    }
}
