<div class="space-y-4">
    @if ($resultados->isEmpty())
        <x-mensaje-vacio titulo="Todavía no tienes evaluaciones" descripcion="Aparecen aquí cuando el docente finaliza la evaluación." />
    @else
        @foreach ($resultados as $registro)
            @php($cumplidos = $registro->items->filter(fn ($i) => (bool) $i->pivot->cumplido)->pluck('id')->all())
            <x-tarjeta wire:key="resultado-{{ $registro->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $registro->evaluacion->tipoEvaluacion->nombre }}</p>
                        <p class="text-sm text-gray-600">
                            {{ $registro->evaluacion->solicitud->materia->nombre }} · {{ $registro->evaluacion->docente->nombre }}
                            · intento {{ $registro->intento }}
                        </p>
                    </div>
                    @if ($registro->resultado)
                        <x-etiqueta-estado :estado="$registro->resultado" />
                    @endif
                </div>

                <ul class="mt-3 space-y-1">
                    @foreach ($registro->evaluacion->items as $item)
                        @php($cumplido = in_array($item->id, $cumplidos, true))
                        <li class="flex items-start gap-2 text-sm">
                            <span @class(['font-semibold', 'text-emerald-700' => $cumplido, 'text-rose-700' => ! $cumplido]) aria-hidden="true">{{ $cumplido ? '✓' : '✗' }}</span>
                            <span class="text-gray-800">{{ $item->descripcion }}<span class="sr-only">{{ $cumplido ? ' (cumplido)' : ' (no cumplido)' }}</span></span>
                        </li>
                    @endforeach
                </ul>

                @if ($registro->observaciones)
                    <div class="mt-3"><x-dato etiqueta="Observaciones del docente">{{ $registro->observaciones }}</x-dato></div>
                @endif
            </x-tarjeta>
        @endforeach
    @endif
</div>
