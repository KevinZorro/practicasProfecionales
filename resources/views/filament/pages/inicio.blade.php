{{-- Solo componentes de Filament y utilidades que trae su CSS: no compila las clases sueltas de Tailwind. --}}
<x-filament-panels::page>
    <x-filament::grid :default="1" :lg="3" class="gap-6">
        @foreach ($this->grupos() as $grupo)
            <x-filament::section :heading="$grupo['titulo']" :description="$grupo['descripcion']">
                <ul class="flex flex-col gap-y-3">
                    @foreach ($grupo['enlaces'] as $enlace)
                        <li>
                            <x-filament::link :href="$enlace['url']" :icon="$enlace['icono']" color="gray">
                                {{ $enlace['etiqueta'] }}
                            </x-filament::link>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endforeach
    </x-filament::grid>

    {{-- Las cuentas tienen reglas (motivo, bitácora, vigencia), por eso no son un recurso de aquí (RF22). --}}
    <x-filament::section heading="Cuentas y roles" description="No están aquí: deshabilitar una cuenta o darle un rol lleva motivo, queda en la bitácora y puede tener fecha de fin.">
        <x-filament::link :href="route('panel.usuarios')" icon="heroicon-o-users" color="gray">
            Ir a Usuarios, en el panel
        </x-filament::link>
    </x-filament::section>
</x-filament-panels::page>
