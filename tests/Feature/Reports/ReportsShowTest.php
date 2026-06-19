<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Show;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsShowTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(Project $project, array $person = []): ProjectShareholder
    {
        $shareholder = Shareholder::factory()->create($person);

        return ProjectShareholder::factory()
            ->for($project)
            ->for($shareholder)
            ->create(['shares' => 100, 'shares_total' => 100]);
    }

    public function test_admins_see_a_projects_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $assignment = $this->assignment($project, ['name' => '홍길동', 'sex' => 'M']);
        Submission::factory()->forAssignment($assignment)->create([
            'user_name' => '김활동',
            'result' => '위임(대면_서명)',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.show', $project))
            ->assertOk()
            ->assertSee('홍길동')
            ->assertSee('김활동')
            ->assertSee('위임(대면_서명)');
    }

    public function test_reports_can_be_filtered_by_result(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $accept = $this->assignment($project, ['name' => 'AcceptPerson']);
        $refuse = $this->assignment($project, ['name' => 'RefusePerson']);
        Submission::factory()->forAssignment($accept)->create(['result' => '위임(대면_서명)']);
        Submission::factory()->forAssignment($refuse)->create(['result' => '거부']);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->set('result', '거부')
            ->assertSee('RefusePerson')
            ->assertDontSee('AcceptPerson');
    }

    public function test_reports_can_be_filtered_by_activist(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $a = $this->assignment($project, ['name' => 'PersonA']);
        $b = $this->assignment($project, ['name' => 'PersonB']);
        Submission::factory()->forAssignment($a)->create(['user_name' => '활동가갑']);
        Submission::factory()->forAssignment($b)->create(['user_name' => '활동가을']);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->set('worker', '활동가갑')
            ->assertSee('PersonA')
            ->assertDontSee('PersonB');
    }

    public function test_latest_only_collapses_to_the_newest_report_per_shareholder(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $assignment = $this->assignment($project, ['name' => 'RepeatPerson']);

        // Older then newer report for the same shareholder.
        Submission::factory()->forAssignment($assignment)->create(['result' => '단순부재', 'date' => now()->subDays(2)]);
        Submission::factory()->forAssignment($assignment)->create(['result' => '위임(대면_서명)', 'date' => now()]);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->assertSee('단순부재')
            ->set('latestOnly', true)
            ->assertSee('위임(대면_서명)')
            ->assertDontSee('단순부재');
    }

    public function test_a_report_row_expands_to_reveal_its_note(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $assignment = $this->assignment($project, ['name' => 'NotePerson']);
        $submission = Submission::factory()->forAssignment($assignment)->create([
            'note' => '문 앞에 안내장 부착함',
        ]);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->assertDontSee('문 앞에 안내장 부착함')
            ->call('toggle', $submission->id)
            ->assertSee('문 앞에 안내장 부착함');
    }

    public function test_sorting_toggles_column_and_direction(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->assertSet('sort', 'date')
            ->call('sortBy', 'shares')
            ->assertSet('sort', 'shares')
            ->assertSet('direction', 'desc')
            ->call('sortBy', 'shares')
            ->assertSet('direction', 'asc');
    }

    public function test_workers_cannot_view_a_projects_reports(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($worker)
            ->get(route('reports.show', $project))
            ->assertRedirect(route('worker.dashboard'));
    }
}
