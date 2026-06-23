<div>


    <div class="flex justify-between items-start mb-10">
        <div class="">
            <h1 class="text-xl font-semibold text-zinc-900">{{ $project->title }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('All shareholders with reports submitted for this project') }}
            </p>
        </div>

        <a href="{{ route('activity.index') }}" wire:navigate
            class="inline-flex items-center gap-1.5 text-[13.5px] font-bold text-primary transition hover:text-primary-light">
            <x-heroicon-o-arrow-left class="size-[15px]" />{{ __('Back') }}
        </a>
    </div>


    <x-ui.card class="p-4 max-w-300 mx-auto mb-6 flex flex-wrap gap-3">
        <div class="relative min-w-[220px] flex-1">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                aria-label="{{ __('Search shareholders') }}"
                class="w-full rounded-[10px] border border-[#e6e8ed] bg-[#fafbfc] py-3 pe-11 ps-4 text-sm text-[#3d424b] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" />
            <x-heroicon-o-magnifying-glass
                class="pointer-events-none absolute end-4 top-1/2 size-[17px] -translate-y-1/2 text-[#aeb4be]" />
        </div>
        <div class="relative w-[200px]">
            <select wire:model.live="reports" aria-label="{{ __('Filter by reports') }}"
                class="w-full appearance-none rounded-[10px] border border-[#e6e8ed] bg-white py-3 pe-9 ps-4 text-[13px] font-semibold text-[#3d424b] outline-none focus:border-primary">
                <option value="">{{ __('All shareholders') }}</option>
                <option value="has">{{ __('Has reports') }}</option>
                <option value="none">{{ __('No reports') }}</option>
            </select>
            <x-heroicon-o-chevron-down
                class="pointer-events-none absolute end-3 top-1/2 size-3.5 -translate-y-1/2 text-[#9aa0aa]" />
        </div>
    </x-ui.card>

    <div class="">


        {{-- Roster --}}
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.heading>{{ __('Shareholder') }}</x-ui.table.heading>
                <x-ui.table.heading align="end">{{ __('Shares') }}</x-ui.table.heading>
                <x-ui.table.heading align="end">{{ __('Total shares') }}</x-ui.table.heading>
                <x-ui.table.heading>{{ __('Judgment') }}</x-ui.table.heading>
                <x-ui.table.heading align="end">{{ __('Reports') }}</x-ui.table.heading>
                <x-ui.table.heading align="end">{{ __('Action') }}</x-ui.table.heading>
            </x-slot:head>

            @forelse ($rows as $row)
                <tr wire:key="roster-{{ $row->id }}" class="hover:bg-zinc-50">
                    <x-ui.table.cell>
                        <div class="flex flex-wrap items-baseline gap-1.5">
                            <a href="{{ route('activity.report', [$project, $row]) }}" wire:navigate
                                class="font-medium text-zinc-800 hover:text-primary hover:underline">{{ $row->shareholder?->name ?? '—' }}</a>
                            <span
                                class="text-xs tabular-nums text-zinc-500">{{ $row->shareholder?->date_of_birth_code ?: $row->shareholder?->registration }}</span>
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell align="end" class="tabular-nums text-zinc-600">{{ $row->shares !== null ? number_format($row->shares) : '—' }}</x-ui.table.cell>
                    <x-ui.table.cell align="end" class="tabular-nums text-zinc-600">{{ $row->shares_total !== null ? number_format($row->shares_total) : '—' }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        @if ($row->result)
                            <x-result.chip :color="$row->result->color" :label="$row->result->name" />
                        @else
                            <span class="text-zinc-400">{{ __('Not yet entered') }}</span>
                        @endif
                    </x-ui.table.cell>
                    <x-ui.table.cell align="end" class="tabular-nums {{ $row->submissions_count > 0 ? 'font-medium text-zinc-700' : 'text-zinc-400' }}">{{ number_format($row->submissions_count) }}</x-ui.table.cell>
                    <x-ui.table.cell align="end">
                        <a href="{{ route('activity.report', [$project, $row]) }}" wire:navigate
                            class="text-primary hover:underline">{{ __('View') }}</a>
                    </x-ui.table.cell>
                </tr>
            @empty
                <x-ui.table.empty :cols="6">{{ __('No shareholders found.') }}</x-ui.table.empty>
            @endforelse
        </x-ui.table>

        @if ($rows->hasPages())
            <div class="mt-6">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
