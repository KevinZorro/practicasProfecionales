<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\AccesoRechazado;
use App\Http\Controllers\Controller;
use App\Services\AccesoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * La pantalla de entrada y la entrada con correo y contraseña (ADR 0011).
 *
 * La pantalla ofrece las entradas que estén disponibles: la contraseña
 * (ENTRADA_CON_CONTRASENA) y Google (GOOGLE_CLIENT_ID), que sigue en
 * AccesoConGoogleController. Sin ninguna, responde 404. Quién entra lo
 * decide AccesoService.
 */
final class AccesoController extends Controller
{
    public function __construct(private readonly AccesoService $acceso) {}

    public function pantalla(): View|RedirectResponse
    {
        abort_unless($this->acceso->entradaDisponible(), 404);

        if (Auth::check()) {
            return redirect()->route('panel.inicio');
        }

        return view('auth.acceso', [
            'conGoogle' => $this->acceso->entradaConGoogleDisponible(),
            'conContrasena' => $this->acceso->entradaConContrasenaDisponible(),
            'dominio' => $this->acceso->dominioInstitucional(),
        ]);
    }

    public function entrar(Request $peticion): RedirectResponse
    {
        abort_unless($this->acceso->entradaConContrasenaDisponible(), 404);

        $datos = $peticion->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'contrasena' => ['required', 'string', 'max:255'],
        ]);

        // Por correo e IP juntos: una IP compartida por toda la universidad
        // no deja sin entrar a quien no hizo nada (ver config/laboratorio.php).
        $clave = 'acceso:'.mb_strtolower(trim($datos['email'])).'|'.$peticion->ip();
        $maximo = (int) config('laboratorio.acceso.intentos_por_minuto');

        if (RateLimiter::tooManyAttempts($clave, $maximo)) {
            return $this->rechazar(AccesoRechazado::demasiadosIntentos(RateLimiter::availableIn($clave)), $datos['email']);
        }

        try {
            $usuario = $this->acceso->cuentaConContrasena($datos['email'], $datos['contrasena']);
        } catch (AccesoRechazado $rechazo) {
            RateLimiter::hit($clave);

            return $this->rechazar($rechazo, $datos['email']);
        }

        RateLimiter::clear($clave);

        // Se guarda antes de invalidar la sesión, que se lo llevaría.
        $destino = $peticion->session()->pull('url.intended', route('panel.inicio'));

        // Nada de la sesión anterior sobrevive, tampoco el rol activo de otra
        // persona que usó el mismo equipo (regla 13).
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        Auth::login($usuario);

        return redirect()->to($destino);
    }

    private function rechazar(AccesoRechazado $rechazo, string $email): RedirectResponse
    {
        return redirect()->route('acceso')
            ->withErrors(['acceso' => $rechazo->getMessage()])
            ->withInput(['email' => $email]);
    }
}
