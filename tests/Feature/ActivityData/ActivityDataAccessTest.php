<?php

namespace Tests\Feature\ActivityData;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityDataAccessTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithReport(): Project
    {
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();
        Submission::factory()->forAssignment($assignment)->create();

        return $project;
    }

    public function test_admins_and_office_can_view_every_activity_data_page(): void
    {
        $project = $this->projectWithReport();

        foreach ([User::factory()->admin()->create(), User::factory()->office()->create()] as $user) {
            $this->actingAs($user)->get(route('activity-data.index'))->assertOk();
            $this->actingAs($user)->get(route('activity-data.show', $project))->assertOk();
            $this->actingAs($user)->get(route('activity-data.export', $project))->assertOk();
        }
    }

    public function test_workers_are_redirected_to_their_own_home(): void
    {
        $project = $this->projectWithReport();
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('activity-data.index'))
            ->assertRedirect(route('worker.dashboard'));

        $this->actingAs($worker)
            ->get(route('activity-data.show', $project))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('activity-data.index'))->assertRedirect(route('login'));
    }
}
