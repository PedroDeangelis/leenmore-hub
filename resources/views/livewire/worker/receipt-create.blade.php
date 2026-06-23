<div>
    {{-- Header --}}
    <header class="sticky top-0 z-20 bg-primary px-5 pb-5 pt-6 text-white">
        <div class="max-w-md mx-auto">
            <h1 class="text-[22px] font-bold tracking-tight">{{ __('Receipt submit') }}</h1>
        </div>
    </header>

    <div class="py-5 max-w-md mx-auto ">
        {{-- Admin-editable announcement banner --}}
        @if (filled($announcement))
            <div
                class="mb-5 flex items-start justify-center gap-2 text-center text-[13px] font-bold leading-relaxed text-primary">
                <x-heroicon-s-megaphone class="mt-0.5 size-5 shrink-0" />
                <p>{{ $announcement }}</p>
            </div>
        @endif

        <form wire:submit="save" class="space-y-3">
            {{-- Date: a prompt layer sits on top; clicking it reveals the date input below. --}}
            <div x-data="{ revealed: false }">
                {{-- Prompt layer (shown first) --}}
                <div x-show="!revealed"
                    x-on:click="revealed = true; $nextTick(() => $refs.dateInput.focus())"
                    class="form-input cursor-pointer text-zinc-500">
                    {{ __('Please enter the actual receipt date.') }}
                </div>

                {{-- Date input (revealed on click) --}}
                <div x-show="revealed" x-cloak class="space-y-1">
                    <label class="form-label">{{ __('Please enter the actual receipt date.') }}</label>
                    <input x-ref="dateInput" wire:model="date" type="date" class="form-input" />
                </div>

                @error('date')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Usage category (admin-editable list) --}}
            <x-ui.select wire:model="receipt_category_id" :label="__('Usage category').' *'">
                <option value="">{{ __('Please select') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-ui.select>

            {{-- Vendor --}}
            <x-ui.input wire:model="vendor" type="text" :label="__('Vendor').' *'" placeholder="{{ __('Vendor') }}" />

            {{-- Amount (KRW, thousands separators) --}}
            <x-ui.number-input wire:model="amount" :label="__('Amount').' *'" placeholder="{{ __('Amount') }}" />

            {{-- Notes --}}
            <x-ui.textarea wire:model="notes" rows="4" :label="__('Notes')" placeholder="{{ __('Notes') }}" />

            {{-- Attachment: a single receipt (photo / audio / PDF) --}}
            <div class="space-y-1">
                <label class="form-label">{{ __('Attachment') }}</label>
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
                    <input type="file" wire:model="attachment" class="hidden"
                        accept="image/*,audio/*,application/pdf" />
                </label>
                <p class="text-xs text-center mt-2 text-zinc-500">{{ __('You can attach only one receipt.') }}</p>
                @error('attachment')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <p class="mt-5 text-center text-sm font-bold text-primary">{{ __('Please fill in all fields') }}</p>

            <x-ui.button variant="primary" type="submit" class="w-full">
                {{ __('Submit') }}
            </x-ui.button>
        </form>
    </div>
</div>
