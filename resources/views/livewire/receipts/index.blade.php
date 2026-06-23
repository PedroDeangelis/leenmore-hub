<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-zinc-900">{{ __('Receipts') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Worker-submitted receipts') }}</p>
    </div>

    <x-ui.card class="p-4 max-w-170 mx-auto mb-6">
        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search...') }}"
                aria-label="{{ __('Search receipts') }}"
                class="w-full rounded-[10px] border border-[#e6e8ed] bg-[#fafbfc] py-3 pe-11 ps-4 text-sm text-[#3d424b] outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" />
            <x-heroicon-o-magnifying-glass
                class="pointer-events-none absolute inset-e-4 top-1/2 size-4.25 -translate-y-1/2 text-[#aeb4be]" />
        </div>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <x-ui.table.heading>{{ __('Date') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Worker') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Usage category') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Vendor') }}</x-ui.table.heading>
            <x-ui.table.heading align="end">{{ __('Amount') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Notes') }}</x-ui.table.heading>
            <x-ui.table.heading>{{ __('Attachment') }}</x-ui.table.heading>
            @can('manage-receipts')
                <x-ui.table.heading align="end">{{ __('Actions') }}</x-ui.table.heading>
            @endcan
        </x-slot:head>
        @forelse ($receipts as $receipt)
            <tr wire:key="receipt-{{ $receipt->id }}">
                <x-ui.table.cell class="whitespace-nowrap tabular-nums text-zinc-500">
                    {{ ($receipt->date ?? $receipt->created_at)->format('Y.m.d') }}
                </x-ui.table.cell>
                <x-ui.table.cell class="font-medium text-zinc-800">{{ $receipt->user_name ?? '—' }}</x-ui.table.cell>
                <x-ui.table.cell>{{ $receipt->category_name ?? '—' }}</x-ui.table.cell>
                <x-ui.table.cell>{{ $receipt->vendor }}</x-ui.table.cell>
                <x-ui.table.cell align="end" class="whitespace-nowrap font-semibold tabular-nums text-zinc-900">
                    ₩{{ number_format($receipt->amount) }}
                </x-ui.table.cell>
                <x-ui.table.cell class="max-w-xs truncate text-zinc-500">{{ $receipt->notes }}</x-ui.table.cell>
                <x-ui.table.cell class="whitespace-nowrap">
                    @if ($receipt->attachment)
                        <a href="{{ route('receipts.file', ['receipt' => $receipt, 'preview' => 1]) }}" target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
                            <x-heroicon-o-paper-clip class="size-4" />{{ __('View') }}
                        </a>
                    @else
                        <span class="text-zinc-300">—</span>
                    @endif
                </x-ui.table.cell>
                @can('manage-receipts')
                    <x-ui.table.cell align="end" class="whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('receipts.edit', $receipt) }}" wire:navigate
                                class="text-zinc-400 transition hover:text-primary" aria-label="{{ __('Edit') }}">
                                <x-heroicon-o-pencil-square class="size-5" />
                            </a>
                            <button type="button" wire:click="delete({{ $receipt->id }})"
                                wire:confirm="{{ __('Delete this receipt?') }}"
                                class="text-zinc-400 transition hover:text-red-600" aria-label="{{ __('Delete') }}">
                                <x-heroicon-o-trash class="size-5" />
                            </button>
                        </div>
                    </x-ui.table.cell>
                @endcan
            </tr>
        @empty
            <tr>
                <x-ui.table.cell colspan="{{ auth()->user()->can('manage-receipts') ? 8 : 7 }}"
                    class="py-16 text-center text-sm font-medium text-zinc-400">
                    {{ __('No receipts yet.') }}
                </x-ui.table.cell>
            </tr>
        @endforelse
    </x-ui.table>

    @if ($receipts->hasPages())
        <div class="mt-6">{{ $receipts->links() }}</div>
    @endif
</div>
