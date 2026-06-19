<?php

namespace Tests\Feature\Projects;

use App\Enums\ResultColor;
use App\Livewire\Projects\Create;
use App\Livewire\Projects\Show;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_projects_results_are_returned_in_sort_order(): void
    {
        $project = Project::factory()->create();
        ProjectResult::factory()->for($project)->create(['name' => 'Second', 'sort_order' => 2]);
        ProjectResult::factory()->for($project)->create(['name' => 'First', 'sort_order' => 1]);

        $this->assertSame(['First', 'Second'], $project->results()->pluck('name')->all());
    }

    public function test_the_color_casts_to_the_result_color_enum(): void
    {
        $result = ProjectResult::factory()->create(['color' => 'green']);

        $this->assertSame(ResultColor::Green, $result->refresh()->color);
    }

    public function test_creating_a_project_seeds_the_default_result_set(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('title', 'Seeded Campaign')
            ->call('save')
            ->assertHasNoErrors();

        $project = Project::firstWhere('title', 'Seeded Campaign');

        $this->assertSame(count(ProjectResult::defaultSet()), $project->results()->count());
        $this->assertDatabaseHas('project_results', [
            'project_id' => $project->id,
            'name' => '거부',
            'color' => 'red',
        ]);
    }

    public function test_the_show_page_renders_the_projects_results(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $project->results()->createMany(ProjectResult::defaultSet());

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('거부')
            ->assertSee('위임(대면_서명)');
    }

    public function test_admins_can_reorder_results(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        ProjectResult::factory()->for($project)->create(['name' => 'A', 'sort_order' => 0]);
        ProjectResult::factory()->for($project)->create(['name' => 'B', 'sort_order' => 1]);
        $c = ProjectResult::factory()->for($project)->create(['name' => 'C', 'sort_order' => 2]);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('reorder', $c->id, 0);

        $this->assertSame(['C', 'A', 'B'], $project->results()->pluck('name')->all());
    }

    public function test_admins_can_add_edit_and_delete_results_in_the_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $keep = ProjectResult::factory()->for($project)->create(['name' => 'Keep', 'color' => 'gray', 'sort_order' => 0]);
        $drop = ProjectResult::factory()->for($project)->create(['name' => 'Drop', 'sort_order' => 1]);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageResults')
            ->assertSet('managingResults', true)
            ->set('rows.0.name', 'Renamed')
            ->set('rows.0.color', 'red')
            ->call('removeResultRow', 1)
            ->call('addResultRow')
            ->set('rows.1.name', 'Brand New')
            ->set('rows.1.color', 'blue')
            ->call('saveResults')
            ->assertHasNoErrors()
            ->assertSet('managingResults', false);

        $this->assertSame(['Renamed', 'Brand New'], $project->results()->pluck('name')->all());
        $this->assertSame(ResultColor::Red, $keep->refresh()->color);
        $this->assertDatabaseMissing('project_results', ['id' => $drop->id]);
    }

    public function test_a_result_name_is_required_when_saving(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        ProjectResult::factory()->for($project)->create(['name' => 'X']);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageResults')
            ->set('rows.0.name', '')
            ->call('saveResults')
            ->assertHasErrors(['rows.0.name']);
    }

    public function test_office_users_cannot_manage_or_reorder_results(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create();
        $result = ProjectResult::factory()->for($project)->create();

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->call('manageResults')
            ->assertForbidden();

        Livewire::test(Show::class, ['project' => $project])
            ->call('reorder', $result->id, 0)
            ->assertForbidden();
    }
}
