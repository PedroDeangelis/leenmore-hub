<section class="w-full space-y-6">
    <div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-md bg-primary/10 px-2.5 py-1 font-mono text-xs font-semibold text-primary">
                {{ $definition['method'] }}
            </span>
            <code class="font-mono text-sm text-zinc-800">{{ $definition['path'] }}</code>
        </div>
        <h1 class="mt-3 text-xl font-semibold text-zinc-900">{{ $definition['title'] }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-zinc-600">{{ $definition['summary'] }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Left: reference + curl --}}
        <div class="space-y-6">
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold text-zinc-900">{{ __('Parameters') }}</h2>
                @if (count($definition['params']) === 0)
                    <p class="mt-2 text-sm text-zinc-500">{{ __('This endpoint takes no parameters.') }}</p>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach ($definition['params'] as $param)
                            <div class="border-b border-zinc-100 pb-3 last:border-0 last:pb-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <code class="font-mono text-sm text-zinc-800">{{ $param['name'] }}</code>
                                    <span class="text-xs text-zinc-400">{{ $param['type'] }}</span>
                                    <span class="text-xs text-zinc-400">·</span>
                                    <span class="text-xs text-zinc-400">{{ $param['in'] }}</span>
                                    @if ($param['required'])
                                        <x-ui.badge variant="danger">{{ __('required') }}</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-zinc-500">{{ $param['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card class="p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-zinc-900">{{ __('Example request') }}</h2>
                    <button type="button" class="text-xs text-primary hover:underline" x-data
                        x-on:click="navigator.clipboard.writeText($refs.curl.textContent.trim())"
                        x-ref="btn">{{ __('Copy') }}</button>
                </div>
                <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-900 p-3 text-xs leading-relaxed text-zinc-100"><code x-ref="curl">{{ $curl }}</code></pre>
                <p class="mt-2 text-xs text-zinc-500">
                    {{ __('Replace YOUR_TOKEN with a token from the API access page.') }}
                </p>
            </x-ui.card>
        </div>

        {{-- Right: live tester --}}
        <div class="space-y-4">
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold text-zinc-900">{{ __('Try it') }}</h2>
                <p class="mt-1 text-xs text-zinc-500">
                    {{ __('Runs against live data and shows exactly what the endpoint returns — already masked.') }}
                </p>

                <form wire:submit="run" class="mt-4 space-y-3">
                    @foreach ($definition['params'] as $param)
                        <div class="space-y-1">
                            <label class="form-label">
                                {{ $param['name'] }}
                                @if ($param['required'])<span class="text-red-500">*</span>@endif
                            </label>

                            @if (($param['input'] ?? null) === 'date-list')
                                <div class="space-y-2">
                                    @foreach ($params[$param['name']] ?? [] as $index => $day)
                                        <div class="flex items-center gap-2" wire:key="{{ $param['name'] }}-row-{{ $index }}">
                                            <input type="date" wire:model="params.{{ $param['name'] }}.{{ $index }}" class="form-input flex-1" />
                                            <button type="button" wire:click="removeListItem('{{ $param['name'] }}', {{ $index }})"
                                                title="{{ __('Remove') }}" class="shrink-0 text-zinc-400 hover:text-red-600">
                                                <x-heroicon-o-x-mark class="size-4" />
                                            </button>
                                        </div>
                                    @endforeach

                                    <button type="button" wire:click="addListItem('{{ $param['name'] }}')"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                                        <x-heroicon-o-plus class="size-3.5" />
                                        {{ __('Add date') }}
                                    </button>
                                </div>
                            @elseif (! empty($param['options']))
                                <select wire:model="params.{{ $param['name'] }}" class="form-input">
                                    <option value="">{{ __('(any)') }}</option>
                                    @foreach ($param['options'] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($param['type'] === 'integer')
                                <input type="number" wire:model="params.{{ $param['name'] }}" class="form-input"
                                    placeholder="{{ $param['example'] ?? '' }}" />
                            @elseif ($param['type'] === 'date')
                                <input type="date" wire:model="params.{{ $param['name'] }}" class="form-input" />
                            @else
                                <input type="text" wire:model="params.{{ $param['name'] }}" class="form-input"
                                    placeholder="{{ $param['example'] ?? '' }}" />
                            @endif

                            @error('params.'.$param['name'])
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <x-ui.button type="submit" class="w-full">
                        <span wire:loading.remove wire:target="run">{{ __('Send request') }}</span>
                        <span wire:loading wire:target="run">{{ __('Running…') }}</span>
                    </x-ui.button>
                </form>
            </x-ui.card>

            @if ($result !== null)
                <x-ui.card class="p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-zinc-900">{{ __('Response') }}</h2>
                        <x-ui.badge :variant="$resultStatus === 200 ? 'success' : 'danger'" dot>
                            {{ $resultStatus }}
                        </x-ui.badge>
                    </div>
                    <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-zinc-900 p-3 text-xs leading-relaxed text-zinc-100"><code>{{ $result }}</code></pre>
                </x-ui.card>
            @endif
        </div>
    </div>
</section>
