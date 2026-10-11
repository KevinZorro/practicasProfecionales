import defaultTheme from 'tailwindcss/defaultTheme';

/*
 * La marca: los mismos colores en la portada y en el panel (DESIGN.md). El
 * rojo institucional de la UFPS es acento: botones, detalles, el estado
 * actual y lo que está en juego; nunca el fondo de una sección.
 */
const marca = {
    tinta: '#1d1d1f',
    gris: '#6e6e73',
    linea: '#d2d2d7',
    niebla: '#f5f5f7',
    rojo: '#d30f23',
    'rojo-hondo': '#ad0c1c',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/livewire/livewire/src/Features/SupportPagination/views/*tailwind.blade.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './app/Support/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Onest se sirve desde public/fonts (DESIGN.md): una sola
                // familia para la portada y el panel.
                sans: ['Onest', ...defaultTheme.fontFamily.sans],
                portada: ['Onest', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                portada: marca,
                tinta: marca.tinta,
                niebla: marca.niebla,
                linea: marca.linea,
                // El rojo de la marca con su escala, para los estados que lo
                // necesitan claro (fondos de aviso) u oscuro (texto sobre ellos).
                rojo: {
                    DEFAULT: marca.rojo,
                    hondo: marca['rojo-hondo'],
                    50: '#fef2f3',
                    100: '#fde3e5',
                    200: '#fbc6cb',
                    300: '#f597a0',
                    400: '#ec5d6b',
                    500: '#e02b3c',
                    600: marca.rojo,
                    700: marca['rojo-hondo'],
                    800: '#8e0d1b',
                    900: '#750f1b',
                },
                // Los grises de Tailwind, en la escala fría de la marca: el
                // 50 es la niebla, el 300 el filete, el 500 el gris de apoyo
                // (5,1:1 sobre blanco, el más claro admitido para texto) y el
                // 900 la tinta.
                gray: {
                    50: marca.niebla,
                    100: '#ededf0',
                    200: '#e2e2e7',
                    300: marca.linea,
                    400: '#a1a1a6',
                    500: marca.gris,
                    600: '#5c5c61',
                    700: '#3f3f43',
                    800: '#2a2a2d',
                    900: marca.tinta,
                    950: '#111112',
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
