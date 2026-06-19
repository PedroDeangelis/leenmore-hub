<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div
            class="relative h-auto w-full"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                init() {
                    if (! this.showRecoveryInput) {
                        this.$nextTick(() => this.$refs.code?.focus());
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.$refs.code?.focus();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="my-5 flex items-center justify-center">
                            <input
                                type="text"
                                name="code"
                                x-ref="code"
                                x-model="code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                aria-label="{{ __('Authentication code') }}"
                                class="form-input form-input-otp"
                            />
                        </div>

                        @error('code')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                                aria-label="{{ __('Recovery code') }}"
                                class="form-input"
                            />
                        </div>

                        @error('recovery_code')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-ui.button variant="primary" type="submit" class="w-full">
                        {{ __('Continue') }}
                    </x-ui.button>
                </div>

                <div class="mt-5 space-x-0.5 text-center text-sm leading-5">
                    <span class="opacity-50">{{ __('or you can') }}</span>
                    <div class="inline cursor-pointer font-medium underline opacity-80">
                        <span x-show="!showRecoveryInput" x-on:click="toggleInput()">{{ __('login using a recovery code') }}</span>
                        <span x-show="showRecoveryInput" x-on:click="toggleInput()">{{ __('login using an authentication code') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
