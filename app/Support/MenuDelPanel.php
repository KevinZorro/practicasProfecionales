<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AsignacionDeRol;
use App\Models\Evaluacion;
use App\Models\FormatoConfidencialidad;
use App\Models\ItemInventario;
use App\Models\ListaDeReposicion;
use App\Models\PlantillaConfidencialidad;
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
                clave: 'reposicion',
                etiqueta: 'Insumos por pedir',
                ruta: 'panel.reposicion',
                icono: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                permiso: 'viewAny',
                sobre: ListaDeReposicion::class,
            ),
            new SeccionDelPanel(
                clave: 'formatos-confidencialidad',
                etiqueta: 'Formatos de confidencialidad',
                ruta: 'panel.formatos-confidencialidad',
                icono: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                permiso: 'viewAny',
                sobre: FormatoConfidencialidad::class,
            ),
            new SeccionDelPanel(
                clave: 'mi-formato',
                etiqueta: 'Mi formato',
                ruta: 'panel.mi-formato',
                icono: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
                permiso: 'create',
                sobre: FormatoConfidencialidad::class,
            ),
            new SeccionDelPanel(
                clave: 'plantillas-confidencialidad',
                etiqueta: 'Plantillas de confidencialidad',
                ruta: 'panel.plantillas-confidencialidad',
                icono: 'M12 4v16m8-8H4',
                permiso: 'create',
                sobre: PlantillaConfidencialidad::class,
            ),
            new SeccionDelPanel(
                clave: 'usuarios',
                etiqueta: 'Roles de usuarios',
                ruta: 'panel.usuarios',
                icono: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                permiso: 'viewAny',
                sobre: AsignacionDeRol::class,
            ),
            // Lleva fuera del panel de Livewire: las pantallas del ADMIN están
            // en Filament (/admin), con su propia navegación.
            new SeccionDelPanel(
                clave: 'administracion',
                etiqueta: 'Administración',
                ruta: 'filament.admin.pages.dashboard',
                icono: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
                permiso: 'accederAlPanelDelAdmin',
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
