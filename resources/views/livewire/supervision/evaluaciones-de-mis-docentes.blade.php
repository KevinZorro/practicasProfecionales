<div class="space-y-5">
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 basis-48">
            <label for="periodo" class="campo-etiqueta">Periodo académico</label>
            <select wire:model.live="periodo" id="periodo" class="campo">
                <option value="">{{ $periodoVigente ? 'Vigente ('.$periodoVigente->nombre.')' : 'Vigente' }}</option>
                @foreach ($periodos as $opcion)
                    @unless ($periodoVigente?->is($opcion))
                        <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                    @endunless
                @endforeach
                <option value="{{ \App\Services\SupervisionService::TODOS_LOS_PERIODOS }}">Todo el historial</option>
            </select>
        </div>
        <div class="min-w-0 flex-1 basis-48">
            <label for="docente" class="campo-etiqueta">Docente</label>
            <select wire:model.live="docente" id="docente" class="campo">
                <option value="">Todos los que superviso</option>
                @foreach ($docentes as $opcion)
                    <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-boton variante="secundario" href="{{ route('panel.supervision.excel', $filtrosDeDescarga) }}">Excel</x-boton>
            <x-boton variante="secundario" href="{{ route('panel.supervision.pdf', $filtrosDeDescarga) }}">PDF</x-boton>
        </div>
    </div>

    <p class="text-[14px] text-gray-500">
        {{ trans_choice(':count resultado|:count resultados', $resultados->total(), ['count' => $resultados->total()]) }}
        · {{ trans_choice(':count docente a tu cargo|:count docentes a tu cargo', $docentes->count(), ['count' => $docentes->count()]) }}
    </p>

    @if ($docentes->isEmpty())
        <x-mensaje-vacio titulo="Todavía no tienes docentes a tu cargo" descripcion="El administrador de la plataforma los asigna. Cuando lo haga, sus evaluaciones aparecen aquí." />
    @elseif ($resultados->isEmpty())
        <x-mensaje-vacio titulo="No hay evaluaciones finalizadas" descripcion="Aparecen aquí cuando un docente a tu cargo finaliza una evaluación del periodo que estás mirando." />
    @else
        @foreach ($resultados as $registro)
            @php($cumplidos = $registro->items->filter(fn ($i) => (bool) $i->pivot->cumplido)->pluck('id')->all())
            <x-tarjeta wire:key="resultado-{{ $registro->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[17px] font-bold leading-snug tracking-[-0.01em] text-gray-900">{{ $registro->estudiante->nombre }}</p>
                        <p class="mt-0.5 text-[14px] leading-snug text-gray-500">
                            {{ $registro->evaluacion->tipoEvaluacion->nombre }} · {{ $registro->evaluacion->solicitud->materia->nombre }}
                            · intento {{ $registro->intento }}
                        </p>
                        <p class="text-[14px] leading-snug text-gray-500">
                            Evaluó {{ $registro->evaluacion->docente->nombre }} · {{ $registro->evaluacion->solicitud->fecha->format('d/m/Y') }}
                        </p>
                    </div>
                    @if ($registro->resultado)
                        <x-etiqueta-estado :estado="$registro->resultado" />
                    @endif
                </div>

                <ul class="mt-4 divide-y divide-gray-100 rounded-xl bg-gray-50">
                    @foreach ($registro->evaluacion->items as $item)
                        @php($cumplido = in_array($item->id, $cumplidos, true))
                        <li class="flex items-start gap-3 px-3.5 py-2.5 text-[15px] leading-snug">
                            <x-marca-de-checklist :cumplido="$cumplido" />
                            <span @class(['text-gray-900' => $cumplido, 'text-gray-600' => ! $cumplido])>{{ $item->descripcion }}<span class="sr-only">{{ $cumplido ? ' (cumplido)' : ' (no cumplido)' }}</span></span>
                        </li>
                    @endforeach
                </ul>

                @if ($registro->observaciones)
                    <dl class="mt-4"><x-dato etiqueta="Observaciones del docente">{{ $registro->observaciones }}</x-dato></dl>
                @endif
            </x-tarjeta>
        @endforeach

        <div>{{ $resultados->links() }}</div>
    @endif
</div>
