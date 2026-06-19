<section class="w-full">
    @include('partials.settings-heading')

    <h1 class="sr-only">{{ __('Profile settings') }}</h1>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and language')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <x-ui.input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <x-ui.input :value="$email" :label="__('Email')" type="email" disabled readonly class="cursor-not-allowed bg-zinc-50 text-zinc-500" />
                <p class="mt-2 text-xs text-zinc-500">{{ __('Your email address cannot be changed. Contact an administrator if it needs updating.') }}</p>

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <p class="mt-4 text-sm text-zinc-600">
                            {{ __('Your email address is unverified.') }}

                            <button type="button" class="cursor-pointer text-sm text-primary underline" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>
                    </div>
                @endif
            </div>

            <x-ui.select wire:model="locale" :label="__('Language')">
                <option value="ko">{{ __('Korean') }}</option>
                <option value="en">{{ __('English') }}</option>
            </x-ui.select>

            <div class="flex items-center gap-4">
                <x-ui.button variant="primary" type="submit">{{ __('Save') }}</x-ui.button>
            </div>
        </form>
    </x-settings.layout>
</section>
