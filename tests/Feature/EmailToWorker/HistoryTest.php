<?php

namespace Tests\Feature\EmailToWorker;

use App\Enums\WorkerEmailRecipientStatus;
use App\Jobs\SendWorkerEmail;
use App\Livewire\EmailToWorker\History;
use App\Models\User;
use App\Models\WorkerEmail;
use App\Models\WorkerEmailRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipients_are_listed_with_their_status(): void
    {
        $batch = WorkerEmail::factory()->create(['subject' => 'Meeting notice']);
        WorkerEmailRecipient::factory()->for($batch)->sent()->create(['email' => 'sent@example.com']);
        WorkerEmailRecipient::factory()->for($batch)->failed()->create(['email' => 'failed@example.com']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(History::class)
            ->assertSee('Meeting notice')
            ->assertSee('sent@example.com')
            ->assertSee('failed@example.com')
            ->assertSee('SMTP connection refused');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        WorkerEmailRecipient::factory()->sent()->create(['email' => 'sent@example.com']);
        WorkerEmailRecipient::factory()->failed()->create(['email' => 'failed@example.com']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(History::class)
            ->set('status', WorkerEmailRecipientStatus::Failed->value)
            ->assertSee('failed@example.com')
            ->assertDontSee('sent@example.com');
    }

    public function test_the_list_can_be_searched_by_subject(): void
    {
        WorkerEmailRecipient::factory()
            ->for(WorkerEmail::factory()->create(['subject' => 'Alpha notice']))
            ->create(['email' => 'alpha@example.com']);
        WorkerEmailRecipient::factory()
            ->for(WorkerEmail::factory()->create(['subject' => 'Beta notice']))
            ->create(['email' => 'beta@example.com']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(History::class)
            ->set('search', 'Alpha')
            ->assertSee('alpha@example.com')
            ->assertDontSee('beta@example.com');
    }

    public function test_a_failed_recipient_can_be_requeued(): void
    {
        Queue::fake();

        $recipient = WorkerEmailRecipient::factory()->failed()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(History::class)
            ->call('retry', $recipient->id);

        $recipient->refresh();
        $this->assertSame(WorkerEmailRecipientStatus::Pending, $recipient->status);
        $this->assertNull($recipient->error_message);

        Queue::assertPushed(SendWorkerEmail::class, 1);
    }

    public function test_a_sent_recipient_is_not_requeued(): void
    {
        Queue::fake();

        $recipient = WorkerEmailRecipient::factory()->sent()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(History::class)
            ->call('retry', $recipient->id);

        $this->assertSame(WorkerEmailRecipientStatus::Sent, $recipient->fresh()->status);
        Queue::assertNothingPushed();
    }
}
