{{--
    Integer input that displays Korean/Western thousands separators (38,428,915)
    while keeping the bound Livewire property a clean integer. The visible text is
    managed by Alpine; only digits are written back to the model via @entangle.
    Usage: <x-ui.number-input wire:model="shares_issued" :label="__('Shares')" />
--}}
@props([
    'label' => null,
])

@php
    $wireModel = $attributes->wire('model');
@endphp

<div class="space-y-1">
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <div
        x-data="{
            value: @entangle($wireModel),
            display: '',
            format(v) {
                if (v === null || v === undefined || v === '') return '';
                const digits = String(v).replace(/[^0-9]/g, '');
                return digits === '' ? '' : Number(digits).toLocaleString('en-US');
            },
            init() {
                this.display = this.format(this.value);
                this.$watch('value', (v) => {
                    const fresh = this.format(v);
                    if (fresh !== this.display) this.display = fresh;
                });
            },
            onInput(e) {
                const digits = e.target.value.replace(/[^0-9]/g, '');
                this.value = digits === '' ? null : Number(digits);
                this.display = this.format(digits);
            },
        }"
    >
        <input
            type="text"
            inputmode="numeric"
            x-bind:value="display"
            x-on:input="onInput"
            {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'form-input']) }}
        >
    </div>

    @if ($wireModel?->value())
        @error($wireModel->value())
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
</div>
