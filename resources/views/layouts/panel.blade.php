{{--
    Layout de las pantallas internas.

    El menú lateral, el rol activo y los roles disponibles los comparte el
    middleware EstablecerRolActivo, ya resueltos: aquí no se consulta ningún
    permiso ni se comprueba ningún rol, solo se pinta lo que llega.

    El plegado del menú en móvil va con un checkbox y las variantes "peer" de
    Tailwind, sin una línea de JavaScript. El administrativo lo abre desde el
    celular mientras monta el escenario, a veces con mala señal (RNF10).

    Una sola cabecera sirve a los dos tamaños: arriba del todo y a lo ancho,
    con el menú debajo. Así el desplegable aparece justo bajo el botón que lo
    abre, y no hay que repetir el marcado por punto de ruptura.
--}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('titulo')@yield('titulo') · @endif{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 font-sans text-gray-900 antialiased">

<div class="flex h-full flex-col">

    {{-- Cabecera --}}
    <header class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-200 bg-white px-4 py-3 md:flex-nowrap">
        <label for="alternar-menu"
               class="-ml-2 cursor-pointer rounded-md p-2 text-gray-600 hover:bg-gray-100 md:hidden"
               aria-label="Mostrar u ocultar el menú">
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
            </svg>
        </label>

        <span class="hidden shrink-0 text-sm font-semibold leading-tight text-gray-900 md:block md:w-56">
            Laboratorio de Simulación Clínica
        </span>

        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-gray-900">{{ auth()->user()->nombre }}</p>
            <p class="truncate text-xs text-gray-500">{{ $rolActivo->etiqueta() }}</p>
        </div>

        @if (count($rolesDisponibles) > 1)
            {{--
                En móvil el selector baja a su propia fila a todo el ancho.
                Apretarlo junto al nombre dejaba el nombre y el rol recortados
                a tres letras, y el rol activo tiene que leerse entero.
            --}}
            <form method="POST" action="{{ route('panel.rol-activo') }}"
                  class="order-last flex w-full items-center gap-2 md:order-none md:w-auto">
                @csrf
                <label for="rol" class="sr-only">Cambiar de rol</label>
                <select name="rol" id="rol"
                        class="min-w-0 flex-1 rounded-md border-gray-300 py-1.5 pl-2 pr-8 text-sm focus:border-sky-600 focus:ring-sky-600 md:flex-none">
                    @foreach ($rolesDisponibles as $rol)
                        <option value="{{ $rol->value }}" @selected($rol === $rolActivo)>{{ $rol->etiqueta() }}</option>
                    @endforeach
                </select>
                <x-boton variante="secundario" class="shrink-0 px-3 py-1.5">Cambiar</x-boton>
            </form>
        @endif

        <form method="POST" action="{{ route('salir') }}" class="shrink-0">
            @csrf
            <x-boton variante="secundario" class="px-3 py-1.5">Salir</x-boton>
        </form>
    </header>

    <div class="flex min-h-0 flex-1 flex-col md:flex-row">

        {{--
            El checkbox tiene que ser hermano del <nav> para que peer-checked
            lo alcance: la variante compila al combinador de hermanos, que no
            atraviesa niveles.
        --}}
        <input type="checkbox" id="alternar-menu" class="peer sr-only">

        <nav aria-label="Secciones del sistema"
             class="hidden shrink-0 border-b border-gray-200 bg-white peer-checked:block md:block md:w-64 md:overflow-y-auto md:border-b-0 md:border-r">
            <ul class="space-y-1 p-2 md:py-4">
                @foreach ($seccionesDelMenu as $seccion)
                    @php($activa = request()->routeIs($seccion->ruta))
                    <li>
                        <a href="{{ route($seccion->ruta) }}"
                           @class([
                               'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium',
                               'bg-sky-50 text-sky-800' => $activa,
                               'text-gray-700 hover:bg-gray-100' => ! $activa,
                           ])
                           @if ($activa) aria-current="page" @endif>
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $seccion->icono }}"/>
                            </svg>
                            {{ $seccion->etiqueta }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <main class="min-w-0 flex-1 px-4 py-6 md:overflow-y-auto">
            @if (session('estado'))
                <p class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-600/20"
                   role="status">
                    {{ session('estado') }}
                </p>
            @endif

            @hasSection('titulo')
                <h1 class="mb-4 text-xl font-semibold text-gray-900">@yield('titulo')</h1>
            @endif

            @yield('contenido')
        </main>
    </div>
</div>

</body>
</html>
