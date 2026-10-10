@props([
    'escenario',
    'destacado' => false,
    'orden' => 0,
])

{{-- Una pieza de la rejilla de escenarios (RF03). Toda la pieza es el
     enlace al detalle ampliado. --}}
<a href="{{ route('portada.escenario', $escenario) }}"
   style="--orden: {{ min($orden, 3) }}"
   {{ $attributes->class([
       'revelar group flex flex-col overflow-hidden rounded-[28px] bg-[var(--pieza)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-portada-rojo',
       'sm:col-span-2 lg:col-span-2' => $destacado,
       'lg:row-span-2' => $destacado && $escenario->imagen,
   ]) }}>
    @if ($escenario->imagen)
        <div @class([
                'm-2 mb-0 overflow-hidden rounded-[20px] bg-[var(--pieza-inversa)]',
                'aspect-[16/10]' => ! $destacado,
                'aspect-[16/10] lg:aspect-auto lg:min-h-[18rem] lg:flex-1' => $destacado,
             ])>
            <x-portada.foto :ruta="$escenario->imagen" :alt="''" />
        </div>
    @endif

    <div @class([
            'flex flex-1 flex-col p-7',
            'sm:p-10' => $destacado,
            'lg:flex-none' => $destacado && $escenario->imagen,
         ])>
        <h3 @class([
                'text-balance font-bold leading-[1.1] tracking-[-0.025em]',
                'text-[clamp(1.875rem,1.4rem+1.8vw,3rem)]' => $destacado,
                'text-2xl' => ! $destacado,
            ])>
            {{ $escenario->nombre }}
        </h3>
        <p @class([
               'mt-3 text-pretty leading-relaxed text-portada-gris',
               'line-clamp-3' => ! $destacado,
               'max-w-[42ch] text-lg' => $destacado,
           ])>{{ $escenario->descripcion }}</p>

        @if ($escenario->capacidades->isNotEmpty())
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="Capacidades del escenario">
                @foreach ($escenario->capacidades as $capacidad)
                    <li class="rounded-full bg-[var(--pieza-inversa)] px-3 py-1 text-[13px] font-medium">{{ $capacidad->nombre }}</li>
                @endforeach
            </ul>
        @endif

        <span class="mt-auto inline-flex items-center gap-1.5 pt-8 text-[15px] font-semibold text-portada-rojo group-hover:underline group-hover:underline-offset-4">
            Ver el escenario <x-portada.chevron />
        </span>
    </div>
</a>
