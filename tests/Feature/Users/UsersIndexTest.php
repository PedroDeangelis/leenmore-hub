<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
