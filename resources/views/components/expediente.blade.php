{{--
    El expediente de una solicitud: la cabecera con sus datos y su línea de
    estados, y las pestañas debajo. Cada pestaña es su propia página, con su
    propio permiso, y mete su contenido en el slot.

    Las pestañas que no son para quien mira no aparecen: novedades solo con
    la sesión aprobada y para quien puede reprogramarla; el formato
    intramural, en las sesiones apartadas antes del semestre (RF57, RF59),
    que es donde llega en papel.

    La cabecera se pinta con la página, no con Livewire: lo que la cambia
    (aprobar, reprogramar, sustituir, retirar o agregar estudiantes) recarga
    la página al terminar.
--}}
@props(['solicitud', 'pestana' => 'detalle'])

@php
    $pestanas = [
        'detalle' => ['Detalle', route('panel.solicitudes.ver', $solicitud), true],
        'participantes' => ['Participantes', route('panel.solicitudes.participantes', $solicitud), true],
        'novedades' => ['Novedades', route('panel.solicitudes.novedades', $solicitud), auth()->user()->can('reprogramar', $solicitud)],
        'formato-intramural' => [
            'Formato intramural',
            route('panel.solicitudes.formato-intramural', $solicitud),
            $solicitud->origen === \App\Enums\OrigenSolicitud::RegistroPrevio && auth()->user()->can('registrarFormatoIntramural', $solicitud),
        ],
    ];
    $quienDicta = $solicitud->docenteQueDicta ?? $solicitud->docente;
    $sala = $solicitud->preparacion?->sala;
@endphp

<div class="space-y-5">
    @can('verBandeja', \App\Models\Solicitud::class)
        <a href="{{ route('panel.solicitudes') }}" class="enlace-de-vuelta">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            Solicitudes
        </a>
    @else
        <a href="{{ route('panel.mis-solicitudes') }}" class="enlace-de-vuelta">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            Mis solicitudes
        </a>
    @endcan

    <header class="rounded-2xl bg-white">
        <div class="px-5 pt-5 sm:px-7 sm:pt-7">
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                <div class="min-w-0">
                    <h1 class="text-balance text-[1.625rem] font-extrabold leading-[1.15] tracking-[-0.025em] text-gray-900 sm:text-[1.75rem]">
                        {{ $solicitud->casoClinico->nombre }}
                    </h1>
                    <p class="mt-1.5 text-pretty text-[15px] leading-snug text-gray-500">
                        {{ $solicitud->materia->nombre }}
                        <span aria-hidden="true">·</span>
                        @if ($solicitud->docenteQueDicta)
                            La dicta {{ $quienDicta->nombre }}, en reemplazo de {{ $solicitud->docente->nombre }}
                        @else
                            {{ $quienDicta->nombre }}
                        @endif
                        @if ($solicitud->grupo)
                            <span aria-hidden="true">·</span> Grupo {{ $solicitud->grupo }}
                        @endif
                    </p>
                </div>
                <x-tipo-de-sesion :tipo="$solicitud->tipo" class="mt-1" />
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 sm:flex sm:flex-wrap sm:gap-x-12">
                <x-dato etiqueta="Fecha">{{ \Illuminate\Support\Str::ucfirst($solicitud->fecha->translatedFormat('l j \d\e F')) }}</x-dato>
                <x-dato etiqueta="Hora">{{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}</x-dato>
                <x-dato etiqueta="Sala">
                    @if ($sala)
                        {{ $sala->nombreCompleto() }}
                    @else
                        <span class="text-gray-500">Se asigna al preparar</span>
                    @endif
                </x-dato>
                {{-- Cambia al retirar o agregar a alguien: la pestaña de participantes recarga la página. --}}
                <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}</x-dato>
            </dl>
        </div>

        <div class="mt-6 border-t border-gray-100 px-2 pb-5 pt-5 sm:px-7">
            <x-linea-de-estados :solicitud="$solicitud" />
        </div>
    </header>

    <nav aria-label="Partes del expediente" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <ul class="flex min-w-max gap-1 border-b border-gray-300">
            @foreach ($pestanas as $clave => [$etiqueta, $enlace, $visible])
                @continue (! $visible)
                <li>
                    <a href="{{ $enlace }}"
                       @class([
                           'relative inline-flex h-11 items-center whitespace-nowrap rounded-t-lg px-3 text-[15px] transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-rojo',
                           'font-semibold text-gray-900 after:absolute after:inset-x-3 after:-bottom-px after:h-0.5 after:rounded-full after:bg-gray-900' => $clave === $pestana,
                           'font-medium text-gray-500 hover:text-gray-900' => $clave !== $pestana,
                       ])
                       @if ($clave === $pestana) aria-current="page" @endif>
                        {{ $etiqueta }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div>{{ $slot }}</div>
</div>
