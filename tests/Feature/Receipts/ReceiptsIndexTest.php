<?php

namespace Tests\Feature\Receipts;

use App\Livewire\Receipts\Index;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_and_office_can_view_the_receipts_archive(): void
    {
        Receipt::factory()->create(['vendor' => 'GS25', 'user_name' => 'Kim']);

        foreach ([User::factory()->admin()->create(), User::factory()->office()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('receipts.index'))
                ->assertOk();
        }
    }

    public function test_workers_cannot_view_the_receipts_archive(): void
    {
        $this->actingAs(User::factory()->worker()->create())
            ->get(route('receipts.index'))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_the_archive_lists_submitted_receipts(): void
    {
        Receipt::factory()->create(['vendor' => 'GS25', 'user_name' => 'Kim']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)
            ->assertSee('GS25')
            ->assertSee('Kim');
    }

    public function test_deleting_a_receipt_soft_deletes_it_and_can_be_reverted_via_the_database(): void
    {
        $receipt = Receipt::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)->call('delete', $receipt->id);

        // The row stays in the database (deleted_at set), out of the default query.
        $this->assertSoftDeleted($receipt);
        $this->assertSame(0, Receipt::count());
        $this->assertSame(1, Receipt::withTrashed()->count());

        // A developer can revert it straight from the database.
        $receipt->restore();
        $this->assertSame(1, Receipt::count());
    }
}
