<?php

namespace Tests\Feature\Shareholders;

use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShareholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_assignment_has_many_worker_users(): void
    {
        $assignment = ProjectShareholder::factory()->create();
        $a = User::factory()->worker()->create();
        $b = User::factory()->worker()->create();

        $assignment->workers()->sync([$a->id, $b->id]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $assignment->workers->pluck('id')->all());
        $this->assertTrue($a->assignedShareholders->contains($assignment));

        // Deleting the assignment clears its pivot rows.
        $assignment->delete();
        $this->assertDatabaseMissing('project_shareholder_user', ['project_shareholder_id' => $assignment->id]);
    }

    public function test_a_projects_roster_is_returned_in_list_order(): void
    {
        $project = Project::factory()->create();
        ProjectShareholder::factory()->for($project)->create(['no' => 2]);
        ProjectShareholder::factory()->for($project)->create(['no' => 1]);

        $this->assertSame([1, 2], $project->shareholders->pluck('no')->all());
    }

    public function test_a_person_cannot_be_assigned_to_the_same_project_twice(): void
    {
        $project = Project::factory()->create();
        $person = Shareholder::factory()->create();
        ProjectShareholder::factory()->for($project)->for($person)->create();

        $this->expectException(QueryException::class);

        ProjectShareholder::factory()->for($project)->for($person)->create();
    }

    public function test_effective_contact_falls_back_to_the_person(): void
    {
        $person = Shareholder::factory()->create(['contact_info' => '010-1111-1111']);
        $assignment = ProjectShareholder::factory()->for($person)->create(['contact_info' => null]);

        $this->assertSame('010-1111-1111', $assignment->effective_contact);
    }

    public function test_effective_contact_uses_the_per_project_override(): void
    {
        $person = Shareholder::factory()->create(['contact_info' => '010-1111-1111']);
        $assignment = ProjectShareholder::factory()->for($person)->create(['contact_info' => '010-9999-9999']);

        $this->assertSame('010-9999-9999', $assignment->effective_contact);
    }

    public function test_effective_address_falls_back_and_overrides(): void
    {
        $person = Shareholder::factory()->create(['address' => 'Base Address']);

        $fallback = ProjectShareholder::factory()->for($person)->create(['address' => null]);
        $override = ProjectShareholder::factory()->for(Shareholder::factory()->create(['address' => 'Base Address']))
            ->create(['address' => 'Project Address']);

        $this->assertSame('Base Address', $fallback->effective_address);
        $this->assertSame('Project Address', $override->effective_address);
    }

    public function test_the_current_result_resolves_to_a_project_result(): void
    {
        $project = Project::factory()->create();
        $result = ProjectResult::factory()->for($project)->create(['name' => '거부']);
        $assignment = ProjectShareholder::factory()->for($project)->create(['result_id' => $result->id]);

        $this->assertTrue($assignment->result->is($result));
    }

    public function test_deleting_a_result_nulls_the_assignment_reference(): void
    {
        $project = Project::factory()->create();
        $result = ProjectResult::factory()->for($project)->create();
        $assignment = ProjectShareholder::factory()->for($project)->create(['result_id' => $result->id]);

        $result->delete();

        $this->assertNull($assignment->refresh()->result_id);
    }
}
