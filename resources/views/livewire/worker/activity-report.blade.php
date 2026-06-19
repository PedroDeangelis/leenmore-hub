<div>
    @php($shareholder = $projectShareholder->shareholder)
    @php($address = $projectShareholder->effective_address)

    {{-- Header: back + shareholder identity --}}
    <header class="sticky top-0 z-20 bg-primary px-4 pb-4 pt-5 text-white">
        <div class="max-w-md mx-auto">
            <div class="flex items-start gap-2">
                <a href="{{ route('worker.projects.show', $project) }}" wire:navigate
                    class="-ms-1 mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white"
                    aria-label="{{ __('Back') }}">
                    <x-heroicon-o-arrow-left class="size-5" />
                </a>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/60">
                        {{ __('Shareholders') }}</p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h1 class="text-[19px] font-bold tracking-tight">{{ $shareholder->name }}</h1>
                        <span class="text-[13px] font-semibold tabular-nums text-white/70">
                            {{ $shareholder->date_of_birth_code ?? ($shareholder->registration ?? '—') }}</span>
                        @if ($shareholder->sex)
                            <span
                                class="rounded-full bg-white/10 px-2 py-0.5 text-[12px] font-semibold text-white/90">{{ $shareholder->sex }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="space-y-3 py-4 max-w-md mx-auto">

        {{-- Shareholder summary: shares, address, prior reports --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            {{-- Shares --}}
            <div class="flex gap-4 rounded-xl bg-zinc-50 px-3 py-2 text-[13px]">
                <div class="flex-1">
                    <div class="text-[11px] font-semibold text-zinc-400">{{ __('Shares') }}</div>
                    <div class="font-bold tabular-nums text-zinc-800">
                        {{ $projectShareholder->shares !== null ? number_format($projectShareholder->shares) : '—' }}
                    </div>
                </div>
                <div class="flex-1 border-l border-zinc-200 pl-4">
                    <div class="text-[11px] font-semibold text-zinc-400">{{ __('Total shares') }}</div>
                    <div class="font-bold tabular-nums text-zinc-800">
                        {{ $projectShareholder->shares_total !== null ? number_format($projectShareholder->shares_total) : '—' }}
                    </div>
                </div>
            </div>

            {{-- Address + field actions --}}
            @if ($address)
                <div class="mt-3">
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

        {{-- Prior submissions for this shareholder --}}
        @if ($previousSubmissions->isNotEmpty())
            <div class="mt-4 mb-6">
                <p class="mb-2 px-4 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                    {{ __('Previous reports') }}</p>
                <div class="space-y-2.5">
                    @foreach ($previousSubmissions as $sub)
                        @php($color = $results->firstWhere('name', $sub->result)?->color)
                        <div wire:key="prev-{{ $sub->id }}"
                            class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                            {{-- Judgment pill + date --}}
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    @if ($sub->result && $color)
                                        <x-result.chip :color="$color" :label="$sub->result" />
                                    @elseif ($sub->result)
                                        <span
                                            class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-[11.5px] font-semibold text-zinc-600">{{ $sub->result }}</span>
                                    @else
                                        <span class="text-[12.5px] text-zinc-400">—</span>
                                    @endif
                                    @if ($sub->user_name)
                                        <span class="text-sm text-zinc-500">{{ $sub->user_name }}</span>
                                    @endif
                                </div>
                                <span class="shrink-0 text-[12px] tabular-nums text-zinc-400">
                                    {{ ($sub->date ?? $sub->created_at)->format('Y.m.d') }}</span>
                            </div>

                            {{-- Contact(s) --}}
                            @if ($sub->contact)
                                <div class="mt-2 space-y-1">
                                    @foreach (array_filter(array_map('trim', explode(',', $sub->contact))) as $phone)
                                        <a href="tel:{{ $phone }}"
                                            class="flex items-center gap-1.5 text-[12.5px] font-semibold text-primary">
                                            <x-heroicon-o-phone class="size-3.5 shrink-0" />{{ $phone }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Note --}}
                            @if ($sub->note)
                                <p
                                    class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-[12.5px] leading-relaxed text-zinc-600">
                                    {{ $sub->note }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Submission form (shared with the admin Activity\Report page) --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            @include('partials.activity-report-form', ['dateLocked' => true])
        </div>
    </div>
</div>
