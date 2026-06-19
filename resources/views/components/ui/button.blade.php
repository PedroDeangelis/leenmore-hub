@props([
    'variant' => 'primary',
    'href' => null,
])

@php
    $classes = 'btn '.match ($variant) {
        'danger' => 'btn-danger',
        'outline' => 'btn-outline',
        'ghost' => 'btn-ghost',
        default => 'btn-primary',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
