@props(['equipo', 'orden' => 0])

{{-- Los demás equipos: una pieza por equipo, en el tono contrario al de la
     sección, con la foto abajo dentro de un marco concéntrico (28 px
     afuera, 8 px de margen, 20 px adentro). --}}
<article {{ $attributes->class('revelar flex flex-col rounded-[28px] bg-[var(--pieza)]') }} style="--orden: {{ min($orden, 2) }}">
    <div class="p-7 sm:p-10">
        <h3 class="text-balance text-[clamp(1.25rem,0.8rem+1.5vw,2.125rem)] font-extrabold leading-[1.08] tracking-[-0.028em]">
            {{ $equipo->nombre }}
        </h3>
        <p class="mt-4 max-w-[34ch] text-pretty text-lg leading-snug text-portada-gris">{{ $equipo->resumen }}</p>

        <x-portada.caracteristicas :lista="$equipo->caracteristicas ?? []" class="mt-8" />
    </div>

    @if ($equipo->imagen)
        <div class="mx-2 mb-2 mt-auto aspect-[4/3] overflow-hidden rounded-[20px] bg-[var(--pieza-inversa)]">
            <x-portada.foto :ruta="$equipo->imagen" :alt="$equipo->nombre" />
        </div>
    @endif
</article>
