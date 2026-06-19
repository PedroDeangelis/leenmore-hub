<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_from_the_root_to_the_login_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_logged_in_workers_are_sent_from_the_root_to_the_worker_portal(): void
    {
        $this->actingAs(User::factory()->worker()->create());

        $response = $this->get(route('home'));

        $response->assertRedirect(route('worker.dashboard'));
    }

    public function test_logged_in_admins_are_sent_from_the_root_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
