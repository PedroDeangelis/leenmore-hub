<div>
    {{-- Header: back + project title + search --}}
    <header class="sticky top-0 z-20 bg-primary px-4 pb-4 pt-5 text-white">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-2">
                <a href="{{ route('worker.resources.index') }}" wire:navigate
                    class="-ms-1 flex size-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white"
                    aria-label="{{ __('Back') }}">
                    <x-heroicon-o-arrow-left class="size-5" />
                </a>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/60">
                        {{ __('Project resources') }}</p>
                    <h1 class="truncate text-[19px] font-bold tracking-tight">{{ $project->title }}</h1>
                </div>
            </div>

            <div class="relative mt-3">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                    class="w-full rounded-lg border-0 bg-white/15 py-2 pe-9 ps-3 text-sm text-white placeholder-white/60 focus:bg-white/20 focus:ring-2 focus:ring-white/30" />
                <x-heroicon-o-magnifying-glass
                    class="pointer-events-none absolute inset-e-3 top-1/2 size-4 -translate-y-1/2 text-white/60" />
            </div>
        </div>
    </header>

    <div class="space-y-2.5 py-4 max-w-md mx-auto">
        @forelse ($resources as $resource)
            <div wire:key="res-{{ $resource->id }}"
                class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    @if ($resource->isLink())
                        <x-heroicon-o-link class="size-4.5" />
                    @else
                        <x-heroicon-o-document class="size-4.5" />
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[14px] font-bold text-zinc-900">{{ $resource->title }}</p>
                    @if ($resource->isLink())
                        <p class="truncate text-[12px] text-zinc-400">{{ $resource->url }}</p>
                    @endif
                </div>
                @if ($resource->isLink())
                    <a href="{{ $resource->url }}" target="_blank" rel="noopener"
                        class="shrink-0 rounded-lg bg-primary px-3 py-2 text-[12.5px] font-semibold text-white transition hover:bg-primary-light">
                        {{ __('View link') }}
                    </a>
                @else
                    <a href="{{ route('resources.file', $resource) }}"
                        class="shrink-0 text-zinc-400 transition hover:text-primary" aria-label="{{ __('Download') }}">
                        <x-heroicon-o-arrow-down-tray class="size-5" />
                    </a>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
                <x-heroicon-o-folder-open class="size-9 text-zinc-300" />
                <p class="text-sm font-medium text-zinc-500">{{ __('No resources yet.') }}</p>
            </div>
        @endforelse
    </div>
</div>
