<?php

namespace Tests\Feature\Shareholders;

use App\Enums\PersonType;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_person_type_casts_to_the_enum(): void
    {
        $shareholder = Shareholder::factory()->create(['person_type' => 'corporation']);

        $this->assertSame(PersonType::Corporation, $shareholder->refresh()->person_type);
    }

    public function test_registration_is_unique(): void
    {
        Shareholder::factory()->create(['registration' => '900101-1234567']);

        $this->expectException(QueryException::class);

        Shareholder::factory()->create(['registration' => '900101-1234567']);
    }

    public function test_multiple_people_may_have_no_registration(): void
    {
        Shareholder::factory()->create(['registration' => null]);
        Shareholder::factory()->create(['registration' => null]);

        $this->assertSame(2, Shareholder::whereNull('registration')->count());
    }

    public function test_a_person_has_many_assignments(): void
    {
        $person = Shareholder::factory()->create();
        ProjectShareholder::factory()->count(2)->for($person)->create();

        $this->assertCount(2, $person->assignments);
    }

    public function test_a_person_lists_the_projects_they_are_assigned_to(): void
    {
        $person = Shareholder::factory()->create();
        $a = Project::factory()->create(['title' => 'Project A']);
        $b = Project::factory()->create(['title' => 'Project B']);

        ProjectShareholder::factory()->for($person)->for($a)->create();
        ProjectShareholder::factory()->for($person)->for($b)->create();

        $this->assertEqualsCanonicalizing(
            ['Project A', 'Project B'],
            $person->projects()->pluck('title')->all(),
        );
    }

    public function test_deleting_a_person_cascades_their_assignments(): void
    {
        $person = Shareholder::factory()->create();
        $assignment = ProjectShareholder::factory()->for($person)->create();

        $person->delete();

        $this->assertDatabaseMissing('project_shareholders', ['id' => $assignment->id]);
    }
}
