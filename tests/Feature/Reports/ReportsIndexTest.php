<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Index;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a project with one activity report against it.
     */
    private function projectWithReport(string $title): Project
    {
        $project = Project::factory()->published()->create(['title' => $title]);
        $assignment = ProjectShareholder::factory()->for($project)->create();
        Submission::factory()->forAssignment($assignment)->create();

        return $project;
    }

    public function test_only_projects_that_have_reports_are_listed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->projectWithReport('Has Reports Campaign');
        Project::factory()->published()->create(['title' => 'No Reports Campaign']);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Has Reports Campaign')
            ->assertDontSee('No Reports Campaign');
    }

    public function test_office_users_can_view_the_reports_archive(): void
    {
        $office = User::factory()->office()->create();
        $this->projectWithReport('Office Visible Reports');

        $this->actingAs($office)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Office Visible Reports');
    }

    public function test_workers_cannot_view_the_reports_archive(): void
    {
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('reports.index'))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_the_archive_can_be_searched_by_project_title(): void
    {
        $admin = User::factory()->admin()->create();
        $this->projectWithReport('Alpha Reports');
        $this->projectWithReport('Beta Reports');

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha Reports')
            ->assertDontSee('Beta Reports');
    }
}
