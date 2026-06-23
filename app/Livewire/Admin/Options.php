<?php

namespace App\Livewire\Admin;

use App\Models\ReceiptCategory;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin-only Options tool (설정): edit the receipt-form announcement banner and the
 * list of usage categories (사용 내역) workers pick from. Gated by `manage-settings`.
 */
#[Title('Options')]
class Options extends Component
{
    public string $announcement = '';

    public string $newCategory = '';

    /** Inline-edit state for an existing category. */
    public ?int $editingCategoryId = null;

    public string $editingCategoryName = '';

    public function mount(): void
    {
        Gate::authorize('manage-settings');

        $this->announcement = Setting::get('receipt_announcement', '') ?? '';
    }

    /**
     * Persist the announcement banner text.
     */
    public function saveAnnouncement(): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'announcement' => ['nullable', 'string', 'max:1000'],
        ]);

        Setting::set('receipt_announcement', $validated['announcement'] ?: null);

        $this->dispatch('toast', message: __('Announcement saved.'), variant: 'success');
    }

    /**
     * Append a new usage category.
     */
    public function addCategory(): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'newCategory' => ['required', 'string', 'max:255'],
        ]);

        ReceiptCategory::create([
            'name' => $validated['newCategory'],
            'position' => (int) ReceiptCategory::max('position') + 1,
        ]);

        $this->newCategory = '';

        $this->dispatch('toast', message: __('Category added.'), variant: 'success');
    }

    /**
     * Begin inline-editing a category's name.
     */
    public function editCategory(int $id): void
    {
        Gate::authorize('manage-settings');

        $category = ReceiptCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->editingCategoryName = $category->name;
    }

    /**
     * Save the inline-edited category name.
     */
    public function updateCategory(): void
    {
        Gate::authorize('manage-settings');

        $validated = $this->validate([
            'editingCategoryName' => ['required', 'string', 'max:255'],
        ]);

        ReceiptCategory::whereKey($this->editingCategoryId)
            ->update(['name' => $validated['editingCategoryName']]);

        $this->cancelEdit();

        $this->dispatch('toast', message: __('Category updated.'), variant: 'success');
    }

    public function cancelEdit(): void
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    /**
     * Remove a category. Existing receipts keep their snapshotted category name.
     */
    public function deleteCategory(int $id): void
    {
        Gate::authorize('manage-settings');

        ReceiptCategory::whereKey($id)->delete();

        if ($this->editingCategoryId === $id) {
            $this->cancelEdit();
        }

        $this->dispatch('toast', message: __('Category deleted.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.options', [
            'categories' => ReceiptCategory::ordered()->get(),
        ]);
    }
}
