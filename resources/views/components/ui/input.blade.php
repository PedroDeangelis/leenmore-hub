@props([
    'label' => null,
    'viewable' => false,
])

@php
    $errorKey = $attributes->get('name') ?? $attributes->whereStartsWith('wire:model')->first();
@endphp

<div class="space-y-1">
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    @if ($viewable)
        <div x-data="{ show: false }" class="relative">
            <input {{ $attributes->merge(['class' => 'form-input pe-10']) }} x-bind:type="show ? 'text' : 'password'">
            <button type="button" x-on:click="show = !show"
                class="absolute inset-y-0 end-0 flex items-center pe-3 text-zinc-400 hover:text-zinc-600" tabindex="-1"
                aria-label="{{ __('Show password') }}">
                <x-heroicon-o-eye x-show="!show" class="size-5" />
                <x-heroicon-o-eye-slash x-show="show" x-cloak class="size-5" />
            </button>
        </div>
    @else
        <input {{ $attributes->merge(['class' => 'form-input']) }}>
    @endif

    @if ($errorKey)
        @error($errorKey)
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
</div>
