<div>
    {{-- Brand header --}}
    <header class="sticky top-0 z-20 bg-primary px-5 pb-5 pt-6 text-white">
        <div class="max-w-md mx-auto">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">{{ config('app.name') }}</p>
            <h1 class="mt-1 text-[22px] font-bold tracking-tight">{{ __('Your projects') }}</h1>
            <p class="mt-1 text-[13px] text-white/70">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</p>
        </div>
    </header>

    <div class="space-y-3 py-4 max-w-md mx-auto">
        @forelse ($projects as $project)
            @php($countdown = $project->meetingCountdown())
            <a href="{{ route('worker.projects.show', $project) }}" wire:navigate wire:key="wp-{{ $project->id }}"
                class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-3.5 shadow-sm transition active:scale-[0.99]">
                {{-- Avatar: first character of the project title --}}
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary">
                    {{ \Illuminate\Support\Str::substr($project->title, 0, 1) }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-[15px] font-bold text-zinc-900">{{ $project->title }}</p>
                    <div class="mt-1 flex items-center gap-2 text-[12px] text-zinc-500">
                        <span class="inline-flex items-center gap-1 font-semibold text-primary">
                            <x-heroicon-m-users
                                class="size-3.5" />{{ __(':count shareholders', ['count' => number_format($project->my_shareholders_count)]) }}
                        </span>
                        @if ($countdown)
                            <span class="text-zinc-300">·</span>
                            @if ($countdown->isUpcoming())
                                <span class="font-semibold text-zinc-600 tabular-nums">{{ $countdown->daysLeft }}
                                    {{ Str::plural(__('day'), $countdown->daysLeft) }} {{ __('left') }}</span>
                            @elseif ($countdown->isToday())
                                <span class="font-bold text-primary">{{ __('Meeting today') }}</span>
                            @else
                                <span class="text-zinc-400">{{ __('Ended') }}</span>
                            @endif
                        @endif
                    </div>
                </div>

                <x-heroicon-o-chevron-right class="size-5 shrink-0 text-zinc-300" />
            </a>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
                <x-heroicon-o-folder-open class="size-9 text-zinc-300" />
                <p class="text-sm font-medium text-zinc-500">{{ __('No projects assigned yet.') }}</p>
                <p class="text-xs text-zinc-400">{{ __('Projects assigned to you will appear here.') }}</p>
            </div>
        @endforelse
    </div>
</div>
