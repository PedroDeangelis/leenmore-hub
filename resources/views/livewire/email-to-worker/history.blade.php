<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Sent emails') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Delivery status of every worker email') }}</p>
        </div>
        <x-ui.button variant="outline" :href="route('email-to-worker.index')" wire:navigate>
            {{ __('Go Back') }}
        </x-ui.button>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <x-ui.card class="p-4">
            <p class="text-xs font-medium text-zinc-500">{{ __('Total') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ number_format($this->stats['total']) }}</p>
        </x-ui.card>
        @foreach (\App\Enums\WorkerEmailRecipientStatus::cases() as $status)
            <x-ui.card class="p-4" wire:key="stat-{{ $status->value }}">
                <p class="text-xs font-medium text-zinc-500">{{ $status->label() }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">
                    {{ number_format($this->stats[$status->value]) }}
                </p>
            </x-ui.card>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.select :label="__('Status')" wire:model.live="status">
            <option value="">{{ __('All') }}</option>
            @foreach (\App\Enums\WorkerEmailRecipientStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select :label="__('Project')" wire:model.live="projectId">
            <option value="">{{ __('All') }}</option>
            @foreach ($this->projectOptions as $projectOption)
                <option value="{{ $projectOption->id }}">{{ $projectOption->title }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input :label="__('Search')" wire:model.live.debounce.300ms="search" type="search"
            :placeholder="__('Search by worker, email or subject')" />
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.table.heading>{{ __('Worker') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Project') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Subject') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Status') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Attempts') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Sent at') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Action') }}</x-ui.table.heading>
        </x-slot:head>

        @forelse ($recipients as $recipient)
            <tr wire:key="recipient-{{ $recipient->id }}" class="hover:bg-zinc-50">
                <x-ui.table.cell>
                    <span class="block font-medium text-zinc-800">{{ $recipient->worker?->name ?? '—' }}</span>
                    <span class="block text-xs text-zinc-400">{{ $recipient->email }}</span>
                </x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">
                    {{ $recipient->workerEmail?->project?->title ?? '—' }}
                </x-ui.table.cell>
                <x-ui.table.cell class="max-w-60">
                    <span class="block truncate text-zinc-600" title="{{ $recipient->workerEmail?->subject }}">
                        {{ $recipient->workerEmail?->subject }}
                    </span>
                    @if ($recipient->error_message)
                        <span class="block truncate text-xs text-red-500" title="{{ $recipient->error_message }}">
                            {{ $recipient->error_message }}
                        </span>
                    @endif
                </x-ui.table.cell>
                <x-ui.table.cell>
                    <x-ui.badge :variant="$recipient->status->badgeVariant()" dot>
                        {{ $recipient->status->label() }}
                    </x-ui.badge>
                </x-ui.table.cell>
                <x-ui.table.cell class="tabular-nums text-zinc-600">{{ $recipient->attempts }}</x-ui.table.cell>
                <x-ui.table.cell class="tabular-nums text-zinc-600">
                    {{ $recipient->sent_at?->format('Y-m-d H:i') ?? '—' }}
                </x-ui.table.cell>
                <x-ui.table.cell>
                    @if ($recipient->status !== \App\Enums\WorkerEmailRecipientStatus::Sent)
                        <button type="button" wire:click="retry({{ $recipient->id }})"
                            wire:loading.attr="disabled" class="cursor-pointer text-primary hover:underline">
                            {{ $recipient->status === \App\Enums\WorkerEmailRecipientStatus::Failed ? __('Retry now') : __('Send now') }}
                        </button>
                    @else
                        —
                    @endif
                </x-ui.table.cell>
            </tr>
        @empty
            <x-ui.table.empty :cols="7">{{ __('No emails sent yet.') }}</x-ui.table.empty>
        @endforelse
    </x-ui.table>

    @if ($recipients->hasPages())
        <div>{{ $recipients->links() }}</div>
    @endif
</section>
