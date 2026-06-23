<div class="max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-zinc-900">{{ __('Options') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('App-wide settings') }}</p>
    </div>

    <div class="space-y-6">
        {{-- Announcement banner --}}
        <x-ui.card class="p-6">
            <x-ui.card-heading icon="heroicon-o-megaphone" :heading="__('Announcement')"
                :subheading="__('Shown at the top of the worker receipt form.')" />

            <form wire:submit="saveAnnouncement" class="space-y-4">
                <x-ui.textarea wire:model="announcement" rows="3" placeholder="{{ __('Announcement') }}" />

                <x-ui.button variant="primary" type="submit">
                    <x-heroicon-o-check class="size-4" />
                    {{ __('Save') }}
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- Usage categories --}}
        <x-ui.card class="p-6">
            <x-ui.card-heading icon="heroicon-o-tag" :heading="__('Usage category')"
                :subheading="__('Options workers choose from on the receipt form.')" />

            <div class="space-y-2">
                @forelse ($categories as $category)
                    <div wire:key="cat-{{ $category->id }}"
                        class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2">
                        @if ($editingCategoryId === $category->id)
                            <input wire:key="cat-input-{{ $category->id }}" wire:model="editingCategoryName"
                                type="text" wire:keydown.enter="updateCategory" class="form-input flex-1" autofocus />
                            <x-ui.button wire:key="cat-save-{{ $category->id }}" variant="primary" type="button"
                                wire:click="updateCategory">
                                <x-heroicon-o-check class="size-4" />
                            </x-ui.button>
                            <x-ui.button wire:key="cat-cancel-{{ $category->id }}" variant="ghost" type="button"
                                wire:click="cancelEdit">
                                <x-heroicon-o-x-mark class="size-4" />
                            </x-ui.button>
                        @else
                            <span class="flex-1 text-sm font-medium text-zinc-700">{{ $category->name }}</span>
                            <button wire:key="cat-edit-{{ $category->id }}" type="button"
                                wire:click="editCategory({{ $category->id }})"
                                class="text-zinc-400 transition hover:text-primary" aria-label="{{ __('Edit') }}">
                                <x-heroicon-o-pencil-square class="size-5" />
                            </button>
                            <button wire:key="cat-delete-{{ $category->id }}" type="button"
                                wire:click="deleteCategory({{ $category->id }})"
                                wire:confirm="{{ __('Delete this category?') }}"
                                class="text-zinc-400 transition hover:text-red-600" aria-label="{{ __('Delete') }}">
                                <x-heroicon-o-trash class="size-5" />
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-zinc-400">{{ __('No categories yet.') }}</p>
                @endforelse
            </div>

            {{-- Add a category --}}
            <form wire:submit="addCategory" class="mt-4 flex items-start gap-2">
                <div class="flex-1">
                    <x-ui.input wire:model="newCategory" type="text" placeholder="{{ __('Add category') }}" />
                </div>
                <x-ui.button variant="outline" type="submit">
                    <x-heroicon-o-plus class="size-4" />
                    {{ __('Add') }}
                </x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
