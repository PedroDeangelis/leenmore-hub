<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Activity data') }}: {{ $this->project->title }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Per-worker contact and proxy statistics') }}</p>
        </div>
        <x-ui.button variant="outline" :href="route('activity-data.index')" wire:navigate>
            {{ __('Go Back') }}
        </x-ui.button>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
        {{-- Day filter --}}
        <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
            <span class="form-label">{{ __('Activity date') }}</span>
            <button type="button" x-on:click="open = !open"
                class="form-select mt-1 flex w-full items-center justify-between text-start">
                <span>
                    @if ($selectedDays === [])
                        {{ __('Show all') }}
                    @else
                        {{ implode(', ', collect($selectedDays)->sort()->all()) }}
                    @endif
                </span>
            </button>

            <div x-show="open" x-cloak
                class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-[#e8eaef] bg-white p-2 shadow-lg">
                <button type="button" wire:click="showAllDays"
                    class="block w-full cursor-pointer rounded px-2 py-1.5 text-start text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                    {{ __('Show all') }}
                </button>
                @foreach (array_reverse($this->data->allDays()) as $day)
                    <label wire:key="day-{{ $day }}"
                        class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50">
                        <input type="checkbox" class="form-checkbox" @checked(in_array($day, $selectedDays, true))
                            wire:click="toggleDay('{{ $day }}')" />
                        {{ $day }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Days-of-activity count + download --}}
        <x-ui.card class="flex items-center justify-between gap-4 p-4">
            <div>
                <p class="text-xs font-medium text-zinc-500">{{ __('Days of activity') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ __(':count days', ['count' => count($this->data->allDays())]) }}</p>
            </div>
            @unless ($this->data->isEmpty())
                <x-ui.button :href="route('activity-data.export', [$this->project, 'days' => $selectedDays])">
                    <x-heroicon-o-arrow-down-tray class="size-4" />
                    {{ __('Download activity data') }}
                </x-ui.button>
            @endunless
        </x-ui.card>
    </div>

    @if ($this->data->isEmpty())
        <div class="rounded-xl border border-[#e8eaef] bg-white px-6 py-16 text-center text-sm font-medium text-[#aeb4be]">
            {{ __('No activity reports yet.') }}
        </div>
    @else
        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-4">
            <div class="xl:col-span-3">
                <x-ui.table>
                    <x-slot:head>
                        @foreach ([
                            'name' => __('Activist'),
                            'totalReports' => __('Contact count'),
                            'sharesContacted' => __('Contacted shares total'),
                            'proxyShareholders' => __('Proxy shareholders'),
                            'proxyShares' => __('Proxy shares total'),
                        ] as $column => $label)
                            <x-ui.table.heading wire:key="head-{{ $column }}">
                                <button type="button" wire:click="sort('{{ $column }}')"
                                    class="inline-flex cursor-pointer items-center gap-1 hover:text-zinc-900">
                                    {{ $label }}
                                    @if ($sortBy === $column)
                                        @if ($sortDirection === 'asc')
                                            <x-heroicon-m-chevron-up class="size-3.5 text-red-500" />
                                        @else
                                            <x-heroicon-m-chevron-down class="size-3.5 text-red-500" />
                                        @endif
                                    @endif
                                </button>
                            </x-ui.table.heading>
                        @endforeach
                    </x-slot:head>

                    @foreach ($this->rows as $row)
                        <tr wire:key="worker-{{ $row['key'] }}" class="odd:bg-[#fbfbfb] hover:bg-zinc-50">
                            <x-ui.table.cell class="font-medium text-zinc-800">{{ $row['name'] }}</x-ui.table.cell>
                            <x-ui.table.cell class="tabular-nums text-zinc-600">{{ number_format($row['totalReports']) }}</x-ui.table.cell>
                            <x-ui.table.cell class="tabular-nums text-zinc-600">{{ number_format($row['sharesContacted']) }}</x-ui.table.cell>
                            <x-ui.table.cell class="bg-amber-100 tabular-nums text-zinc-800">{{ number_format($row['proxyShareholders']) }}</x-ui.table.cell>
                            <x-ui.table.cell class="bg-emerald-100 tabular-nums text-zinc-800">{{ number_format($row['proxyShares']) }}</x-ui.table.cell>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>

            <div class="flex flex-col gap-3">
                @php $summary = $this->data->summary(); @endphp
                <x-ui.card class="p-4">
                    <p class="text-xs font-medium text-zinc-500">{{ __('Total workers') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ number_format($summary['workers']) }}</p>
                </x-ui.card>
                <x-ui.card class="p-4">
                    <p class="text-xs font-medium text-zinc-500">{{ __('Proxy shareholders (total)') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ number_format($summary['proxyShareholders']) }}</p>
                </x-ui.card>
                <x-ui.card class="p-4">
                    <p class="text-xs font-medium text-zinc-500">{{ __('Proxy shares total (all workers)') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ number_format($summary['proxyShares']) }}</p>
                </x-ui.card>
            </div>
        </div>
    @endif
</section>
