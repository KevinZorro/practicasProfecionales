{{--
    Acceso provisional de desarrollo. Solo se sirve en entorno local
    (middleware SoloEnDesarrollo); desaparece cuando entre Socialite (RF18).
--}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso de desarrollo · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-gray-50 px-4 font-sans text-gray-900 antialiased">

<div class="w-full max-w-md">
    <x-tarjeta titulo="Acceso de desarrollo">
        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
            Entrada provisional para probar las vistas con distintos roles. La
            autenticación definitiva es por Google y esta pantalla no existe
            fuera del entorno local.
        </p>

        @error('usuario')
            <p class="mb-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        @if ($usuarios->isEmpty())
            <x-mensaje-vacio
                titulo="No hay usuarios sembrados"
                descripcion="Ejecuta php artisan migrate:fresh --seed para poder entrar."
            />
        @else
            <form method="POST" action="{{ route('desarrollo.entrar') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="usuario" class="mb-1 block text-sm font-medium text-gray-700">Entrar como</label>
                    <select name="usuario" id="usuario" required
                            class="w-full rounded-md border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}">
                                {{ $usuario->nombre }} — {{ $usuario->roles->pluck('name')->join(', ') ?: 'sin rol' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-boton class="w-full">Entrar</x-boton>
            </form>
        @endif
    </x-tarjeta>
</div>

</body>
</html>
