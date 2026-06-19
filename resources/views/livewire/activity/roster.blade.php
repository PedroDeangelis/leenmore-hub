<div class="-m-6 min-h-screen bg-[#f4f5f7] p-4 text-[13px] text-[#3d424b] sm:px-10 sm:py-8"
    style="font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',system-ui,sans-serif;">
    {{-- Pretendard gives proper Korean glyphs; falls back to system Korean fonts. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />

    <div class="mx-auto max-w-[1200px]">

        {{-- Header --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-[24px] font-bold tracking-tight text-[#171a1f]">{{ $project->title }}</h1>
            <a href="{{ route('activity.index') }}" wire:navigate
                class="inline-flex items-center gap-1.5 text-[13.5px] font-bold text-primary transition hover:text-primary-light">
                <x-heroicon-o-arrow-left class="size-[15px]" />{{ __('Back') }}
            </a>
        </div>

        {{-- Search + filter --}}
        <div class="mb-[18px] flex flex-wrap items-center gap-3 rounded-2xl border border-[#e8eaef] bg-white p-3.5">
            <div class="relative min-w-[220px] flex-1">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                    aria-label="{{ __('Search shareholders') }}"
                    class="w-full rounded-[10px] border border-[#e6e8ed] bg-[#fafbfc] py-3 pe-11 ps-4 text-sm text-[#3d424b] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" />
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute end-4 top-1/2 size-[17px] -translate-y-1/2 text-[#aeb4be]" />
            </div>
            <div class="relative w-[200px]">
                <select wire:model.live="reports" aria-label="{{ __('Filter by reports') }}"
                    class="w-full appearance-none rounded-[10px] border border-[#e6e8ed] bg-white py-3 pe-9 ps-4 text-[13px] font-semibold text-[#3d424b] outline-none focus:border-primary">
                    <option value="">{{ __('All shareholders') }}</option>
                    <option value="has">{{ __('Has reports') }}</option>
                    <option value="none">{{ __('No reports') }}</option>
                </select>
                <x-heroicon-o-chevron-down class="pointer-events-none absolute end-3 top-1/2 size-3.5 -translate-y-1/2 text-[#9aa0aa]" />
            </div>
        </div>

        {{-- Roster --}}
        <div class="overflow-hidden rounded-2xl border border-[#e8eaef] bg-white">
            <div class="grid grid-cols-[minmax(150px,1.6fr)_0.9fr_1.1fr_1.4fr_0.7fr_auto] items-center gap-3 border-b border-[#e8eaef] bg-[#fafbfc] px-5 py-[13px] text-[11px] font-semibold tracking-wide text-[#8b919c]">
                <span>{{ __('Shareholder') }}</span>
                <span class="text-right">{{ __('Shares') }}</span>
                <span class="text-right">{{ __('Total shares') }}</span>
                <span>{{ __('Judgment') }}</span>
                <span class="text-right">{{ __('Reports') }}</span>
                <span></span>
            </div>

            @forelse ($rows as $row)
                <a href="{{ route('activity.report', [$project, $row]) }}" wire:navigate wire:key="roster-{{ $row->id }}"
                    class="grid grid-cols-[minmax(150px,1.6fr)_0.9fr_1.1fr_1.4fr_0.7fr_auto] items-center gap-3 border-b border-[#f1f2f5] px-5 py-3.5 transition hover:bg-[#fafbfc]">
                    <span class="flex flex-wrap items-baseline gap-1.5">
                        <span class="text-[14px] font-bold text-[#171a1f]">{{ $row->shareholder?->name ?? '—' }}</span>
                        <span class="text-[12.5px] font-medium tabular-nums text-[#8b919c]">{{ $row->shareholder?->date_of_birth_code ?: $row->shareholder?->registration }}</span>
                    </span>
                    <span class="text-right text-[13px] tabular-nums text-[#3d424b]">{{ $row->shares !== null ? number_format($row->shares) : '—' }}</span>
                    <span class="text-right text-[13px] tabular-nums text-[#3d424b]">{{ $row->shares_total !== null ? number_format($row->shares_total) : '—' }}</span>
                    <span>
                        @if ($row->result)
                            <x-result.chip :color="$row->result->color" :label="$row->result->name" />
                        @else
                            <span class="text-[12.5px] text-[#aeb4be]">{{ __('Not yet entered') }}</span>
                        @endif
                    </span>
                    <span class="text-right text-[12.5px] font-semibold tabular-nums {{ $row->submissions_count > 0 ? 'text-[#2f4bb8]' : 'text-[#c5cad2]' }}">{{ number_format($row->submissions_count) }}</span>
                    <x-heroicon-o-chevron-right class="size-4 text-[#c5cad2]" />
                </a>
            @empty
                <div class="px-5 py-16 text-center text-sm font-medium text-[#aeb4be]">{{ __('No shareholders found.') }}</div>
            @endforelse
        </div>

        @if ($rows->hasPages())
            <div class="mt-6">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
