@props([
    'model' => null,   // Livewire property to two-way bind visibility to
    'show' => false,   // initial visibility when not Livewire-bound
    'maxWidth' => 'max-w-lg',
])

<div
    x-data="{ show: @if ($model) $wire.entangle('{{ $model }}') @else {{ $show ? 'true' : 'false' }} @endif }"
    x-show="show"
    x-cloak
    x-on:keydown.escape.window="show = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
>
    <div class="fixed inset-0 bg-black/50" x-on:click="show = false"></div>

    <div {{ $attributes->merge(['class' => 'relative w-full '.$maxWidth.' rounded-xl bg-white p-6 shadow-xl']) }}>
        {{ $slot }}
    </div>
</div>
