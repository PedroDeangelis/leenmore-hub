{{--
    Small square icon button used across the project dashboard (edit pencils,
    download/sort actions). Pass a Blade-icon name via `icon`. Renders as a
    <button> by default, or an <a> when `href` is set.
--}}
@props([
    'icon' => null,
    'href' => null,
    'size' => 'size-[30px]',
])

@php
    $classes = 'inline-flex '.$size.' shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-400 transition hover:border-zinc-300 hover:text-zinc-600';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            @svg($icon, 'size-3.5')
        @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        @if ($icon)
            @svg($icon, 'size-3.5')
        @endif
        {{ $slot }}
    </button>
@endif
