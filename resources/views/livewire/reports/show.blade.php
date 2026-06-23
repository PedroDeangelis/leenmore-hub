<div class="" x-data>


    <div class="mb-6">
        <h1 class="text-xl font-semibold text-zinc-900">{{ $project->title }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('All reports submitted for this project') }}</p>
    </div>

    {{-- Filter / sort panel --}}
    <x-ui.card class="p-4 space-y-2 max-w-300 mx-auto mb-10">

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
    </x-ui.card>

    <div class="">



        {{-- Table --}}
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.heading>{{ __('Shareholder') }}</x-ui.table.heading>
                <x-ui.table.heading>{{ __('Shares') }}</x-ui.table.heading>
                <x-ui.table.heading>{{ __('Total shares') }}</x-ui.table.heading>
                <x-ui.table.heading>{{ __('Activist') }}</x-ui.table.heading>
                <x-ui.table.heading>{{ __('Submitted') }}</x-ui.table.heading>
                <x-ui.table.heading>{{ __('Project') }}</x-ui.table.heading>
                <x-ui.table.heading align="end">{{ __('Result') }}</x-ui.table.heading>
            </x-slot:head>

            @if (count($rows) > 0)
                {{-- summary --}}
                <tr class="bg-zinc-50 font-semibold text-zinc-800">
                    <x-ui.table.cell
                        class="font-bold tabular-nums">{{ number_format($summary['count']) }}</x-ui.table.cell>
                    <x-ui.table.cell
                        class="font-bold tabular-nums">{{ number_format($summary['shares']) }}</x-ui.table.cell>
                    <x-ui.table.cell
                        class="font-bold tabular-nums">{{ number_format($summary['total']) }}</x-ui.table.cell>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                @foreach ($rows as $row)
                    <tr wire:key="report-row-{{ $row['id'] }}" wire:click="toggle({{ $row['id'] }})"
                        class="cursor-pointer hover:bg-zinc-50">
                        <x-ui.table.cell>
                            <div class="flex flex-wrap items-baseline gap-1.5">
                                <span class="font-medium text-zinc-800">{{ $row['name'] }}</span>
                                @if ($row['idNum'])
                                    <span class="text-xs tabular-nums text-zinc-500">{{ $row['idNum'] }}</span>
                                @endif
                                @if ($row['gender'])
                                    <span class="text-xs font-semibold text-primary">({{ $row['gender'] }})</span>
                                @endif
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell
                            class="tabular-nums text-zinc-600">{{ $row['shares'] !== null ? number_format($row['shares']) : '—' }}</x-ui.table.cell>
                        <x-ui.table.cell
                            class="tabular-nums text-zinc-600">{{ $row['total'] !== null ? number_format($row['total']) : '—' }}</x-ui.table.cell>
                        <x-ui.table.cell class="text-zinc-600">{{ $row['worker'] }}</x-ui.table.cell>
                        <x-ui.table.cell
                            class="tabular-nums text-zinc-500">{{ $row['submitDate']?->format('y/m/d H:i') ?? '—' }}</x-ui.table.cell>
                        <x-ui.table.cell class="text-zinc-600"><span
                                class="block truncate">{{ $project->title }}</span></x-ui.table.cell>
                        <x-ui.table.cell align="end">
                            <span class="flex items-center justify-end gap-2.5">
                                <span
                                    class="text-xs tabular-nums text-zinc-400">{{ $row['judgeDate']?->format('y/m/d') ?? '' }}</span>
                                <span
                                    class="inline-flex items-center whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $row['chip'] }}">{{ $row['judgment'] }}</span>
                            </span>
                        </x-ui.table.cell>
                    </tr>

                    {{-- expandable detail --}}
                    @if ($row['isOpen'])
                        <tr wire:key="report-detail-{{ $row['id'] }}">
                            <td colspan="7" class="p-0">
                                <div class="grid grid-cols-1 gap-7 bg-zinc-50 px-6 pb-6 pt-5 md:grid-cols-2">
                                    <div class="flex flex-col gap-3 text-sm">
                                        <div class="flex gap-2.5"><span
                                                class="w-21 flex-none text-xs font-semibold text-zinc-500">{{ __('Created') }}</span><span
                                                class="tabular-nums text-zinc-700">{{ $row['detail']['created']?->format('Y-m-d H:i') ?? '—' }}</span>
                                        </div>
                                        <div class="flex gap-2.5"><span
                                                class="w-21 flex-none text-xs font-semibold text-zinc-500">{{ __('Activity date') }}</span><span
                                                class="tabular-nums text-zinc-700">{{ $row['detail']['actDate']?->format('Y-m-d') ?? '—' }}</span>
                                        </div>
                                        <div class="flex gap-2.5"><span
                                                class="w-21 flex-none text-xs font-semibold text-zinc-500">{{ __('Address') }}</span><span
                                                class="leading-relaxed text-zinc-700"
                                                style="text-wrap:pretty;">{{ $row['detail']['address'] }}</span></div>
                                        <div class="flex gap-2.5"><span
                                                class="w-21 flex-none text-xs font-semibold text-zinc-500">{{ __('Contact information') }}</span><span
                                                class="tabular-nums text-zinc-700">{{ $row['detail']['contact'] }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-3.5">
                                        <div>
                                            <div class="mb-2 text-xs font-semibold tracking-wide text-zinc-500">
                                                {{ __('Files') }}</div>
                                            @forelse ($row['detail']['files'] as $file)
                                                <div
                                                    class="mb-2 flex items-center justify-between gap-3 rounded-[10px] border border-zinc-200 bg-white px-3 py-2.5">
                                                    <span
                                                        class="flex min-w-0 items-center gap-2.5 text-sm font-medium text-zinc-700">
                                                        <x-heroicon-o-document class="size-4 flex-none text-zinc-400" />
                                                        <span class="truncate">{{ $file['name'] }}</span>
                                                    </span>
                                                    <span class="flex flex-none gap-1.5">
                                                        <a href="{{ route('reports.file', ['submission' => $row['id'], 'index' => $file['index'], 'preview' => 1]) }}"
                                                            target="_blank" rel="noopener"
                                                            class="rounded-[7px] border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50">{{ __('Preview') }}</a>
                                                        <a href="{{ route('reports.file', ['submission' => $row['id'], 'index' => $file['index']]) }}"
                                                            class="rounded-[7px] bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-light">{{ __('Download') }}</a>
                                                    </span>
                                                </div>
                                            @empty
                                                <div
                                                    class="rounded-[10px] border border-dashed border-zinc-200 px-3 py-2.5 text-xs text-zinc-400">
                                                    {{ __('No files') }}</div>
                                            @endforelse
                                        </div>
                                        <div>
                                            <div class="mb-2 text-xs font-semibold tracking-wide text-zinc-500">
                                                {{ __('Notes') }}</div>
                                            <div class="text-sm leading-relaxed text-zinc-700"
                                                style="text-wrap:pretty;">{{ $row['detail']['note'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @else
                <x-ui.table.empty :cols="7">{{ __('No reports for this project yet.') }}</x-ui.table.empty>
            @endif
        </x-ui.table>

        {{-- scroll to top --}}
        <button type="button" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="fixed bottom-8 inset-e-8 flex size-11.5 items-center justify-center rounded-full bg-primary text-white shadow-[0_8px_20px_rgba(20,23,28,0.25)] transition hover:bg-primary-light"
            aria-label="{{ __('Scroll to top') }}">
            <x-heroicon-o-chevron-up class="size-5" />
        </button>
    </div>
</div>
