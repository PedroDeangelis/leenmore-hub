<?php

namespace Tests\Feature\Activity;

use App\Livewire\Activity\Index;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_only_published_projects(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->published()->create(['title' => 'Published Campaign']);
        Project::factory()->draft()->create(['title' => 'Draft Campaign']);

        $this->actingAs($admin)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee('Published Campaign')
            ->assertDontSee('Draft Campaign');
    }

    public function test_office_users_can_view_the_activity_index(): void
    {
        $office = User::factory()->office()->create();
        Project::factory()->published()->create(['title' => 'Office Visible']);

        $this->actingAs($office)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertSee('Office Visible');
    }

    public function test_workers_cannot_view_the_activity_index(): void
    {
        $worker = User::factory()->worker()->create();

        $this->actingAs($worker)
            ->get(route('activity.index'))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_the_index_can_be_searched_by_title(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->published()->create(['title' => 'Alpha Published']);
        Project::factory()->published()->create(['title' => 'Beta Published']);

        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha Published')
            ->assertDontSee('Beta Published');
    }
}
