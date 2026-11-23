<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_the_users_list(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create(['name' => 'Listed Person']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Listed Person');
    }

    public function test_viewers_cannot_view_the_users_list(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_the_list_can_be_searched(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Alice Example']);
        User::factory()->create(['name' => 'Bob Sample']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('search', 'Alice')
            ->assertSee('Alice Example')
            ->assertDontSee('Bob Sample');
    }

    public function test_admins_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('name', 'New Person')
            ->set('email', 'new@example.com')
            ->set('role', UserRole::Viewer->value)
            ->set('locale', 'en')
            ->set('password', 'correct-horse-battery')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('showCreate', false)
            ->assertSet('password', '');

        $created = User::where('email', 'new@example.com')->sole();

        $this->assertSame('New Person', $created->name);
        $this->assertSame(UserRole::Viewer, $created->role);
        $this->assertSame('en', $created->locale);
        $this->assertTrue(Hash::check('correct-horse-battery', $created->password));
    }

    public function test_creating_a_user_requires_a_unique_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('name', 'New Person')
            ->set('email', 'taken@example.com')
            ->set('password', 'correct-horse-battery')
            ->call('create')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_viewers_cannot_create_a_user(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer);

        Livewire::test(Index::class)
            ->set('name', 'New Person')
            ->set('email', 'new@example.com')
            ->set('password', 'correct-horse-battery')
            ->call('create')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
