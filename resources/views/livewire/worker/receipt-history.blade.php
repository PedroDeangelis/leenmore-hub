<div>
    {{-- Header --}}
    <header class="sticky top-0 z-20 bg-primary px-5 pb-5 pt-6 text-white">
        <div class="max-w-md mx-auto">
            <h1 class="text-[22px] font-bold tracking-tight">{{ __('View receipt history') }}</h1>
        </div>
    </header>

    <div class="space-y-2.5 py-4 max-w-md mx-auto">
        @forelse ($receipts as $receipt)
            <div wire:key="receipt-{{ $receipt->id }}" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <span
                        class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 text-[12px] font-semibold text-primary">
                        {{ $receipt->category_name ?? '—' }}
                    </span>
                    <span class="shrink-0 text-[12px] tabular-nums text-zinc-400">
                        {{ ($receipt->date ?? $receipt->created_at)->format('Y.m.d') }}
                    </span>
                </div>
                <div class="mt-2 flex items-baseline justify-between gap-2">
                    <span class="text-[14px] font-medium text-zinc-700">{{ $receipt->vendor }}</span>
                    <span
                        class="text-[16px] font-bold tabular-nums text-zinc-900">₩{{ number_format($receipt->amount) }}</span>
                </div>
                @if ($receipt->notes)
                    <p class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-[12.5px] leading-relaxed text-zinc-600">
                        {{ $receipt->notes }}</p>
                @endif
                @if ($receipt->attachment)
                    <a href="{{ route('receipts.file', ['receipt' => $receipt, 'preview' => 1]) }}" target="_blank"
                        rel="noopener"
                        class="mt-2 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-primary">
                        <x-heroicon-o-paper-clip class="size-3.5" />{{ __('View') }}
                    </a>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-20 text-center">
                <x-heroicon-o-receipt-percent class="size-9 text-zinc-300" />
                <p class="text-sm font-medium text-zinc-500">{{ __('No receipts yet.') }}</p>
            </div>
        @endforelse

        @if ($receipts->hasPages())
            <div class="pt-2">{{ $receipts->links() }}</div>
        @endif
    </div>
</div>
