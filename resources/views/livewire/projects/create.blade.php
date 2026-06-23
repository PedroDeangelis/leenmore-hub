<section class="w-full space-y-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Add project') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Create a new campaign.') }}</p>
        </div>
        <a href="{{ route('projects.index') }}" wire:navigate
            class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-700">
            <x-heroicon-o-chevron-left class="size-4" />
            {{ __('Back to projects') }}
        </a>
    </div>

    <div class="max-w-2xl">
        <x-ui.card class="p-6">
            <x-ui.card-heading icon="heroicon-o-megaphone" :heading="__('Project details')"
                :subheading="__('Workers see published projects and the message below.')" />

            <form wire:submit="save" class="space-y-6">
                <x-ui.input wire:model="title" :label="__('Title')" type="text" required autocomplete="off" />

                <div class="grid grid-cols-2 gap-6">
                    <x-ui.select wire:model="status" :label="__('Status')">
                        @foreach (\App\Enums\ProjectStatus::assignable() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <x-ui.input wire:model="start_date" :label="__('Start date')" type="date" />
                    <x-ui.input wire:model="end_date" :label="__('End date')" type="date" />
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <x-ui.number-input wire:model="shares_issued" :label="__('Shares issued')" />
                    <x-ui.number-input wire:model="shares_target" :label="__('Shares target')" />
                </div>

                <x-ui.textarea wire:model="message" :label="__('Message to workers')"
                    :placeholder="__('Optional. Shown to workers on this project.')" />

                <div class="flex items-center gap-3">
                    <x-ui.button variant="primary" type="submit">
                        <x-heroicon-o-check class="size-4" />
                        {{ __('Create project') }}
                    </x-ui.button>
                    <x-ui.button :href="route('projects.index')" variant="ghost" wire:navigate>
                        {{ __('Cancel') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</section>
