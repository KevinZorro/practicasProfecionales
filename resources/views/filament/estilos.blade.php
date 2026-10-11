{{--
    Lo único de la marca que Filament no deja poner con su API de colores:
    el ícono de la sección activa en rojo, como en el riel del panel. Va en
    línea por el gancho de estilos (AdminPanelProvider) para no compilar un
    tema propio de Filament solo por esto.
--}}
<style>
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: #d30f23;
    }
</style>
