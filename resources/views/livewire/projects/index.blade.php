@use('App\Enums\ProjectStatus')

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Projects') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('All campaigns') }}</p>
        </div>

        @can('manage-projects')
            <x-ui.button :href="route('projects.create')" variant="primary" wire:navigate>
                <x-heroicon-o-plus class="size-4" />
                {{ __('Add project') }}
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" :placeholder="__('Search by title')"
                aria-label="{{ __('Search projects') }}" />
        </div>

        <div class="w-full sm:w-48">
            <x-ui.select wire:model.live="status" aria-label="{{ __('Filter by status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (ProjectStatus::assignable() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
                <option value="archived">{{ ProjectStatus::Archived->label() }}</option>
            </x-ui.select>
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.table.heading>{{ __('Title') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Status') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Dates') }}</x-ui.table.heading>
            <x-ui.table.heading align="end">{{ __('Shares (issued / target)') }}</x-ui.table.heading>
            <x-ui.table.heading align="end">{{ __('Action') }}</x-ui.table.heading>
        </x-slot:head>

        @forelse ($projects as $project)
            <tr wire:key="project-{{ $project->id }}" class="hover:bg-zinc-50">
                <x-ui.table.cell>
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-primary">
                            <x-heroicon-o-megaphone class="size-4" />
                        </span>
                        @if ($project->status === ProjectStatus::Archived)
                            <span class="font-medium text-zinc-500">{{ $project->title }}</span>
                        @else
                            <a href="{{ route('projects.show', $project) }}" wire:navigate
                                class="font-medium text-zinc-800 hover:text-primary hover:underline">{{ $project->title }}</a>
                        @endif
                    </div>
                </x-ui.table.cell>
                <x-ui.table.cell>
                    <x-ui.badge :variant="$project->status->badgeVariant()" dot>{{ $project->status->label() }}</x-ui.badge>
                </x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">
                    {{ $project->start_date?->format('d M Y') ?? '—' }}
                    <span class="text-zinc-400">→</span>
                    {{ $project->end_date?->format('d M Y') ?? '—' }}
                </x-ui.table.cell>
                <x-ui.table.cell align="end" class="tabular-nums text-zinc-600">
                    {{ $project->shares_issued !== null ? number_format($project->shares_issued) : '—' }}
                    <span class="text-zinc-400">/</span>
                    {{ $project->shares_target !== null ? number_format($project->shares_target) : '—' }}
                </x-ui.table.cell>
                <x-ui.table.cell align="end">
                    @if ($project->status === ProjectStatus::Archived)
                        @can('manage-projects')
                            <button type="button" wire:click="restore({{ $project->id }})"
                                class="cursor-pointer text-primary hover:underline">{{ __('Restore') }}</button>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endcan
                    @else
                        <a href="{{ route('projects.show', $project) }}" wire:navigate
                            class="text-primary hover:underline">{{ __('View') }}</a>
                    @endif
                </x-ui.table.cell>
            </tr>
        @empty
            <x-ui.table.empty :cols="5">{{ __('No projects found.') }}</x-ui.table.empty>
        @endforelse
    </x-ui.table>

    @if ($projects->hasPages())
        <div>
            {{ $projects->links() }}
        </div>
    @endif
</section>
