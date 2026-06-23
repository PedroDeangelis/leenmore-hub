<?php

namespace App\Livewire\Receipts;

use App\Models\Receipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The receipts archive (영수증 내역): every worker-submitted receipt, newest first.
 * Read-only — admins + office (gated by `view-receipts`).
 */
#[Title('Receipts')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('view-receipts');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Soft-delete a receipt. The row stays in the database (deleted_at set) and is
     * reversible only via the database — there is intentionally no restore UI.
     */
    public function delete(int $id): void
    {
        Gate::authorize('manage-receipts');

        Receipt::findOrFail($id)->delete();

        $this->dispatch('toast', message: __('Receipt deleted.'), variant: 'success');
    }

    /**
     * Receipts filtered by worker / vendor / category, newest first.
     *
     * @return LengthAwarePaginator<int, Receipt>
     */
    private function receipts(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Receipt::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('user_name', 'like', '%'.$search.'%')
                ->orWhere('vendor', 'like', '%'.$search.'%')
                ->orWhere('category_name', 'like', '%'.$search.'%')))
            ->latest() // most recently submitted first (created_at desc)
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.receipts.index', [
            'receipts' => $this->receipts(),
        ]);
    }
}
