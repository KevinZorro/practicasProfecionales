import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Vite corre dentro del contenedor "node": debe escuchar en todas las
        // interfaces para que el navegador del host lo alcance.
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        // Los cambios llegan por un volumen montado; sin sondeo Vite no los detecta.
        watch: {
            usePolling: true,
        },
    },
});
