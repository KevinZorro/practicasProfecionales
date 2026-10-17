<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * A dónde lleva «volver» en el expediente de una solicitud.
 *
 * Al expediente se llega desde muchas pantallas: Hoy, el calendario, la
 * bandeja, las sesiones apartadas, el tablero de preparaciones. Un enlace
 * fijo a la bandeja dejaba a la persona en una pantalla que no había
 * abierto, y quien no se mueve con soltura por el sistema se pierde.
 *
 * Al entrar al expediente desde una sección del menú se recuerda esa
 * pantalla, con sus filtros, y se conserva mientras se pasa de una pestaña
 * a otra o se vuelve de una página que cuelga de él (el estado de los
 * formatos de la sesión): si no, el origen sería la pestaña anterior.
 *
 * Solo cuentan como origen las secciones del menú que el usuario ve con su
 * rol activo: así nunca se ofrece volver a una pantalla que le daría 403.
 */
final class VueltaDelExpediente
{
    public const CLAVE_DE_SESION = 'vuelta_del_expediente';

    public function __construct(
        private readonly Session $sesion,
        private readonly MenuDelPanel $menu,
    ) {}

    public function para(Request $peticion, Solicitud $solicitud): EnlaceDeVuelta
    {
        /** @var User $usuario */
        $usuario = $peticion->user();
        $secciones = $this->menu->visiblesPara($usuario);

        $desde = $this->seccionDeOrigen($peticion, $secciones);

        if ($desde !== null) {
            $this->sesion->put(self::CLAVE_DE_SESION, [
                'solicitud' => $solicitud->id,
                'url' => (string) $peticion->headers->get('referer'),
                'ruta' => $desde->ruta,
            ]);

            return new EnlaceDeVuelta((string) $peticion->headers->get('referer'), $desde->etiqueta);
        }

        $recordada = $this->sesion->get(self::CLAVE_DE_SESION);

        if (is_array($recordada) && ($recordada['solicitud'] ?? null) === $solicitud->id) {
            foreach ($secciones as $seccion) {
                if ($seccion->ruta === ($recordada['ruta'] ?? null)) {
                    return new EnlaceDeVuelta((string) $recordada['url'], $seccion->etiqueta);
                }
            }
        }

        return $this->porDefecto($usuario);
    }

    /**
     * La sección del menú de la que viene la petición, si viene de una.
     *
     * @param  list<SeccionDelPanel>  $secciones
     */
    private function seccionDeOrigen(Request $peticion, array $secciones): ?SeccionDelPanel
    {
        $referer = $peticion->headers->get('referer');

        foreach ($secciones as $seccion) {
            if (EnlaceDeVuelta::esLaMismaPantalla($peticion, $referer, route($seccion->ruta))) {
                return $seccion;
            }
        }

        return null;
    }

    /** Sin origen conocido —un enlace de un correo, la URL escrita a mano—, a la lista de solicitudes de quien mira. */
    private function porDefecto(User $usuario): EnlaceDeVuelta
    {
        if ($usuario->can('verBandeja', Solicitud::class)) {
            return new EnlaceDeVuelta(route('panel.solicitudes'), 'Solicitudes');
        }

        return new EnlaceDeVuelta(route('panel.mis-solicitudes'), 'Mis solicitudes');
    }
}
