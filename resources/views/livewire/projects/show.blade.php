@use('App\Enums\ProjectStatus')
@use('App\Enums\ResultColor')

@php
    $thClass = 'px-3.5 py-2.5 text-[11px] font-semibold tracking-wide text-zinc-500 border-b border-zinc-200 bg-[#fafbfc]';
@endphp

<div class="-m-6 min-h-screen bg-zinc-100 p-4 text-[13px] text-zinc-700 sm:p-6"
    style="font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',system-ui,sans-serif;">
    {{-- Pretendard gives proper Korean glyphs; falls back to system Korean fonts. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />

    <div class="flex flex-col gap-4">

        {{-- Header --}}
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                @if ($editing === 'title')
                    <input type="text" wire:model="title" wire:keydown.enter="save" wire:keydown.escape="cancelEdit"
                        x-data x-init="$nextTick(() => $el.focus())"
                        class="w-[340px] max-w-full rounded-lg border border-zinc-300 px-3 py-1.5 text-[20px] font-bold text-zinc-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    <x-project.edit-actions />
                    @error('title')
                        <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                    @enderror
                @else
                    <h1 class="text-[22px] font-bold tracking-tight text-zinc-900">{{ $project->title }}</h1>
                    @can('manage-projects')
                        <x-ui.icon-button wire:click="edit('title')" icon="heroicon-o-pencil" aria-label="{{ __('Edit') }}" />
                    @endcan
                @endif
                <x-ui.badge :variant="$project->status->badgeVariant()" dot>{{ $project->status->label() }}</x-ui.badge>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-zinc-700 transition hover:bg-zinc-50">{{ __('Project resources') }}</button>
                <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-zinc-700 transition hover:bg-zinc-50">{{ __('Activity report') }}</button>
                @can('manage-projects')
                    @if ($project->status === ProjectStatus::Draft)
                        <button type="button" wire:click="publish"
                            wire:confirm="{{ __('Publish this project? Assigned workers will be able to see it.') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-[13px] font-semibold text-white transition hover:bg-primary-light">
                            <x-heroicon-o-rocket-launch class="size-3.5" />{{ __('Publish project') }}
                        </button>
                    @elseif ($project->status === ProjectStatus::Publish)
                        <button type="button" wire:click="revertToDraft"
                            wire:confirm="{{ __('Revert this project to draft? Workers will no longer see it.') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-zinc-700 transition hover:bg-zinc-50">
                            <x-heroicon-o-arrow-uturn-left class="size-3.5" />{{ __('Revert to draft') }}
                        </button>
                    @endif
                    <button type="button" wire:click="archive" wire:confirm="{{ __('Archive this project?') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-[13px] font-semibold text-white transition hover:bg-primary-light">
                        <x-heroicon-o-archive-box class="size-3.5" />{{ __('Archive project') }}
                    </button>
                    <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this project? This cannot be undone.') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-red-600 transition hover:bg-red-50">
                        <x-heroicon-o-trash class="size-3.5" />{{ __('Delete project') }}
                    </button>
                @endcan
            </div>
        </header>

        {{-- Message + ESIGNON --}}
        <div class="flex flex-wrap items-stretch gap-3.5">
            <div
                class="flex min-w-[440px] flex-1 items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-white px-4 py-4">
                <div class="flex flex-1 items-center gap-2">
                    @if ($editing === 'message')
                        <input type="text" wire:model="message" placeholder="{{ __('Enter a message...') }}"
                            wire:keydown.enter="save" wire:keydown.escape="cancelEdit" x-data
                            x-init="$nextTick(() => $el.focus())"
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm text-zinc-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                        <x-project.edit-actions />
                    @else
                        <span class="text-sm {{ $project->message ? 'text-zinc-700' : 'text-zinc-400' }}">{{ $project->message ?: __('No message') }}</span>
                        @can('manage-projects')
                            <button type="button" wire:click="edit('message')" class="text-zinc-300 hover:text-zinc-500"
                                aria-label="{{ __('Edit') }}">
                                <x-heroicon-o-pencil class="size-3.5" />
                            </button>
                        @endcan
                    @endif
                </div>
                @if ($countdown)
                    @php($meetingDay = $countdown->meetingDate->format('m/d'))
                    <div class="flex items-center gap-1.5 whitespace-nowrap text-sm">
                        @if ($countdown->isUpcoming())
                            <span class="font-semibold text-zinc-700">{{ __('Until the meeting (:date)', ['date' => $meetingDay]) }}</span>
                            <span class="text-[15px] font-bold text-primary tabular-nums">{{ __(':count days', ['count' => $countdown->daysLeft]) }}</span>
                            <span class="font-semibold text-zinc-700">{{ __('remaining') }}</span>
                        @elseif ($countdown->isToday())
                            <span class="inline-flex items-center gap-1.5 font-bold text-primary">
                                <x-heroicon-m-bell-alert class="size-4" />
                                {{ __('The meeting is today (:date)', ['date' => $meetingDay]) }}
                            </span>
                        @else
                            <span class="font-medium text-zinc-400">{{ __('The meeting (:date) has passed', ['date' => $meetingDay]) }}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div
                class="flex w-[300px] flex-none items-center justify-between rounded-xl border border-zinc-200 bg-white px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="mb-1 text-[11px] font-semibold tracking-wide text-zinc-500">ESIGNON ID</div>
                    @if ($editing === 'esignon')
                        <input type="text" wire:model="link_manage_id" wire:keydown.enter="save"
                            wire:keydown.escape="cancelEdit" x-data x-init="$nextTick(() => $el.focus())"
                            class="w-32 rounded-lg border border-zinc-300 px-2.5 py-1 text-base font-bold tabular-nums text-zinc-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    @else
                        <div class="text-xl font-bold tabular-nums text-zinc-900">{{ $project->link_manage_id ?? '—' }}</div>
                    @endif
                </div>
                @can('manage-projects')
                    @if ($editing === 'esignon')
                        <x-project.edit-actions />
                    @else
                        <x-ui.icon-button wire:click="edit('esignon')" icon="heroicon-o-pencil" aria-label="{{ __('Edit') }}" />
                    @endif
                @endcan
            </div>
        </div>

        {{-- Dates + downloads --}}
        <div class="flex flex-wrap items-stretch gap-3.5">
            <div
                class="flex min-w-[400px] flex-[1.5] flex-wrap items-center gap-x-8 gap-y-3 rounded-xl border border-zinc-200 bg-white px-5 py-3.5">
                @if ($editing === 'dates')
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold tracking-wide text-zinc-500">{{ __('Start date') }}</label>
                        <input type="date" wire:model="start_date"
                            class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm tabular-nums text-zinc-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold tracking-wide text-zinc-500">{{ __('End date') }}</label>
                        <input type="date" wire:model="end_date"
                            class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm tabular-nums text-zinc-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        @error('end_date')
                            <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                        @enderror
                        <x-project.edit-actions />
                    </div>
                @else
                    <div>
                        <div class="mb-1 text-[11px] font-semibold tracking-wide text-zinc-500">{{ __('Start date') }}</div>
                        <div class="text-base font-bold tabular-nums text-zinc-900">{{ $project->start_date?->format('Y/m/d') ?? '—' }}</div>
                    </div>
                    <div class="w-px self-stretch bg-zinc-100"></div>
                    <div>
                        <div class="mb-1 text-[11px] font-semibold tracking-wide text-zinc-500">{{ __('End date') }}</div>
                        <div class="text-base font-bold tabular-nums text-zinc-900">{{ $project->end_date?->format('Y/m/d') ?? '—' }}</div>
                    </div>
                    @can('manage-projects')
                        <button type="button" wire:click="edit('dates')"
                            class="ml-auto inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary hover:underline">
                            <x-heroicon-o-pencil class="size-3.5" />{{ __('Edit details') }}
                        </button>
                    @endcan
                @endif
            </div>
            <button type="button"
                class="flex min-w-[160px] flex-1 items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-[18px] text-[13.5px] font-semibold text-zinc-700 transition hover:bg-zinc-50">
                <x-heroicon-o-arrow-down-tray class="size-4 text-zinc-400" />{{ __('Download attachments') }}
            </button>
            <button type="button"
                class="flex min-w-[160px] flex-1 items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-[18px] text-[13.5px] font-semibold text-zinc-700 transition hover:bg-zinc-50">
                <x-heroicon-o-arrow-down-tray class="size-4 text-zinc-400" />{{ __('Download activity log (Excel)') }}
            </button>
            <button type="button"
                class="flex min-w-[150px] flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-3 py-[18px] text-[13.5px] font-semibold text-white transition hover:bg-primary-light">{{ __('Live status') }}</button>
        </div>

        {{-- Judgment results + legend --}}
        <div class="flex flex-wrap items-start gap-3.5">

            {{-- 판단 결과 현황 (share sums per current 판단, grouped into colour bands) --}}
            <x-ui.card class="min-w-[580px] flex-[1.75]">
                <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="text-sm font-bold text-zinc-900">{{ __('Judgment results') }}</span>
                        <x-ui.icon-button icon="heroicon-o-arrow-down-tray" size="size-7" aria-label="{{ __('Download') }}" />
                        @can('manage-projects')
                            <x-ui.icon-button wire:click="edit('shares')" icon="heroicon-o-pencil" size="size-7"
                                aria-label="{{ __('Edit') }}" />
                        @endcan
                    </div>
                </div>
                <div class="flex items-end justify-between gap-4 border-b border-zinc-100 bg-[#fafbfc] px-5 py-4">
                    @if ($editing === 'shares')
                        <div class="flex flex-1 flex-wrap items-end gap-4">
                            <div>
                                <label class="mb-1 block text-[11.5px] font-semibold tracking-wide text-zinc-500">{{ __('Total issued shares') }}</label>
                                <div class="w-44">
                                    <x-ui.number-input wire:model="shares_issued" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11.5px] font-semibold tracking-wide text-zinc-500">{{ __('Target shares') }}</label>
                                <div class="w-44">
                                    <x-ui.number-input wire:model="shares_target" />
                                </div>
                            </div>
                            <div class="ml-auto pb-1">
                                <x-project.edit-actions />
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="mb-1 text-[11.5px] font-semibold tracking-wide text-zinc-500">{{ __('Total issued shares') }}</div>
                            <div class="text-[22px] font-bold tabular-nums text-zinc-900">{{ $project->shares_issued !== null ? number_format($project->shares_issued) : '—' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="mb-1 text-[11.5px] font-semibold tracking-wide text-zinc-500">{{ __('Target shares') }}</div>
                            <div class="text-[22px] font-bold tabular-nums text-primary">{{ $project->shares_target !== null ? number_format($project->shares_target) : '—' }}</div>
                        </div>
                    @endif
                </div>
                @if ($tally['hasData'])
                    <table class="w-full text-[13px]">
                        <colgroup>
                            <col class="w-[34%]"><col class="w-[15%]"><col class="w-[23%]"><col class="w-[17%]"><col class="w-[11%]">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-left {{ $thClass }}">{{ __('Judgment') }}</th>
                                <th class="text-right {{ $thClass }}">{{ __('Shares') }}</th>
                                <th class="text-right {{ $thClass }}">{{ __('Progress by result (%)') }}</th>
                                <th class="text-right {{ $thClass }}">{{ __('Group total (shares)') }}</th>
                                <th class="text-right {{ $thClass }}">{{ __('Group total (%)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tally['groups'] as $gi => $group)
                                @foreach ($group['rows'] as $i => $row)
                                    {{-- A 2px white rule separates each colour group (its first row). --}}
                                    @php($sep = $gi > 0 && $i === 0 ? 'border-t-2 border-t-white' : '')
                                    <tr class="{{ $row['color']->bandClasses() }}">
                                        <td class="border-b border-b-black/5 border-l-[3px] {{ $sep }} px-3.5 py-2.5 font-semibold text-zinc-700 {{ $row['color']->borderClasses() }}">{{ $row['name'] }}</td>
                                        <td class="border-b border-b-black/5 {{ $sep }} px-3.5 py-2.5 text-right tabular-nums text-zinc-700">{{ number_format($row['shares']) }}</td>
                                        <td class="border-b border-b-black/5 {{ $sep }} px-3.5 py-2">
                                            <div class="flex flex-col items-end gap-1.5">
                                                <span class="text-right font-semibold tabular-nums {{ $row['color']->accentText() }}">{{ $row['percent'] !== null ? number_format($row['percent'], 2) . '%' : '—' }}</span>
                                                @if ($row['percent'] !== null)
                                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-black/[0.08]">
                                                        <div class="h-full rounded-full {{ $row['color']->barClasses() }}" style="width:{{ max(min($row['percent'], 100), 2) }}%"></div>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        @if ($i === 0)
                                            <td rowspan="{{ count($group['rows']) }}" class="{{ $sep }} px-3.5 py-2.5 text-right align-middle font-bold tabular-nums {{ $group['color']->totalClasses() }}">{{ number_format($group['totalShares']) }}</td>
                                            <td rowspan="{{ count($group['rows']) }}" class="{{ $sep }} px-3.5 py-2.5 text-right align-middle font-bold tabular-nums {{ $group['color']->totalClasses() }}">{{ $group['totalPercent'] !== null ? number_format($group['totalPercent'], 2) . '%' : '—' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <x-heroicon-o-chart-bar class="size-7 text-zinc-300" />
                        <p class="text-sm font-medium text-zinc-500">{{ __('No judgment results yet') }}</p>
                        <p class="text-xs text-zinc-400">{{ __('Judgment breakdowns will appear here once worker submissions are tallied.') }}</p>
                    </div>
                @endif
            </x-ui.card>

            {{-- 판단 (the project's result definitions) --}}
            <x-ui.card class="w-[430px] flex-none">
                <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3">
                    <span class="text-sm font-bold text-zinc-900">{{ __('Judgment') }}</span>
                    @can('manage-projects')
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="manageResults"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50">
                                <x-heroicon-o-adjustments-horizontal class="size-3.5" />{{ __('Manage results') }}
                            </button>
                            <button type="button" wire:click="toggleSort" @class([
                                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                'border-primary bg-primary text-white' => $sorting,
                                'border-zinc-200 bg-white text-primary hover:bg-zinc-50' => ! $sorting,
                            ])>
                                <x-heroicon-o-bars-arrow-down class="size-3.5" />{{ $sorting ? __('Done') : __('Sort results') }}
                            </button>
                        </div>
                    @endcan
                </div>
                @if ($sorting)
                    <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-700">
                        {{ __('Drag rows to reorder.') }}
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <colgroup>
                            <col class="w-[38px]"><col class="w-[38px]"><col><col class="w-[160px]">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center {{ $thClass }}">C</th>
                                <th class="text-center {{ $thClass }}">A</th>
                                <th class="text-left {{ $thClass }}">{{ __('Result name') }}</th>
                                <th class="text-left {{ $thClass }}">{{ __('Icon colour') }}</th>
                            </tr>
                        </thead>
                        <tbody @if ($sorting) x-sort="$wire.reorder($item, $position)" @endif>
                            @forelse ($project->results as $result)
                                <tr wire:key="result-{{ $result->id }}"
                                    @if ($sorting) x-sort:item="{{ $result->id }}" @endif
                                    class="border-b border-zinc-100 {{ $sorting ? 'cursor-move hover:bg-zinc-50' : '' }}">
                                    <td class="px-1.5 py-2 text-center">
                                        @if ($result->contact_required)
                                            <span class="inline-block size-2.5 rounded-full bg-[#34a36a]"></span>
                                        @endif
                                    </td>
                                    <td class="px-1.5 py-2 text-center">
                                        @if ($result->attachment_required)
                                            <span class="inline-block size-2.5 rounded-full bg-[#34a36a]"></span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-zinc-700">{{ $result->name }}</td>
                                    <td class="px-3 py-2">
                                        <x-result.chip :color="$result->color" :label="$result->name" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-400">{{ __('No results defined.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>

        {{-- 주주 (shareholders roster) --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-100 px-4 py-3.5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-[15px] font-bold text-zinc-900">{{ __('Shareholders') }}</span>
                        @if ($shareholderCount > 0)
                            <span
                                class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-zinc-600">{{ number_format($shareholderCount) }}</span>
                        @endif
                    </div>
                    @if ($shareholderCount > 0)
                        <div class="relative">
                            <x-heroicon-o-magnifying-glass
                                class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input wire:model.live.debounce.300ms="shareholderSearch" type="search"
                                placeholder="{{ __('Search by name, registration, DOB, or worker') }}"
                                aria-label="{{ __('Search shareholders') }}"
                                class="w-72 max-w-full rounded-lg border border-zinc-300 py-1.5 pl-8 pr-3 text-[13px] text-zinc-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                        </div>
                    @endif
                </div>
                @can('manage-shareholders')
                    <div class="flex items-center gap-2">
                        @if ($shareholderCount > 0)
                            <a href="{{ route('projects.shareholders.export', $project) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-4 py-2 text-[13px] font-semibold text-zinc-700 transition hover:bg-zinc-50">
                                <x-heroicon-o-arrow-down-tray class="size-4 text-zinc-400" />{{ __('Download CSV') }}
                            </a>
                        @endif
                        <livewire:projects.shareholder-import :project="$project" :key="'sh-import-'.$project->id" />
                    </div>
                @endcan
            </div>

            @if ($shareholderCount > 0)
                @if ($shareholders->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-1 px-6 py-12 text-center">
                        <x-heroicon-o-magnifying-glass class="size-6 text-zinc-300" />
                        <p class="text-sm font-medium text-zinc-500">{{ __('No shareholders match your search.') }}</p>
                    </div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-100 text-[13px]">
                        <thead class="bg-[#fafbfc]">
                            <tr class="text-left text-[11px] font-semibold tracking-wide text-zinc-500">
                                <th class="px-4 py-2.5">{{ __('No.') }}</th>
                                <th class="px-4 py-2.5">{{ __('Name') }}</th>
                                <th class="px-4 py-2.5">{{ __('Registration') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Shares') }}</th>
                                <th class="px-4 py-2.5">{{ __('Contact') }}</th>
                                <th class="px-4 py-2.5">{{ __('Workers') }}</th>
                                <th class="px-4 py-2.5">{{ __('Judgment') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-50">
                            @foreach ($shareholders as $row)
                                <tr wire:key="sh-{{ $row->id }}">
                                    <td class="px-4 py-2.5 tabular-nums text-zinc-500">{{ $row->no ?? '—' }}</td>
                                    <td class="px-4 py-2.5 font-medium text-zinc-800">{{ $row->shareholder->name }}</td>
                                    <td class="px-4 py-2.5 tabular-nums text-zinc-500">{{ $row->shareholder->registration ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-zinc-700">{{ $row->shares !== null ? number_format($row->shares) : '—' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600">{{ $row->effective_contact ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600">
                                        <div class="flex items-center gap-1.5">
                                            <span>{{ $row->workers->isNotEmpty() ? $row->workers->pluck('name')->join(' / ') : '—' }}</span>
                                            @can('manage-shareholders')
                                                <x-ui.icon-button wire:click="manageWorkers({{ $row->id }})"
                                                    icon="heroicon-o-user-plus" size="size-7"
                                                    aria-label="{{ __('Manage workers') }}" />
                                            @endcan
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($row->result)
                                            <x-result.chip :color="$row->result->color" :label="$row->result->name" />
                                        @else
                                            <span class="text-zinc-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($matchCount > $shareholders->count())
                    <div class="border-t border-zinc-100 px-4 py-2.5 text-center text-xs text-zinc-400">
                        @if ($shareholderSearching)
                            {{ __('Showing :shown of :total matches', ['shown' => $shareholders->count(), 'total' => number_format($matchCount)]) }}
                        @else
                            {{ __('Showing :shown of :total', ['shown' => $shareholders->count(), 'total' => number_format($matchCount)]) }}
                        @endif
                    </div>
                @endif
                @endif
            @else
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                    <x-heroicon-o-users class="size-7 text-zinc-300" />
                    <p class="text-sm font-medium text-zinc-500">{{ __('No shareholders yet') }}</p>
                    <p class="text-xs text-zinc-400">{{ __('Shareholders will appear here once a list is added.') }}</p>
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- Manage results modal --}}
    @can('manage-projects')
        <x-ui.modal model="managingResults" max-width="max-w-3xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-zinc-800">{{ __('Manage results') }}</h2>
                <button type="button" wire:click="$set('managingResults', false)"
                    class="text-zinc-400 hover:text-zinc-600" aria-label="{{ __('Close') }}">
                    <x-heroicon-o-x-mark class="size-5" />
                </button>
            </div>

            <div class="-mx-1 max-h-[60vh] space-y-2 overflow-y-auto px-1">
                @forelse ($rows as $i => $row)
                    <div class="flex items-start gap-2" wire:key="row-{{ $row['_uid'] }}">
                        <div class="min-w-0 flex-1">
                            <input type="text" wire:model="rows.{{ $i }}.name" placeholder="{{ __('Result name') }}"
                                class="w-full rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm text-zinc-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                            @error('rows.' . $i . '.name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <select wire:model="rows.{{ $i }}.color"
                            class="rounded-lg border border-zinc-300 px-2 py-1.5 text-sm text-zinc-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                            @foreach (ResultColor::cases() as $case)
                                <option value="{{ $case->value }}">{{ __($case->label()) }}</option>
                            @endforeach
                        </select>
                        <x-result.chip :color="ResultColor::tryFrom($row['color']) ?? ResultColor::Gray"
                            :label="filled($row['name']) ? $row['name'] : '—'" class="mt-1.5 shrink-0" />
                        <label class="mt-1.5 flex items-center gap-1 text-xs font-medium text-zinc-600"
                            title="{{ __('Contact required') }}">
                            <input type="checkbox" wire:model="rows.{{ $i }}.contact_required" class="form-checkbox size-3.5" />C
                        </label>
                        <label class="mt-1.5 flex items-center gap-1 text-xs font-medium text-zinc-600"
                            title="{{ __('Attachment required') }}">
                            <input type="checkbox" wire:model="rows.{{ $i }}.attachment_required" class="form-checkbox size-3.5" />A
                        </label>
                        <button type="button" wire:click="removeResultRow({{ $i }})"
                            class="mt-1 text-zinc-400 hover:text-red-600" aria-label="{{ __('Delete') }}">
                            <x-heroicon-o-trash class="size-4" />
                        </button>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-zinc-400">{{ __('No results defined.') }}</p>
                @endforelse
            </div>

            <button type="button" wire:click="addResultRow"
                class="mt-3 inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary hover:underline">
                <x-heroicon-o-plus class="size-4" />{{ __('Add result') }}
            </button>

            <div class="mt-5 flex items-center justify-end gap-2 border-t border-zinc-100 pt-4">
                <x-ui.button variant="outline" wire:click="$set('managingResults', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="primary" wire:click="saveResults">{{ __('Save') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endcan

    {{-- Manage workers modal --}}
    @can('manage-shareholders')
        <x-ui.modal model="managingWorkers" max-width="max-w-md">
            @if ($managingAssignment)
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-zinc-900">{{ __('Manage workers') }}</h2>
                        <p class="mt-1 text-[13px] text-zinc-500">{{ $managingAssignment->shareholder->name }}</p>
                    </div>
                    <button type="button" wire:click="$set('managingWorkers', false)"
                        class="text-zinc-400 hover:text-zinc-600" aria-label="{{ __('Close') }}">
                        <x-heroicon-o-x-mark class="size-5" />
                    </button>
                </div>

                {{-- Current workers --}}
                <div class="mt-4">
                    <div class="mb-1.5 text-xs font-semibold text-zinc-500">{{ __('Assigned workers') }}</div>
                    @if ($managingAssignment->workers->isEmpty())
                        <p class="text-[13px] text-zinc-400">{{ __('No workers assigned yet.') }}</p>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($managingAssignment->workers as $worker)
                                <span wire:key="assigned-{{ $worker->id }}"
                                    class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-[13px] font-medium text-zinc-700">
                                    {{ $worker->name }}
                                    <button type="button" wire:click="removeWorker({{ $worker->id }})"
                                        class="text-zinc-400 hover:text-red-600" aria-label="{{ __('Remove') }}">
                                        <x-heroicon-m-x-mark class="size-3.5" />
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Add a worker --}}
                <div class="mt-5">
                    <div class="mb-1.5 text-xs font-semibold text-zinc-500">{{ __('Add a worker') }}</div>
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass
                            class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input wire:model.live.debounce.250ms="workerSearch" type="search"
                            placeholder="{{ __('Search workers by name') }}"
                            class="w-full rounded-lg border border-zinc-300 py-1.5 pl-8 pr-3 text-[13px] text-zinc-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <div class="mt-2 max-h-48 divide-y divide-zinc-50 overflow-y-auto rounded-lg border border-zinc-100">
                        @forelse ($workerOptions as $option)
                            <button type="button" wire:key="wopt-{{ $option->id }}" wire:click="addWorker({{ $option->id }})"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-zinc-50">
                                <span class="text-[13px] font-medium text-zinc-700">{{ $option->name }}</span>
                                <span class="truncate text-xs text-zinc-400">{{ $option->email }}</span>
                            </button>
                        @empty
                            <p class="px-3 py-2 text-[13px] text-zinc-400">{{ __('No workers found.') }}</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <x-ui.button wire:click="$set('managingWorkers', false)">{{ __('Done') }}</x-ui.button>
                </div>
            @endif
        </x-ui.modal>
    @endcan

    {{-- Back to top --}}
    <a href="#"
        class="fixed bottom-6 right-6 flex size-11 items-center justify-center rounded-full bg-primary text-white shadow-lg transition hover:bg-primary-light">
        <x-heroicon-o-chevron-up class="size-5" />
    </a>
</div>
