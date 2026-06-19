<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectStatus;
use App\Livewire\Projects\Index;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_the_projects_list(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['title' => 'Listed Campaign']);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Listed Campaign');
    }

    public function test_office_users_can_view_the_projects_list(): void
    {
        $office = User::factory()->office()->create();
        Project::factory()->create(['title' => 'Office Visible']);

        $this->actingAs($office)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Office Visible');
    }

    public function test_workers_cannot_view_the_projects_list(): void
    {
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('projects.index'))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_the_list_can_be_searched_by_title(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['title' => 'Alpha Campaign']);
        Project::factory()->create(['title' => 'Beta Campaign']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha Campaign')
            ->assertDontSee('Beta Campaign');
    }

    public function test_archived_filter_shows_only_archived_projects(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->published()->create(['title' => 'Active One']);
        Project::factory()->archived()->create(['title' => 'Archived One']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('status', 'archived')
            ->assertSee('Archived One')
            ->assertDontSee('Active One');
    }

    public function test_the_active_list_hides_archived_projects(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->published()->create(['title' => 'Active One']);
        Project::factory()->archived()->create(['title' => 'Archived One']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->assertSee('Active One')
            ->assertDontSee('Archived One');
    }

    public function test_the_archived_badge_is_localised(): void
    {
        Project::factory()->archived()->create(['title' => 'Localised Archive']);

        $this->actingAs(User::factory()->admin()->create(['locale' => 'ko']))
            ->get(route('projects.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('보관됨');

        $this->actingAs(User::factory()->admin()->create(['locale' => 'en']))
            ->get(route('projects.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Archived');
    }

    public function test_admins_can_restore_an_archived_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->archived()->create();

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->call('restore', $project->id);

        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_office_users_cannot_restore_a_project(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->archived()->create();

        $this->actingAs($office);

        Livewire::test(Index::class)
            ->call('restore', $project->id)
            ->assertForbidden();

        $this->assertSame(ProjectStatus::Archived, $project->fresh()->status);
    }

    public function test_deleted_projects_never_appear_in_any_filter(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->deleted()->create(['title' => 'Deleted One']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->assertDontSee('Deleted One')
            ->set('status', 'archived')
            ->assertDontSee('Deleted One')
            ->set('status', 'draft')
            ->assertDontSee('Deleted One')
            ->set('status', 'publish')
            ->assertDontSee('Deleted One');
    }

    public function test_a_deleted_project_cannot_be_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->deleted()->create();

        $this->actingAs($admin);

        // The lookup is scoped to archived projects, so a deleted id is never
        // found — restore can't bring it back (404 over HTTP).
        try {
            Livewire::test(Index::class)->call('restore', $project->id);
            $this->fail('A deleted project should not be restorable.');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertSame(ProjectStatus::Deleted, Project::withDeleted()->find($project->id)->status);
    }
}
