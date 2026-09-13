@props(['titulo' => 'No hay nada que mostrar', 'descripcion' => null])

<div {{ $attributes->class('rounded-lg border border-dashed border-gray-300 bg-white px-6 py-10 text-center') }}>
    <p class="text-sm font-medium text-gray-900">{{ $titulo }}</p>

    @if ($descripcion)
        <p class="mt-1 text-sm text-gray-500">{{ $descripcion }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
