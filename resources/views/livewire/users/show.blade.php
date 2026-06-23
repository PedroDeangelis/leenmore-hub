<section class="w-full space-y-6">
    <div class="flex justify-between items-end">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Profile') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Manage this user\'s profile, password and account status.') }}
            </p>
        </div>
        <div>
            <a href="{{ route('users.index') }}" wire:navigate
                class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-700">
                <x-heroicon-o-chevron-left class="size-4" />
                {{ __('Back to users') }}
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-10 items-start">
        <div>

            {{-- Profile details --}}
            <x-ui.card class="p-6">
                <x-ui.card-heading icon="heroicon-o-user">
                    <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-700">
                        {{ $name }}
                        @if ($user->deactivated_at)
                            <x-ui.badge variant="danger" dot>{{ __('Inactive') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="success" dot>{{ __('Active') }}</x-ui.badge>
                        @endif
                    </h2>
                    <p class="text-sm text-zinc-500">
                        {{ __('Manage this :name\'s profile information.', ['name' => $name]) }}</p>
                </x-ui.card-heading>


                <form wire:submit="updateProfile" class="space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <x-ui.input wire:model="name" :label="__('Name')" type="text" required autocomplete="off" />

                        <div>
                            <x-ui.select wire:model="role" :label="__('Role')" :disabled="$this->isSelf">
                                @foreach (\App\Enums\UserRole::cases() as $role)
                                    <option value="{{ $role->value }}">{{ __(ucfirst($role->value)) }}</option>
                                @endforeach
                            </x-ui.select>
                            @if ($this->isSelf)
                                <p class="mt-2 text-xs text-zinc-500">{{ __('You cannot change your own role.') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <x-ui.input :value="$email" :label="__('Email (login)')" type="email" disabled readonly
                                class="cursor-not-allowed bg-zinc-50 text-zinc-500" />
                            <p class="mt-2 text-xs text-zinc-500">{{ __('The login email cannot be changed.') }}</p>
                        </div>

                        <div>
                            <x-ui.input wire:model="email_receiver" :label="__('Email recipient')" type="email" :placeholder="$email"
                                autocomplete="off" />
                            <p class="mt-2 text-xs text-zinc-500">
                                {{ __('Optional. Notifications are sent here instead of the login email.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <x-ui.phone-input wire:model="phone" :label="__('Phone number')" autocomplete="off" />

                        <x-ui.select wire:model="locale" :label="__('Language')">
                            <option value="ko">{{ __('Korean') }}</option>
                            <option value="en">{{ __('English') }}</option>
                        </x-ui.select>
                    </div>


                    <div class="">
                        <x-ui.button variant="primary" type="submit">
                            <x-heroicon-o-check class="size-4" />
                            {{ __('Save') }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>

        {{-- Change password --}}
        <div>
            <x-ui.card class="p-6 mb-7">
                <x-ui.card-heading icon="heroicon-o-lock-closed" :heading="__('Change password')" :subheading="__('Set a new password for this user.')" />

                <form wire:submit="updatePassword" class="space-y-6">
                    <x-ui.input wire:model="password" :label="__('New password')" type="password" autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        viewable />

                    <div class="flex items-center gap-4">
                        <x-ui.button variant="primary" type="submit">
                            <x-heroicon-o-check class="size-4" />
                            {{ __('Update password') }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Activation --}}
            @unless ($this->isSelf)
                <x-ui.card class="p-6">
                    <x-ui.card-heading icon="heroicon-o-exclamation-circle" :heading="$user->deactivated_at ? __('Reactivate account') : __('Deactivate account')" :subheading="$user->deactivated_at
                        ? __('Restore this user\'s access to the application.')
                        : __('Prevent this user from signing in. You can reactivate them at any time.')" />

                    <div class="">
                        @if ($user->deactivated_at)
                            <x-ui.button variant="primary" wire:click="toggleActivation"
                                wire:confirm="{{ __('Reactivate this user?') }}">
                                {{ __('Reactivate user') }}
                            </x-ui.button>
                        @else
                            <x-ui.button variant="danger" wire:click="toggleActivation"
                                wire:confirm="{{ __('Deactivate this user?') }}">
                                {{ __('Deactivate user') }}
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endunless
        </div>
    </div>
</section>
