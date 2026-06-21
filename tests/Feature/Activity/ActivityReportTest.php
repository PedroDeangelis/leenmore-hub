<?php

namespace Tests\Feature\Activity;

use App\Livewire\Activity\Report;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Project, 1: ProjectShareholder, 2: ProjectResult, 3: User} */
    private function scenario(): array
    {
        $project = Project::factory()->published()->create();
        $result = ProjectResult::factory()->for($project)->create([
            'name' => '위임(대면_서명)',
            'contact_required' => false,
            'attachment_required' => false,
        ]);
        $assignment = ProjectShareholder::factory()
            ->for($project)
            ->for(Shareholder::factory()->create())
            ->create();
        $worker = User::factory()->worker()->create(['name' => 'Kim Worker']);

        return [$project, $assignment, $result, $worker];
    }

    public function test_admin_creates_a_report_attributed_to_the_worker_and_to_themselves(): void
    {
        [$project, $assignment, $result, $worker] = $this->scenario();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->set('selectedWorkerId', $worker->id)
            ->set('resultId', $result->id)
            ->set('date', now()->toDateString())
            ->set('note', 'Phoned in by the shareholder.')
            ->call('save')
            ->assertHasNoErrors();

        $submission = Submission::firstOrFail();
        $this->assertSame($worker->id, $submission->user_id);
        $this->assertSame('Kim Worker', $submission->user_name);
        $this->assertSame($admin->id, $submission->created_by_user_id);
        $this->assertSame('위임(대면_서명)', $submission->result);
        // The new report drives the shareholder's current 판단.
        $this->assertSame($result->id, $assignment->fresh()->result_id);
    }

    public function test_a_worker_must_be_selected(): void
    {
        [$project, $assignment, $result] = $this->scenario();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->set('resultId', $result->id)
            ->set('date', now()->toDateString())
            ->call('save')
            ->assertHasErrors('selectedWorkerId');

        $this->assertSame(0, Submission::count());
    }

    public function test_the_date_cannot_be_in_the_future(): void
    {
        [$project, $assignment, $result, $worker] = $this->scenario();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->set('selectedWorkerId', $worker->id)
            ->set('resultId', $result->id)
            ->set('date', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors('date');
    }

    public function test_admin_can_edit_an_existing_report(): void
    {
        [$project, $assignment, $result, $worker] = $this->scenario();
        $other = ProjectResult::factory()->for($project)->create(['name' => '단순부재']);
        $admin = User::factory()->admin()->create();
        $submission = Submission::factory()->forAssignment($assignment)->create([
            'user_id' => $worker->id,
            'user_name' => 'Kim Worker',
            'result' => '단순부재',
            'note' => 'old note',
        ]);

        $this->actingAs($admin);

        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->call('editReport', $submission->id)
            ->assertSet('editingId', $submission->id)
            ->assertSet('resultId', $other->id)
            ->set('resultId', $result->id)
            ->set('note', 'updated note')
            ->call('save')
            ->assertHasNoErrors();

        $submission->refresh();
        $this->assertSame('위임(대면_서명)', $submission->result);
        $this->assertSame('updated note', $submission->note);
        $this->assertSame(1, Submission::count());
        $this->assertSame($result->id, $assignment->fresh()->result_id);
    }

    public function test_an_existing_attachment_satisfies_the_requirement_when_editing(): void
    {
        [$project, $assignment, $result, $worker] = $this->scenario();
        $needsFile = ProjectResult::factory()->for($project)->create([
            'name' => '첨부필요',
            'attachment_required' => true,
        ]);
        $submission = Submission::factory()->forAssignment($assignment)->create([
            'user_id' => $worker->id,
            'result' => '위임(대면_서명)',
            'files' => ['submissions/'.$assignment->id.'/existing.jpg'],
        ]);

        $this->actingAs(User::factory()->admin()->create());

        // Switching to an attachment-required 판단 without uploading a new file is
        // fine because the report already has one.
        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->call('editReport', $submission->id)
            ->set('resultId', $needsFile->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('첨부필요', $submission->fresh()->result);
    }

    public function test_removing_the_only_attachment_re_triggers_the_requirement(): void
    {
        [$project, $assignment, $result, $worker] = $this->scenario();
        $needsFile = ProjectResult::factory()->for($project)->create([
            'name' => '첨부필요',
            'attachment_required' => true,
        ]);
        $submission = Submission::factory()->forAssignment($assignment)->create([
            'user_id' => $worker->id,
            'result' => '위임(대면_서명)',
            'files' => ['submissions/'.$assignment->id.'/existing.jpg'],
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->call('editReport', $submission->id)
            ->call('removeExistingAttachment', 0)
            ->set('resultId', $needsFile->id)
            ->call('save')
            ->assertHasErrors('attachments');
    }

    public function test_admin_can_delete_a_report_and_the_current_result_resyncs(): void
    {
        [$project, $assignment, $result, $worker] = $this->scenario();
        $admin = User::factory()->admin()->create();
        $submission = Submission::factory()->forAssignment($assignment)->create([
            'user_id' => $worker->id,
            'result' => '위임(대면_서명)',
        ]);
        $assignment->update(['result_id' => $result->id]);

        $this->actingAs($admin);

        Livewire::test(Report::class, ['project' => $project, 'projectShareholder' => $assignment])
            ->call('deleteReport', $submission->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted($submission);
        // No reports remain → the shareholder's current 판단 is cleared.
        $this->assertNull($assignment->fresh()->result_id);
    }

    public function test_workers_cannot_open_the_report_page(): void
    {
        [$project, $assignment] = $this->scenario();
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('activity.report', [$project, $assignment]))
            ->assertRedirect(route('worker.dashboard'));
    }
}
