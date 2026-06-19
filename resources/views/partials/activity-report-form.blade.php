{{--
    The activity-report submission form. Shared by the field-worker view and the
    admin Activity\Report page. Expects: $results (the project's 판단 list),
    $dateLocked (bool — worker pins the date to today; admin may edit it), and the
    component's form properties (date, resultId, privacyConsent, consentFiles,
    contacts, note, attachments). Submits via wire:submit="save".
--}}
<form wire:submit="save" class="space-y-5">
    @php($selectedResult = $results->firstWhere('id', $resultId))

    {{-- Activity date — locked to today for workers, editable for admins --}}
    <x-ui.input type="date" wire:model="date" :label="__('Activity date')" :disabled="$dateLocked"
        @class(['cursor-not-allowed bg-zinc-100 text-zinc-500' => $dateLocked]) />

    {{-- 판단 --}}
    <x-ui.select wire:model.live="resultId" :label="__('Judgment')" required>
        <option value="">{{ __('Please select') }}</option>
        @foreach ($results as $result)
            <option value="{{ $result->id }}">{{ $result->name }}</option>
        @endforeach
    </x-ui.select>

    {{-- Privacy consent — checking it reveals the consent-form uploader --}}
    <div class="space-y-2">
        <x-ui.checkbox wire:model.live="privacyConsent" :label="__('Privacy consent form attached')" />

        @if ($privacyConsent)
            <div class="space-y-1">
                <label
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 bg-white px-4 py-4 text-[13.5px] font-semibold text-zinc-600 transition hover:border-primary hover:text-primary">
                    <x-heroicon-o-arrow-up-tray class="size-5 text-zinc-400" />
                    <span wire:loading.remove wire:target="consentFiles">
                        @if (count($consentFiles))
                            {{ __(':count file(s) selected', ['count' => count($consentFiles)]) }}
                        @else
                            {{ __('Upload privacy consent form') }}
                        @endif
                    </span>
                    <span wire:loading wire:target="consentFiles">{{ __('Uploading…') }}</span>
                    <input type="file" multiple wire:model="consentFiles" class="hidden"
                        accept="image/*,application/pdf" />
                </label>
                @error('consentFiles.*')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                @error('consentFiles')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>

    {{-- Contact information (3 segments per number, repeatable) --}}
    <div class="space-y-1">
        <label class="form-label">{{ __('Contact information') }}@if ($selectedResult?->contact_required)
                <span class="text-primary"> *</span>
            @endif
        </label>
        <div class="space-y-2">
            @foreach ($contacts as $i => $parts)
                <div wire:key="contact-{{ $i }}" class="flex items-center gap-2">
                    <input type="text" inputmode="numeric" maxlength="3" x-mask="999"
                        wire:model="contacts.{{ $i }}.0" placeholder="000"
                        class="form-input min-w-0 flex-1 px-2 text-center tabular-nums" />
                    <span class="shrink-0 font-semibold text-zinc-300">–</span>
                    <input type="text" inputmode="numeric" maxlength="4" x-mask="9999"
                        wire:model="contacts.{{ $i }}.1" placeholder="0000"
                        class="form-input min-w-0 flex-1 px-2 text-center tabular-nums" />
                    <span class="shrink-0 font-semibold text-zinc-300">–</span>
                    <input type="text" inputmode="numeric" maxlength="4" x-mask="9999"
                        wire:model="contacts.{{ $i }}.2" placeholder="0000"
                        class="form-input min-w-0 flex-1 px-2 text-center tabular-nums" />
                    @if ($i === 0)
                        <button type="button" wire:click="addContact"
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary text-white transition hover:bg-primary-light"
                            aria-label="{{ __('Add') }}">
                            <x-heroicon-o-plus class="size-4" />
                        </button>
                    @else
                        <button type="button" wire:click="removeContact({{ $i }})"
                            class="flex size-11 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-zinc-400 transition hover:bg-zinc-50 hover:text-zinc-600"
                            aria-label="{{ __('Remove') }}">
                            <x-heroicon-o-minus class="size-4" />
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
        @error('contacts')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    {{-- Notes --}}
    <x-ui.textarea wire:model="note" :label="__('Notes')" :placeholder="__('Remarks')" rows="4" />

    {{-- Attachments --}}
    <div class="space-y-1">
        <label
            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 bg-white px-4 py-4 text-[13.5px] font-semibold text-zinc-600 transition hover:border-primary hover:text-primary">
            <x-heroicon-o-arrow-up-tray class="size-5 text-zinc-400" />
            <span wire:loading.remove wire:target="attachments">
                @if (count($attachments))
                    {{ __(':count file(s) selected', ['count' => count($attachments)]) }}
                @else
                    {{ __('Upload attachments (photos, audio, etc.)') }}
                @endif
                @if ($selectedResult?->attachment_required)
                    <span class="text-primary"> *</span>
                @endif
            </span>
            <span wire:loading wire:target="attachments">{{ __('Uploading…') }}</span>
            <input type="file" multiple wire:model="attachments" class="hidden"
                accept="image/*,audio/*,application/pdf" />
        </label>
        @error('attachments.*')
            <p class="form-error">{{ $message }}</p>
        @enderror
        @error('attachments')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    {{-- Submit --}}
    <x-ui.button variant="primary" type="submit" class="btn-tall w-full" wire:target="save"
        wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="save">{{ $submitLabel ?? __('Submit') }}</span>
        <span wire:loading wire:target="save">{{ __('Submitting…') }}</span>
    </x-ui.button>
</form>
