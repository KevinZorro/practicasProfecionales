{{--
    Layout de las pantallas internas (DESIGN.md, «Panel»).

    El menú lateral, el rol activo y los roles disponibles los comparte el
    middleware EstablecerRolActivo, ya resueltos: aquí no se consulta ningún
    permiso ni se comprueba ningún rol, solo se pinta lo que llega.

    El riel va en tinta y queda fijo en escritorio. En el celular y la tableta
    se abre desde la barra superior con un checkbox y las variantes "peer" de
    Tailwind, sin una línea de JavaScript: el administrativo lo abre desde el
    celular mientras monta el escenario, a veces con mala señal (RNF10).

    Cada pantalla da su título; puede añadir una descripción, sus acciones y
    un enlace de vuelta. El expediente de una solicitud pinta su propia
    cabecera y pide «sin-cabecera».
--}}
<!DOCTYPE html>
<html lang="es" class="bg-niebla">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d1d1f">

    <title>@hasSection('titulo')@yield('titulo') · @endif{{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('marca/apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/onest/onest-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css'])

    {{--
        Livewire trae Alpine, y por su cuenta solo inyecta sus scripts en las
        páginas que montan un componente. «Hoy» y el calendario no montan
        ninguno, así que el aviso de la acción se quedaba pegado: sin Alpine
        no se iba solo ni se cerraba con la equis. Se cargan siempre.
    --}}
    @livewireStyles
</head>
<body class="min-h-screen bg-niebla font-sans text-[15px] text-gray-900 antialiased"
      x-data="{ menu: false }" @keydown.escape.window="menu = false">

<a href="#contenido"
   class="sr-only rounded-full bg-gray-900 px-4 py-2 text-[14px] font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60]">
    Saltar al contenido
</a>

{{--
    El checkbox solo guarda el estado para el CSS: tiene que ser hermano de lo
    que abre y cierra para que peer-checked lo alcance (la variante compila
    al combinador de hermanos, que no atraviesa niveles). No es un control:
    quien lo maneja son los botones, con nombre, aria-expanded y foco visible
    (QA-12), y Alpine mantiene la casilla al día.
--}}
<input type="checkbox" id="alternar-menu" class="peer sr-only" aria-hidden="true" tabindex="-1" x-model="menu">

{{-- Barra superior: celular y tableta. --}}
<header class="sticky top-0 z-30 flex h-14 items-center gap-2 bg-gray-900 px-2 text-white lg:hidden">
    <button type="button" @click="menu = true" aria-controls="menu-del-panel" aria-expanded="false" :aria-expanded="menu.toString()"
            class="flex size-11 cursor-pointer items-center justify-center rounded-full hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            aria-label="Abrir el menú">
        <svg class="size-[22px]" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
        </svg>
    </button>
    <a href="{{ route('panel.inicio') }}" class="flex min-w-0 items-center gap-2.5 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo">
        <img src="{{ asset('marca/ufps-simbolo.webp') }}" alt="" class="size-7 shrink-0 rounded-[6px]" width="28" height="28">
        <span class="truncate text-[15px] font-semibold">Laboratorio de Simulación</span>
    </a>
</header>

{{-- Velo detrás del riel abierto en el celular: tocarlo lo cierra. --}}
<div @click="menu = false" class="fixed inset-0 z-40 hidden bg-black/40 peer-checked:block lg:!hidden" aria-hidden="true"></div>

{{-- Riel de navegación. --}}
<aside id="menu-del-panel" class="fixed inset-y-0 left-0 z-50 hidden w-[17rem] max-w-[86vw] flex-col bg-gray-900 text-white peer-checked:flex lg:flex lg:w-[15.5rem]">
    <div class="flex items-center gap-3 px-5 pb-4 pt-5">
        <a href="{{ route('panel.inicio') }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-rojo">
            <img src="{{ asset('marca/ufps-simbolo.webp') }}" alt="" class="size-9 shrink-0 rounded-[7px]" width="36" height="36">
            <span class="min-w-0 text-[14px] font-semibold leading-tight">
                Laboratorio de Simulación Clínica
                <span class="mt-0.5 block text-[12px] font-normal text-white/55">UFPS · Ciencias de la Salud</span>
            </span>
        </a>
        <button type="button" @click="menu = false"
                class="-mr-2 flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-white/70 hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white lg:hidden"
                aria-label="Cerrar el menú">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <nav aria-label="Secciones del sistema" class="min-h-0 flex-1 overflow-y-auto px-3 pb-4 [scrollbar-color:theme(colors.gray.700)_transparent]">
        <ul class="space-y-0.5">
            @foreach ($seccionesDelMenu as $seccion)
                {{-- La sección está activa también en sus páginas hijas
                     (la edición de un ítem, el expediente de una solicitud). --}}
                @php($activa = request()->routeIs(...$seccion->rutasDondeEstaActiva()))
                <li>
                    <a href="{{ route($seccion->ruta) }}"
                       @class([
                           'group flex items-center gap-3 rounded-[10px] px-3 py-2 text-[14px] transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-rojo',
                           'bg-white/[0.1] font-semibold text-white' => $activa,
                           'font-medium text-white/70 hover:bg-white/[0.06] hover:text-white' => ! $activa,
                       ])
                       @if ($activa) aria-current="page" @endif>
                        <svg @class(['size-5 shrink-0', 'text-rojo' => $activa, 'text-white/50 group-hover:text-white/80' => ! $activa])
                             fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $seccion->icono }}"/>
                        </svg>
                        <span class="min-w-0 leading-snug">{{ $seccion->etiqueta }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Quien está dentro, con qué rol y cómo salir. --}}
    <div class="border-t border-white/10 p-3">
        <div class="flex items-center gap-3 px-2 py-1.5">
            <x-iniciales :nombre="auth()->user()->nombre" class="size-9 bg-white/10 text-[13px]" />
            <div class="min-w-0">
                <p class="truncate text-[14px] font-semibold">{{ auth()->user()->nombre }}</p>
                <p class="truncate text-[12px] text-white/60">{{ $rolActivo->etiqueta() }}</p>
            </div>
        </div>

        {{-- Rol prestado: que no se descubra al fallar un permiso (RF63). --}}
        @if ($vigenciaDelRolActivo)
            <p class="mx-2 mt-1.5 rounded-lg bg-amber-300/15 px-2.5 py-1.5 text-[12px] font-medium text-amber-200">
                Rol temporal · hasta el {{ $vigenciaDelRolActivo->format('d/m/Y') }}
            </p>
        @endif

        @if (count($rolesDisponibles) > 1)
            <form method="POST" action="{{ route('panel.rol-activo') }}" class="mt-2 flex items-center gap-2 px-2">
                @csrf
                <label for="rol" class="sr-only">Cambiar de rol</label>
                <select name="rol" id="rol"
                        class="min-w-0 flex-1 appearance-none rounded-[10px] border border-white/15 bg-white/[0.06] px-3 py-2 text-[13px] text-white focus:border-white/40 focus:outline-none focus:ring-1 focus:ring-white/40 [&>option]:text-gray-900">
                    @foreach ($rolesDisponibles as $rol)
                        @php($vigencia = $vigenciaDeLosRoles[$rol->value] ?? null)
                        <option value="{{ $rol->value }}" @selected($rol === $rolActivo)>
                            {{ $rol->etiqueta() }}@if ($vigencia) · temporal hasta {{ $vigencia->format('d/m/Y') }}@endif
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                        class="shrink-0 rounded-full bg-white px-3 py-2 text-[13px] font-semibold text-gray-900 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo">
                    Cambiar
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('salir') }}" class="mt-1">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-3 rounded-[10px] px-3 py-2 text-[14px] font-medium text-white/70 hover:bg-white/[0.06] hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-rojo">
                <svg class="size-5 shrink-0 text-white/50" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                </svg>
                Salir
            </button>
        </form>
    </div>
</aside>

<div class="lg:pl-[15.5rem]">
    <main id="contenido" class="mx-auto w-full max-w-[76rem] px-4 pb-24 pt-5 sm:px-6 sm:pt-8 lg:px-10 lg:pt-10">
        @if (View::hasSection('titulo') && ! View::hasSection('sin-cabecera'))
            <header class="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4 sm:mb-8">
                <div class="min-w-0">
                    @hasSection('volver')
                        <div class="mb-2">@yield('volver')</div>
                    @endif
                    <h1 class="text-balance text-[1.625rem] font-extrabold leading-[1.15] tracking-[-0.025em] text-gray-900 sm:text-[1.75rem]">@yield('titulo')</h1>
                    @hasSection('descripcion')
                        <p class="mt-1.5 max-w-[62ch] text-pretty text-[15px] leading-snug text-gray-500">@yield('descripcion')</p>
                    @endif
                </div>
                @hasSection('acciones')
                    <div class="flex flex-wrap items-center gap-2">@yield('acciones')</div>
                @endif
            </header>
        @endif

        @yield('contenido')

        <p class="mt-10 text-[13px] text-gray-600">
            <x-politica-de-datos class="underline underline-offset-4 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo" />
        </p>
    </main>
</div>

{{--
    Aviso de la acción: lo que acaba de pasar, arriba, debajo de la barra en
    el celular. Arriba y no abajo porque abajo van las barras de decisión del
    expediente, que se quedan pegadas al pie. Llega de la sesión al cargar la
    página o como evento cuando la pantalla se queda
    (App\Livewire\Concerns\AvisaDelResultado). Se va solo a los siete
    segundos y se puede cerrar antes.
--}}
<div x-data="{
         mensaje: @js(session('estado')),
         reloj: null,
         mostrar(texto) {
             this.mensaje = texto;
             clearTimeout(this.reloj);
             this.reloj = setTimeout(() => this.mensaje = null, 7000);
         },
     }"
     x-init="mensaje && mostrar(mensaje)"
     x-on:aviso-de-la-accion.window="mostrar($event.detail.mensaje)"
     class="pointer-events-none fixed inset-x-0 top-0 z-[45] flex justify-center px-4 pt-[4.25rem] lg:left-[15.5rem] lg:pt-5"
     role="status" aria-live="polite">
    <div x-show="mensaje"
         x-transition:enter="transition duration-200 ease-llegada motion-reduce:transition-none"
         x-transition:enter-start="-translate-y-2 opacity-0"
         x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none"
         x-transition:leave-end="opacity-0"
         @unless (session('estado')) style="display: none" @endunless
         class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl bg-gray-900 py-3 pl-4 pr-2 text-[14px] leading-snug text-white shadow-[0_10px_30px_-8px_rgb(0_0_0/0.35)]">
        <svg class="mt-px size-5 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
        </svg>
        <p class="min-w-0 flex-1 py-px font-medium" x-text="mensaje">{{ session('estado') }}</p>
        <button type="button" x-on:click="mensaje = null"
                class="-my-1 flex size-8 shrink-0 items-center justify-center rounded-full text-white/60 hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-white"
                aria-label="Cerrar el aviso">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

@livewireScripts
</body>
</html>
