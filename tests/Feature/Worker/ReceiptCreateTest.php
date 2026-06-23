<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\ReceiptCreate;
use App\Models\Receipt;
use App\Models\ReceiptCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_workers_can_open_the_receipt_form_with_the_announcement(): void
    {
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('worker.receipts.create'))
            ->assertOk()
            ->assertSee('식대'); // the seeded announcement banner
    }

    public function test_non_workers_cannot_reach_the_receipt_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('worker.receipts.create'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_worker_can_submit_a_receipt(): void
    {
        $worker = User::factory()->worker()->create(['name' => 'Kim']);
        $category = ReceiptCategory::ordered()->first();

        $this->actingAs($worker);

        Livewire::test(ReceiptCreate::class)
            ->set('date', '2026-06-21')
            ->set('receipt_category_id', $category->id)
            ->set('vendor', 'GS25')
            ->set('amount', 9000)
            ->set('notes', 'Lunch')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('worker.receipts.index'));

        $receipt = Receipt::firstWhere('vendor', 'GS25');

        $this->assertNotNull($receipt);
        $this->assertSame($worker->id, $receipt->user_id);
        $this->assertSame('Kim', $receipt->user_name);
        $this->assertSame($category->id, $receipt->receipt_category_id);
        $this->assertSame($category->name, $receipt->category_name); // snapshot
        $this->assertSame(9000, $receipt->amount);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(ReceiptCreate::class)
            ->set('date', '') // the date defaults to today; clear it to test the rule
            ->call('save')
            ->assertHasErrors([
                'date' => 'required',
                'receipt_category_id' => 'required',
                'vendor' => 'required',
                'amount' => 'required',
            ]);
    }

    public function test_the_date_defaults_to_today(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        Livewire::test(ReceiptCreate::class)
            ->assertSet('date', now()->toDateString());
    }

    public function test_a_worker_can_attach_a_single_receipt_file(): void
    {
        Storage::fake('local');

        $worker = User::factory()->worker()->create();
        $category = ReceiptCategory::ordered()->first();

        $this->actingAs($worker);

        Livewire::test(ReceiptCreate::class)
            ->set('date', '2026-06-21')
            ->set('receipt_category_id', $category->id)
            ->set('vendor', 'GS25')
            ->set('amount', 9000)
            ->set('attachment', UploadedFile::fake()->image('receipt.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $receipt = Receipt::firstWhere('vendor', 'GS25');

        $this->assertNotNull($receipt->attachment);
        Storage::disk('local')->assertExists($receipt->attachment);
    }

    public function test_the_owner_and_admin_can_view_the_attachment_but_strangers_cannot(): void
    {
        Storage::fake('local');

        $worker = User::factory()->worker()->create();
        $category = ReceiptCategory::ordered()->first();

        $this->actingAs($worker);
        Livewire::test(ReceiptCreate::class)
            ->set('date', '2026-06-21')
            ->set('receipt_category_id', $category->id)
            ->set('vendor', 'GS25')
            ->set('amount', 9000)
            ->set('attachment', UploadedFile::fake()->image('receipt.jpg'))
            ->call('save');

        $receipt = Receipt::firstWhere('vendor', 'GS25');

        // Owner may view their own.
        $this->actingAs($worker)->get(route('receipts.file', $receipt))->assertOk();

        // Admin (view-receipts) may view any.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('receipts.file', $receipt))->assertOk();

        // Another worker may not.
        $this->actingAs(User::factory()->worker()->create())
            ->get(route('receipts.file', $receipt))->assertForbidden();
    }

    public function test_the_amount_must_be_at_least_one(): void
    {
        $worker = User::factory()->worker()->create();
        $category = ReceiptCategory::ordered()->first();

        $this->actingAs($worker);

        Livewire::test(ReceiptCreate::class)
            ->set('date', '2026-06-21')
            ->set('receipt_category_id', $category->id)
            ->set('vendor', 'GS25')
            ->set('amount', 0)
            ->call('save')
            ->assertHasErrors(['amount']);
    }
}
