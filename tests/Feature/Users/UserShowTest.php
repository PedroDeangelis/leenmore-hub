<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Livewire\Users\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_a_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Profiled Person']);

        $this->actingAs($admin)
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('Profiled Person');
    }

    public function test_viewers_cannot_view_a_user_profile(): void
    {
        $viewer = User::factory()->viewer()->create();
        $user = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('users.show', $user))
            ->assertForbidden();
    }

    public function test_editable_fields_can_be_updated(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->viewer()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $user])
            ->set('name', 'Updated Name')
            ->set('role', UserRole::Admin->value)
            ->set('locale', 'en')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame('en', $user->locale);
    }

    public function test_the_language_must_be_supported(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $user])
            ->set('locale', 'fr')
            ->call('updateProfile')
            ->assertHasErrors(['locale']);
    }

    public function test_login_email_cannot_be_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $originalEmail = $user->email;

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $user])
            ->set('email', 'changed@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame($originalEmail, $user->refresh()->email);
    }

    public function test_admins_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $admin])
            ->set('role', UserRole::Viewer->value)
            ->call('updateProfile')
            ->assertHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_a_password_can_be_set_without_the_current_one(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $user])
            ->set('password', 'new-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('password', '');

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_a_user_can_be_deactivated_and_reactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin);

        $component = Livewire::test(Show::class, ['user' => $user])
            ->call('toggleActivation');

        $this->assertNotNull($user->refresh()->deactivated_at);

        $component->call('toggleActivation');

        $this->assertNull($user->refresh()->deactivated_at);
    }

    public function test_admins_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $admin])
            ->call('toggleActivation');

        $this->assertNull($admin->refresh()->deactivated_at);
    }
}
