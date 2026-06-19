<div class="-m-6 min-h-screen bg-[#f4f5f7] p-4 text-[13px] text-[#3d424b] sm:px-10 sm:py-8"
    style="font-family:'Pretendard','Apple SD Gothic Neo','Malgun Gothic',system-ui,sans-serif;">
    {{-- Pretendard gives proper Korean glyphs; falls back to system Korean fonts. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" />

    @php($shareholder = $projectShareholder->shareholder)
    @php($address = $projectShareholder->effective_address)

    <div class="mx-auto max-w-[1200px]">

        {{-- Header --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-[24px] font-bold tracking-tight text-[#171a1f]">{{ __('Activity report') }}</h1>
            <a href="{{ route('activity.project', $project) }}" wire:navigate
                class="inline-flex items-center gap-1.5 text-[13.5px] font-bold text-primary transition hover:text-primary-light">
                <x-heroicon-o-arrow-left class="size-[15px]" />{{ __('Back') }}
            </a>
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-2">

            {{-- LEFT: shareholder identity + previous reports --}}
            <div class="space-y-4">
                <div class="rounded-2xl border border-[#e8eaef] bg-white p-5">
                    <div class="flex items-start justify-between gap-4 border-b border-[#f1f2f5] pb-4">
                        <div>
                            <div class="mb-1 text-[11.5px] font-semibold text-[#8b919c]">{{ __('Shareholder') }}</div>
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="text-[18px] font-bold text-[#171a1f]">{{ $shareholder?->name ?? '—' }}</span>
                                <span class="text-[13px] font-medium tabular-nums text-[#8b919c]">{{ $shareholder?->date_of_birth_code ?: $shareholder?->registration }}</span>
                            </div>
                        </div>
                        @if ($shareholder?->sex)
                            <div class="text-right">
                                <div class="mb-1 text-[11.5px] font-semibold text-[#8b919c]">{{ __('Sex') }}</div>
                                <div class="text-[15px] font-bold text-primary">({{ $shareholder->sex }})</div>
                            </div>
                        @endif
                    </div>
                    <div class="flex items-end justify-between gap-4 pt-4">
                        <div class="min-w-0">
                            <div class="mb-1 text-[11.5px] font-semibold text-[#8b919c]">{{ __('Address') }}</div>
                            <div class="text-[14px] text-[#3d424b]">{{ $address ?: '—' }}</div>
                        </div>
                        <div class="flex flex-none gap-6 text-right">
                            <div>
                                <div class="mb-1 text-[11.5px] font-semibold text-[#8b919c]">{{ __('Shares') }}</div>
                                <div class="text-[15px] font-bold tabular-nums text-[#171a1f]">{{ $projectShareholder->shares !== null ? number_format($projectShareholder->shares) : '—' }}</div>
                            </div>
                            <div>
                                <div class="mb-1 text-[11.5px] font-semibold text-[#8b919c]">{{ __('Total shares') }}</div>
                                <div class="text-[15px] font-bold tabular-nums text-[#171a1f]">{{ $projectShareholder->shares_total !== null ? number_format($projectShareholder->shares_total) : '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Previous reports --}}
                <div class="rounded-2xl border border-[#e8eaef] bg-white p-5">
                    <div class="mb-3 text-[11.5px] font-semibold uppercase tracking-wide text-[#8b919c]">{{ __('Previous reports') }}</div>
                    <div class="space-y-2.5">
                        @forelse ($previousSubmissions as $sub)
                            @php($color = $results->firstWhere('name', $sub->result)?->color)
                            <div wire:key="prev-{{ $sub->id }}" class="rounded-xl border border-[#eef0f3] p-3.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($sub->result && $color)
                                            <x-result.chip :color="$color" :label="$sub->result" />
                                        @elseif ($sub->result)
                                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-[11.5px] font-semibold text-zinc-600">{{ $sub->result }}</span>
                                        @endif
                                        @if ($sub->user_name)
                                            <span class="text-[13px] text-[#5b616c]">{{ $sub->user_name }}</span>
                                        @endif
                                        @if ($sub->created_by_user_id)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-[#eef0f4] px-2 py-0.5 text-[10.5px] font-semibold text-[#7d838f]"
                                                title="{{ $sub->creator?->name }}">
                                                <x-heroicon-o-pencil class="size-3" />{{ __('Manually entered (admin)') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="text-[12px] tabular-nums text-[#aeb4be]">{{ ($sub->date ?? $sub->created_at)->format('Y/m/d') }}</span>
                                        <button type="button" wire:click="editReport({{ $sub->id }})"
                                            class="flex size-7 items-center justify-center rounded-md border border-[#e6e8ed] text-[#7d838f] transition hover:bg-[#fafbfc]"
                                            aria-label="{{ __('Edit') }}">
                                            <x-heroicon-o-pencil class="size-3.5" />
                                        </button>
                                        <button type="button" wire:click="deleteReport({{ $sub->id }})"
                                            wire:confirm="{{ __('Delete this report?') }}"
                                            class="flex size-7 items-center justify-center rounded-md border border-red-200 text-red-500 transition hover:bg-red-50"
                                            aria-label="{{ __('Delete') }}">
                                            <x-heroicon-o-x-mark class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                                @if ($sub->contact)
                                    <div class="mt-2 text-[12.5px] font-semibold text-primary">{{ __('Contact information') }}: {{ $sub->contact }}</div>
                                @endif
                                @if ($sub->note)
                                    <p class="mt-1.5 text-[13px] leading-relaxed text-[#3d424b]">{{ $sub->note }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="py-8 text-center text-[13px] text-[#aeb4be]">{{ __('No reports yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- RIGHT: worker selector + form --}}
            <div class="space-y-4">
                @unless ($showForm)
                    <div class="flex items-center gap-2.5 rounded-2xl border border-[#bfe6cf] bg-[#e9f7ef] px-5 py-4 text-[14px] font-semibold text-[#15834a]">
                        <x-heroicon-o-information-circle class="size-5 shrink-0" />
                        {{ __('Select a user to display the submission form') }}
                    </div>
                @endunless

                <div class="rounded-2xl border border-[#e8eaef] bg-white p-5">
                    <div class="relative">
                        <select wire:model.live="selectedWorkerId" aria-label="{{ __('Select a worker') }}"
                            class="w-full appearance-none rounded-[10px] border border-[#e6e8ed] bg-white py-3 pe-9 ps-4 text-[14px] font-semibold text-[#3d424b] outline-none focus:border-primary">
                            <option value="">{{ __('Select a worker') }}</option>
                            @foreach ($workers as $worker)
                                <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                            @endforeach
                        </select>
                        <x-heroicon-o-chevron-down class="pointer-events-none absolute end-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9aa0aa]" />
                    </div>

                    @if ($showForm)
                        <div class="mt-5 space-y-4 border-t border-[#f1f2f5] pt-5">
                            @if ($editingId)
                                <div class="rounded-lg bg-[#fff7e8] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#9a7406]">{{ __('Editing an existing report') }}</div>
                            @endif

                            {{-- Existing attachments (edit mode) --}}
                            @if (count($existingAttachments))
                                <div class="space-y-1.5">
                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-[#8b919c]">{{ __('Attached files') }}</div>
                                    @foreach ($existingAttachments as $i => $path)
                                        <div wire:key="existing-{{ $i }}" class="flex items-center justify-between gap-2 rounded-lg border border-[#e6e8ed] px-3 py-2 text-[12.5px]">
                                            <span class="flex min-w-0 items-center gap-2 text-[#3d424b]">
                                                <x-heroicon-o-document class="size-4 shrink-0 text-[#9aa0aa]" />
                                                <span class="truncate">{{ basename($path) }}</span>
                                            </span>
                                            <button type="button" wire:click="removeExistingAttachment({{ $i }})"
                                                class="shrink-0 text-red-500 hover:text-red-600" aria-label="{{ __('Remove') }}">
                                                <x-heroicon-o-x-mark class="size-4" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @include('partials.activity-report-form', [
                                'dateLocked' => false,
                                'submitLabel' => $editingId ? __('Save changes') : __('Create report'),
                            ])

                            @if ($editingId)
                                <button type="button" wire:click="cancelEdit"
                                    class="w-full rounded-lg border border-zinc-200 py-2.5 text-[13.5px] font-semibold text-zinc-600 transition hover:bg-zinc-50">{{ __('Cancel') }}</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
