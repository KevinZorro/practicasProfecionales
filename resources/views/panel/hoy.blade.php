{{--
    «Hoy»: lo que espera a quien entra, con el rol que tiene puesto
    (ResumenDelDiaService). Cada bloque está si su Policy lo permite; aquí
    solo se pinta lo que llega. A la izquierda, el trabajo, en el orden en
    que pide atención; a la derecha, los trámites del formato.
--}}
@extends('layouts.panel')

@section('titulo', 'Hoy')
@section('descripcion', \Illuminate\Support\Str::ucfirst($hoy->translatedFormat('l j \d\e F')))

@section('contenido')
    @if ($resumen->vacio())
        <x-mensaje-vacio titulo="Nada pendiente por ahora" descripcion="Lo que necesite tu atención aparecerá aquí." />
    @else
        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-5">

                @if ($resumen->porAprobar !== null)
                    <x-bloque-de-solicitudes titulo="Por aprobar" decide
                                             :solicitudes="$resumen->porAprobar"
                                             vacio="Nada espera tu aprobación."
                                             :enlace="route('panel.solicitudes', ['estado' => \App\Enums\EstadoSolicitud::Revisada->value])" />
                @endif

                @if ($resumen->montajesDeHoy !== null)
                    @php
                        $montajes = $resumen->montajesDeHoy;
                        $faltan = $montajes->where('estado', '!=', \App\Enums\EstadoPreparacion::Preparado)->count();
                    @endphp
                    <section class="min-w-0 overflow-hidden rounded-2xl bg-white">
                        <header class="flex items-center justify-between gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
                            <div class="min-w-0">
                                <h2 class="text-[17px] font-bold leading-snug tracking-[-0.01em] text-gray-900">Escenarios de hoy</h2>
                                @if ($montajes->isNotEmpty())
                                    <p class="mt-0.5 text-[14px] text-gray-500">
                                        @if ($faltan === 0)
                                            Todos listos.
                                        @else
                                            {{ trans_choice('Falta :count de :total por montar|Faltan :count de :total por montar', $faltan, ['count' => $faltan, 'total' => $montajes->count()]) }}.
                                        @endif
                                    </p>
                                @endif
                            </div>
                            @if ($montajes->isNotEmpty())
                                <x-boton variante="secundario" tamano="chico" :href="route('panel.preparaciones')">Abrir el tablero</x-boton>
                            @endif
                        </header>

                        @if ($montajes->isEmpty())
                            <p class="flex items-center gap-2 px-4 pb-5 pt-2 text-[14px] text-gray-500 sm:px-5">
                                <svg class="size-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                No hay escenarios aprobados para hoy.
                            </p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100">
                                @foreach ($montajes as $montaje)
                                    @php
                                        $solicitud = $montaje->solicitud;
                                        $total = $montaje->items->count();
                                        $alistados = $montaje->items->where('pivot.alistado', true)->count();
                                    @endphp
                                    <li class="relative grid grid-cols-[3rem_minmax(0,1fr)] gap-x-4 gap-y-2 px-4 py-4 transition-colors duration-150 hover:bg-gray-100 sm:grid-cols-[3rem_minmax(0,1fr)_auto] sm:items-center sm:px-5">
                                        <p class="text-[15px] font-semibold leading-tight text-gray-900">
                                            {{ substr($solicitud->hora_inicio, 0, 5) }}
                                            <span class="block text-[13px] font-normal text-gray-500">{{ substr($solicitud->hora_fin, 0, 5) }}</span>
                                        </p>
                                        <div class="min-w-0">
                                            <a href="{{ route('panel.solicitudes.ver', $solicitud) }}"
                                               class="text-[15px] font-semibold leading-snug text-gray-900 after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-rojo">
                                                {{ $solicitud->casoClinico->nombre }}
                                            </a>
                                            <p class="mt-0.5 text-[14px] leading-snug text-gray-500">
                                                @if ($montaje->sala)
                                                    {{ $montaje->sala->nombre }}
                                                @else
                                                    <span class="font-medium text-amber-800">Sin sala</span>
                                                @endif
                                                · {{ ($solicitud->docenteQueDicta ?? $solicitud->docente)->nombre }}
                                            </p>
                                            @if ($total > 0)
                                                <x-progreso-alistamiento :alistados="$alistados" :total="$total" class="mt-2 max-w-xs" />
                                            @endif
                                        </div>
                                        <x-etiqueta-estado :estado="$montaje->estado" class="col-start-2 justify-self-start sm:col-start-auto sm:justify-self-end" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                @if ($resumen->porRevisar !== null)
                    <x-bloque-de-solicitudes titulo="Por revisar" decide
                                             :solicitudes="$resumen->porRevisar"
                                             vacio="No hay solicitudes nuevas por revisar."
                                             :enlace="route('panel.solicitudes', ['estado' => \App\Enums\EstadoSolicitud::Pendiente->value])" />
                @endif

                @if ($resumen->proximasSesiones !== null)
                    <x-bloque-de-solicitudes titulo="Tus próximas sesiones" :con-docente="false"
                                             :solicitudes="$resumen->proximasSesiones"
                                             vacio="No tienes sesiones aprobadas de hoy en adelante."
                                             :enlace="route('panel.mis-solicitudes', ['estado' => \App\Enums\EstadoSolicitud::Aprobada->value])">
                        <x-boton :href="route('panel.solicitudes.nueva')" tamano="chico">Solicitar escenario</x-boton>
                    </x-bloque-de-solicitudes>
                @endif

                @if ($resumen->enTramite !== null && $resumen->enTramite->total() > 0)
                    <x-bloque-de-solicitudes titulo="Tus solicitudes en trámite" :con-docente="false"
                                             :solicitudes="$resumen->enTramite"
                                             vacio=""
                                             :enlace="route('panel.mis-solicitudes')" />
                @endif

                @if ($resumen->porEvaluar !== null && $resumen->porEvaluar->isNotEmpty())
                    <x-bloque-de-solicitudes titulo="Sesiones por evaluar" :con-docente="false"
                                             :solicitudes="$resumen->porEvaluar->take(\App\Services\ResumenDelDiaService::FILAS_POR_BLOQUE)"
                                             vacio=""
                                             :enlace="route('panel.evaluaciones')" />
                @endif

                @if ($resumen->ultimosResultados !== null)
                    <x-tarjeta titulo="Tus últimos resultados">
                        @if ($resumen->ultimosResultados->isEmpty())
                            <p class="text-[14px] text-gray-500">Todavía no tienes resultados publicados. Aparecen cuando el docente finaliza la evaluación.</p>
                        @else
                            <ul class="-my-2 divide-y divide-gray-100">
                                @foreach ($resumen->ultimosResultados as $registro)
                                    <li class="flex items-center justify-between gap-3 py-3">
                                        <div class="min-w-0">
                                            <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $registro->evaluacion->tipoEvaluacion->nombre }}</p>
                                            <p class="text-[13px] text-gray-500">
                                                {{ $registro->evaluacion->solicitud->materia->nombre }} · intento {{ $registro->intento }}
                                            </p>
                                        </div>
                                        @if ($registro->resultado)
                                            <x-etiqueta-estado :estado="$registro->resultado" />
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <x-slot:pie>
                            <a href="{{ route('panel.mis-resultados') }}" class="font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900">Ver todos tus resultados</a>
                        </x-slot:pie>
                    </x-tarjeta>
                @endif
            </div>

            @if ($resumen->formatosPorVerificar !== null || $resumen->miFormato !== null)
                <div class="space-y-5">
                    @if ($resumen->formatosPorVerificar !== null)
                        <x-tarjeta titulo="Formatos por verificar">
                            @if ($resumen->formatosPorVerificar === 0)
                                <p class="flex items-center gap-2 text-[14px] text-gray-500">
                                    <svg class="size-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    Ninguno espera verificación.
                                </p>
                            @else
                                <p class="text-[15px] text-gray-700">
                                    <span class="text-[1.75rem] font-bold leading-none tracking-[-0.02em] text-gray-900">{{ $resumen->formatosPorVerificar }}</span>
                                    {{ trans_choice('formato cargado espera que lo leas y lo verifiques.|formatos cargados esperan que los leas y los verifiques.', $resumen->formatosPorVerificar) }}
                                </p>
                                <x-boton :href="route('panel.formatos-confidencialidad')" tamano="chico" class="mt-4">Verificar</x-boton>
                            @endif
                        </x-tarjeta>
                    @endif

                    @if ($resumen->miFormato !== null)
                        @php($formato = $resumen->miFormato)
                        <x-tarjeta titulo="Tu formato de confidencialidad">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[14px] text-gray-500">{{ $formato->periodo ? 'Periodo '.$formato->periodo : 'Sin periodo abierto' }}</p>
                                @if ($formato->alDia())
                                    <x-insignia tono="exito">Al día</x-insignia>
                                @elseif ($formato->estado() === \App\Enums\EstadoFormatoConfidencialidad::Cargado)
                                    <x-insignia tono="atencion">En revisión</x-insignia>
                                @elseif ($formato->entregadoEnFisico())
                                    <x-insignia tono="atencion">Entregado en papel</x-insignia>
                                @else
                                    <x-insignia tono="peligro">Pendiente</x-insignia>
                                @endif
                            </div>
                            <p class="mt-3 text-[14px] leading-snug text-gray-700">
                                @if ($formato->alDia())
                                    Está verificado: puedes entrar a las prácticas de este periodo.
                                @elseif ($formato->devuelto())
                                    Te lo devolvieron para corregirlo. Vuelve a subirlo.
                                @elseif ($formato->estado() === \App\Enums\EstadoFormatoConfidencialidad::Cargado)
                                    Ya lo subiste. El laboratorio lo está revisando.
                                @elseif ($formato->entregadoEnFisico())
                                    Lo entregaste en papel y ya puedes entrar; falta subir el escaneo.
                                @else
                                    Sin él no puedes entrar a las prácticas. Se renueva cada periodo.
                                @endif
                            </p>
                            @unless ($formato->alDia())
                                <x-boton :href="route('panel.mi-formato')" tamano="chico" class="mt-4">Ir a mi formato</x-boton>
                            @endunless
                        </x-tarjeta>
                    @endif
                </div>
            @endif
        </div>
    @endif
@endsection
