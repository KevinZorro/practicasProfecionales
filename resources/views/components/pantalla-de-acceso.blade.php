{{--
    Pantalla de acceso: la marca y una pieza blanca con lo que haya que
    hacer para entrar. La usan la entrada con Google (RF18) y, en local, el
    acceso de desarrollo. Sin riel ni menú: todavía no hay sesión.
--}}
@props(['titulo'])

<!DOCTYPE html>
<html lang="es" class="h-full bg-niebla">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f5f7">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('marca/apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/onest/onest-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-niebla px-4 py-10 font-sans text-[15px] text-gray-900 antialiased">

<main class="w-full max-w-[26rem]">
    <a href="{{ route('inicio.publico') }}" class="mx-auto mb-8 flex w-fit items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-rojo">
        <img src="{{ asset('marca/ufps-simbolo.webp') }}" alt="" class="size-11 rounded-[9px]" width="44" height="44">
        <span class="text-[15px] font-semibold leading-tight">
            Laboratorio de Simulación Clínica
            <span class="mt-0.5 block text-[13px] font-normal text-gray-500">UFPS · Facultad de Ciencias de la Salud</span>
        </span>
    </a>

    <section class="rounded-2xl bg-white p-6 sm:p-8">
        <h1 class="text-[1.625rem] font-extrabold leading-[1.15] tracking-[-0.025em]">{{ $titulo }}</h1>
        <div class="mt-3">{{ $slot }}</div>
    </section>

    <p class="mt-6 text-center">
        <a href="{{ route('inicio.publico') }}" class="text-[14px] font-semibold text-gray-500 hover:text-gray-900">Volver a la portada</a>
    </p>
</main>

</body>
</html>
