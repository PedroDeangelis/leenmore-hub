@props(['cols' => 1])

<tr>
    <td colspan="{{ $cols }}" class="px-4 py-10 text-center text-zinc-500">{{ $slot }}</td>
</tr>
