<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\AccesoRechazado;
use App\Http\Controllers\Controller;
use App\Services\AccesoService;
use App\Services\IdentidadDeGoogle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as RedireccionExterna;

/**
 * Entrada a la plataforma con la cuenta institucional de Google (RF18).
 *
 * Quién entra lo decide AccesoService; aquí solo se habla con Google y se
 * abre la sesión. Sin credenciales configuradas, las tres rutas responden
 * 404: la entrada no existe a medias.
 */
final class AccesoConGoogleController extends Controller
{
    public function __construct(private readonly AccesoService $acceso) {}

    public function pantalla(): View|RedirectResponse
    {
        $this->garantizarDisponible();

        if (Auth::check()) {
            return redirect()->route('panel.inicio');
        }

        return view('auth.acceso', ['dominio' => $this->acceso->dominioInstitucional()]);
    }

    public function redirigir(): RedireccionExterna
    {
        $this->garantizarDisponible();

        // "hd" hace que Google ofrezca solo cuentas del dominio, y
        // "select_account" que pregunte cuál usar en un equipo compartido
        // de la facultad, en vez de entrar con la última que quedó abierta.
        $parametros = array_filter([
            'hd' => $this->acceso->dominioInstitucional(),
            'prompt' => 'select_account',
        ]);

        return $this->google()->with($parametros)->redirect();
    }

    public function volver(Request $peticion): RedirectResponse
    {
        $this->garantizarDisponible();

        // La persona pulsó "Cancelar" en la pantalla de Google.
        if ($peticion->filled('error')) {
            return $this->rechazar('No se completó el ingreso con Google. Puedes intentarlo de nuevo.');
        }

        try {
            $cuenta = $this->google()->user();
        } catch (InvalidStateException) {
            // El "state" no coincide: la persona volvió atrás, abrió el
            // enlace de retorno en otra pestaña o dejó pasar demasiado tiempo.
            return $this->rechazar('El ingreso con Google caducó. Vuelve a intentarlo.');
        }

        try {
            $usuario = $this->acceso->cuentaDeGoogle(IdentidadDeGoogle::desdeSocialite($cuenta));
        } catch (AccesoRechazado $rechazo) {
            return $this->rechazar($rechazo->getMessage());
        }

        // Se guarda antes de invalidar la sesión, que se lo llevaría.
        $destino = $peticion->session()->pull('url.intended', route('panel.inicio'));

        // Nada de lo que había en la sesión antes de entrar sobrevive,
        // tampoco un rol activo de otra persona que usó el mismo equipo: se
        // entra con el rol permanente (regla 13).
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        Auth::login($usuario);

        return redirect()->to($destino);
    }

    /**
     * Socialite::driver() se declara con el contrato genérico, que no tiene
     * with() ni devuelve los datos crudos de Google. El de Google sí.
     */
    private function google(): GoogleProvider
    {
        $proveedor = Socialite::driver('google');

        if (! $proveedor instanceof GoogleProvider) {
            throw new LogicException('El proveedor "google" de Socialite no es el de Google.');
        }

        return $proveedor;
    }

    private function garantizarDisponible(): void
    {
        abort_unless($this->acceso->entradaConGoogleDisponible(), 404);
    }

    private function rechazar(string $motivo): RedirectResponse
    {
        return redirect()->route('acceso')->withErrors(['acceso' => $motivo]);
    }
}
