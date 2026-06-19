@props([
    'label' => null,
    'rows' => 4,
])

@php
    $errorKey = $attributes->get('name') ?? $attributes->whereStartsWith('wire:model')->first();
@endphp

<div class="space-y-1">
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <textarea {{ $attributes->merge(['class' => 'form-input', 'rows' => $rows]) }}>{{ $slot }}</textarea>

    @if ($errorKey)
        @error($errorKey)
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
</div>
