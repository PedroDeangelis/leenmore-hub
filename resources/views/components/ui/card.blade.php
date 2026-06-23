{{-- Bordered white panel. The canonical card used across the app. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-zinc-200 bg-white']) }}>
    {{ $slot }}
</div>
