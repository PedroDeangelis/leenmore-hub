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

    <div class="overflow-x-auto rounded-xl border border-zinc-200">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50">
                <tr class="text-start text-xs font-medium uppercase tracking-wide text-zinc-500">
                    <th class="px-4 py-3 text-start font-medium">{{ __('Title') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Dates') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Shares (issued / target)') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white">
                @forelse ($projects as $project)
                    <tr wire:key="project-{{ $project->id }}" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
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
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge :variant="$project->status->badgeVariant()" dot>{{ $project->status->label() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-600">
                            {{ $project->start_date?->format('d M Y') ?? '—' }}
                            <span class="text-zinc-400">→</span>
                            {{ $project->end_date?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-end tabular-nums text-zinc-600">
                            {{ $project->shares_issued !== null ? number_format($project->shares_issued) : '—' }}
                            <span class="text-zinc-400">/</span>
                            {{ $project->shares_target !== null ? number_format($project->shares_target) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-end">
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
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-zinc-500">{{ __('No projects found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($projects->hasPages())
        <div>
            {{ $projects->links() }}
        </div>
    @endif
</section>
