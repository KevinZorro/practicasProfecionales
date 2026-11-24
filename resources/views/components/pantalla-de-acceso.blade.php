{{--
    Pantalla de acceso, con el lenguaje de la portada (DESIGN.md): blanco y
    niebla, Onest en peso 800 para el titular, la línea de pulso en rojo y
    la pieza blanca con lo que haya que hacer. En escritorio, la marca y el
    titular a la izquierda y la pieza a la derecha; en el celular, apilados.
    La usan la entrada (contraseña y Google), recuperar la contraseña y, en
    local, el acceso de desarrollo. Sin riel ni menú: todavía no hay sesión.
    Sin animaciones: las seis de la portada son las únicas acordadas.
--}}
@props(['titulo', 'bajada' => null])

<!DOCTYPE html>
<html lang="es" class="h-full bg-niebla">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f5f7">
    <meta name="robots" content="noindex">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('marca/apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/onest/onest-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-niebla font-portada text-[15px] text-portada-tinta antialiased">

<div class="mx-auto flex min-h-screen w-full max-w-[1180px] flex-col px-4 sm:px-6 lg:px-8">
    <header class="flex items-center justify-between py-6">
        <a href="{{ route('inicio.publico') }}" class="flex items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
            <img src="{{ asset('marca/ufps-simbolo.webp') }}" alt="" class="size-10 rounded-[9px]" width="40" height="40">
            <span class="text-[15px] font-semibold leading-tight">
                Laboratorio de Simulación Clínica
                <span class="mt-0.5 block text-[13px] font-normal text-portada-gris">UFPS · Facultad de Ciencias de la Salud</span>
            </span>
        </a>
        <a href="{{ route('inicio.publico') }}" class="hidden text-[15px] font-semibold text-portada-gris underline-offset-4 hover:text-portada-tinta hover:underline sm:inline">Volver a la portada</a>
    </header>

    <main class="grid flex-1 items-center gap-10 py-8 lg:grid-cols-[1fr_minmax(0,28rem)] lg:gap-16 lg:py-12">
        <div class="text-center lg:text-left">
            <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-portada-rojo">Plataforma del laboratorio</p>
            <h1 class="mx-auto mt-4 max-w-[14ch] text-balance text-[clamp(2.25rem,1.3rem+3.6vw,4rem)] font-extrabold leading-[1.04] tracking-[-0.034em] lg:mx-0">
                {{ $titulo }}
            </h1>
            @if ($bajada)
                <p class="mx-auto mt-5 max-w-[32rem] text-pretty text-base leading-relaxed text-portada-gris sm:text-lg lg:mx-0">{{ $bajada }}</p>
            @endif
            <x-portada.pulso class="mt-8 hidden lg:flex" />
        </div>

        <section class="w-full rounded-[28px] bg-white p-6 sm:p-9">
            {{ $slot }}
        </section>
    </main>

    <footer class="py-6 text-center text-[13px] text-portada-gris lg:text-left">
        <a href="{{ route('inicio.publico') }}" class="font-semibold underline-offset-4 hover:text-portada-tinta hover:underline sm:hidden">Volver a la portada</a>
        <span class="hidden sm:inline">Universidad Francisco de Paula Santander · Cúcuta</span>
    </footer>
</div>

{{-- Los botones «Mostrar» de x-campo-de-contrasena: un solo escuchador. --}}
<script>
    document.querySelectorAll('[data-mostrar-contrasena]').forEach(function (boton) {
        var campo = document.getElementById(boton.dataset.mostrarContrasena);
        boton.hidden = false;
        boton.addEventListener('click', function () {
            var mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            boton.textContent = mostrar ? 'Ocultar' : 'Mostrar';
            boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
            campo.focus();
        });
    });
</script>
</body>
</html>
