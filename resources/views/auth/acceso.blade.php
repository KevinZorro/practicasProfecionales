{{--
    Entrada a la plataforma (RF18). Una sola vía: la cuenta institucional de
    Google. Es provisional en su aspecto, no en su función: cuando se apruebe
    el diseño de la landing, el botón puede vivir allí y esta pantalla queda
    para mostrar por qué no se pudo entrar.
--}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-gray-50 px-4 font-sans text-gray-900 antialiased">

<div class="w-full max-w-md">
    <x-tarjeta titulo="Laboratorio de Simulación Clínica">
        <p class="mb-4 text-sm text-gray-700">
            Entra con tu cuenta institucional de Google{{ $dominio ? ", la que termina en @{$dominio}" : '' }}.
        </p>

        @error('acceso')
            <p class="mb-4 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
                {{ $message }}
            </p>
        @enderror

        <x-boton :href="route('acceso.google')" class="w-full">Entrar con Google</x-boton>
    </x-tarjeta>
</div>

</body>
</html>
