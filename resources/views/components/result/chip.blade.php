{{--
    Coloured outcome (판단) pill. Colours come from the global App\Enums\ResultColor
    palette. Usage: <x-result.chip :color="$result->color" :label="$result->name" />
--}}
@props([
    'color',
    'label' => null,
])

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-[11.5px] font-semibold '.$color->chipClasses()]) }}>{{ $label ?? $slot }}</span>
