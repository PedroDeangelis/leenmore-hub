<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\ProjectShow;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkerProjectShowTest extends TestCase
{
    use RefreshDatabase;

    private function assignedRow(Project $project, User $worker, array $person = []): ProjectShareholder
    {
        $shareholder = Shareholder::factory()->create($person);
        $row = ProjectShareholder::factory()->for($project)->for($shareholder)->create();
        $row->workers()->attach($worker);

        return $row;
    }

    public function test_a_worker_sees_only_their_assigned_shareholders(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();

        $this->assignedRow($project, $worker, ['name' => 'Mine Shareholder']);
        // Assigned to someone else — must not appear.
        ProjectShareholder::factory()->for($project)
            ->for(Shareholder::factory()->create(['name' => 'Other Shareholder']))
            ->create();

        $this->actingAs($worker);

        Livewire::test(ProjectShow::class, ['project' => $project])
            ->assertSee('Mine Shareholder')
            ->assertDontSee('Other Shareholder');
    }

    public function test_a_worker_can_search_their_shareholders(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        $this->assignedRow($project, $worker, ['name' => 'Alice Roster']);
        $this->assignedRow($project, $worker, ['name' => 'Bob Roster']);

        $this->actingAs($worker);

        Livewire::test(ProjectShow::class, ['project' => $project])
            ->set('search', 'Alice')
            ->assertSee('Alice Roster')
            ->assertDontSee('Bob Roster');
    }

    public function test_the_project_message_is_shown_to_workers(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create(['message' => '오늘은 강남구 위주로 방문해 주세요']);
        $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        Livewire::test(ProjectShow::class, ['project' => $project])
            ->assertSee('오늘은 강남구 위주로 방문해 주세요');
    }

    public function test_no_message_banner_when_the_project_has_no_message(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create(['message' => null]);
        $this->assignedRow($project, $worker);

        $this->actingAs($worker);

        // The megaphone banner block is skipped entirely.
        Livewire::test(ProjectShow::class, ['project' => $project])
            ->assertDontSeeHtml('font-bold leading-relaxed text-primary');
    }

    public function test_a_worker_cannot_open_a_project_they_are_not_assigned_to(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        // No assignment for this worker.

        $this->actingAs($worker)
            ->get(route('worker.projects.show', $project))
            ->assertNotFound();
    }

    public function test_a_worker_cannot_open_an_unpublished_project(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->draft()->create();
        $this->assignedRow($project, $worker);

        $this->actingAs($worker)
            ->get(route('worker.projects.show', $project))
            ->assertNotFound();
    }
}
