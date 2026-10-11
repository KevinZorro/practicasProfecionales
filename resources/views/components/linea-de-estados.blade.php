{{--
    La línea de estados de una solicitud: la firma del panel (DESIGN.md).

    Un trazo fino con un nodo por fase. Lo que ya pasó, en tinta y con su
    fecha; la fase actual, en rojo y con lo que está pasando («Por aprobar»);
    lo que falta, hueco y en gris, unido con un trazo punteado. Un rechazo
    corta la línea con una cruz roja en la fase donde ocurrió.

    Compacta: solo el trazo y la situación, para las filas de las listas.
    Las fases las calcula App\Support\LineaDeEstados.
--}}
@props(['solicitud', 'compacta' => false])

@php
    $pasos = \App\Support\LineaDeEstados::deSolicitud($solicitud);
    $situacion = \App\Support\LineaDeEstados::situacion($pasos);
    $hecho = \App\Enums\EstadoDelPaso::Hecho;
    $actual = \App\Enums\EstadoDelPaso::Actual;
    $cortado = \App\Enums\EstadoDelPaso::Cortado;
    $leyenda = [
        'hecho' => 'hecha',
        'actual' => 'en curso',
        'pendiente' => 'pendiente',
        'cortado' => 'rechazada',
    ];
@endphp

@if ($compacta)
    <div {{ $attributes->class('flex items-center gap-2.5') }}>
        <span class="flex items-center" aria-hidden="true">
            @foreach ($pasos as $paso)
                @unless ($loop->first)
                    <span @class([
                        'w-3',
                        'h-0.5 bg-gray-900' => $paso->estado !== \App\Enums\EstadoDelPaso::Pendiente,
                        'border-t-2 border-dotted border-gray-300' => $paso->estado === \App\Enums\EstadoDelPaso::Pendiente,
                    ])></span>
                @endunless
                <span @class([
                    'shrink-0 rounded-full',
                    'size-1.5 bg-gray-900' => $paso->estado === $hecho,
                    'size-2.5 bg-rojo ring-[3px] ring-rojo/15' => $paso->estado === $actual,
                    'size-1.5 border-[1.5px] border-gray-300 bg-white' => $paso->estado === \App\Enums\EstadoDelPaso::Pendiente,
                    'size-2.5 bg-rojo' => $paso->estado === $cortado,
                ])></span>
            @endforeach
        </span>
        <span class="whitespace-nowrap text-[13px] font-semibold text-gray-900">
            <span class="sr-only">Situación: </span>{{ $situacion }}
        </span>
    </div>
@else
    <ol {{ $attributes->class('grid') }} style="grid-template-columns: repeat({{ count($pasos) }}, minmax(0, 1fr));"
        aria-label="Fases de la solicitud">
        @foreach ($pasos as $paso)
            @php($siguiente = $pasos[$loop->index + 1] ?? null)
            <li class="flex min-w-0 flex-col items-center text-center" @if ($paso->estado === $actual) aria-current="step" @endif>
                <div class="relative flex h-6 w-full items-center justify-center" aria-hidden="true">
                    {{-- Medio tramo que llega desde la fase anterior. --}}
                    @unless ($loop->first)
                        <span @class([
                            'absolute left-0 right-1/2 top-1/2',
                            'h-0.5 -translate-y-1/2 bg-gray-900' => $paso->estado !== \App\Enums\EstadoDelPaso::Pendiente,
                            'border-t-2 border-dashed border-gray-300' => $paso->estado === \App\Enums\EstadoDelPaso::Pendiente,
                        ])></span>
                    @endunless
                    {{-- Medio tramo que sale hacia la siguiente. --}}
                    @if ($siguiente)
                        <span @class([
                            'absolute left-1/2 right-0 top-1/2',
                            'h-0.5 -translate-y-1/2 bg-gray-900' => $siguiente->estado !== \App\Enums\EstadoDelPaso::Pendiente,
                            'border-t-2 border-dashed border-gray-300' => $siguiente->estado === \App\Enums\EstadoDelPaso::Pendiente,
                        ])></span>
                    @endif

                    @if ($paso->estado === $cortado)
                        <span class="relative flex size-5 items-center justify-center rounded-full bg-rojo text-white">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </span>
                    @else
                        <span @class([
                            'relative rounded-full',
                            'size-3 bg-gray-900 ring-4 ring-white' => $paso->estado === $hecho,
                            'size-4 bg-rojo ring-[5px] ring-rojo/15' => $paso->estado === $actual,
                            'size-3 border-2 border-gray-300 bg-white ring-4 ring-white' => $paso->estado === \App\Enums\EstadoDelPaso::Pendiente,
                        ])></span>
                    @endif
                </div>

                <span @class([
                    'mt-2 text-balance text-[13px] leading-tight',
                    'font-medium text-gray-900' => $paso->estado === $hecho,
                    'font-semibold text-rojo' => $paso->estado === $actual || $paso->estado === $cortado,
                    'text-gray-500' => $paso->estado === \App\Enums\EstadoDelPaso::Pendiente,
                ])>
                    {{ $paso->fase }}<span class="sr-only">: {{ $leyenda[$paso->estado->value] }}</span>
                </span>

                @if ($paso->situacion !== null)
                    <span class="mt-0.5 text-[12px] font-medium leading-tight text-gray-700">{{ $paso->situacion }}</span>
                @elseif ($paso->fecha)
                    <span class="mt-0.5 text-[12px] leading-tight text-gray-500">{{ $paso->fecha->translatedFormat('j M') }}</span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
