@props([
    'icon' => null,
    'heading' => null,
    'subheading' => null,
    'tile' => 'bg-red-50 text-primary',
    'divider' => true,
])

<div
    {{ $attributes->merge(['class' => 'flex items-center mb-6 gap-4' . ($divider ? ' border-b border-zinc-200 pb-6' : '')]) }}>
    @if ($icon)
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tile }}">
            @svg($icon, 'size-5')
        </span>
    @endif

    <div>
        @if ($heading)
            <h2 class="text-base font-semibold text-zinc-700">{{ $heading }}</h2>
        @endif

        @if ($subheading)
            <p class="text-sm text-zinc-500">{{ $subheading }}</p>
        @endif

        {{ $slot }}
    </div>
</div>
