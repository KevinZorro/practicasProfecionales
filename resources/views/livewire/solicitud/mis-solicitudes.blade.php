<div class="space-y-5">

    <x-filtro-pastillas leyenda="Filtrar por estado" nombre="estado" wire:model.live="estado"
                        :opciones="['' => 'Todas'] + collect($estados)->mapWithKeys(fn ($unEstado) => [$unEstado->value => $unEstado->etiqueta()])->all()" />

    @if ($solicitudes->isEmpty())
        <x-mensaje-vacio
            titulo="No tienes solicitudes"
            descripcion="Cuando pidas un escenario clínico aparecerá aquí con su estado."
        >
            <x-boton href="{{ route('panel.solicitudes.nueva') }}">Solicitar escenario</x-boton>
        </x-mensaje-vacio>
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white" wire:loading.class="opacity-60" wire:target="estado, gotoPage, nextPage, previousPage">
            @foreach ($solicitudes as $solicitud)
                <x-fila-de-solicitud :solicitud="$solicitud" :con-docente="false" wire:key="solicitud-{{ $solicitud->id }}">
                    @if ($solicitud->docente_que_dicta_id === auth()->id())
                        <p class="font-medium text-gray-700">La dictas en reemplazo de {{ $solicitud->docente->nombre }}</p>
                    @elseif ($solicitud->docenteQueDicta)
                        <p class="font-medium text-amber-800">La dicta {{ $solicitud->docenteQueDicta->nombre }} en tu reemplazo</p>
                    @endif

                    @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Rechazada)
                        <p class="text-rojo-800">
                            <span class="font-semibold">Motivo del rechazo:</span>
                            {{ $solicitud->motivo_rechazo ?: 'No se dejó un motivo escrito.' }}
                        </p>
                    @elseif ($solicitud->estado === \App\Enums\EstadoSolicitud::Aprobada && ! $solicitud->preparacion?->sala)
                        <p class="text-gray-500">Sala por asignar: se asigna al preparar el escenario.</p>
                    @endif
                </x-fila-de-solicitud>
            @endforeach
        </ul>

        <div>{{ $solicitudes->links() }}</div>
    @endif
</div>
