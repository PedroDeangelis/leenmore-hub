<?php

namespace Tests\Feature\Activity;

use App\Livewire\Activity\Roster;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityRosterTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(Project $project, string $name): ProjectShareholder
    {
        return ProjectShareholder::factory()
            ->for($project)
            ->for(Shareholder::factory()->create(['name' => $name]))
            ->create();
    }

    public function test_admins_can_view_the_roster(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $this->assignment($project, 'Roster Person');

        $this->actingAs($admin)
            ->get(route('activity.project', $project))
            ->assertOk()
            ->assertSee('Roster Person');
    }

    public function test_the_reports_filter_splits_shareholders_with_and_without_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $withReport = $this->assignment($project, 'HasReportPerson');
        $this->assignment($project, 'NoReportPerson');
        Submission::factory()->forAssignment($withReport)->create();

        $this->actingAs($admin);

        Livewire::test(Roster::class, ['project' => $project])
            ->assertSee('HasReportPerson')
            ->assertSee('NoReportPerson')
            ->set('reports', 'has')
            ->assertSee('HasReportPerson')
            ->assertDontSee('NoReportPerson')
            ->set('reports', 'none')
            ->assertSee('NoReportPerson')
            ->assertDontSee('HasReportPerson');
    }

    public function test_the_roster_can_be_searched_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();
        $this->assignment($project, 'Alpha Holder');
        $this->assignment($project, 'Beta Holder');

        $this->actingAs($admin);

        Livewire::test(Roster::class, ['project' => $project])
            ->set('search', 'Alpha')
            ->assertSee('Alpha Holder')
            ->assertDontSee('Beta Holder');
    }

    public function test_workers_cannot_view_the_roster(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($worker)
            ->get(route('activity.project', $project))
            ->assertRedirect(route('worker.dashboard'));
    }
}
