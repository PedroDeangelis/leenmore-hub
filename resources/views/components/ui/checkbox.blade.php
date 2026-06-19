@props([
    'label' => null,
])

<label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700">
    <input type="checkbox" {{ $attributes->merge(['class' => 'form-checkbox']) }}>
    @if ($label)
        <span>{{ $label }}</span>
    @endif
</label>
