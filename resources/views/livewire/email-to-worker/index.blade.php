<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Email to worker') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Pick a project, then compose an email to its workers') }}</p>
        </div>
        <x-ui.button variant="outline" :href="route('email-to-worker.history')" wire:navigate>
            <x-heroicon-o-clock class="size-4" />
            {{ __('Sent emails') }}
        </x-ui.button>
    </div>

    <x-ui.card class="p-4 max-w-170 mx-auto mb-6">
        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                aria-label="{{ __('Search projects') }}"
                class="w-full rounded-[10px] border border-[#e6e8ed] bg-[#fafbfc] py-3 pe-11 ps-4 text-sm text-[#3d424b] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" />
            <x-heroicon-o-magnifying-glass
                class="pointer-events-none absolute inset-e-4 top-1/2 size-4.25 -translate-y-1/2 text-[#aeb4be]" />
        </div>
    </x-ui.card>

    <div class="flex flex-col gap-2.5">
        @forelse ($projects as $project)
            <a href="{{ route('email-to-worker.compose', $project) }}" wire:navigate wire:key="ew-{{ $project->id }}"
                class="flex items-center justify-between gap-5 rounded-xl border border-[#e8eaef] bg-white px-6 py-4.5 transition hover:shadow-[0_8px_22px_-12px_rgba(20,23,28,0.22)]">
                <span class="text-[15.5px] font-bold tracking-tight text-[#1f2a5b]">{{ $project->title }}</span>
                @if ($project->end_date)
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-[12.5px] font-medium tabular-nums text-[#8b919c]">
                        <x-heroicon-o-calendar class="size-4" />
                        {{ $project->end_date->format('Y-m-d') }}
                    </span>
                @endif
            </a>
        @empty
            <div class="rounded-xl border border-[#e8eaef] bg-white px-6 py-16 text-center text-sm font-medium text-[#aeb4be]">
                {{ __('No projects found') }}
            </div>
        @endforelse
    </div>

    @if ($projects->hasPages())
        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
</div>
