<div class="max-w-2xl">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('receipts.index') }}" wire:navigate class="text-zinc-400 transition hover:text-zinc-600"
            aria-label="{{ __('Back') }}">
            <x-heroicon-o-arrow-left class="size-5" />
        </a>
        <h1 class="text-xl font-semibold text-zinc-900">{{ __('Edit receipt') }}</h1>
    </div>

    <x-ui.card class="p-6">
        <form wire:submit="update" class="space-y-4">
            <x-ui.input type="date" wire:model="date" :label="__('Please enter the actual receipt date.')" />

            <x-ui.select wire:model="receipt_category_id" :label="__('Usage category').' *'">
                <option value="">{{ __('Please select') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input wire:model="vendor" type="text" :label="__('Vendor').' *'" />

            <x-ui.number-input wire:model="amount" :label="__('Amount').' *'" />

            <x-ui.textarea wire:model="notes" rows="4" :label="__('Notes')" />

            {{-- Attachment --}}
            <div class="space-y-1">
                <label class="form-label">{{ __('Attachment') }}</label>

                @if ($receipt->attachment)
                    <a href="{{ route('receipts.file', ['receipt' => $receipt, 'preview' => 1]) }}" target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                        <x-heroicon-o-paper-clip class="size-4" />{{ __('View current attachment') }}
                    </a>
                @endif

                <label
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 bg-white px-4 py-4 text-[13.5px] font-semibold text-zinc-600 transition hover:border-primary hover:text-primary">
                    <x-heroicon-o-arrow-up-tray class="size-5 text-zinc-400" />
                    <span wire:loading.remove wire:target="attachment">
                        @if ($attachment)
                            {{ $attachment->getClientOriginalName() }}
                        @else
                            {{ __('Upload attachments (photos, audio, etc.)') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="attachment">{{ __('Uploading…') }}</span>
                    <input type="file" wire:model="attachment" class="hidden" accept="image/*,audio/*,application/pdf" />
                </label>
                <p class="text-xs text-zinc-500">{{ __('Leave empty to keep the current attachment.') }}</p>
                @error('attachment')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <x-ui.button variant="primary" type="submit">
                    <x-heroicon-o-check class="size-4" />
                    {{ __('Save') }}
                </x-ui.button>
                <x-ui.button variant="ghost" :href="route('receipts.index')" wire:navigate>
                    {{ __('Cancel') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
