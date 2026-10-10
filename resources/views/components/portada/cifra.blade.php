@props([
    'cifra',
    'indice',
    'total',
])

@php
    // Rejilla bento de 4 columnas en escritorio y 2 en tablet: la primera
    // cifra es la grande y el resto llena los huecos sin dejar celdas
    // vacías para 1 a 6 cifras, que es lo que maneja el laboratorio.
    $principal = $indice === 0 && $total >= 3;

    $tamano = match (true) {
        $total === 1 => 'sm:col-span-2 lg:col-span-4',
        $total === 2 => 'lg:col-span-2',
        $principal => 'sm:col-span-2 lg:col-span-2 lg:row-span-2',
        $total === 3 => 'lg:col-span-2',
        $total === 4 && $indice === 1 => 'sm:col-span-2 lg:col-span-2',
        $total === 6 && $indice === 5 => 'sm:col-span-2 lg:col-span-4',
        default => '',
    };
@endphp

<div @class([
        'revelar relative flex min-h-36 flex-col justify-between gap-8 overflow-hidden rounded-[28px] bg-[var(--pieza)] p-7 sm:min-h-44 sm:p-8',
        'lg:min-h-[26rem] lg:p-10' => $principal,
        $tamano,
     ])
     style="--orden: {{ min($indice, 3) }}">
    <dt class="text-[13px] font-semibold uppercase tracking-[0.14em] text-portada-gris">{{ $cifra->etiqueta }}</dt>
    <dd @class([
            'font-light leading-none tracking-[-0.045em] text-portada-rojo tabular-nums',
            'text-[clamp(4.5rem,2.6rem+7vw,9rem)]' => $principal,
            'text-[clamp(3.25rem,2.5rem+2.6vw,4.75rem)]' => ! $principal,
        ])>
        @if ($principal)
            {{-- Como en un monitor: la onda encima de la lectura, de borde a
                 borde de la pieza. Va dentro del dd porque un dl no admite
                 otros hijos; es solo un trazo. --}}
            <x-portada.pulso class="pointer-events-none -mx-7 mb-4 opacity-30 sm:-mx-8 lg:-mx-10" />
        @endif

        <span class="sr-only">{{ $cifra->valor }}</span>
        {{-- El valor final reserva el ancho mientras cuenta: nada se mueve. --}}
        <span class="inline-grid" aria-hidden="true">
            <span class="invisible col-start-1 row-start-1">{{ $cifra->valor }}</span>
            <span class="col-start-1 row-start-1" data-contador>{{ $cifra->valor }}</span>
        </span>
    </dd>
</div>
