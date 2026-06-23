<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\ResourceIndex;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\ProjectShareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceIndexTest extends TestCase
{
    use RefreshDatabase;

    private function assign(Project $project, User $worker): void
    {
        ProjectShareholder::factory()->for($project)->create()
            ->workers()->attach($worker);
    }

    public function test_workers_see_only_assigned_published_projects_that_have_resources(): void
    {
        $worker = User::factory()->worker()->create();

        $withResources = Project::factory()->published()->create(['title' => 'Has Resources']);
        $this->assign($withResources, $worker);
        ProjectResource::factory()->for($withResources)->create();

        $noResources = Project::factory()->published()->create(['title' => 'No Resources']);
        $this->assign($noResources, $worker);

        $notMine = Project::factory()->published()->create(['title' => 'Not Mine']);
        ProjectResource::factory()->for($notMine)->create();

        $this->actingAs($worker);

        Livewire::test(ResourceIndex::class)
            ->assertSee('Has Resources')
            ->assertDontSee('No Resources')
            ->assertDontSee('Not Mine');
    }

    public function test_non_workers_cannot_reach_the_worker_resources(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('worker.resources.index'))
            ->assertRedirect(route('dashboard'));
    }
}
