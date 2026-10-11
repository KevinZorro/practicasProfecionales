{{--
    Pieza blanca y plana sobre el fondo niebla del panel: 16 px de esquina,
    sin sombra ni borde, como las piezas de la portada en pequeño. El título
    es opcional; el pie es para las acciones de la pieza.
--}}
@props(['titulo' => null, 'descripcion' => null, 'pie' => null])

<section {{ $attributes->class('min-w-0 rounded-2xl bg-white') }}>
    @if ($titulo)
        <header class="px-4 pt-4 sm:px-5 sm:pt-5">
            <h2 class="text-[17px] font-bold leading-snug tracking-[-0.01em] text-gray-900">{{ $titulo }}</h2>
            @if ($descripcion)
                <p class="mt-1 text-[14px] leading-snug text-gray-500">{{ $descripcion }}</p>
            @endif
        </header>
    @endif

    <div class="p-4 sm:p-5">
        {{ $slot }}
    </div>

    @if ($pie)
        <footer class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-gray-100 px-4 py-3 text-[14px] text-gray-600 sm:px-5">
            {{ $pie }}
        </footer>
    @endif
</section>
