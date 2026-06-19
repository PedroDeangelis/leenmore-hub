{{--
    Phone field with a Korean number mask (e.g. 010-1234-5678, 02-1234-5678).
    Uses Alpine's bundled x-mask plugin; the masked value (with dashes) is what
    gets stored. Usage: <x-ui.phone-input wire:model="phone" :label="__('Phone')" />
--}}
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

    <input
        type="tel"
        inputmode="numeric"
        x-mask:dynamic="
            (input) => {
                const d = input.replace(/\D/g, '');
                if (d.startsWith('02')) return d.length > 9 ? '99-9999-9999' : '99-999-9999';
                return d.length > 10 ? '999-9999-9999' : '999-999-9999';
            }
        "
        {{ $attributes->merge(['class' => 'form-input']) }}
    >

    @if ($errorKey)
        @error($errorKey)
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
</div>
