<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">
            {{ $errorDeRegla }}
        </x-aviso>
    @endif

    @if (! $esAlta && $cuenta->origen->vieneDeLaSincronizacion())
        <x-aviso tono="atencion">
            Esta cuenta viene de la base institucional. Lo que cambies aquí lo volverá a escribir
            la siguiente sincronización: si un dato está mal, hay que corregirlo en la universidad.
        </x-aviso>
    @endif

    <form wire:submit="guardar" class="space-y-5">
        <x-tarjeta titulo="Datos de la cuenta">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="nombre" class="campo-etiqueta">Nombre completo</label>
                    <input type="text" wire:model="nombre" id="nombre" class="campo">
                    @error('nombre') <p class="campo-error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="correo" class="campo-etiqueta">Correo institucional</label>
                    <input type="email" wire:model="correo" id="correo" autocomplete="off"
                           class="campo">
                    <p class="campo-ayuda">Con este correo entra por Google. No puede repetirse.</p>
                    @error('correo') <p class="campo-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="documento" class="campo-etiqueta">Documento <span class="font-normal text-gray-500">(opcional)</span></label>
                    <input type="text" wire:model="documento" id="documento" inputmode="numeric"
                           class="campo">
                    @error('documento') <p class="campo-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="codigoInstitucional" class="campo-etiqueta">Código institucional <span class="font-normal text-gray-500">(opcional)</span></label>
                    <input type="text" wire:model="codigoInstitucional" id="codigoInstitucional"
                           class="campo">
                    @error('codigoInstitucional') <p class="campo-error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="programa" class="campo-etiqueta">Programa <span class="font-normal text-gray-500">(opcional)</span></label>
                    <select wire:model="programa" id="programa"
                            class="campo">
                        <option value="">Sin programa</option>
                        @foreach ($programas as $unPrograma)
                            <option value="{{ $unPrograma }}">{{ $unPrograma }}</option>
                        @endforeach
                    </select>
                    @error('programa') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-tarjeta>

        <div class="flex flex-wrap items-center gap-2">
            <x-boton wire:loading.attr="disabled" wire:target="guardar">Guardar</x-boton>
            <x-boton variante="texto" href="{{ route('panel.usuarios') }}">Cancelar</x-boton>
        </div>
    </form>
</div>
