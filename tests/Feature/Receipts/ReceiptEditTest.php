<?php

namespace Tests\Feature\Receipts;

use App\Livewire\Receipts\Edit;
use App\Models\Receipt;
use App\Models\ReceiptCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_edit_page(): void
    {
        $receipt = Receipt::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('receipts.edit', $receipt))
            ->assertOk();
    }

    public function test_workers_cannot_open_the_edit_page(): void
    {
        $receipt = Receipt::factory()->create();

        $this->actingAs(User::factory()->worker()->create())
            ->get(route('receipts.edit', $receipt))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_an_admin_can_update_a_receipt(): void
    {
        $receipt = Receipt::factory()->create(['vendor' => 'Old', 'amount' => 1000]);
        $category = ReceiptCategory::ordered()->first();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Edit::class, ['receipt' => $receipt])
            ->set('date', '2026-06-21')
            ->set('receipt_category_id', $category->id)
            ->set('vendor', 'New Vendor')
            ->set('amount', 7500)
            ->set('notes', 'fixed')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('receipts.index'));

        $receipt->refresh();

        $this->assertSame('New Vendor', $receipt->vendor);
        $this->assertSame(7500, $receipt->amount);
        $this->assertSame($category->name, $receipt->category_name); // snapshot refreshed
    }
}
