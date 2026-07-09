<?php

namespace Tests\Feature\EmailToWorker;

use App\Enums\WorkerEmailRecipientStatus;
use App\Jobs\SendWorkerEmail;
use App\Livewire\EmailToWorker\Compose;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\ProjectShareholder;
use App\Models\User;
use App\Models\WorkerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ComposeTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithWorker(string $workerName = 'Worker One'): array
    {
        $project = Project::factory()->create();
        $worker = User::factory()->worker()->create(['name' => $workerName]);
        ProjectShareholder::factory()->for($project)->create()->workers()->sync([$worker->id]);

        return [$project, $worker];
    }

    public function test_only_this_projects_active_workers_are_listed(): void
    {
        [$project, $worker] = $this->projectWithWorker('Assigned Worker');

        $deactivated = User::factory()->worker()->create(['name' => 'Deactivated Worker', 'deactivated_at' => now()]);
        ProjectShareholder::factory()->for($project)->create()->workers()->sync([$deactivated->id]);

        $otherProjectWorker = User::factory()->worker()->create(['name' => 'Other Project Worker']);
        ProjectShareholder::factory()->create()->workers()->sync([$otherProjectWorker->id]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Compose::class, ['project' => $project])
            ->assertSee('Assigned Worker')
            ->assertDontSee('Deactivated Worker')
            ->assertDontSee('Other Project Worker');
    }

    public function test_sending_requires_workers_subject_and_message(): void
    {
        [$project, $worker] = $this->projectWithWorker();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Compose::class, ['project' => $project])
            ->set('subject', '')
            ->set('message', '')
            ->call('send')
            ->assertHasErrors(['selectedWorkerIds', 'subject', 'message']);

        $this->assertDatabaseCount('worker_emails', 0);
    }

    public function test_sending_queues_one_job_per_recipient_with_snapshots(): void
    {
        Queue::fake();

        $project = Project::factory()->create();

        $plain = User::factory()->worker()->create(['email' => 'plain@example.com', 'email_receiver' => null]);
        $override = User::factory()->worker()->create(['email' => 'login@example.com', 'email_receiver' => 'inbox@example.com']);
        ProjectShareholder::factory()->for($project)->create()->workers()->sync([$plain->id, $override->id]);

        $link = ProjectResource::factory()->link()->for($project)->create(['title' => 'Guide', 'url' => 'https://example.com/guide']);
        $file = ProjectResource::factory()->file()->for($project)->create(['file_name' => 'form.pdf', 'file_path' => 'resources/1/form.pdf']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Compose::class, ['project' => $project])
            ->set('selectedWorkerIds', [$plain->id, $override->id])
            ->set('subject', 'Meeting notice')
            ->set('message', "Hello\nworkers")
            ->set('selectedLinkIds', [$link->id])
            ->set('selectedAttachmentIds', [$file->id])
            ->set('includeRosterXlsx', true)
            ->set('includeRosterPdf', false)
            ->set('sortColumn', 'shares_total')
            ->set('sortOrder', 'desc')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('subject', '')
            ->assertSet('message', '');

        $batch = WorkerEmail::sole();
        $this->assertSame('Meeting notice', $batch->subject);
        $this->assertSame([['title' => 'Guide', 'url' => 'https://example.com/guide']], $batch->links);
        $this->assertSame([['resource_id' => $file->id, 'filename' => 'form.pdf', 'path' => 'resources/1/form.pdf']], $batch->attachments);
        $this->assertTrue($batch->include_roster_xlsx);
        $this->assertFalse($batch->include_roster_pdf);
        $this->assertTrue($batch->roster_sort_desc);

        // The override address wins over the login email in the snapshot.
        $emails = $batch->recipients->pluck('email')->all();
        $this->assertEqualsCanonicalizing(['plain@example.com', 'inbox@example.com'], $emails);
        $this->assertTrue($batch->recipients->every(
            fn ($recipient): bool => $recipient->status === WorkerEmailRecipientStatus::Pending,
        ));

        Queue::assertPushed(SendWorkerEmail::class, 2);
    }

    public function test_workers_not_on_the_project_are_ignored(): void
    {
        Queue::fake();

        [$project, $worker] = $this->projectWithWorker();
        $outsider = User::factory()->worker()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Compose::class, ['project' => $project])
            ->set('selectedWorkerIds', [$outsider->id])
            ->set('subject', 'Subject')
            ->set('message', 'Message')
            ->call('send')
            ->assertHasErrors(['selectedWorkerIds']);

        $this->assertDatabaseCount('worker_emails', 0);
        Queue::assertNothingPushed();
    }
}
