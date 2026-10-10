{{--
    Layout de las páginas públicas: la portada y el detalle de cada escenario.

    El script en línea decide, antes de pintar, si la página anima: solo con
    IntersectionObserver y sin prefers-reduced-motion. Sin la clase "animar"
    nada empieza oculto, así que el contenido se ve completo aunque
    portada.js no llegue; y si tarda demasiado, la clase se retira sola.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('titulo', config('app.name'))</title>
    <meta name="description" content="@yield('descripcion', 'Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud, Universidad Francisco de Paula Santander.')">
    <meta name="theme-color" content="#ffffff">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_CO">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="@yield('titulo', config('app.name'))">
    <meta property="og:description" content="@yield('descripcion', 'Laboratorio de Simulación Clínica de la Facultad de Ciencias de la Salud, Universidad Francisco de Paula Santander.')">
    <meta property="og:image" content="{{ asset('marca/apple-touch-icon.png') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('marca/apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/onest/onest-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    <script>
        (function () {
            var raiz = document.documentElement;
            if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            raiz.classList.add('animar');
            window.setTimeout(function () {
                if (!raiz.hasAttribute('data-portada-lista')) {
                    raiz.classList.remove('animar');
                }
            }, 2500);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/portada.js'])
</head>
<body class="bg-white font-portada text-portada-tinta antialiased selection:bg-portada-rojo/15 selection:text-portada-tinta">

<a href="#contenido"
   class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-portada-tinta focus:px-5 focus:py-3 focus:text-sm focus:font-semibold focus:text-white">
    Saltar al contenido
</a>

<x-portada.cabecera :enlaces="$enlaces ?? []" />

<main id="contenido">
    @yield('contenido')
</main>

<x-portada.pie />

</body>
</html>
