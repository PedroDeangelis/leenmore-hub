{{-- Save / cancel buttons shown while a field is being edited inline. --}}
<div class="flex items-center gap-1.5">
    <button type="button" wire:click="save"
        class="inline-flex size-[30px] shrink-0 items-center justify-center rounded-lg bg-primary text-white transition hover:bg-primary-light"
        aria-label="{{ __('Save') }}">
        <x-heroicon-o-check class="size-4" />
    </button>
    <button type="button" wire:click="cancelEdit"
        class="inline-flex size-[30px] shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-400 transition hover:text-zinc-600"
        aria-label="{{ __('Cancel') }}">
        <x-heroicon-o-x-mark class="size-4" />
    </button>
</div>
