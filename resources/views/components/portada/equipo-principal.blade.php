@props(['equipo'])

{{--
    El equipo principal, presentado como producto protagonista: nombre muy
    grande, su frase, la ficha y la foto a casi todo el ancho. La foto se
    acerca levemente al entrar en pantalla en escritorio y aparece con un
    fundido en móvil (animación 4 de DESIGN.md, data-acercamiento).
--}}
<article class="revelar">
    <div class="grid gap-10 lg:grid-cols-12 lg:items-start lg:gap-12">
        <div class="lg:col-span-7">
            {{-- El titular de la sección dividido por 1,5, en cualquier ancho:
                 24 px en móvil y 48 en escritorio. --}}
            <h3 class="text-balance text-[clamp(1.5rem,0.9rem+2.4vw,3rem)] font-extrabold leading-[1.04] tracking-[-0.032em]">
                {{ $equipo->nombre }}
            </h3>
            <p class="mt-5 max-w-[30ch] text-pretty text-lg leading-snug text-portada-gris sm:text-2xl">{{ $equipo->resumen }}</p>
        </div>

        <x-portada.caracteristicas :lista="$equipo->caracteristicas ?? []" class="lg:col-span-5" />
    </div>

    @if ($equipo->imagen)
        <div class="mt-12 overflow-hidden rounded-[28px] bg-[var(--pieza)] md:mt-16" data-acercamiento>
            <div class="aspect-[4/5] sm:aspect-[16/10] lg:aspect-[2/1]">
                <x-portada.foto :ruta="$equipo->imagen" :alt="$equipo->nombre" class="portada-acercamiento" />
            </div>
        </div>
    @endif
</article>
