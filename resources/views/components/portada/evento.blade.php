@props(['evento', 'orden' => 0])

{{-- Un evento (RF05): la fecha manda, con el día en cifra de monitor; luego
     el título, el tipo y si está abierto al público. --}}
<article class="revelar flex grow basis-full flex-col rounded-[28px] bg-[var(--pieza)] p-7 sm:basis-[calc(50%-0.5rem)] lg:basis-[calc(25%-0.75rem)]"
         style="--orden: {{ min($orden, 3) }}">
    <p class="flex items-baseline gap-3">
        <time datetime="{{ $evento->fecha->toDateString() }}" class="contents">
            <span class="text-[4rem] font-light leading-none tracking-[-0.04em] text-portada-rojo tabular-nums">{{ $evento->fecha->format('j') }}</span>
            <span class="text-[15px] font-semibold leading-tight">
                {{ $evento->fecha->translatedFormat('F') }}<br>
                <span class="font-normal text-portada-gris">{{ $evento->fecha->format('Y') }}</span>
            </span>
        </time>
    </p>

    <h3 class="mt-8 text-balance text-xl font-bold leading-[1.15] tracking-[-0.02em]">{{ $evento->titulo }}</h3>
    @if ($evento->tipoEvento)
        <p class="mt-1.5 text-[15px] text-portada-gris">{{ $evento->tipoEvento->nombre }}</p>
    @endif

    <p class="mt-auto flex items-center gap-2 pt-6 text-sm font-medium">
        @if ($evento->abierto_publico)
            <span class="size-2 rounded-full bg-portada-rojo" aria-hidden="true"></span>
            Abierto al público
        @else
            <span class="size-2 rounded-full border border-portada-gris" aria-hidden="true"></span>
            <span class="text-portada-gris">Para la comunidad universitaria</span>
        @endif
    </p>
</article>
