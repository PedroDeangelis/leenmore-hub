{{-- Bordered, scrollable data table. Pass the header cells via the `head` slot and the
     row(s) as the default slot (the <tbody> content). --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-zinc-200']) }}>
    <table class="min-w-full divide-y divide-zinc-200 text-sm">
        @isset($head)
            <thead class="bg-zinc-50">
                <tr class="text-start text-xs font-medium uppercase tracking-wide text-zinc-500">
                    {{ $head }}
                </tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-zinc-100 bg-white">
            {{ $slot }}
        </tbody>
    </table>
</div>
