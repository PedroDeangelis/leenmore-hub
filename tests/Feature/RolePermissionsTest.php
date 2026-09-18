<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The whole permission map is three abilities; this pins each one to the roles
 * that should hold it, so widening access has to be a deliberate edit here.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_have_every_ability(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['use-api-portal', 'manage-api-tokens', 'manage-users'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), "admin should be allowed: {$ability}");
        }
    }

    public function test_viewers_may_use_the_portal_but_not_administer_it(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue(Gate::forUser($viewer)->allows('use-api-portal'));

        foreach (['manage-api-tokens', 'manage-users'] as $ability) {
            $this->assertFalse(Gate::forUser($viewer)->allows($ability), "viewer should be denied: {$ability}");
        }
    }

    public function test_registration_and_password_reset_routes_are_disabled(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('password.request'));
    }
}
