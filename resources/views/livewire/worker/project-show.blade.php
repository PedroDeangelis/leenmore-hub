<div>
    {{-- Header: back + project title --}}
    <header class="sticky top-0 z-20 bg-primary px-4 pb-4 pt-5 text-white">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-2">
                <a href="{{ route('worker.dashboard') }}" wire:navigate
                    class="-ms-1 flex size-8 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white"
                    aria-label="{{ __('Back') }}">
                    <x-heroicon-o-arrow-left class="size-5" />
                </a>
                <h1 class="min-w-0 flex-1 truncate text-[17px] font-bold tracking-tight">{{ $project->title }}</h1>
            </div>

            {{-- Search --}}
            <div class="relative mt-3">
                <x-heroicon-o-magnifying-glass
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-white/50" />
                <input wire:model.live.debounce.300ms="search" type="search"
                    placeholder="{{ __('Search shareholders') }}" aria-label="{{ __('Search shareholders') }}"
                    class="w-full rounded-xl border border-white/15 bg-white/10 py-2 pl-9 pr-3 text-[14px] text-white placeholder:text-white/50 outline-none focus:border-white/40 focus:bg-white/15" />
            </div>
        </div>
    </header>

    <div class="space-y-3 py-4 max-w-md mx-auto">
        @forelse ($shareholders as $row)
            @php($address = $row->effective_address)
            <div wire:key="ws-{{ $row->id }}" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                {{-- Name + identity + result --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[15px] font-bold text-zinc-900">{{ $row->shareholder->name }}</p>
                        <p class="mt-0.5 text-[12px] tabular-nums text-zinc-500">
                            {{ $row->shareholder->date_of_birth_code ?? ($row->shareholder->registration ?? '—') }}
                            @if ($row->shareholder->sex)
                                <span class="text-zinc-400"> ({{ $row->shareholder->sex }})</span>
                            @endif
                        </p>
                    </div>
                    @if ($row->result)
                        <x-result.chip :color="$row->result->color" :label="$row->result->name" />
                    @endif
                </div>

                {{-- Shares --}}
                <div class="mt-3 flex gap-4 rounded-xl bg-zinc-50 px-3 py-2 text-[13px]">
                    <div class="flex-1">
                        <div class="text-[11px] font-semibold text-zinc-400">{{ __('Shares') }}</div>
                        <div class="font-bold tabular-nums text-zinc-800">
                            {{ $row->shares !== null ? number_format($row->shares) : '—' }}</div>
                    </div>
                    <div class="flex-1 border-l border-zinc-200 pl-4">
                        <div class="text-[11px] font-semibold text-zinc-400">{{ __('Total shares') }}</div>
                        <div class="font-bold tabular-nums text-zinc-800">
                            {{ $row->shares_total !== null ? number_format($row->shares_total) : '—' }}</div>
                    </div>
                </div>

                {{-- Contact --}}
                @if ($row->effective_contact)
                    <a href="tel:{{ $row->effective_contact }}"
                        class="mt-2 inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary">
                        <x-heroicon-o-phone class="size-4" />{{ $row->effective_contact }}
                    </a>
                @endif

                {{-- Address + field actions --}}
                @if ($address)
                    <div class="mt-3 border-t border-zinc-100 pt-3">
                        <div class="flex gap-1.5 text-[13px] text-zinc-600">
                            <x-heroicon-o-map-pin class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                            <span>{{ $address }}</span>
                        </div>
                        <div class="mt-2.5 flex gap-2" x-data="{ copied: false }">
                            <button type="button"
                                x-on:click="navigator.clipboard.writeText(@js($address)); copied = true; setTimeout(() => copied = false, 1500)"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-zinc-200 py-2 text-[13px] font-semibold text-zinc-700 transition hover:bg-zinc-50">
                                <template x-if="!copied">
                                    <span class="inline-flex items-center gap-1.5"><x-heroicon-o-clipboard-document
                                            class="size-4" />{{ __('Copy address') }}</span>
                                </template>
                                <template x-if="copied">
                                    <span
                                        class="inline-flex items-center gap-1.5 text-green-600"><x-heroicon-o-clipboard-document-check
                                            class="size-4" />{{ __('Copied') }}</span>
                                </template>
                            </button>
                            <a href="https://map.naver.com/v5/search/{{ urlencode($address) }}" target="_blank"
                                rel="noopener"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary py-2 text-[13px] font-semibold text-white transition hover:bg-primary-light">
                                <x-heroicon-o-map class="size-4" />{{ __('View on Naver Map') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
                <x-heroicon-o-users class="size-9 text-zinc-300" />
                <p class="text-sm font-medium text-zinc-500">
                    {{ trim($search) !== '' ? __('No shareholders match your search.') : __('No shareholders assigned to you.') }}
                </p>
            </div>
        @endforelse
    </div>
</div>
