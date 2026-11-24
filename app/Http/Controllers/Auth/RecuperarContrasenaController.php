<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\RecuperacionInvalida;
use App\Exceptions\UsuarioInvalido;
use App\Http\Controllers\Controller;
use App\Services\AccesoService;
use App\Services\RecuperacionDeContrasenaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Recuperar la contraseña por correo (ADR 0011): pedir el enlace y elegir
 * la contraseña nueva. Existe solo si la entrada con contraseña está
 * encendida; la lógica es de RecuperacionDeContrasenaService.
 */
final class RecuperarContrasenaController extends Controller
{
    /** Un mensaje para todos: no se le dice a nadie qué correos tienen cuenta. */
    private const ENLACE_ENVIADO = 'Si ese correo tiene cuenta en la plataforma, te enviamos un enlace para elegir una contraseña nueva. Revisa tu bandeja y la carpeta de spam.';

    public function __construct(
        private readonly AccesoService $acceso,
        private readonly RecuperacionDeContrasenaService $recuperacion,
    ) {}

    public function formulario(): View
    {
        $this->garantizarDisponible();

        return view('auth.recuperar-contrasena');
    }

    public function enviar(Request $peticion): RedirectResponse
    {
        $this->garantizarDisponible();

        $datos = $peticion->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        // Por correo e IP, como la entrada: frena a quien llena buzones ajenos
        // sin cerrarle la puerta a toda la universidad.
        $clave = 'recuperar:'.mb_strtolower(trim($datos['email'])).'|'.$peticion->ip();

        if (RateLimiter::tooManyAttempts($clave, (int) config('laboratorio.acceso.intentos_por_minuto'))) {
            return back()->withErrors(['email' => 'Demasiados intentos. Vuelve a intentarlo en '.RateLimiter::availableIn($clave).' segundos.']);
        }

        RateLimiter::hit($clave);
        $this->recuperacion->enviarEnlace($datos['email']);

        return back()->with('estado', self::ENLACE_ENVIADO);
    }

    public function formularioDeRestablecer(Request $peticion, string $token): View
    {
        $this->garantizarDisponible();

        $email = (string) $peticion->query('email', '');

        return view('auth.restablecer-contrasena', [
            'token' => $token,
            'email' => $email,
            'enlaceVigente' => $this->recuperacion->enlaceVigente($email, $token),
            'longitudMinima' => (int) config('laboratorio.acceso.longitud_minima_contrasena'),
            'longitudMaxima' => (int) config('laboratorio.acceso.longitud_maxima_contrasena'),
        ]);
    }

    public function restablecer(Request $peticion): RedirectResponse
    {
        $this->garantizarDisponible();

        $datos = $peticion->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'contrasena' => ['required', 'string', 'confirmed', 'min:'.(int) config('laboratorio.acceso.longitud_minima_contrasena'), 'max:255'],
        ]);

        try {
            $this->recuperacion->restablecer($datos['email'], $datos['token'], $datos['contrasena']);
        } catch (RecuperacionInvalida $vencido) {
            return back()->withInput($peticion->only('email'))->withErrors(['acceso' => $vencido->getMessage()]);
        } catch (UsuarioInvalido $invalida) {
            return back()->withInput($peticion->only('email'))->withErrors(['contrasena' => $invalida->getMessage()]);
        }

        return redirect()->route('acceso')
            ->with('estado', 'Tu contraseña quedó guardada. Ya puedes entrar con ella.')
            ->withInput(['email' => $datos['email']]);
    }

    private function garantizarDisponible(): void
    {
        abort_unless($this->acceso->entradaConContrasenaDisponible(), 404);
    }
}
