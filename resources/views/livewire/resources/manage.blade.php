<div>
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-zinc-900">
            {{ __('Project resources') }}: <span class="text-primary">{{ $project->title }}</span>
        </h1>
        <a href="{{ route('resources.index') }}" wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-500 transition hover:text-zinc-800">
            <x-heroicon-o-arrow-left class="size-4" />{{ __('Back') }}
        </a>
    </div>

    <div class="grid items-start gap-8 lg:grid-cols-2">
        {{-- Left: add a link + upload files --}}
        <div class="space-y-6">
            <x-ui.card class="p-6">
                <x-ui.card-heading icon="heroicon-o-link" :heading="__('Insert link button')" :divider="false" />

                <form wire:submit="addLink" class="mt-4 space-y-4">
                    <x-ui.input wire:model="linkUrl" type="url" :label="__('Site address').' *'"
                        placeholder="https://..." />
                    <x-ui.input wire:model="linkTitle" type="text" :label="__('Content').' *'" />
                    <div class="flex justify-end">
                        <x-ui.button variant="primary" type="submit">
                            <x-heroicon-o-plus class="size-4" />
                            {{ __('Insert link button') }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Drag-and-drop upload --}}
            <div x-data="{ dragging: false }" x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                :class="dragging ? 'border-primary bg-primary/5' : 'border-zinc-300 bg-white'"
                class="rounded-xl border-2 border-dashed px-4 py-12 text-center transition">
                <label class="flex cursor-pointer flex-col items-center gap-2">
                    <x-heroicon-o-cloud-arrow-up class="size-9 text-zinc-400" />
                    <span wire:loading.remove wire:target="files" class="text-sm font-semibold text-zinc-600">
                        <span class="text-primary">{{ __('Click to upload') }}</span> {{ __('or drag and drop') }}
                    </span>
                    <span wire:loading wire:target="files"
                        class="text-sm font-semibold text-primary">{{ __('Uploading…') }}</span>
                    <span class="text-xs text-zinc-400">{{ __('PNG, JPG, GIF, PDF, CSV up to 10MB') }}</span>
                    <input x-ref="fileInput" type="file" multiple wire:model="files" class="hidden"
                        accept=".png,.jpg,.jpeg,.gif,.pdf,.csv" />
                </label>
            </div>
            @error('files.*')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Right: resource list + manage --}}
        <div>
            <div class="mb-4 flex items-center gap-3">
                <x-ui.button variant="outline" type="button" wire:click="$toggle('organizing')">
                    <x-heroicon-o-bars-3 class="size-4" />
                    {{ __('Organize') }}
                </x-ui.button>
                <div class="relative flex-1">
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                        class="form-input pe-9" />
                    <x-heroicon-o-magnifying-glass
                        class="pointer-events-none absolute inset-e-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                </div>
            </div>

            <div class="space-y-2">
                @forelse ($resources as $resource)
                    <x-ui.card wire:key="res-{{ $resource->id }}" class="flex items-center gap-3 p-3.5">
                        @if ($organizing)
                            <div class="flex flex-col text-zinc-400">
                                <button type="button" wire:click="moveUp({{ $resource->id }})"
                                    class="transition hover:text-primary" aria-label="{{ __('Move up') }}">
                                    <x-heroicon-o-chevron-up class="size-4" />
                                </button>
                                <button type="button" wire:click="moveDown({{ $resource->id }})"
                                    class="transition hover:text-primary" aria-label="{{ __('Move down') }}">
                                    <x-heroicon-o-chevron-down class="size-4" />
                                </button>
                            </div>
                        @endif

                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            @if ($resource->isLink())
                                <x-heroicon-o-link class="size-4.5" />
                            @else
                                <x-heroicon-o-document class="size-4.5" />
                            @endif
                        </span>

                        @if ($editingId === $resource->id)
                            <div class="flex-1 space-y-2">
                                <input wire:key="edit-title-{{ $resource->id }}" wire:model="editTitle" type="text"
                                    class="form-input" placeholder="{{ __('Content') }}" />
                                @if ($resource->isLink())
                                    <input wire:key="edit-url-{{ $resource->id }}" wire:model="editUrl" type="url"
                                        class="form-input" placeholder="{{ __('Site address') }}" />
                                @endif
                            </div>
                            <x-ui.button wire:key="save-{{ $resource->id }}" variant="primary" type="button"
                                wire:click="updateResource">
                                <x-heroicon-o-check class="size-4" />
                            </x-ui.button>
                            <x-ui.button wire:key="cancel-{{ $resource->id }}" variant="ghost" type="button"
                                wire:click="cancelEdit">
                                <x-heroicon-o-x-mark class="size-4" />
                            </x-ui.button>
                        @else
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-800">{{ $resource->title }}</p>
                                @if ($resource->isLink())
                                    <p class="truncate text-xs text-zinc-400">{{ $resource->url }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                @if ($resource->isFile())
                                    <a wire:key="dl-{{ $resource->id }}" href="{{ route('resources.file', $resource) }}"
                                        class="text-zinc-400 transition hover:text-primary" aria-label="{{ __('Download') }}">
                                        <x-heroicon-o-arrow-down-tray class="size-5" />
                                    </a>
                                @endif

                                @if ($resource->isLink())
                                    <a wire:key="view-{{ $resource->id }}" href="{{ $resource->url }}" target="_blank"
                                        rel="noopener"
                                        class="rounded-md border border-zinc-200 px-2.5 py-1.5 text-xs font-semibold text-zinc-600 transition hover:border-primary hover:text-primary">
                                        {{ __('View link') }}
                                    </a>
                                @else
                                    <a wire:key="view-{{ $resource->id }}"
                                        href="{{ route('resources.file', ['resource' => $resource, 'preview' => 1]) }}"
                                        target="_blank" rel="noopener"
                                        class="rounded-md border border-zinc-200 px-2.5 py-1.5 text-xs font-semibold text-zinc-600 transition hover:border-primary hover:text-primary">
                                        {{ __('View resource') }}
                                    </a>
                                @endif

                                <button wire:key="editbtn-{{ $resource->id }}" type="button"
                                    wire:click="editResource({{ $resource->id }})"
                                    class="rounded-md border border-zinc-200 px-2.5 py-1.5 text-xs font-semibold text-zinc-600 transition hover:border-primary hover:text-primary">
                                    {{ __('Edit') }}
                                </button>
                                <button wire:key="delbtn-{{ $resource->id }}" type="button"
                                    wire:click="deleteResource({{ $resource->id }})"
                                    wire:confirm="{{ __('Delete this resource?') }}"
                                    class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        @endif
                    </x-ui.card>
                @empty
                    <x-ui.card class="px-6 py-16 text-center text-sm font-medium text-zinc-400">
                        {{ __('No resources yet.') }}
                    </x-ui.card>
                @endforelse
            </div>
        </div>
    </div>
</div>
