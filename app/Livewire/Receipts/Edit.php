<?php

namespace App\Livewire\Receipts;

use App\Concerns\ReceiptValidationRules;
use App\Models\Receipt;
use App\Models\ReceiptCategory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin/office edit of a submitted receipt. Gated by `manage-receipts`. The text
 * fields and category are editable; uploading a new file replaces the attachment.
 */
#[Title('Edit receipt')]
class Edit extends Component
{
    use ReceiptValidationRules;
    use WithFileUploads;

    #[Locked]
    public int $receiptId;

    public ?string $date = null;

    public ?int $receipt_category_id = null;

    public string $vendor = '';

    public ?int $amount = null;

    public ?string $notes = '';

    /** A replacement attachment; leave empty to keep the current one. */
    public $attachment = null;

    public function mount(Receipt $receipt): void
    {
        Gate::authorize('manage-receipts');

        $this->receiptId = $receipt->id;
        $this->date = $receipt->date?->toDateString();
        $this->receipt_category_id = $receipt->receipt_category_id;
        $this->vendor = $receipt->vendor;
        $this->amount = $receipt->amount;
        $this->notes = $receipt->notes;
    }

    /**
     * Validate and persist the changes, then return to the receipts archive.
     */
    public function update(): mixed
    {
        Gate::authorize('manage-receipts');

        $validated = $this->validate($this->receiptRules());

        $receipt = Receipt::findOrFail($this->receiptId);
        $category = ReceiptCategory::find($validated['receipt_category_id']);

        $attributes = [
            'receipt_category_id' => $category?->id,
            'category_name' => $category?->name,
            'date' => $validated['date'],
            'vendor' => $validated['vendor'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?: null,
        ];

        // Replace the attachment only when a new file was uploaded.
        if ($this->attachment) {
            $old = $receipt->attachment;
            $attributes['attachment'] = $this->attachment->store('receipts/'.($receipt->user_id ?? 0), 'local');

            if ($old) {
                Storage::disk('local')->delete($old);
            }
        }

        $receipt->update($attributes);

        session()->flash('toast', ['message' => __('Receipt updated.'), 'variant' => 'success']);

        return $this->redirectRoute('receipts.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.receipts.edit', [
            'categories' => ReceiptCategory::ordered()->get(),
            'receipt' => Receipt::findOrFail($this->receiptId),
        ]);
    }
}
