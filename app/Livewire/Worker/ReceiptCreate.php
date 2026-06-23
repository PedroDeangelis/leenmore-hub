<?php

namespace App\Livewire\Worker;

use App\Concerns\ReceiptValidationRules;
use App\Models\Receipt;
use App\Models\ReceiptCategory;
use App\Models\Setting;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The field worker's receipt submission form (영수증 제출). Records a Receipt for the
 * current worker, snapshotting their name and the chosen category name.
 */
#[Layout('components.layouts.worker')]
#[Title('Receipt submit')]
class ReceiptCreate extends Component
{
    use ReceiptValidationRules;
    use WithFileUploads;

    public ?string $date = null;

    public ?int $receipt_category_id = null;

    public string $vendor = '';

    public ?int $amount = null;

    public ?string $notes = '';

    /** A single optional receipt attachment (photo / audio / PDF). */
    public $attachment = null;

    /**
     * Default the receipt date to today; the worker can still change it.
     */
    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    /**
     * Validate, store the receipt, and open the worker's receipt history.
     */
    public function save(): mixed
    {
        $validated = $this->validate($this->receiptRules());

        $category = ReceiptCategory::find($validated['receipt_category_id']);

        Receipt::create([
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'receipt_category_id' => $category?->id,
            'category_name' => $category?->name,
            'date' => $validated['date'],
            'vendor' => $validated['vendor'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?: null,
            'attachment' => $this->attachment?->store('receipts/'.auth()->id(), 'local') ?: null,
        ]);

        session()->flash('toast', ['message' => __('Receipt submitted.'), 'variant' => 'success']);

        return $this->redirectRoute('worker.receipts.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.worker.receipt-create', [
            'categories' => ReceiptCategory::ordered()->get(),
            'announcement' => Setting::get('receipt_announcement'),
        ]);
    }
}
