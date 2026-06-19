@props([
    'variant' => 'neutral',
    'dot' => false,
])

@php
    $styles = match ($variant) {
        'success' => ['badge' => 'bg-green-50 text-green-700', 'dot' => 'bg-green-500'],
        'danger' => ['badge' => 'bg-red-50 text-red-700', 'dot' => 'bg-red-500'],
        'warning' => ['badge' => 'bg-orange-50 text-orange-700', 'dot' => 'bg-orange-500'],
        default => ['badge' => 'bg-zinc-100 text-zinc-600', 'dot' => 'bg-zinc-400'],
    };
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ' . $styles['badge']]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $styles['dot'] }}"></span>
    @endif
    {{ $slot }}
</span>
