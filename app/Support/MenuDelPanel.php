<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ConsentimientoEstudiante;
use App\Models\ConsentimientoPlantilla;
use App\Models\Evaluacion;
use App\Models\ItemInventario;
use App\Models\Preparacion;
use App\Models\Solicitud;
use App\Models\User;

/**
 * Navegación lateral del panel interno.
 *
 * Cada sección declara qué permiso hace falta para verla, y la decisión la
 * toman las Policies y Gates que ya escribimos con los Services. Aquí no se
 * comprueba ningún rol: si la Policy lo niega, el enlace no aparece, y si
 * mañana cambia la regla, cambia sola en los dos sitios a la vez.
 *
 * Como el rol activo ya viene aplicado por el middleware, esto devuelve lo
 * que el usuario puede ver CON EL ROL QUE TIENE PUESTO, no con todos los
 * que le pertenecen.
 */
final class MenuDelPanel
{
    /**
     * @return list<SeccionDelPanel>
     */
    public function todas(): array
    {
        return [
            new SeccionDelPanel(
                clave: 'inicio',
                etiqueta: 'Escritorio',
                ruta: 'panel.inicio',
                icono: 'M3 12l9-9 9 9M5 10v10h14V10',
            ),
            new SeccionDelPanel(
                clave: 'calendario',
                etiqueta: 'Calendario',
                ruta: 'panel.calendario',
                icono: 'M8 7V3m8 4V3M3 11h18M5 5h14v16H5z',
                permiso: 'verCalendario',
                sobre: Solicitud::class,
            ),
            // El docente y quien revisa ven pantallas distintas, así que son
            // dos entradas con permisos distintos, no una con un condicional.
            new SeccionDelPanel(
                clave: 'mis-solicitudes',
                etiqueta: 'Mis solicitudes',
                ruta: 'panel.mis-solicitudes',
                icono: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6l4 4v12a2 2 0 01-2 2z',
                permiso: 'create',
                sobre: Solicitud::class,
            ),
            new SeccionDelPanel(
                clave: 'solicitudes',
                etiqueta: 'Solicitudes',
                ruta: 'panel.solicitudes',
                icono: 'M3 8h18M3 12h18M3 16h10',
                permiso: 'verBandeja',
                sobre: Solicitud::class,
            ),
            new SeccionDelPanel(
                clave: 'preparaciones',
                etiqueta: 'Preparaciones',
                ruta: 'panel.preparaciones',
                icono: 'M4 6h16M4 12h16M4 18h10',
                permiso: 'viewAny',
                sobre: Preparacion::class,
            ),
            new SeccionDelPanel(
                clave: 'evaluaciones',
                etiqueta: 'Evaluaciones',
                ruta: 'panel.evaluaciones',
                icono: 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11',
                permiso: 'viewAny',
                sobre: Evaluacion::class,
            ),
            new SeccionDelPanel(
                clave: 'inventario',
                etiqueta: 'Inventario',
                ruta: 'panel.inventario',
                icono: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                permiso: 'viewAny',
                sobre: ItemInventario::class,
            ),
            new SeccionDelPanel(
                clave: 'consentimientos',
                etiqueta: 'Consentimientos',
                ruta: 'panel.consentimientos',
                icono: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                permiso: 'viewAny',
                sobre: ConsentimientoEstudiante::class,
            ),
            new SeccionDelPanel(
                clave: 'mi-consentimiento',
                etiqueta: 'Mi consentimiento',
                ruta: 'panel.mi-consentimiento',
                icono: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
                permiso: 'create',
                sobre: ConsentimientoEstudiante::class,
            ),
            new SeccionDelPanel(
                clave: 'plantillas-consentimiento',
                etiqueta: 'Plantillas de consentimiento',
                ruta: 'panel.plantillas-consentimiento',
                icono: 'M12 4v16m8-8H4',
                permiso: 'create',
                sobre: ConsentimientoPlantilla::class,
            ),
            new SeccionDelPanel(
                clave: 'reportes',
                etiqueta: 'Reportes',
                ruta: 'panel.reportes',
                icono: 'M9 19v-6m4 6V7m4 12v-9M4 21h16a1 1 0 001-1V4a1 1 0 00-1-1H4a1 1 0 00-1 1v16a1 1 0 001 1z',
                permiso: 'generarReportes',
            ),
        ];
    }

    /**
     * @return list<SeccionDelPanel>
     */
    public function visiblesPara(User $usuario): array
    {
        return array_values(array_filter(
            $this->todas(),
            static fn (SeccionDelPanel $seccion): bool => $seccion->visiblePara($usuario),
        ));
    }
}
