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
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_logged_in_users_are_sent_from_the_root_to_the_api_portal(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('home'))->assertRedirect(route('api.tokens'));
    }

    public function test_viewers_also_land_on_the_api_portal(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        $this->get(route('home'))->assertRedirect(route('api.tokens'));
    }
}
