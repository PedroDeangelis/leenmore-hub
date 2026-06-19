<div class="-m-6 min-h-screen bg-[#f4f5f7] p-4 text-[13px] text-[#3d424b] sm:px-10 sm:py-8"
    style="font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',system-ui,sans-serif;">
    {{-- Pretendard gives proper Korean glyphs; falls back to system Korean fonts. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />

    <div class="mx-auto max-w-[1200px]">
        <h1 class="mb-2 text-[26px] font-bold tracking-tight text-[#171a1f]">{{ __('Activity reports') }}</h1>
        <p class="mb-6 text-sm text-[#8b919c]">{{ __('Select a project to enter reports') }}</p>

        {{-- Search --}}
        <div class="mx-auto mb-6 max-w-[680px] rounded-2xl border border-[#e8eaef] bg-white px-6 py-5 shadow-[0_6px_20px_-14px_rgba(20,23,28,0.2)]">
            <div class="relative">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                    aria-label="{{ __('Search projects') }}"
                    class="w-full rounded-[10px] border border-[#e6e8ed] bg-[#fafbfc] py-3 pe-11 ps-4 text-sm text-[#3d424b] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" />
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute end-4 top-1/2 size-[17px] -translate-y-1/2 text-[#aeb4be]" />
            </div>
        </div>

        {{-- Project cards --}}
        <div class="flex flex-col gap-2.5">
            @forelse ($projects as $project)
                @php($countdown = $project->meetingCountdown())
                <a href="{{ route('activity.project', $project) }}" wire:navigate wire:key="activity-project-{{ $project->id }}"
                    class="flex flex-wrap items-center justify-between gap-5 rounded-xl border border-[#e8eaef] bg-white px-6 py-[18px] transition hover:shadow-[0_8px_22px_-12px_rgba(20,23,28,0.22)]">
                    <div class="flex min-w-0 flex-wrap items-baseline gap-3">
                        <span class="text-[15.5px] font-bold tracking-tight text-[#1f2a5b]">{{ $project->title }}</span>
                    </div>
                    <div class="flex flex-none items-center gap-5">
                        @if ($countdown)
                            @if ($countdown->isUpcoming())
                                <span class="whitespace-nowrap text-[13.5px] font-semibold text-[#2f4bb8]">{{ __('Meeting day (:date)', ['date' => $countdown->meetingDate->format('m/d')]) }} <span class="font-bold">{{ $countdown->daysLeft }}</span> {{ __('days left') }}</span>
                            @elseif ($countdown->isToday())
                                <span class="whitespace-nowrap text-[13.5px] font-semibold text-[#2f4bb8]">{{ __('Meeting is today') }}</span>
                            @else
                                <span class="whitespace-nowrap text-[13.5px] font-medium text-[#aeb4be]">{{ __('Meeting day (:date)', ['date' => $countdown->meetingDate->format('m/d')]) }} <span class="font-semibold">{{ __('has passed') }}</span></span>
                            @endif
                        @endif
                        <span class="whitespace-nowrap text-[12.5px] font-medium tabular-nums text-[#8b919c]">{{ trans_choice(':count report|:count reports', $project->submissions_count, ['count' => number_format($project->submissions_count)]) }}</span>
                        <x-heroicon-o-chevron-right class="size-4 text-[#c5cad2]" />
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-[#e8eaef] bg-white px-6 py-16 text-center text-sm font-medium text-[#aeb4be]">
                    {{ __('No published projects.') }}
                </div>
            @endforelse
        </div>

        @if ($projects->hasPages())
            <div class="mt-6">{{ $projects->links() }}</div>
        @endif
    </div>
</div>
