{{--
    Las páginas de error, con la marca, qué pasó en palabras de la persona
    y una salida. Las genéricas de Laravel decían «Prohibido» o «No
    encontrado» y nada más: no había por dónde volver.

    Se llama como la plantilla de Laravel (errors::minimal) a propósito: así
    también sus páginas que no tenemos (401, 402, 429) salen con esta forma.
    Cada página da su título, su código, su mensaje y, si quiere, su ayuda y
    sus propias acciones.

    Sin consultas a la base: la de 500 puede pintarse con la base caída.
    La salida depende de la dirección, no de la sesión, que en un 404 de
    una ruta inexistente ni siquiera se ha abierto: dentro del panel lleva a
    «Hoy»; fuera, a la portada.
--}}
@php
    $enElPanel = request()->is('panel', 'panel/*', 'admin', 'admin/*', 'livewire/*');
@endphp
<!DOCTYPE html>
<html lang="es" class="h-full bg-niebla">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f5f7">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-niebla px-4 py-10 font-sans text-[15px] text-gray-900 antialiased">

<main class="w-full max-w-[28rem]">
    <a href="{{ url('/') }}" class="mx-auto mb-8 flex w-fit items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-rojo">
        <img src="{{ asset('marca/ufps-simbolo.webp') }}" alt="" class="size-11 rounded-[9px]" width="44" height="44">
        <span class="text-[15px] font-semibold leading-tight">
            Laboratorio de Simulación Clínica
            <span class="mt-0.5 block text-[13px] font-normal text-gray-500">UFPS · Facultad de Ciencias de la Salud</span>
        </span>
    </a>

    <section class="rounded-2xl bg-white p-6 sm:p-8">
        <p class="text-[13px] font-semibold text-gray-500">Error @yield('code')</p>
        <h1 class="mt-1 text-balance text-[1.625rem] font-extrabold leading-[1.15] tracking-[-0.025em]">@yield('title')</h1>
        <p class="mt-3 text-pretty text-[15px] leading-snug text-gray-700">@yield('message')</p>

        @hasSection('ayuda')
            <div class="mt-3 text-pretty text-[14px] leading-snug text-gray-600">@yield('ayuda')</div>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            @hasSection('acciones')
                @yield('acciones')
            @elseif ($enElPanel)
                <x-boton :href="url('/panel')">Ir a «Hoy»</x-boton>
            @else
                <x-boton :href="url('/')">Ir a la portada</x-boton>
            @endif
        </div>
    </section>
</main>

</body>
</html>
