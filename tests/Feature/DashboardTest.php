<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admins_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_office_users_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->office()->create());

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_workers_are_redirected_from_the_dashboard_to_the_worker_portal(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('worker.dashboard'));
    }

    public function test_workers_can_visit_the_worker_portal(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $response = $this->get(route('worker.dashboard'));
        $response->assertOk();
    }

    public function test_admins_are_redirected_from_the_worker_portal_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('worker.dashboard'));
        $response->assertRedirect(route('dashboard'));
    }
}
