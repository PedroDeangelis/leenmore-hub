<div>
    {{-- Header --}}
    <header class="sticky top-0 z-20 bg-primary px-5 pb-5 pt-6 text-white">
        <div class="max-w-md mx-auto">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">{{ config('app.name') }}</p>
            <h1 class="mt-1 text-[22px] font-bold tracking-tight">{{ __('Project resources') }}</h1>
        </div>
    </header>

    <div class="space-y-3 py-4 max-w-md mx-auto">
        @forelse ($projects as $project)
            <a href="{{ route('worker.resources.show', $project) }}" wire:navigate wire:key="rp-{{ $project->id }}"
                class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-3.5 shadow-sm transition active:scale-[0.99]">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-heroicon-o-folder class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[15px] font-bold text-zinc-900">{{ $project->title }}</p>
                    <div class="mt-1 flex items-center gap-1 text-[12px] font-semibold text-primary">
                        <x-heroicon-m-document class="size-3.5" />
                        {{ __(':count resources', ['count' => number_format($project->resources_count)]) }}
                    </div>
                </div>
                <x-heroicon-o-chevron-right class="size-5 shrink-0 text-zinc-300" />
            </a>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
                <x-heroicon-o-folder-open class="size-9 text-zinc-300" />
                <p class="text-sm font-medium text-zinc-500">{{ __('No resources yet.') }}</p>
            </div>
        @endforelse
    </div>
</div>
