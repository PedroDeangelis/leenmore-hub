<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_have_admin_only_abilities(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['manage-users', 'manage-projects', 'manage-settings', 'send-worker-emails', 'bulk-delete'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), "admin should be allowed: {$ability}");
        }
    }

    public function test_office_users_lack_admin_only_abilities_but_keep_shared_ones(): void
    {
        $office = User::factory()->office()->create();

        foreach (['manage-users', 'manage-projects', 'manage-settings', 'send-worker-emails', 'bulk-delete'] as $ability) {
            $this->assertFalse(Gate::forUser($office)->allows($ability), "office should be denied: {$ability}");
        }

        foreach (['access-admin-area', 'view-projects', 'edit-submissions', 'manage-resources', 'export-data'] as $ability) {
            $this->assertTrue(Gate::forUser($office)->allows($ability), "office should be allowed: {$ability}");
        }
    }

    public function test_workers_have_no_admin_area_abilities(): void
    {
        $worker = User::factory()->worker()->create();

        foreach (['access-admin-area', 'manage-users', 'view-projects', 'export-data'] as $ability) {
            $this->assertFalse(Gate::forUser($worker)->allows($ability), "worker should be denied: {$ability}");
        }
    }

    public function test_registration_routes_are_disabled(): void
    {
        $this->assertFalse(Route::has('register'));
    }
}
