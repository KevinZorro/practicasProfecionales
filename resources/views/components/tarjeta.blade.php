@props(['titulo' => null, 'pie' => null])

<div {{ $attributes->class('overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm') }}>
    @if ($titulo)
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">{{ $titulo }}</h2>
        </div>
    @endif

    <div class="px-4 py-4">
        {{ $slot }}
    </div>

    @if ($pie)
        <div class="border-t border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            {{ $pie }}
        </div>
    @endif
</div>
