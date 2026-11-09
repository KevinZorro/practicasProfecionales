{{--
    Si la sesión caduca con una pantalla abierta, la siguiente acción de
    Livewire responde 419 y Livewire lo pregunta en inglés con su propio
    confirm() («This page has expired»), y después pinta la página de error
    dentro de un recuadro. Aquí se pregunta en español y nada más: al
    recargar, quien ya no tiene sesión entra con Google y vuelve a la misma
    pantalla. Va en el panel y en /admin (AdminPanelProvider).
--}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status !== 419) {
                    return;
                }

                preventDefault();

                if (window.confirm('Tu sesión caducó por inactividad y lo último que hiciste no se guardó.\n¿Recargar la página para volver a entrar?')) {
                    window.location.reload();
                }
            });
        });
    });
</script>
