<?php

namespace Tests\Feature\EmailToWorker;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailToWorkerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_every_email_to_worker_page(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->get(route('email-to-worker.index'))->assertOk();
        $this->actingAs($admin)->get(route('email-to-worker.history'))->assertOk();
        $this->actingAs($admin)->get(route('email-to-worker.compose', $project))->assertOk();
    }

    public function test_office_users_are_forbidden(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create();
        $worker = User::factory()->worker()->create();

        // send-worker-emails is admin-only; office reaches the admin area but
        // fails the gate.
        $this->actingAs($office)->get(route('email-to-worker.index'))->assertForbidden();
        $this->actingAs($office)->get(route('email-to-worker.history'))->assertForbidden();
        $this->actingAs($office)->get(route('email-to-worker.compose', $project))->assertForbidden();
        $this->actingAs($office)->get(route('email-to-worker.roster', [$project, $worker]))->assertForbidden();
        $this->actingAs($office)->get(route('email-to-worker.roster.all', $project))->assertForbidden();
    }

    public function test_workers_are_redirected_to_their_own_home(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->create();

        $this->actingAs($worker)
            ->get(route('email-to-worker.index'))
            ->assertRedirect(route('worker.dashboard'));

        $this->actingAs($worker)
            ->get(route('email-to-worker.compose', $project))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('email-to-worker.index'))->assertRedirect(route('login'));
    }
}
