{{-- Par etiqueta/valor. Se repite en todos los detalles del módulo. --}}
@props(['etiqueta'])

<div {{ $attributes->class('min-w-0') }}>
    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $etiqueta }}</dt>
    <dd class="mt-0.5 break-words text-sm text-gray-900">{{ $slot }}</dd>
</div>
