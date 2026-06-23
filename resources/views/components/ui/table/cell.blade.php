@props(['align' => null])

<td {{ $attributes->merge(['class' => 'px-4 py-3' . ($align === 'end' ? ' text-end' : '')]) }}>
    {{ $slot }}
</td>
