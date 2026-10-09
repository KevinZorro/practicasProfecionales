<li wire:key="evaluacion-{{ $evaluacion->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
    <div class="min-w-0">
        <p class="text-sm font-medium text-gray-900">{{ $evaluacion->tipoEvaluacion->nombre }}</p>
        <p class="text-sm text-gray-600">
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
        <x-boton variante="secundario" href="{{ route('panel.evaluaciones.registro', $evaluacion) }}" class="px-3 py-2">
            {{ $evaluacion->estado === \App\Enums\EstadoEvaluacion::Borrador ? 'Continuar' : 'Ver' }}
        </x-boton>
    </div>
</li>
