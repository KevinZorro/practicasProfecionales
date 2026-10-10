import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/livewire/livewire/src/Features/SupportPagination/views/*tailwind.blade.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // La portada pública. Onest se sirve desde public/fonts (DESIGN.md).
                portada: ['Onest', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // La portada pública. El rojo institucional de la UFPS es solo
                // acento: botones, enlaces, detalles y cifras, nunca un fondo.
                portada: {
                    tinta: '#1d1d1f',
                    gris: '#6e6e73',
                    linea: '#d2d2d7',
                    niebla: '#f5f5f7',
                    rojo: '#d30f23',
                    'rojo-hondo': '#ad0c1c',
                },
            },
            transitionTimingFunction: {
                // Salida fuerte: arranca rápido y frena largo. La de la portada.
                llegada: 'cubic-bezier(0.23, 1, 0.32, 1)',
            },
        },
    },
    plugins: [],
};
