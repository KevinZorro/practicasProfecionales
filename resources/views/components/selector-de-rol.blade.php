{{--
    El rol activo, arriba a la derecha (RF21): en la barra del celular y
    sobre el contenido en escritorio. Dentro del riel no se veía en el
    celular sin abrir el menú, y quien tiene dos roles no sabía con cuál
    estaba mirando.

    Con varios roles es un selector. Con uno solo no hay nada que elegir y
    no aparece, salvo que sea prestado: quien usa un rol temporal lo ve
    dicho en la cabecera, con la fecha (RF63).

    Es un <details>, así que abre y cierra sin JavaScript; Alpine solo añade
    cerrarlo al tocar fuera o con Escape. Cada rol es un botón del mismo
    formulario: un toque cambia, sin elegir primero y confirmar después.

    Lee lo que comparte el middleware EstablecerRolActivo: $rolActivo,
    $rolesDisponibles, $vigenciaDelRolActivo y $vigenciaDeLosRoles.
--}}
@props(['tono' => 'claro'])

@php
    $oscuro = $tono === 'oscuro';
    $pastilla = 'flex h-10 min-w-0 max-w-full items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 text-[14px] font-semibold ring-1 ring-inset '
        .($oscuro ? 'bg-white/10 text-white ring-white/15' : 'bg-white text-gray-900 ring-gray-300');
    $alTocar = $oscuro ? 'hover:bg-white/15 focus-visible:outline-white' : 'hover:ring-gray-400 focus-visible:outline-rojo';
    $fecha = $oscuro ? 'text-amber-200' : 'text-amber-800';
@endphp

@if (count($rolesDisponibles) > 1)
    <details {{ $attributes->class('group relative min-w-0') }}
             x-data @click.outside="$el.open = false"
             @keydown.escape="if ($el.open) { $el.open = false; $el.querySelector('summary').focus() }">
        <summary class="{{ $pastilla }} {{ $alTocar }} cursor-pointer list-none transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 [&::-webkit-details-marker]:hidden">
            <span class="sr-only">Rol activo:</span>
            <span class="truncate">{{ $rolActivo->etiqueta() }}</span>
            @if ($vigenciaDelRolActivo)
                <span class="shrink-0 font-medium {{ $fecha }}"><span class="sr-only">temporal</span> · hasta {{ $vigenciaDelRolActivo->format('d/m') }}</span>
            @endif
            <svg class="size-4 shrink-0 opacity-70 group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </summary>

        <div class="absolute right-0 top-full z-50 mt-2 w-72 max-w-[calc(100vw-1rem)] rounded-2xl bg-white p-2 text-gray-900 shadow-[0_12px_32px_-8px_rgb(0_0_0/0.25)] ring-1 ring-gray-900/10">
            <p class="px-3 pb-1.5 pt-2 text-[13px] font-semibold text-gray-500">Cambiar de rol</p>
            <form method="POST" action="{{ route('panel.rol-activo') }}">
                @csrf
                <ul class="space-y-0.5">
                    @foreach ($rolesDisponibles as $rol)
                        @php($vigencia = $vigenciaDeLosRoles[$rol->value] ?? null)
                        <li>
                            @if ($rol === $rolActivo)
                                <p class="flex items-center justify-between gap-3 rounded-xl bg-gray-100 px-3 py-2.5" aria-current="true">
                                    <span class="min-w-0">
                                        <span class="block text-[15px] font-semibold">{{ $rol->etiqueta() }}</span>
                                        <span class="block text-[13px] text-gray-600">
                                            Con este estás viendo el sistema{{ $vigencia ? ' · temporal hasta el '.$vigencia->format('d/m/Y') : '' }}
                                        </span>
                                    </span>
                                    <svg class="size-5 shrink-0 text-gray-900" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </p>
                            @else
                                <button type="submit" name="rol" value="{{ $rol->value }}"
                                        class="block w-full rounded-xl px-3 py-2.5 text-left transition-colors duration-150 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-rojo">
                                    <span class="block text-[15px] font-medium">{{ $rol->etiqueta() }}</span>
                                    @if ($vigencia)
                                        <span class="block text-[13px] text-gray-600">Temporal hasta el {{ $vigencia->format('d/m/Y') }}</span>
                                    @endif
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </form>
        </div>
    </details>
@elseif ($vigenciaDelRolActivo)
    <p {{ $attributes->class($pastilla) }}>
        <span class="sr-only">Rol activo:</span>
        <span class="truncate">{{ $rolActivo->etiqueta() }}</span>
        <span class="shrink-0 font-medium {{ $fecha }}"><span class="sr-only">temporal</span> · hasta {{ $vigenciaDelRolActivo->format('d/m') }}</span>
    </p>
@endif
