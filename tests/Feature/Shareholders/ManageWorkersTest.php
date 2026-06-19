<?php

namespace Tests\Feature\Shareholders;

use App\Livewire\Projects\Show;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageWorkersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_manage_workers_modal(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();
        $worker = User::factory()->worker()->create(['name' => 'Jin Park']);
        $assignment->workers()->attach($worker);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $assignment->id)
            ->assertSet('managingWorkers', true)
            ->assertSet('managingWorkersFor', $assignment->id)
            ->assertSee('Jin Park'); // current worker chip
    }

    public function test_a_worker_can_be_added_and_removed(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();
        $worker = User::factory()->worker()->create(['name' => 'Soo Min']);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $assignment->id)
            ->call('addWorker', $worker->id);

        $this->assertTrue($assignment->workers()->whereKey($worker->id)->exists());

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $assignment->id)
            ->call('removeWorker', $worker->id);

        $this->assertFalse($assignment->workers()->whereKey($worker->id)->exists());
    }

    public function test_only_worker_users_can_be_added(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();
        $office = User::factory()->office()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $assignment->id)
            ->call('addWorker', $office->id);
    }

    public function test_the_add_picker_searches_workers_and_hides_already_assigned(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();

        $alice = User::factory()->worker()->create(['name' => 'Alice Worker']);
        User::factory()->worker()->create(['name' => 'Bob Worker']);
        $assignment->workers()->attach($alice);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $assignment->id)
            ->set('workerSearch', 'Bob')
            ->assertSee('Bob Worker')      // matches the search
            ->set('workerSearch', 'Alice')
            ->assertDontSee('Bob Worker'); // Alice is already assigned, so the picker is empty
    }

    public function test_office_users_can_manage_workers(): void
    {
        $this->actingAs(User::factory()->office()->create());
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();
        $worker = User::factory()->worker()->create();

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $assignment->id)
            ->call('addWorker', $worker->id);

        $this->assertTrue($assignment->workers()->whereKey($worker->id)->exists());
    }

    public function test_cannot_manage_workers_for_another_projects_assignment(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        $other = ProjectShareholder::factory()->create(); // belongs to a different project

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageWorkers', $other->id);
    }
}
