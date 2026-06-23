<?php

namespace Tests\Feature\Worker;

use App\Livewire\Worker\ResourceShow;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\ProjectShareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceShowTest extends TestCase
{
    use RefreshDatabase;

    private function assign(Project $project, User $worker): void
    {
        ProjectShareholder::factory()->for($project)->create()
            ->workers()->attach($worker);
    }

    public function test_an_assigned_worker_sees_a_projects_resources(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        $this->assign($project, $worker);
        ProjectResource::factory()->for($project)->link()->create(['title' => 'Activity site']);

        $this->actingAs($worker);

        Livewire::test(ResourceShow::class, ['project' => $project])
            ->assertOk()
            ->assertSee('Activity site');
    }

    public function test_an_unassigned_worker_gets_a_404(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        ProjectResource::factory()->for($project)->create();

        $this->actingAs($worker)
            ->get(route('worker.resources.show', $project))
            ->assertNotFound();
    }

    public function test_file_resources_are_served_to_the_owner_admin_but_not_strangers(): void
    {
        Storage::fake('local');

        $worker = User::factory()->worker()->create();
        $project = Project::factory()->published()->create();
        $this->assign($project, $worker);

        $path = 'resources/'.$project->id.'/file.pdf';
        Storage::disk('local')->put($path, 'pdf-bytes');
        $resource = ProjectResource::factory()->for($project)->file()->create([
            'file_path' => $path,
            'file_name' => 'file.pdf',
        ]);

        // Assigned worker may download.
        $this->actingAs($worker)->get(route('resources.file', $resource))->assertOk();

        // Admin may download.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('resources.file', $resource))->assertOk();

        // A worker not assigned to the project may not.
        $this->actingAs(User::factory()->worker()->create())
            ->get(route('resources.file', $resource))->assertForbidden();
    }
}
