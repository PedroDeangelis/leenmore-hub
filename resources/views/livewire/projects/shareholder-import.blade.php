<div>
    <button type="button" wire:click="$set('showModal', true)"
        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-[13px] font-semibold text-white transition hover:bg-primary-light">
        <x-heroicon-o-arrow-up-tray class="size-4" />{{ __('Add shareholder list') }}
    </button>

    <x-ui.modal model="showModal" max-width="max-w-lg">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-zinc-900">{{ __('Import shareholders') }}</h2>
                <p class="mt-1 text-[13px] text-zinc-500">
                    {{ __('Upload the shareholder list as a CSV or Excel (.xlsx) file.') }}
                </p>
            </div>
            <button type="button" wire:click="$set('showModal', false)"
                class="text-zinc-400 hover:text-zinc-600" aria-label="{{ __('Close') }}">
                <x-heroicon-o-x-mark class="size-5" />
            </button>
        </div>

        @if ($importing)
            {{-- Determinate progress, advanced one chunk per poll --}}
            <div class="mt-6" wire:poll.800ms="step">
                <div class="mb-1.5 flex items-center justify-between text-xs font-semibold text-zinc-600">
                    <span>{{ $status === 'processing' ? __('Importing…') : __('Reading the file…') }}</span>
                    <span class="tabular-nums">{{ $this->progress() }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full rounded-full bg-primary transition-all duration-300"
                        style="width: {{ max(2, $this->progress()) }}%"></div>
                </div>
                @if ($total > 0)
                    <p class="mt-2 text-xs tabular-nums text-zinc-400">
                        {{ number_format($processed) }} / {{ number_format($total) }}
                    </p>
                @else
                    <p class="mt-2 text-xs text-zinc-400">{{ __('Large files can take a moment.') }}</p>
                @endif
            </div>
        @else
            <div class="mt-4" wire:key="shareholder-file" x-data="{ uploading: false, progress: 0 }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-cancel="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress">
                <input type="file" wire:model="file" accept=".csv,.txt,.xlsx"
                    class="block w-full cursor-pointer text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-light" />

                {{-- Live upload progress --}}
                <div x-show="uploading" x-cloak class="mt-3">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                        <div class="h-full rounded-full bg-primary transition-all" :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="mt-1 text-xs tabular-nums text-zinc-400"><span x-text="progress"></span>% · {{ __('Uploading…') }}</p>
                </div>

                <div class="mt-2 flex items-center justify-between gap-3">
                    <p class="text-xs text-zinc-400">{{ __('Existing shareholders are matched by registration number and updated.') }}</p>
                    <a href="{{ route('shareholders.template') }}"
                        class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Download sample CSV') }}</a>
                </div>

                @error('file')
                    <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button variant="outline" wire:click="$set('showModal', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button wire:click="start" wire:loading.attr="disabled" wire:target="start,file">
                    <span wire:loading.remove wire:target="start">{{ __('Import') }}</span>
                    <span wire:loading wire:target="start">{{ __('Preparing…') }}</span>
                </x-ui.button>
            </div>
        @endif
    </x-ui.modal>
</div>
