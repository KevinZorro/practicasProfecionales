@props(['lista' => []])

{{-- Las frases cortas de un equipo, como una ficha técnica: separadas por
     filetes y marcadas con un trazo rojo a la altura del texto. --}}
@if ($lista !== [])
    <ul {{ $attributes->class('border-b border-portada-linea') }}>
        @foreach ($lista as $caracteristica)
            <li class="flex items-baseline gap-4 border-t border-portada-linea py-4 text-[17px] font-medium leading-snug sm:text-lg">
                <span class="h-[2px] w-3 shrink-0 translate-y-[-0.3em] bg-portada-rojo" aria-hidden="true"></span>
                {{ $caracteristica }}
            </li>
        @endforeach
    </ul>
@endif
