<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FormularioDeInformacionNoDisponible;
use App\Http\Requests\SolicitudDeInformacionRequest;
use App\Services\SolicitudDeInformacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * El formulario público de información por taller (RF09). Sin sesión: lo
 * usa cualquiera desde la portada. Si la sección está oculta o el taller no
 * está publicado, no existe: 404.
 */
final class SolicitudDeInformacionController extends Controller
{
    public const ENVIADA = 'Recibimos su solicitud. El laboratorio le responderá al correo que dejó.';

    public function __construct(private readonly SolicitudDeInformacionService $solicitudes) {}

    public function formulario(int $taller): View
    {
        try {
            $disponible = $this->solicitudes->tallerDisponible($taller);
        } catch (FormularioDeInformacionNoDisponible) {
            abort(404);
        }

        return view('portada.informacion-taller', ['taller' => $disponible]);
    }

    public function enviar(SolicitudDeInformacionRequest $peticion, int $taller): RedirectResponse
    {
        $volver = redirect()->route('portada.taller.informacion', $taller);

        // A un robot se le responde igual que a una persona: si notara la
        // diferencia, aprendería a no llenar el campo (D16).
        if ($peticion->esDeUnRobot()) {
            return $volver->with('enviada', self::ENVIADA);
        }

        try {
            $this->solicitudes->registrar($taller, $peticion->datos());
        } catch (FormularioDeInformacionNoDisponible) {
            abort(404);
        }

        return $volver->with('enviada', self::ENVIADA);
    }
}
