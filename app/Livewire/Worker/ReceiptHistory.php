<?php

namespace App\Livewire\Worker;

use App\Models\Receipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The field worker's own submitted receipts (영수증 내역 보기), newest first.
 */
#[Layout('components.layouts.worker')]
#[Title('View receipt history')]
class ReceiptHistory extends Component
{
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Receipt>
     */
    private function receipts(): LengthAwarePaginator
    {
        return Receipt::query()
            ->where('user_id', auth()->id())
            ->latest() // most recently submitted first (created_at desc)
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.worker.receipt-history', [
            'receipts' => $this->receipts(),
        ]);
    }
}
