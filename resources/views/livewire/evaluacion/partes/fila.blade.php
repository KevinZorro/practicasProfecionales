<li wire:key="evaluacion-{{ $evaluacion->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
    <div class="min-w-0">
        <p class="text-[15px] font-semibold text-gray-900">{{ $evaluacion->tipoEvaluacion->nombre }}</p>
        <p class="text-[14px] leading-snug text-gray-500">
            {{ $evaluacion->solicitud->casoClinico->nombre }} · {{ $evaluacion->solicitud->materia->nombre }}
            · {{ $evaluacion->solicitud->fecha->format('d/m/Y') }}
            @if ($conDocente ?? false)
                · {{ $evaluacion->docente->nombre }}
            @endif
            · {{ $cuantos }} {{ $cuantos === 1 ? 'estudiante' : 'estudiantes' }}
        </p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
        <x-etiqueta-estado :estado="$evaluacion->estado" />
        <x-boton tamano="chico" variante="secundario" href="{{ route('panel.evaluaciones.registro', $evaluacion) }}">
            {{ $evaluacion->estado === \App\Enums\EstadoEvaluacion::Borrador ? 'Continuar' : 'Ver' }}
        </x-boton>
    </div>
</li>
