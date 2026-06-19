<?php

namespace Tests\Feature\Shareholders;

use App\Livewire\Projects\Show;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShareholderRosterSearchTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        $project = Project::factory()->create();

        $alice = Shareholder::factory()->create([
            'name' => 'Alice Kim', 'registration' => '9001011234567', 'date_of_birth_code' => '900101',
        ]);
        $bob = Shareholder::factory()->create([
            'name' => 'Bob Lee', 'registration' => '8505052345678', 'date_of_birth_code' => '850505',
        ]);

        ProjectShareholder::factory()->for($project)->for($alice)->create(['no' => 1]);
        ProjectShareholder::factory()->for($project)->for($bob)->create(['no' => 2]);

        return $project;
    }

    public function test_the_roster_can_be_searched_by_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project()])
            ->assertSee('Alice Kim')
            ->assertSee('Bob Lee')
            ->set('shareholderSearch', 'Alice')
            ->assertSee('Alice Kim')
            ->assertDontSee('Bob Lee');
    }

    public function test_the_roster_can_be_searched_by_registration_including_hyphens(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project()])
            ->set('shareholderSearch', '900101-1234567')
            ->assertSee('Alice Kim')
            ->assertDontSee('Bob Lee');
    }

    public function test_the_roster_can_be_searched_by_dob_code(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project()])
            ->set('shareholderSearch', '850505')
            ->assertSee('Bob Lee')
            ->assertDontSee('Alice Kim');
    }

    public function test_a_search_with_no_matches_shows_an_empty_message(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $this->project()])
            ->set('shareholderSearch', 'Nobody')
            ->assertSee('No shareholders match your search.')
            ->assertDontSee('Alice Kim');
    }

    public function test_the_roster_shows_assigned_worker_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = $this->project();
        $worker = User::factory()->worker()->create(['name' => 'Jin Park']);
        $project->shareholders()->where('no', 1)->first()->workers()->attach($worker);

        Livewire::test(Show::class, ['project' => $project])
            ->assertSee('Alice Kim')
            ->assertSee('Jin Park');
    }

    public function test_the_roster_can_be_searched_by_worker_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = $this->project();
        $worker = User::factory()->worker()->create(['name' => 'Jin Park']);
        $project->shareholders()->where('no', 1)->first()->workers()->attach($worker); // Alice

        Livewire::test(Show::class, ['project' => $project])
            ->set('shareholderSearch', 'Jin Park')
            ->assertSee('Alice Kim')
            ->assertDontSee('Bob Lee');
    }
}
