<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectStatus;
use App\Livewire\Projects\Create;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_create_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('projects.create'))
            ->assertOk();
    }

    public function test_office_users_cannot_open_the_create_page(): void
    {
        $office = User::factory()->office()->create();

        $this->actingAs($office)
            ->get(route('projects.create'))
            ->assertForbidden();
    }

    public function test_a_project_can_be_created(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('title', 'New Campaign')
            ->set('status', ProjectStatus::Publish->value)
            ->set('start_date', '2026-06-01')
            ->set('end_date', '2026-07-01')
            ->set('shares_issued', 5000)
            ->set('shares_target', 4000)
            ->set('message', 'Hello workers')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $project = Project::firstWhere('title', 'New Campaign');

        $this->assertNotNull($project);
        $this->assertSame(ProjectStatus::Publish, $project->status);
        $this->assertSame(5000, $project->shares_issued);
        $this->assertSame('Hello workers', $project->message);
    }

    public function test_archived_or_deleted_status_cannot_be_assigned_on_create(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('title', 'Sneaky Status')
            ->set('status', ProjectStatus::Archived->value)
            ->call('save')
            ->assertHasErrors(['status']);

        Livewire::test(Create::class)
            ->set('title', 'Sneaky Status')
            ->set('status', ProjectStatus::Deleted->value)
            ->call('save')
            ->assertHasErrors(['status']);
    }

    public function test_the_title_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }

    public function test_the_end_date_cannot_precede_the_start_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('title', 'Bad Dates')
            ->set('start_date', '2026-06-10')
            ->set('end_date', '2026-06-01')
            ->call('save')
            ->assertHasErrors(['end_date']);
    }

    public function test_a_blank_message_is_stored_as_null(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('title', 'No Message')
            ->set('message', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Project::firstWhere('title', 'No Message')->message);
    }
}
