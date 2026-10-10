@props(['certificacion', 'orden' => 0])

@php
    // Sin imagen, el sello lleva las siglas de la entidad: «American Heart
    // Association» → AHA. El nombre completo va debajo, en el pie.
    $siglas = collect(preg_split('/\s+/', trim($certificacion->entidad)) ?: [])
        ->reject(static fn (string $palabra): bool => in_array(mb_strtolower($palabra), ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'en', 'for', 'in', 'of', 'the', 'and'], true))
        ->map(static fn (string $palabra): string => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(4)
        ->implode('');
@endphp

{{--
    Una certificación como insignia (RF06): un disco plano. Sin imagen es un
    sello: doble filete y las siglas de la entidad. Lo metálico es solo el
    brillo que pasa una vez al entrar en pantalla; con cursor, la pieza se
    inclina hacia él (animación 5 de DESIGN.md, data-insignia).
--}}
<figure class="revelar flex w-36 flex-col items-center text-center sm:w-48" style="--orden: {{ min($orden, 3) }}">
    <div class="portada-insignia relative size-32 overflow-hidden rounded-full bg-[var(--pieza)] ring-1 ring-black/[0.06] sm:size-44"
         data-insignia style="--orden-brillo: {{ min($orden, 5) }}">
        @if ($certificacion->imagen_insignia)
            {{-- Casi todo el diámetro, para que un logo apaisado se lea; el
                 blanco de un logo en JPG se funde con el disco. --}}
            <x-portada.foto :ruta="$certificacion->imagen_insignia" :alt="''" class="!object-contain p-3 mix-blend-multiply sm:p-4" />
        @else
            <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full border border-portada-linea" aria-hidden="true">
                <span class="text-[1.625rem] font-semibold leading-none tracking-[0.06em] text-portada-tinta sm:text-[2rem]">{{ $siglas }}</span>
                <span class="mt-3 h-[2px] w-6 bg-portada-rojo"></span>
            </div>
        @endif
    </div>
    <figcaption class="mt-5">
        <span class="block text-[15px] font-semibold leading-snug">{{ $certificacion->nombre }}</span>
        <span class="mt-1 block text-sm text-portada-gris">{{ $certificacion->entidad }}</span>
    </figcaption>
</figure>
