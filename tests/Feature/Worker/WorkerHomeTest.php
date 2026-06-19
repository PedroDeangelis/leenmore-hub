<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\Home;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkerHomeTest extends TestCase
{
    use RefreshDatabase;

    private function assign(Project $project, User $worker, int $count = 1): void
    {
        ProjectShareholder::factory()->count($count)->for($project)->create()
            ->each(fn (ProjectShareholder $a) => $a->workers()->attach($worker));
    }

    public function test_workers_see_published_projects_they_are_assigned_to_with_a_count(): void
    {
        $worker = User::factory()->worker()->create();
        $mine = Project::factory()->published()->create(['title' => 'My Campaign']);
        $this->assign($mine, $worker, 3);

        $this->actingAs($worker);

        Livewire::test(Home::class)
            ->assertSee('My Campaign')
            ->assertSee('3 shareholders'); // Livewire::test renders in the app default locale (en)
    }

    public function test_workers_do_not_see_projects_they_are_not_assigned_to(): void
    {
        $worker = User::factory()->worker()->create();
        Project::factory()->published()->create(['title' => 'Someone Elses']);

        $this->actingAs($worker);

        Livewire::test(Home::class)
            ->assertDontSee('Someone Elses')
            ->assertSee('No projects assigned yet.');
    }

    public function test_workers_only_see_published_projects(): void
    {
        $worker = User::factory()->worker()->create();
        $draft = Project::factory()->draft()->create(['title' => 'Draft Campaign']);
        $archived = Project::factory()->archived()->create(['title' => 'Archived Campaign']);
        $this->assign($draft, $worker);
        $this->assign($archived, $worker);

        $this->actingAs($worker);

        Livewire::test(Home::class)
            ->assertDontSee('Draft Campaign')
            ->assertDontSee('Archived Campaign');
    }

    public function test_non_workers_cannot_reach_the_worker_home(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('worker.dashboard'))
            ->assertRedirect(route('dashboard'));
    }
}
