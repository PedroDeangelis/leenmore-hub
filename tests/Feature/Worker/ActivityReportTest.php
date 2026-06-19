<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\ActivityReport;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    private function assignedRow(Project $project, User $worker, array $person = []): ProjectShareholder
    {
        $shareholder = Shareholder::factory()->create($person);
        $row = ProjectShareholder::factory()->for($project)->for($shareholder)->create();
        $row->workers()->attach($worker);

        return $row;
    }

    public function test_a_worker_can_file_a_report_which_sets_the_shareholder_result(): void
    {
        Storage::fake('local');

        $worker = User::factory()->worker()->create(['name' => 'Field Worker']);
        $project = Project::factory()->published()->create();
        $result = ProjectResult::factory()->for($project)->create([
            'name' => '위임대기',
            'contact_required' => false,
            'attachment_required' => false,
        ]);
        $row = $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        Livewire::test(ActivityReport::class, ['project' => $project, 'projectShareholder' => $row])
            ->set('date', '2026-06-19')
            ->set('resultId', $result->id)
            ->set('contacts', [['010', '1234', '5678']])
            ->set('note', 'Visited, signed.')
            ->set('attachments', [UploadedFile::fake()->image('proof.jpg')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('worker.projects.show', $project));

        $submission = Submission::first();
        $this->assertNotNull($submission);
        $this->assertSame($row->id, $submission->project_shareholder_id);
        $this->assertSame($project->id, $submission->project_id);
        $this->assertSame($worker->id, $submission->user_id);
        $this->assertSame('Field Worker', $submission->user_name);
        $this->assertSame('위임대기', $submission->result);
        $this->assertSame('010-1234-5678', $submission->contact);
        $this->assertCount(1, $submission->files);
        Storage::disk('local')->assertExists($submission->files[0]);

        // The submission drives the shareholder's current 판단 result + note.
        $this->assertSame($result->id, $row->fresh()->result_id);
        $this->assertSame('Visited, signed.', $row->fresh()->last_note);
    }

    public function test_consent_file_is_stored_when_consent_is_ticked_and_date_is_locked_to_today(): void
    {
        Storage::fake('local');

        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        $result = ProjectResult::factory()->for($project)->create([
            'contact_required' => false,
            'attachment_required' => false,
        ]);
        $row = $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        Livewire::test(ActivityReport::class, ['project' => $project, 'projectShareholder' => $row])
            // A tampered date must be ignored — the server pins it to today.
            ->set('date', '2000-01-01')
            ->set('resultId', $result->id)
            ->set('privacyConsent', true)
            ->set('consentFiles', [UploadedFile::fake()->create('consent.pdf', 80, 'application/pdf')])
            ->call('save')
            ->assertHasNoErrors();

        $submission = Submission::first();
        $this->assertTrue($submission->privacy_consent);
        $this->assertCount(1, $submission->privacy_consent_files);
        Storage::disk('local')->assertExists($submission->privacy_consent_files[0]);
        $this->assertTrue($submission->date->isToday());
    }

    public function test_previous_reports_render_as_cards_with_judgment_author_contact_and_note(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        ProjectResult::factory()->for($project)->create(['name' => '거부']);
        $row = $this->assignedRow($project, $worker);

        Submission::create([
            'project_id' => $project->id,
            'project_shareholder_id' => $row->id,
            'user_id' => $worker->id,
            'user_name' => 'Prior Worker',
            'date' => now()->subDay(),
            'result' => '거부',
            'contact' => '010-1111-2222',
            'note' => 'Refused at door.',
        ]);

        $this->actingAs($worker);

        Livewire::test(ActivityReport::class, ['project' => $project, 'projectShareholder' => $row])
            ->assertSee('거부')
            ->assertSee('Prior Worker')
            ->assertSee('010-1111-2222')
            ->assertSee('Refused at door.');
    }

    public function test_judgment_is_required(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        ProjectResult::factory()->for($project)->create();
        $row = $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        Livewire::test(ActivityReport::class, ['project' => $project, 'projectShareholder' => $row])
            ->set('resultId', null)
            ->call('save')
            ->assertHasErrors(['resultId' => 'required']);

        $this->assertSame(0, Submission::count());
    }

    public function test_contact_and_attachment_are_required_when_the_result_demands_them(): void
    {
        Storage::fake('local');

        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        $result = ProjectResult::factory()->for($project)->create([
            'contact_required' => true,
            'attachment_required' => true,
        ]);
        $row = $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        // No contact, no attachment → both conditional rules fire.
        Livewire::test(ActivityReport::class, ['project' => $project, 'projectShareholder' => $row])
            ->set('resultId', $result->id)
            ->set('contacts', [['', '', '']])
            ->set('attachments', [])
            ->call('save')
            ->assertHasErrors(['contacts']);

        $this->assertSame(0, Submission::count());
    }

    public function test_contact_segments_must_contain_only_digits(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        $result = ProjectResult::factory()->for($project)->create([
            'contact_required' => false,
            'attachment_required' => false,
        ]);
        $row = $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        Livewire::test(ActivityReport::class, ['project' => $project, 'projectShareholder' => $row])
            ->set('resultId', $result->id)
            ->set('contacts', [['010', 'abcd', '5678']])
            ->call('save')
            ->assertHasErrors('contacts.0.1');

        $this->assertSame(0, Submission::count());
    }

    public function test_a_worker_cannot_report_on_a_shareholder_they_are_not_assigned_to(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        // Assignment belongs to nobody (this worker is not attached).
        $row = ProjectShareholder::factory()->for($project)
            ->for(Shareholder::factory()->create())
            ->create();

        $this->actingAs($worker)
            ->get(route('worker.projects.shareholders.report', [$project, $row]))
            ->assertNotFound();
    }
}
