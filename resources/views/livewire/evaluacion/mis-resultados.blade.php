<div class="space-y-5">
    @if ($resultados->isEmpty())
        <x-mensaje-vacio titulo="Todavía no tienes evaluaciones" descripcion="Aparecen aquí cuando el docente finaliza la evaluación." />
    @else
        @foreach ($resultados as $registro)
            @php($cumplidos = $registro->items->filter(fn ($i) => (bool) $i->pivot->cumplido)->pluck('id')->all())
            <x-tarjeta wire:key="resultado-{{ $registro->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[17px] font-bold leading-snug tracking-[-0.01em] text-gray-900">{{ $registro->evaluacion->tipoEvaluacion->nombre }}</p>
                        <p class="mt-0.5 text-[14px] leading-snug text-gray-500">
                            {{ $registro->evaluacion->solicitud->materia->nombre }} · {{ $registro->evaluacion->docente->nombre }}
                            · intento {{ $registro->intento }}
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
    @endif
</div>
