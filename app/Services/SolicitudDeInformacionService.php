<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClaveConfiguracionLanding;
use App\Events\SolicitudDeInformacionRecibida;
use App\Exceptions\FormularioDeInformacionNoDisponible;
use App\Models\SolicitudInformacion;
use App\Models\Taller;

/**
 * El formulario de información por taller de la portada (RF09).
 *
 * Se ofrece solo si el ADMIN lo habilitó en la landing y hay correo de
 * contacto, que es adonde llega (D8); y en cada taller, si ese taller lo
 * muestra (`muestra_formulario`) y está publicado y de hoy en adelante, los
 * mismos que anuncia la portada (D18). Cada solicitud se guarda y el
 * correo sale en cola.
 */
final class SolicitudDeInformacionService
{
    public function __construct(private readonly ConfiguracionLandingService $configuracion) {}

    /** Si la portada ofrece el formulario. Sin él, «Pedir información» abre un correo. */
    public function habilitado(): bool
    {
        $valores = $this->configuracion->valores();

        return $valores[ClaveConfiguracionLanding::FormularioInformacion->value] === '1'
            && $valores[ClaveConfiguracionLanding::ContactoEmail->value] !== null;
    }

    /** El taller del formulario, o la excepción que para el visitante es un 404. */
    public function tallerDisponible(int $tallerId): Taller
    {
        if (! $this->habilitado()) {
            throw FormularioDeInformacionNoDisponible::paraEseTaller();
        }

        $taller = Taller::query()
            ->where('activo', true)
            ->where('muestra_formulario', true)
            ->whereDate('fecha', '>=', now()->toDateString())
            ->find($tallerId);

        if (! $taller instanceof Taller) {
            throw FormularioDeInformacionNoDisponible::paraEseTaller();
        }

        return $taller;
    }

    public function registrar(int $tallerId, DatosDeSolicitudDeInformacion $datos): SolicitudInformacion
    {
        $taller = $this->tallerDisponible($tallerId);

        $solicitud = $taller->solicitudesInformacion()->create([
            'nombre' => $datos->nombre,
            'email' => $datos->email,
            'telefono' => $datos->telefono,
            'mensaje' => $datos->mensaje,
        ]);

        SolicitudDeInformacionRecibida::dispatch($solicitud);

        return $solicitud;
    }
}
