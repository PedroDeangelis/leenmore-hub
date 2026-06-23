@use('Illuminate\Support\Carbon')

<div>
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />


    <div class="mb-6">
        <h1 class="text-xl font-semibold text-zinc-900">{{ __('Reports') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Projects with activity reports') }}</p>
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


    {{-- Project cards --}}
    <div class="flex flex-col gap-2.5">
        @forelse ($projects as $project)
            @php $countdown = $project->meetingCountdown(); @endphp
            <a href="{{ route('reports.show', $project) }}" wire:navigate wire:key="report-project-{{ $project->id }}"
                class="flex flex-wrap items-center justify-between gap-5 rounded-xl border border-[#e8eaef] bg-white px-6 py-4.5 transition hover:shadow-[0_8px_22px_-12px_rgba(20,23,28,0.22)]">
                <div class="flex min-w-0 flex-wrap items-baseline gap-3">
                    <span class="text-[15.5px] font-bold tracking-tight text-[#1f2a5b]">{{ $project->title }}</span>
                    @if ($project->last_report_at)
                        <span
                            class="text-[12.5px] font-medium tabular-nums text-[#aeb4be]">{{ Carbon::parse($project->last_report_at)->format('Y/m/d H:i') }}h</span>
                    @endif
                </div>
                <div class="flex flex-none items-center gap-5">
                    @if ($countdown)
                        @if ($countdown->isUpcoming())
                            <span
                                class="whitespace-nowrap text-[13.5px] font-semibold text-[#2f4bb8]">{{ __('Meeting day (:date)', ['date' => $countdown->meetingDate->format('m/d')]) }}
                                <span class="font-bold">{{ $countdown->daysLeft }}</span>
                                {{ __('days left') }}</span>
                        @elseif ($countdown->isToday())
                            <span
                                class="whitespace-nowrap text-[13.5px] font-semibold text-[#2f4bb8]">{{ __('Meeting is today') }}</span>
                        @else
                            <span
                                class="whitespace-nowrap text-[13.5px] font-medium text-[#aeb4be]">{{ __('Meeting day (:date)', ['date' => $countdown->meetingDate->format('m/d')]) }}
                                <span class="font-semibold">{{ __('has passed') }}</span></span>
                        @endif
                    @endif
                    <span
                        class="whitespace-nowrap text-[12.5px] font-medium tabular-nums text-[#8b919c]">{{ trans_choice(':count report|:count reports', $project->submissions_count, ['count' => number_format($project->submissions_count)]) }}</span>
                    @php
                        $statusColor = match ($project->status->badgeVariant()) {
                            'success' => '#15834a',
                            'danger' => '#b53048',
                            default => '#9aa0ab',
                        };
                    @endphp
                    <span class="whitespace-nowrap text-[13px] font-semibold"
                        style="color:{{ $statusColor }};">{{ $project->status->label() }}</span>
                </div>
            </a>
        @empty
            <div
                class="rounded-xl border border-[#e8eaef] bg-white px-6 py-16 text-center text-sm font-medium text-[#aeb4be]">
                {{ __('No reports yet.') }}
            </div>
        @endforelse
    </div>


    @if ($projects->hasPages())
        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
</div>
