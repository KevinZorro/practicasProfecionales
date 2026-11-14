<div class="space-y-5">
    <x-tarjeta titulo="Salas">
        @if ($salas->isEmpty())
            <x-mensaje-vacio titulo="No hay salas" descripcion="El ADMIN todavía no ha registrado ninguna sala." />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($salas as $sala)
                    <li wire:key="sala-{{ $sala->id }}" class="py-3.5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-[15px] font-semibold text-gray-900">
                                    {{ $sala->nombreCompleto() }}
                                    <x-insignia :tono="match ($sala->estado) {
                                        \App\Enums\EstadoSala::Operativa => 'exito',
                                        \App\Enums\EstadoSala::ConNovedad => 'atencion',
                                        \App\Enums\EstadoSala::FueraDeServicio => 'peligro',
                                    }">{{ $sala->estado->etiqueta() }}</x-insignia>
                                </p>
                                @if ($sala->novedad)
                                    <p class="mt-0.5 text-[14px] text-gray-700">{{ $sala->novedad }}</p>
                                @endif
                                @if ($sala->estado_actualizado_at)
                                    <p class="mt-0.5 text-[13px] text-gray-500">
                                        Anotado el {{ $sala->estado_actualizado_at->format('d/m/Y H:i') }} por {{ $sala->estadoActualizadoPor?->nombre ?? 'desconocido' }}
                                    </p>
                                @endif
                            </div>
                            @if ($editando !== $sala->id)
                                <x-boton variante="secundario" tamano="chico" type="button" wire:click="editar({{ $sala->id }})">Cambiar estado</x-boton>
                            @endif
                        </div>

                        @if ($editando === $sala->id)
                            <div class="mt-3 space-y-3 rounded-xl bg-gray-50 p-3.5">
                                <div>
                                    <label for="estado-{{ $sala->id }}" class="campo-etiqueta">Estado</label>
                                    <select wire:model.live="estado" id="estado-{{ $sala->id }}" class="campo">
                                        @foreach ($estados as $opcion)
                                            <option value="{{ $opcion->value }}">{{ $opcion->etiqueta() }}</option>
                                        @endforeach
                                    </select>
                                    @error('estado') <p class="campo-error">{{ $message }}</p> @enderror
                                </div>
                                @if (\App\Enums\EstadoSala::tryFrom($estado)?->exigeNovedad())
                                    <div>
                                        <label for="novedad-{{ $sala->id }}" class="campo-etiqueta">Qué pasa</label>
                                        <textarea wire:model="novedad" id="novedad-{{ $sala->id }}" rows="2" class="campo"
                                                  placeholder="El aire acondicionado no enfría, hay una filtración en el techo…"></textarea>
                                        @error('novedad') <p class="campo-error">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                                <div class="flex flex-wrap gap-2">
                                    <x-boton tamano="chico" type="button" wire:click="guardar">Guardar</x-boton>
                                    <x-boton variante="secundario" tamano="chico" type="button" wire:click="cancelar">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot:pie>
            Una sala con novedad se sigue ofreciendo al preparar, con la nota a la vista. Una fuera de servicio no se asigna a ninguna sesión hasta que vuelva a estar operativa.
        </x-slot:pie>
    </x-tarjeta>
</div>
