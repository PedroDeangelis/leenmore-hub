@php
    // Shared 7-column grid for the header, summary, and every data row.
    $grid =
        'display:grid;grid-template-columns:minmax(150px,1.5fr) 0.9fr 1.1fr 0.9fr 1.15fr 1.7fr 1.3fr;align-items:center;gap:12px;';
    $thClass = 'text-[11px] font-semibold tracking-wide text-[#8b919c]';
@endphp

<div class="" x-data>


    <div class="mb-6">
        <h1 class="text-xl font-semibold text-zinc-900">{{ $project->title }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('All reports submitted for this project') }}</p>
    </div>

    {{-- Filter / sort panel --}}
    <x-project.card class="p-4 space-y-2 max-w-300 mx-auto mb-10">

        <div class="flex items-center gap-3">
            <div
                class="w-[120px] flex-none rounded-[9px] bg-[#eef0f4] py-[11px] text-center text-[13px] font-bold text-[#5b616c]">
                {{ __('Filter') }}</div>
            <div class="flex flex-1 flex-wrap gap-3">
                <div class="relative min-w-[160px] flex-1">
                    <select wire:model.live="worker" aria-label="{{ __('Filter by activist') }}"
                        class="w-full appearance-none rounded-[9px] border border-[#e6e8ed] py-2.5 pe-9 ps-3.5 text-[13px] font-semibold text-[#3d424b] outline-none focus:border-primary">
                        <option value="">{{ __('All activists') }}</option>
                        @foreach ($workers as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-heroicon-o-chevron-down
                        class="pointer-events-none absolute end-3 top-1/2 size-3.5 -translate-y-1/2 text-[#9aa0aa]" />
                </div>
                <div class="relative min-w-[160px] flex-1">
                    <select wire:model.live="result" aria-label="{{ __('Filter by result') }}"
                        class="w-full appearance-none rounded-[9px] border border-[#e6e8ed] py-2.5 pe-9 ps-3.5 text-[13px] font-semibold text-[#3d424b] outline-none focus:border-primary">
                        <option value="">{{ __('All results') }}</option>
                        @foreach ($results as $result)
                            <option value="{{ $result->name }}">{{ $result->name }}</option>
                        @endforeach
                    </select>
                    <x-heroicon-o-chevron-down
                        class="pointer-events-none absolute end-3 top-1/2 size-3.5 -translate-y-1/2 text-[#9aa0aa]" />
                </div>
            </div>
        </div>
        {{-- sort --}}
        <div class="flex items-center gap-3">
            <div
                class="w-[120px] flex-none rounded-[9px] bg-[#eef0f4] py-[11px] text-center text-[13px] font-bold text-[#5b616c]">
                {{ __('Sort') }}</div>
            <div class="flex flex-1 flex-wrap gap-3">
                <button type="button" wire:click="sortBy('shares')"
                    class="flex min-w-[160px] flex-1 cursor-pointer items-center justify-between rounded-[9px] border border-[#e6e8ed] px-3.5 py-2.5 text-[13px] font-semibold text-[#3d424b]">
                    <span>{{ __('Total shares') }}</span>
                    @if ($sort === 'shares')
                        @if ($direction === 'asc')
                            <x-heroicon-o-arrow-up class="size-[15px] text-primary" />
                        @else
                            <x-heroicon-o-arrow-down class="size-[15px] text-primary" />
                        @endif
                    @else
                        <x-heroicon-o-chevron-up-down class="size-[15px] text-[#c5cad2]" />
                    @endif
                </button>
                <button type="button" wire:click="sortBy('date')"
                    class="flex min-w-[160px] flex-1 cursor-pointer items-center justify-between rounded-[9px] border border-[#e6e8ed] px-3.5 py-2.5 text-[13px] font-semibold text-[#3d424b]">
                    <span>{{ __('Submitted') }}</span>
                    @if ($sort === 'date')
                        @if ($direction === 'asc')
                            <x-heroicon-o-arrow-up class="size-[15px] text-primary" />
                        @else
                            <x-heroicon-o-arrow-down class="size-[15px] text-primary" />
                        @endif
                    @else
                        <x-heroicon-o-chevron-up-down class="size-[15px] text-[#c5cad2]" />
                    @endif
                </button>
                <label
                    class="flex min-w-[160px] flex-1 cursor-pointer items-center gap-2.5 rounded-[9px] border border-[#e6e8ed] px-3.5 py-2.5 text-[13px] font-semibold text-[#3d424b]">
                    <input type="checkbox" wire:model.live="latestOnly"
                        class="size-[17px] rounded-[5px] border-[1.5px] border-[#cdd2da] accent-primary focus:ring-2 focus:ring-primary/30" />
                    {{ __('Latest activity per shareholder only') }}
                </label>
            </div>
        </div>
        {{-- search --}}
        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                aria-label="{{ __('Search reports') }}"
                class="w-full rounded-[10px] border border-[#e6e8ed] bg-[#fafbfc] py-3 pe-11 ps-4 text-sm text-[#3d424b] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" />
            <x-heroicon-o-magnifying-glass
                class="pointer-events-none absolute end-4 top-1/2 size-[17px] -translate-y-1/2 text-[#aeb4be]" />
        </div>
    </x-project.card>

    <div class="">



        {{-- Table --}}
        <div class="overflow-hidden rounded-2xl border border-[#e8eaef] bg-white">
            {{-- header --}}
            <div class="border-b border-[#e8eaef] bg-[#fafbfc] px-[22px] py-[13px]" style="{{ $grid }}">
                <span class="{{ $thClass }}">{{ __('Shareholder') }}</span>
                <span class="{{ $thClass }}">{{ __('Shares') }}</span>
                <span class="{{ $thClass }}">{{ __('Total shares') }}</span>
                <span class="{{ $thClass }}">{{ __('Activist') }}</span>
                <span class="{{ $thClass }}">{{ __('Submitted') }}</span>
                <span class="{{ $thClass }}">{{ __('Project') }}</span>
                <span class="{{ $thClass }} text-end">{{ __('Result') }}</span>
            </div>

            @if (count($rows) > 0)
                {{-- summary --}}
                <div class="border-b border-[#f1f2f5] bg-[#fbfcfd] px-[22px] py-3.5" style="{{ $grid }}">
                    <span
                        class="text-[14px] font-bold tabular-nums text-[#171a1f]">{{ number_format($summary['count']) }}</span>
                    <span
                        class="text-[13.5px] font-bold tabular-nums text-[#171a1f]">{{ number_format($summary['shares']) }}</span>
                    <span
                        class="text-[13.5px] font-bold tabular-nums text-[#171a1f]">{{ number_format($summary['total']) }}</span>
                    <span></span><span></span><span></span><span></span>
                </div>

                @foreach ($rows as $row)
                    <div wire:key="report-row-{{ $row['id'] }}">
                        {{-- row --}}
                        <div wire:click="toggle({{ $row['id'] }})"
                            class="cursor-pointer border-b border-[#f1f2f5] px-[22px] py-[15px] transition hover:bg-[#fafbfc]"
                            style="{{ $grid }}">
                            <span class="flex flex-wrap items-baseline gap-1.5">
                                <span class="text-[14px] font-bold text-[#171a1f]">{{ $row['name'] }}</span>
                                @if ($row['idNum'])
                                    <span
                                        class="text-[12.5px] font-medium tabular-nums text-[#8b919c]">{{ $row['idNum'] }}</span>
                                @endif
                                @if ($row['gender'])
                                    <span
                                        class="text-[12.5px] font-semibold text-primary">({{ $row['gender'] }})</span>
                                @endif
                            </span>
                            <span
                                class="text-[13px] tabular-nums text-[#3d424b]">{{ $row['shares'] !== null ? number_format($row['shares']) : '—' }}</span>
                            <span
                                class="text-[13px] tabular-nums text-[#3d424b]">{{ $row['total'] !== null ? number_format($row['total']) : '—' }}</span>
                            <span class="text-[13px] text-[#3d424b]">{{ $row['worker'] }}</span>
                            <span
                                class="text-[12.5px] tabular-nums text-[#5b616c]">{{ $row['submitDate']?->format('y/m/d H:i') ?? '—' }}</span>
                            <span class="truncate text-[13px] text-[#5b616c]">{{ $project->title }}</span>
                            <span class="flex items-center justify-end gap-2.5">
                                <span
                                    class="text-[11.5px] tabular-nums text-[#aeb4be]">{{ $row['judgeDate']?->format('y/m/d') ?? '' }}</span>
                                <span
                                    class="inline-flex items-center whitespace-nowrap rounded-full px-3 py-1 text-[11.5px] font-semibold {{ $row['chip'] }}">{{ $row['judgment'] }}</span>
                            </span>
                        </div>

                        {{-- expandable detail --}}
                        @if ($row['isOpen'])
                            <div
                                class="grid grid-cols-1 gap-7 border-b border-[#f1f2f5] bg-[#fafbfc] px-[26px] pb-6 pt-5 md:grid-cols-2">
                                <div class="flex flex-col gap-[11px]">
                                    <div class="flex gap-2.5"><span
                                            class="w-[84px] flex-none text-[12px] font-semibold text-[#8b919c]">{{ __('Created') }}</span><span
                                            class="tabular-nums text-[13px] text-[#3d424b]">{{ $row['detail']['created']?->format('Y-m-d H:i') ?? '—' }}</span>
                                    </div>
                                    <div class="flex gap-2.5"><span
                                            class="w-[84px] flex-none text-[12px] font-semibold text-[#8b919c]">{{ __('Activity date') }}</span><span
                                            class="tabular-nums text-[13px] text-[#3d424b]">{{ $row['detail']['actDate']?->format('Y-m-d') ?? '—' }}</span>
                                    </div>
                                    <div class="flex gap-2.5"><span
                                            class="w-[84px] flex-none text-[12px] font-semibold text-[#8b919c]">{{ __('Address') }}</span><span
                                            class="text-[13px] leading-relaxed text-[#3d424b]"
                                            style="text-wrap:pretty;">{{ $row['detail']['address'] }}</span></div>
                                    <div class="flex gap-2.5"><span
                                            class="w-[84px] flex-none text-[12px] font-semibold text-[#8b919c]">{{ __('Contact information') }}</span><span
                                            class="tabular-nums text-[13px] text-[#3d424b]">{{ $row['detail']['contact'] }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-3.5">
                                    <div>
                                        <div class="mb-2 text-[11px] font-semibold tracking-wide text-[#8b919c]">
                                            {{ __('Files') }}</div>
                                        @forelse ($row['detail']['files'] as $file)
                                            <div
                                                class="mb-2 flex items-center justify-between gap-3 rounded-[10px] border border-[#e6e8ed] bg-white px-3 py-2.5">
                                                <span
                                                    class="flex min-w-0 items-center gap-2.5 text-[13px] font-medium text-[#3d424b]">
                                                    <x-heroicon-o-document
                                                        class="size-[15px] flex-none text-[#9aa0aa]" />
                                                    <span class="truncate">{{ $file['name'] }}</span>
                                                </span>
                                                <span class="flex flex-none gap-1.5">
                                                    <a href="{{ route('reports.file', ['submission' => $row['id'], 'index' => $file['index'], 'preview' => 1]) }}"
                                                        target="_blank" rel="noopener"
                                                        class="rounded-[7px] border border-[#e0e3e9] bg-white px-3 py-1.5 text-[12px] font-semibold text-[#3d424b] transition hover:bg-[#fafbfc]">{{ __('Preview') }}</a>
                                                    <a href="{{ route('reports.file', ['submission' => $row['id'], 'index' => $file['index']]) }}"
                                                        class="rounded-[7px] bg-primary px-3 py-1.5 text-[12px] font-semibold text-white transition hover:bg-primary-light">{{ __('Download') }}</a>
                                                </span>
                                            </div>
                                        @empty
                                            <div
                                                class="rounded-[10px] border border-dashed border-[#e6e8ed] px-3 py-2.5 text-[12.5px] text-[#aeb4be]">
                                                {{ __('No files') }}</div>
                                        @endforelse
                                    </div>
                                    <div>
                                        <div class="mb-2 text-[11px] font-semibold tracking-wide text-[#8b919c]">
                                            {{ __('Notes') }}</div>
                                        <div class="text-[13.5px] leading-relaxed text-[#3d424b]"
                                            style="text-wrap:pretty;">{{ $row['detail']['note'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="px-5 py-16 text-center text-sm font-medium text-[#aeb4be]">
                    {{ __('No reports for this project yet.') }}</div>
            @endif
        </div>

        {{-- scroll to top --}}
        <button type="button" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="fixed bottom-8 end-8 flex size-[46px] items-center justify-center rounded-full bg-primary text-white shadow-[0_8px_20px_rgba(20,23,28,0.25)] transition hover:bg-primary-light"
            aria-label="{{ __('Scroll to top') }}">
            <x-heroicon-o-chevron-up class="size-5" />
        </button>
    </div>
</div>
