@props(['align' => 'start'])

<th {{ $attributes->merge(['class' => 'px-4 py-3 font-medium ' . ($align === 'end' ? 'text-end' : 'text-start')]) }}>
    {{ $slot }}
</th>
