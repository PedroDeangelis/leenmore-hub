<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\ReceiptHistory;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_worker_only_sees_their_own_receipts(): void
    {
        $worker = User::factory()->worker()->create();
        $other = User::factory()->worker()->create();

        Receipt::factory()->for($worker)->create(['vendor' => 'My Mart']);
        Receipt::factory()->for($other)->create(['vendor' => 'Their Mart']);

        $this->actingAs($worker);

        Livewire::test(ReceiptHistory::class)
            ->assertSee('My Mart')
            ->assertDontSee('Their Mart');
    }
}
