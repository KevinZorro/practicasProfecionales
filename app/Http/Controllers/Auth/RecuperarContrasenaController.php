<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\RecuperacionInvalida;
use App\Exceptions\UsuarioInvalido;
use App\Http\Controllers\Controller;
use App\Services\AccesoService;
use App\Services\RecuperacionDeContrasenaService;
use App\Support\EsperaDeReenvio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recuperar la contraseña por correo (ADR 0011): pedir el enlace y elegir
 * la contraseña nueva. Existe solo si la entrada con contraseña está
 * encendida; la lógica es de RecuperacionDeContrasenaService.
 */
final class RecuperarContrasenaController extends Controller
{
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

        $espera = new EsperaDeReenvio($datos['email']);

        if ($espera->segundosRestantes() > 0) {
            return redirect()->route('acceso.recuperar')
                ->with(['enviadoA' => $datos['email'], 'antesDeTiempo' => true]);
        }

        $this->recuperacion->enviarEnlace($datos['email']);
        $espera->registrarEnvio();

        // La pantalla responde igual exista o no la cuenta: no delata cuáles hay.
        return redirect()->route('acceso.recuperar')->with('enviadoA', $datos['email']);
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
