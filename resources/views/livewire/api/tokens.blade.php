<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900">{{ __('API access') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ __('Create a token, label it with who it is for, and hand it to that client. Endpoints are read-only and personal data is masked.') }}
            </p>
        </div>

        @can('manage-api-tokens')
            <x-ui.button wire:click="$set('showCreate', true)">
                <x-heroicon-o-plus class="size-4" />
                {{ __('Create token') }}
            </x-ui.button>
        @endcan
    </div>

    {{-- One-time plaintext reveal. Shown once, right after creation. --}}
    @if ($plainText)
        <x-ui.card class="border-primary/30 bg-primary/5 p-5">
            <div class="flex items-start gap-3">
                <x-heroicon-o-key class="mt-0.5 size-5 shrink-0 text-primary" />
                <div class="min-w-0 flex-1 space-y-2">
                    <p class="text-sm font-medium text-zinc-900">
                        {{ __('Copy this token and hand it to the client. You can view it again later by clicking it in the list.') }}
                    </p>
                    <div class="flex items-center gap-2" x-data="{ copied: false }">
                        <code class="min-w-0 flex-1 truncate rounded-lg border border-zinc-200 bg-white px-3 py-2 font-mono text-sm text-zinc-800"
                            x-ref="tok">{{ $plainText }}</code>
                        <x-ui.button variant="outline" type="button"
                            x-on:click="navigator.clipboard.writeText($refs.tok.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </x-ui.button>
                    </div>
                    <button type="button" wire:click="dismissPlainText"
                        class="text-xs text-zinc-500 hover:text-zinc-700 hover:underline">
                        {{ __('Done — hide this') }}
                    </button>
                </div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.table>
        <x-slot:head>
            <x-ui.table.heading>{{ __('For') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Token') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Status') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Assigned to') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Created by') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Created') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Expires') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Last used') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Action') }}</x-ui.table.heading>
        </x-slot:head>

        @forelse ($tokens as $token)
            <tr wire:key="token-{{ $token->id }}" class="hover:bg-zinc-50">
                <x-ui.table.cell class="font-medium text-zinc-800">{{ $token->name }}</x-ui.table.cell>
                <x-ui.table.cell class="font-mono text-zinc-500">
                    @if ($token->token === null)
                        <span title="{{ __('Created before tokens could be viewed again — not recoverable.') }}">lmh_…{{ $token->last_four }}</span>
                    @elseif ($revealedId === $token->id)
                        <div class="flex items-center gap-2" x-data="{ copied: false }">
                            <code x-ref="tok" class="break-all text-zinc-800">{{ $token->token }}</code>
                            <button type="button" title="{{ __('Copy') }}" class="shrink-0 text-zinc-400 hover:text-zinc-600"
                                x-on:click="navigator.clipboard.writeText($refs.tok.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="!copied"><x-heroicon-o-clipboard class="size-4" /></span>
                                <span x-show="copied" x-cloak><x-heroicon-c-check class="size-4 text-green-600" /></span>
                            </button>
                            <button type="button" wire:click="reveal({{ $token->id }})" title="{{ __('Hide') }}"
                                class="shrink-0 text-zinc-400 hover:text-zinc-600">
                                <x-heroicon-o-eye-slash class="size-4" />
                            </button>
                        </div>
                    @else
                        <button type="button" wire:click="reveal({{ $token->id }})" title="{{ __('Show full token') }}"
                            class="group inline-flex items-center gap-1.5 hover:text-zinc-800">
                            lmh_…{{ $token->last_four }}
                            <x-heroicon-o-eye class="size-4 text-zinc-400 group-hover:text-zinc-600" />
                        </button>
                    @endif
                </x-ui.table.cell>
                <x-ui.table.cell>
                    @php($status = $token->status())
                    <x-ui.badge :variant="$status === 'active' ? 'success' : ($status === 'revoked' ? 'danger' : 'warning')" dot>
                        {{ __(ucfirst($status)) }}
                    </x-ui.badge>
                </x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">{{ $token->assignee?->name ?? '—' }}</x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">{{ $token->creator?->name ?? '—' }}</x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">{{ $token->created_at?->format('Y/m/d') }}</x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">{{ $token->expires_at?->format('Y/m/d') ?? __('Never') }}</x-ui.table.cell>
                <x-ui.table.cell class="text-zinc-600">
                    {{ $token->last_used_at?->diffForHumans() ?? __('Never') }}
                </x-ui.table.cell>
                <x-ui.table.cell>
                    @can('manage-api-tokens')
                        <div class="flex items-center gap-3">
                            @unless ($token->isRevoked())
                                <button type="button" wire:click="revoke({{ $token->id }})"
                                    wire:confirm="{{ __('Block this token? It will stop working immediately.') }}"
                                    class="text-orange-600 hover:underline">{{ __('Block') }}</button>
                            @endunless
                            <button type="button" wire:click="delete({{ $token->id }})"
                                wire:confirm="{{ __('Delete this token permanently?') }}"
                                class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </div>
                    @else
                        <span class="text-zinc-400">—</span>
                    @endcan
                </x-ui.table.cell>
            </tr>
        @empty
            <x-ui.table.empty :cols="9">{{ __('No tokens yet.') }}</x-ui.table.empty>
        @endforelse
    </x-ui.table>

    @if ($tokens->hasPages())
        <div>{{ $tokens->links() }}</div>
    @endif

    {{-- Create-token modal --}}
    <x-ui.modal model="showCreate">
        <form wire:submit="create" class="space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('Create token') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('The token is shown right after you create it, and can be viewed again from the list.') }}</p>
            </div>

            <x-ui.input wire:model="name" :label="__('Who is this token for?')"
                :placeholder="__('e.g. 회계법인 김앤장')" required autofocus />

            <x-ui.input wire:model="expires_at" type="date" :label="__('Expires on (optional)')" />

            <div>
                <x-ui.select wire:model="assigned_to" :label="__('Assign to a portal account (optional)')">
                    <option value="">{{ __('Not assigned — for an outside client') }}</option>
                    @foreach ($assignableUsers as $assignable)
                        <option value="{{ $assignable->id }}">{{ $assignable->name }} ({{ $assignable->email }})</option>
                    @endforeach
                </x-ui.select>
                <p class="mt-2 text-xs text-zinc-500">
                    {{ __('A viewer can only see and test with the tokens assigned to them.') }}</p>
            </div>

            <div class="flex justify-end gap-3">
                <x-ui.button type="button" variant="outline" wire:click="$set('showCreate', false)">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit">
                    <span wire:loading.remove wire:target="create">{{ __('Create token') }}</span>
                    <span wire:loading wire:target="create">{{ __('Creating…') }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</section>
