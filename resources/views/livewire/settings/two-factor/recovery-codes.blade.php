<div
    class="space-y-6 rounded-xl border border-zinc-200 py-6 shadow-sm"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="space-y-2 px-6">
        <h3 class="text-lg font-semibold text-zinc-900">{{ __('2FA recovery codes') }}</h3>
        <p class="text-sm text-zinc-500">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div class="px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-ui.button
                x-show="!showRecoveryCodes"
                variant="primary"
                x-on:click="showRecoveryCodes = true"
                aria-controls="recovery-codes-section"
            >
                {{ __('View recovery codes') }}
            </x-ui.button>

            <x-ui.button
                x-show="showRecoveryCodes"
                x-cloak
                variant="primary"
                x-on:click="showRecoveryCodes = false"
                aria-controls="recovery-codes-section"
            >
                {{ __('Hide recovery codes') }}
            </x-ui.button>

            @if (filled($recoveryCodes))
                <x-ui.button
                    x-show="showRecoveryCodes"
                    x-cloak
                    variant="outline"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerate codes') }}
                </x-ui.button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-cloak
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 rounded-lg bg-zinc-100 p-4 font-mono text-sm"
                        role="list"
                        aria-label="{{ __('Recovery codes') }}"
                    >
                        @foreach ($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-zinc-500">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
