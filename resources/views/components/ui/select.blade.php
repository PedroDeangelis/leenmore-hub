@props([
    'label' => null,
])

@php
    $errorKey = $attributes->get('name') ?? $attributes->whereStartsWith('wire:model')->first();
@endphp

<div class="space-y-1">
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <select {{ $attributes->merge(['class' => 'form-select']) }}>
        {{ $slot }}
    </select>

    @if ($errorKey)
        @error($errorKey)
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
</div>
