{{--
    Acceso provisional de desarrollo. Solo se sirve en entorno local
    (middleware SoloEnDesarrollo); se retira cuando la entrada con Google (RF18)
    esté probada con las credenciales reales.
--}}
<x-pantalla-de-acceso titulo="Acceso de desarrollo">
    <x-aviso tono="atencion">
        Entrada provisional para probar las vistas con distintos roles. La
        autenticación definitiva es por Google y esta pantalla no existe
        fuera del entorno local.
    </x-aviso>

    @error('usuario')
        <x-aviso tono="peligro" role="alert" class="mt-4">{{ $message }}</x-aviso>
    @enderror

    @if ($usuarios->isEmpty())
        <p class="mt-5 text-[15px] text-gray-600">
            No hay usuarios sembrados. Ejecuta <code class="rounded bg-gray-100 px-1.5 py-0.5 text-[13px]">php artisan migrate:fresh --seed</code> para poder entrar.
        </p>
    @else
        <form method="POST" action="{{ route('desarrollo.entrar') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="usuario" class="campo-etiqueta">Entrar como</label>
                <select name="usuario" id="usuario" required class="campo">
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}">
                            {{ $usuario->nombre }} — {{ $usuario->roles->pluck('name')->join(', ') ?: 'sin rol' }}@if ($usuario->estado !== \App\Enums\EstadoUsuario::Activo) · inactivo @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <x-boton class="w-full">Entrar</x-boton>
        </form>
    @endif
</x-pantalla-de-acceso>
