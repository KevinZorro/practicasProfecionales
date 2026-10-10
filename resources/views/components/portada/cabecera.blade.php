@props([
    // ['#ancla' => 'Texto'] de las secciones que existen en la página.
    'enlaces' => [],
])

{{--
    Cabecera blanca y fina, sin fijar al hacer scroll. En móvil los enlaces
    se pliegan en un <details>: funciona sin JavaScript y abre sin
    animación (el brief limita las animaciones a las seis de la portada).
--}}
<header class="relative z-20 border-b border-black/[0.06] bg-white">
    <div class="mx-auto flex h-16 max-w-[1180px] items-center gap-4 px-4 sm:px-6 lg:gap-8 lg:px-8">
        <a href="{{ route('inicio.publico') }}"
           class="flex min-w-0 items-center gap-3 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">
            <img src="{{ asset('marca/ufps-simbolo.webp') }}" alt="" width="32" height="32" class="size-8 shrink-0">
            {{-- En móvil el nombre parte en dos líneas y la facultad se omite:
                 cortarlo con puntos suspensivos borraba la mitad del nombre. --}}
            <span class="min-w-0 leading-tight">
                <span class="block text-[15px] font-semibold tracking-[-0.01em]">Laboratorio de Simulación Clínica</span>
                <span class="hidden truncate text-xs text-portada-gris sm:block">UFPS · Facultad de Ciencias de la Salud</span>
            </span>
        </a>

        @if ($enlaces !== [])
            <nav aria-label="Secciones de la portada" class="ml-auto hidden lg:block">
                <ul class="flex items-center gap-7 text-sm">
                    @foreach ($enlaces as $ancla => $texto)
                        <li>
                            <a href="{{ $ancla }}"
                               class="text-portada-tinta/80 transition-colors duration-150 hover:text-portada-tinta focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo">{{ $texto }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <a href="{{ route('panel.inicio') }}"
           @class([
               'shrink-0 rounded-full bg-portada-rojo px-4 py-2 text-sm font-semibold text-white transition-colors duration-150 hover:bg-portada-rojo-hondo focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo',
               'ml-auto lg:ml-0' => $enlaces !== [],
               'ml-auto' => $enlaces === [],
           ])>
            Ingresar
        </a>

        @if ($enlaces !== [])
            <details class="group lg:hidden" data-menu>
                <summary class="-mr-2 flex size-10 cursor-pointer list-none items-center justify-center rounded-full text-portada-tinta hover:bg-portada-niebla focus-visible:outline focus-visible:outline-2 focus-visible:outline-portada-rojo [&::-webkit-details-marker]:hidden">
                    <span class="sr-only">Menú</span>
                    <svg class="size-5 group-open:hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M3 7h14M3 13h14" stroke-linecap="round"/>
                    </svg>
                    <svg class="hidden size-5 group-open:block" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                    </svg>
                </summary>
                <nav aria-label="Secciones de la portada" class="absolute inset-x-0 top-16 border-b border-black/[0.06] bg-white px-4 pb-6 pt-2 sm:px-6">
                    <ul class="mx-auto max-w-[1180px]">
                        @foreach ($enlaces as $ancla => $texto)
                            <li class="border-b border-portada-linea/70 last:border-0">
                                <a href="{{ $ancla }}" class="block py-4 text-2xl font-semibold tracking-[-0.02em]">{{ $texto }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </details>
        @endif
    </div>
</header>
