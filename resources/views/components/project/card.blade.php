{{-- Bordered white panel used for every section on the project dashboard. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-zinc-200 bg-white']) }}>
    {{ $slot }}
</div>
