<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectStatus;
use App\Livewire\Projects\Show;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_a_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['title' => 'Viewable Campaign']);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Viewable Campaign');
    }

    public function test_office_users_can_view_a_project(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create(['title' => 'Office Campaign']);

        $this->actingAs($office)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Office Campaign');
    }

    public function test_workers_cannot_view_a_project(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->create();

        $this->actingAs($worker)
            ->get(route('projects.show', $project))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_admins_can_edit_the_title_inline(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['title' => 'Before']);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'title')
            ->assertSet('editing', 'title')
            ->set('title', 'After')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', null);

        $this->assertSame('After', $project->refresh()->title);
    }

    public function test_inline_title_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['title' => 'Keep me']);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'title')
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);

        $this->assertSame('Keep me', $project->refresh()->title);
    }

    public function test_admins_can_edit_dates_inline(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'dates')
            ->set('start_date', '2026-06-01')
            ->set('end_date', '2026-06-26')
            ->call('save')
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame('2026-06-01', $project->start_date->toDateString());
        $this->assertSame('2026-06-26', $project->end_date->toDateString());
    }

    public function test_inline_end_date_cannot_precede_start_date(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'dates')
            ->set('start_date', '2026-06-10')
            ->set('end_date', '2026-06-01')
            ->call('save')
            ->assertHasErrors(['end_date']);
    }

    public function test_admins_can_edit_the_esignon_id_inline(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'esignon')
            ->set('link_manage_id', '90')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('90', $project->refresh()->link_manage_id);
    }

    public function test_admins_can_edit_the_shares_inline(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['shares_issued' => 100, 'shares_target' => 200]);

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'shares')
            ->assertSet('editing', 'shares')
            ->set('shares_issued', 38428915)
            ->set('shares_target', 3124924)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', null);

        $project->refresh();
        $this->assertSame(38428915, $project->shares_issued);
        $this->assertSame(3124924, $project->shares_target);
    }

    public function test_shares_cannot_be_negative(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'shares')
            ->set('shares_issued', -5)
            ->call('save')
            ->assertHasErrors(['shares_issued']);
    }

    public function test_office_users_cannot_edit_a_field(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create(['title' => 'Untouched']);

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->call('edit', 'title')
            ->assertForbidden();

        $this->assertSame('Untouched', $project->refresh()->title);
    }

    public function test_office_users_cannot_save_a_field(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create(['title' => 'Untouched']);

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->set('editing', 'title')
            ->set('title', 'Changed By Office')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('Untouched', $project->refresh()->title);
    }

    public function test_admins_can_publish_a_draft_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->draft()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertSame(ProjectStatus::Publish, $project->fresh()->status);
    }

    public function test_office_users_cannot_publish_a_project(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->draft()->create();

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->call('publish')
            ->assertForbidden();

        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_admins_can_revert_a_published_project_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('revertToDraft')
            ->assertHasNoErrors();

        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_office_users_cannot_revert_a_project_to_draft(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->call('revertToDraft')
            ->assertForbidden();

        $this->assertSame(ProjectStatus::Publish, $project->fresh()->status);
    }

    public function test_admins_can_archive_a_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('archive')
            ->assertRedirect(route('projects.index'));

        $this->assertSame(ProjectStatus::Archived, $project->fresh()->status);
    }

    public function test_office_users_cannot_archive_a_project(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->call('archive')
            ->assertForbidden();

        $this->assertSame(ProjectStatus::Publish, $project->fresh()->status);
    }

    public function test_admins_can_delete_a_project_permanently(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['project' => $project])
            ->call('delete')
            ->assertRedirect(route('projects.index'));

        // The row stays in the database but is hidden from every normal query.
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'deleted']);
        $this->assertNull(Project::find($project->id));
        $this->assertSame(ProjectStatus::Deleted, Project::withDeleted()->find($project->id)->status);
    }

    public function test_office_users_cannot_delete_a_project(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->published()->create();

        $this->actingAs($office);

        Livewire::test(Show::class, ['project' => $project])
            ->call('delete')
            ->assertForbidden();

        $this->assertSame(ProjectStatus::Publish, $project->fresh()->status);
    }

    public function test_a_deleted_project_cannot_be_viewed(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->deleted()->create();

        $this->actingAs($admin)
            ->get(route('projects.show', $project->id))
            ->assertNotFound();
    }

    public function test_the_meeting_countdown_reflects_the_current_day(): void
    {
        Carbon::setTestNow('2026-06-16 09:00:00');

        $upcoming = Project::factory()->make(['end_date' => '2026-06-20'])->meetingCountdown();
        $this->assertSame(4, $upcoming->daysLeft);
        $this->assertTrue($upcoming->isUpcoming());

        $this->assertTrue(Project::factory()->make(['end_date' => '2026-06-16'])->meetingCountdown()->isToday());
        $this->assertTrue(Project::factory()->make(['end_date' => '2026-06-10'])->meetingCountdown()->hasPassed());
        $this->assertNull(Project::factory()->make(['end_date' => null])->meetingCountdown());

        Carbon::setTestNow();
    }

    public function test_a_passed_meeting_is_shown_as_passed_not_remaining(): void
    {
        Carbon::setTestNow('2026-06-16');

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['end_date' => '2026-06-10']);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('The meeting (06/10) has passed')
            ->assertDontSee('remaining');

        Carbon::setTestNow();
    }

    public function test_the_page_is_localised(): void
    {
        $project = Project::factory()->published()->create();

        $this->actingAs(User::factory()->admin()->create(['locale' => 'ko']))
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('진행 중')
            ->assertSee('판단 결과 현황')
            ->assertSee('주주');

        $this->actingAs(User::factory()->admin()->create(['locale' => 'en']))
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('In progress')
            ->assertSee('Judgment results')
            ->assertSee('Shareholders');
    }
}
