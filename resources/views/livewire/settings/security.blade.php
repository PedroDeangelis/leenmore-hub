<section class="w-full">
    @include('partials.settings-heading')

    <h1 class="sr-only">{{ __('Security settings') }}</h1>

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-ui.input wire:model="current_password" :label="__('Current password')" type="password" autocomplete="current-password"
                viewable />
            <x-ui.input wire:model="password" :label="__('New password')" type="password" autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable />

            <div class="flex items-center gap-4">
                <x-ui.button variant="primary" type="submit"
                    data-test="update-password-button">{{ __('Save') }}</x-ui.button>
            </div>
        </form>

        @if ($canManageTwoFactor)
            <section class="mt-12">
                <h2 class="text-base font-semibold text-zinc-900">{{ __('Two-factor authentication') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Manage your two-factor authentication settings') }}</p>

                <div class="mx-auto mt-4 flex w-full flex-col space-y-6 text-sm" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <p class="text-sm text-zinc-600">
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </p>

                            <div class="flex justify-start">
                                <x-ui.button variant="danger" wire:click="disable">
                                    {{ __('Disable 2FA') }}
                                </x-ui.button>
                            </div>

                            <livewire:settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="space-y-4">
                            <p class="text-sm text-zinc-500">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </p>

                            <x-ui.button variant="primary" wire:click="enable">
                                {{ __('Enable 2FA') }}
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($canManageTwoFactor)
            <x-ui.modal model="showModal" max-width="max-w-md">
                <div class="space-y-6">
                    <div class="space-y-2 text-center">
                        <h3 class="text-lg font-semibold text-zinc-900">{{ $this->modalConfig['title'] }}</h3>
                        <p class="text-sm text-zinc-500">{{ $this->modalConfig['description'] }}</p>
                    </div>

                    @if ($showVerificationStep)
                        <div class="space-y-6">
                            <div class="flex flex-col items-center justify-center space-y-3" x-data
                                x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                                <input type="text" name="code" wire:model="code" inputmode="numeric"
                                    autocomplete="one-time-code" maxlength="6"
                                    aria-label="{{ __('Authentication code') }}" class="form-input form-input-otp" />

                                @error('code')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center space-x-3">
                                <x-ui.button variant="outline" class="flex-1" wire:click="resetVerification">
                                    {{ __('Back') }}
                                </x-ui.button>

                                <x-ui.button variant="primary" class="flex-1" wire:click="confirmTwoFactor"
                                    x-bind:disabled="$wire.code.length < 6">
                                    {{ __('Confirm') }}
                                </x-ui.button>
                            </div>
                        </div>
                    @else
                        @error('setupData')
                            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ $message }}</div>
                        @enderror

                        <div class="flex justify-center">
                            <div class="relative aspect-square w-64 overflow-hidden rounded-lg border border-zinc-200">
                            @empty($qrCodeSvg)
                                <div class="absolute inset-0 flex animate-pulse items-center justify-center bg-white">
                                    <div
                                        class="size-6 animate-spin rounded-full border-2 border-zinc-300 border-t-primary">
                                    </div>
                                </div>
                            @else
                                <div class="flex h-full items-center justify-center p-4">
                                    <div class="rounded bg-white p-3">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>
                            @endempty
                        </div>
                    </div>

                    <div>
                        <x-ui.button :disabled="$errors->has('setupData')" variant="primary" class="w-full"
                            wire:click="showVerificationIfNecessary">
                            {{ $this->modalConfig['buttonText'] }}
                        </x-ui.button>
                    </div>

                    <div class="space-y-4">
                        <div class="relative flex w-full items-center justify-center">
                            <div class="absolute inset-0 top-1/2 h-px w-full bg-zinc-200"></div>
                            <span class="relative bg-white px-2 text-sm text-zinc-600">
                                {{ __('or, enter the code manually') }}
                            </span>
                        </div>

                        <div class="flex items-center space-x-2" x-data="{
                            copied: false,
                            async copy() {
                                try {
                                    await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 1500);
                                } catch (e) {
                                    console.warn('Could not copy to clipboard');
                                }
                            }
                        }">
                            <div class="flex w-full items-stretch rounded-xl border border-zinc-200">
                            @empty($manualSetupKey)
                                <div class="flex w-full items-center justify-center bg-zinc-100 p-3">
                                    <div
                                        class="size-4 animate-spin rounded-full border-2 border-zinc-300 border-t-primary">
                                    </div>
                                </div>
                            @else
                                <input type="text" readonly value="{{ $manualSetupKey }}"
                                    class="w-full bg-transparent p-3 font-mono text-sm text-zinc-900 outline-none" />

                                <button type="button" x-on:click="copy()"
                                    class="cursor-pointer border-s border-zinc-200 px-3 text-sm text-zinc-600 transition-colors hover:text-zinc-900">
                                    <span x-show="!copied">{{ __('Copy') }}</span>
                                    <span x-show="copied" x-cloak class="text-green-600">{{ __('Copied') }}</span>
                                </button>
                            @endempty
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-ui.modal>
@endif
</x-settings.layout>
</section>
