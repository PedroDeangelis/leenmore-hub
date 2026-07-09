<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('Email to worker') }}: {{ $this->project->title }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Compose an email to this project\'s workers') }}</p>
        </div>
        <x-ui.button variant="outline" :href="route('email-to-worker.index')" wire:navigate>
            {{ __('Go Back') }}
        </x-ui.button>
    </div>

    <div class="grid grid-cols-1 items-start gap-8 xl:grid-cols-2">
        {{-- Left column: workers + 명부양식 download controls --}}
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.select :label="__('Sort Column')" wire:model.live="sortColumn">
                    @foreach (\App\Enums\RosterSortColumn::cases() as $column)
                        <option value="{{ $column->value }}">{{ $column->label() }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select :label="__('Sort Order')" wire:model.live="sortOrder">
                    <option value="asc">{{ __('Ascending') }}</option>
                    <option value="desc">{{ __('Descending') }}</option>
                </x-ui.select>

                <x-ui.select :label="__('Download Type')" wire:model.live="downloadType">
                    <option value="xlsx">Excel</option>
                    <option value="pdf">PDF</option>
                </x-ui.select>
            </div>

            @php
                $rosterQuery = [
                    'sort' => $sortColumn,
                    'desc' => $sortOrder === 'desc' ? 1 : 0,
                ];
                $selectedCount = count(array_intersect($selectedWorkerIds, $this->workers->modelKeys()));
                $allSelected = $this->workers->isNotEmpty() && $selectedCount === $this->workers->count();
            @endphp

            <div class="flex items-center justify-between gap-4 rounded-xl border border-[#e8eaef] bg-white px-4 py-3">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-zinc-700">
                    <input type="checkbox" class="form-checkbox" wire:click="toggleSelectAll" @checked($allSelected)
                        wire:key="select-all-{{ $selectedCount }}"
                        x-data x-init="$el.indeterminate = @js($selectedCount > 0 && ! $allSelected)" />
                    {{ __('Select all') }}
                </label>

                <x-ui.button variant="outline" :href="route('email-to-worker.roster.all', [$this->project, ...$rosterQuery])">
                    <x-heroicon-o-arrow-down-tray class="size-4" />
                    {{ __('Download all roster forms') }}
                </x-ui.button>
            </div>

            @error('selectedWorkerIds')
                <p class="form-error">{{ $message }}</p>
            @enderror

            <div class="flex flex-col gap-2">
                @forelse ($this->workers as $worker)
                    <div wire:key="worker-{{ $worker->id }}"
                        class="flex items-center justify-between gap-4 rounded-xl border border-[#e8eaef] bg-white px-4 py-3">
                        <label class="flex min-w-0 cursor-pointer items-center gap-3 text-sm text-zinc-700">
                            <input type="checkbox" class="form-checkbox" value="{{ $worker->id }}"
                                wire:model.live="selectedWorkerIds" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-zinc-800">{{ $worker->name }}</span>
                                <span class="block truncate text-xs text-zinc-400">{{ $worker->email_receiver ?: $worker->email }}</span>
                            </span>
                        </label>

                        <x-ui.button variant="outline" class="shrink-0 whitespace-nowrap"
                            :href="route('email-to-worker.roster', [$this->project, $worker, 'type' => $downloadType, ...$rosterQuery])">
                            {{ __('Download roster form') }}
                        </x-ui.button>
                    </div>
                @empty
                    <div class="rounded-xl border border-[#e8eaef] bg-white px-6 py-16 text-center text-sm font-medium text-[#aeb4be]">
                        {{ __('No workers assigned to this project.') }}
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right column: compose + links + attachments + 명부양식 toggles --}}
        <div class="space-y-6">
            <x-ui.card class="space-y-4 p-6">
                <x-ui.input :label="__('Email Subject')" wire:model="subject" type="text" name="subject" />

                <x-ui.textarea :label="__('Email Message')" wire:model="message" name="message" rows="6" />

                <div class="flex justify-end">
                    <x-ui.button wire:click="send" wire:loading.attr="disabled" wire:target="send">
                        <span wire:loading.remove wire:target="send">{{ __('Send Email') }}</span>
                        <span wire:loading wire:target="send">{{ __('Sending...') }}</span>
                    </x-ui.button>
                </div>
            </x-ui.card>

            <div>
                <p class="mb-2 text-sm font-semibold text-zinc-800">{{ __('Links') }}</p>
                <div class="flex flex-col gap-2">
                    @forelse ($this->linkResources as $resource)
                        <label wire:key="link-{{ $resource->id }}"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#e8eaef] bg-white px-4 py-3 text-sm text-zinc-700">
                            <input type="checkbox" class="form-checkbox" value="{{ $resource->id }}"
                                wire:model="selectedLinkIds" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-zinc-800">{{ $resource->title }}</span>
                                <span class="block truncate text-xs text-zinc-400">{{ $resource->url }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-zinc-400">{{ __('No links found') }}</p>
                    @endforelse
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-zinc-800">{{ __('Attachments') }}</p>
                <div class="flex flex-col gap-2">
                    @forelse ($this->fileResources as $resource)
                        <label wire:key="file-{{ $resource->id }}"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#e8eaef] bg-white px-4 py-3 text-sm text-zinc-700">
                            <input type="checkbox" class="form-checkbox" value="{{ $resource->id }}"
                                wire:model="selectedAttachmentIds" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-zinc-800">{{ $resource->title ?: $resource->file_name }}</span>
                                <span class="block truncate text-xs text-zinc-400">{{ $resource->file_name }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-zinc-400">{{ __('No attachments found') }}</p>
                    @endforelse
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-zinc-800">{{ __('Roster form') }}</p>
                <div class="flex flex-col gap-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#e8eaef] bg-white px-4 py-3 text-sm text-zinc-700">
                        <input type="checkbox" class="form-checkbox" wire:model="includeRosterXlsx" />
                        {{ __('Include roster form in email (Excel)') }}
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#e8eaef] bg-white px-4 py-3 text-sm text-zinc-700">
                        <input type="checkbox" class="form-checkbox" wire:model="includeRosterPdf" />
                        {{ __('Include roster form in email (PDF)') }}
                    </label>
                </div>
            </div>
        </div>
    </div>
</section>
