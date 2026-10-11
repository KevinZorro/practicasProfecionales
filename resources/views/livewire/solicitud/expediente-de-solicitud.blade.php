@php
    $estados = \App\Enums\EstadoSolicitud::class;
    $presentes = $solicitud->estudiantes->reject(fn ($estudiante) => $estudiante->participacion->fueRetirado());
    $retirados = $solicitud->estudiantes->count() - $presentes->count();
    $deRegistroPrevio = $solicitud->origen === \App\Enums\OrigenSolicitud::RegistroPrevio;
    $preparacion = $solicitud->preparacion;
@endphp

<div class="space-y-5">

    {{-- Lo que hay que saber antes de seguir leyendo. --}}
    @if ($solicitud->estado === $estados::Rechazada)
        <x-aviso tono="peligro">
            <p class="font-semibold">Motivo del rechazo</p>
            <p class="mt-0.5">{{ $solicitud->motivo_rechazo ?: 'No se dejó un motivo escrito.' }}</p>
        </x-aviso>
    @endif

    @if ($faltantes !== [])
        <x-aviso tono="atencion">
            Se pidieron más unidades de las que quedan libres en esa franja. El docente no ve
            disponibilidad al solicitar, así que puede pedir de más. Es un aviso, no un impedimento.
        </x-aviso>
    @endif

    @if ($sinCobertura)
        {{-- RF66: solo se avisa; qué hacer con la práctica lo decide el laboratorio. --}}
        <x-aviso tono="peligro" role="alert">
            Sin unidades suficientes: alguno de los equipos de esta práctica dejó de estar operativo
            después de aprobarla.
        </x-aviso>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="min-w-0 space-y-5">
            @if ($solicitud->observaciones)
                <x-tarjeta titulo="Observaciones del docente">
                    <p class="whitespace-pre-line text-[15px] leading-relaxed text-gray-800">{{ $solicitud->observaciones }}</p>
                </x-tarjeta>
            @endif

            <x-tarjeta titulo="Equipos solicitados">
                <x-lista-equipos :items="$solicitud->items" :faltantes="$faltantes" />
            </x-tarjeta>

            <x-tarjeta titulo="Estudiantes de la sesión"
                       :descripcion="trans_choice(':count estudiante|:count estudiantes', $presentes->count(), ['count' => $presentes->count()]).($retirados > 0 ? ' · '.trans_choice(':count retirado|:count retirados', $retirados, ['count' => $retirados]) : '')">
                @if ($presentes->isEmpty())
                    <p class="text-[14px] text-gray-500">Todavía no hay estudiantes en la lista.</p>
                @else
                    <ul class="grid gap-x-6 sm:grid-cols-2">
                        @foreach ($presentes as $estudiante)
                            <li wire:key="estudiante-{{ $estudiante->id }}" class="flex items-center gap-2.5 border-b border-gray-100 py-2 text-[14px] text-gray-900">
                                <x-iniciales :nombre="$estudiante->nombre" class="size-7 bg-gray-100 text-[11px] text-gray-700" />
                                <span class="min-w-0 truncate">{{ $estudiante->nombre }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <x-slot:pie>
                    <a href="{{ route('panel.solicitudes.participantes', $solicitud) }}"
                       class="inline-flex items-center gap-1 font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900">
                        Quién puede ingresar y quién no
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </x-slot:pie>
            </x-tarjeta>
        </div>

        <div class="space-y-5">
            {{-- Quién hizo cada paso: la línea de estados dice cuándo, esto dice quién. --}}
            <x-tarjeta titulo="Trámite">
                <dl class="space-y-4">
                    @if ($deRegistroPrevio)
                        <x-dato etiqueta="Apartada antes del semestre">
                            {{ $solicitud->registradaPor?->nombre ?? 'Sin registro' }}
                            <span class="block text-[13px] text-gray-500">{{ $solicitud->created_at?->translatedFormat('j M Y, H:i') }}</span>
                        </x-dato>
                        <x-dato etiqueta="Formato intramural">
                            @if ($solicitud->tieneFormatoIntramural())
                                Registrado
                                <span class="block text-[13px] text-gray-500">{{ $solicitud->formato_intramural_at->translatedFormat('j M Y, H:i') }}</span>
                            @else
                                <span class="font-medium text-amber-800">Sin registrar</span>
                            @endif
                        </x-dato>
                    @else
                        <x-dato etiqueta="Solicitó">
                            {{ $solicitud->docente->nombre }}
                            <span class="block text-[13px] text-gray-500">{{ $solicitud->created_at?->translatedFormat('j M Y, H:i') }}</span>
                        </x-dato>
                        @if ($solicitud->revisadaPor)
                            <x-dato etiqueta="Revisó">
                                {{ $solicitud->revisadaPor->nombre }}
                                <span class="block text-[13px] text-gray-500">{{ $solicitud->revisada_at?->translatedFormat('j M Y, H:i') }}</span>
                            </x-dato>
                        @endif
                    @endif

                    @if ($solicitud->resueltaPor && in_array($solicitud->estado, [$estados::Aprobada, $estados::Rechazada], true))
                        <x-dato :etiqueta="$solicitud->estado === $estados::Aprobada ? 'Aprobó' : 'Rechazó'">
                            {{ $solicitud->resueltaPor->nombre }}
                            <span class="block text-[13px] text-gray-500">{{ $solicitud->resuelta_at?->translatedFormat('j M Y, H:i') }}</span>
                        </x-dato>
                    @endif
                </dl>
            </x-tarjeta>

            @if ($preparacion && $veLaPreparacion)
                <x-tarjeta titulo="Montaje">
                    <div class="flex items-center justify-between gap-3">
                        <x-etiqueta-estado :estado="$preparacion->estado" />
                        @if ($preparacion->sala)
                            <span class="min-w-0 truncate text-[14px] text-gray-700">{{ $preparacion->sala->nombre }}</span>
                        @endif
                    </div>
                    <x-slot:pie>
                        <a href="{{ route('panel.preparaciones', ['fecha' => $solicitud->fecha->format('Y-m-d')]) }}"
                           class="inline-flex items-center gap-1 font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900">
                            Preparaciones de ese día
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </x-slot:pie>
                </x-tarjeta>
            @endif
        </div>
    </div>

    {{--
        La decisión, al pie y pegada al borde inferior mientras se lee el
        detalle: quien decide tiene que leer antes de pulsar, y no debe tener
        que volver a buscar el botón.
    --}}
    @if ($puedeRevisar || $puedeAprobar || $puedeRechazar)
        <div class="sticky bottom-3 z-20 rounded-2xl bg-white p-4 shadow-[0_12px_32px_-12px_rgb(0_0_0/0.28)] ring-1 ring-gray-300 sm:bottom-5 sm:px-5">
            @if ($rechazando)
                <form wire:submit="rechazar" class="space-y-3">
                    <div>
                        <label for="motivo-rechazo" class="campo-etiqueta">
                            Motivo del rechazo <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <textarea wire:model="motivoRechazo" id="motivo-rechazo" rows="2" class="campo" x-init="$el.focus()"></textarea>
                        @error('motivoRechazo') <p class="campo-error">{{ $message }}</p> @enderror
                        <p class="campo-ayuda">El docente lo verá en su historial y le llegará por correo.</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <x-boton variante="secundario" type="button" wire:click="cancelarRechazo">Cancelar</x-boton>
                        <x-boton variante="peligro">Confirmar rechazo</x-boton>
                    </div>
                </form>
            @else
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                    <p class="min-w-0 max-w-[52ch] flex-1 basis-64 text-[14px] leading-snug text-gray-600">
                        @if ($puedeRevisar)
                            Revisa el escenario, la fecha y los equipos. Si todo cuadra, pasa a coordinación para que la apruebe.
                        @elseif ($puedeAprobar)
                            La revisó {{ $solicitud->revisadaPor?->nombre ?? 'el laboratorio' }}. Al aprobarla se crea la preparación y se le avisa al docente.
                        @else
                            Puedes rechazarla si no se puede atender.
                        @endif
                    </p>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                        @if ($puedeRechazar)
                            <x-boton variante="peligro-suave" type="button" wire:click="pedirMotivo" class="flex-1 sm:flex-none">Rechazar</x-boton>
                        @endif
                        @if ($puedeRevisar)
                            <x-boton type="button" wire:click="revisar" class="flex-1 sm:flex-none">Marcar como revisada</x-boton>
                        @endif
                        @if ($puedeAprobar)
                            <x-boton type="button" wire:click="aprobar" class="flex-1 sm:flex-none">Aprobar</x-boton>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @elseif ($solicitud->estado === $estados::Pendiente)
        <p class="text-[14px] text-gray-500">Espera la revisión del laboratorio.</p>
    @elseif ($solicitud->estado === $estados::Revisada)
        <p class="text-[14px] text-gray-500">Espera la aprobación de coordinación.</p>
    @endif
</div>
