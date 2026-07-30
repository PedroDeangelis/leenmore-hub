<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectStatus;
use App\Enums\ResultColor;
use App\Models\ApiToken;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Receipt;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = ApiToken::generate('Test client', User::factory()->admin()->create()->id)['plainText'];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function apiGet(string $path, array $query = []): TestResponse
    {
        return $this->getJson(
            '/api/v1/'.ltrim($path, '/').($query === [] ? '' : '?'.http_build_query($query)),
            ['Authorization' => 'Bearer '.$this->token],
        );
    }

    public function test_it_lists_projects_with_counts(): void
    {
        $project = Project::factory()->create(['title' => 'Spring', 'status' => ProjectStatus::Publish, 'shares_target' => 5000]);
        ProjectShareholder::factory()->count(2)->for($project)->create();

        $this->apiGet('projects')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Spring')
            ->assertJsonPath('data.0.shareholder_count', 2);
    }

    public function test_project_filters_and_pagination_cap_apply(): void
    {
        Project::factory()->create(['title' => 'Live', 'status' => ProjectStatus::Publish]);
        Project::factory()->create(['title' => 'Old', 'status' => ProjectStatus::Archived]);

        $this->apiGet('projects', ['status' => 'active'])->assertOk()->assertJsonPath('total', 1);
        $this->apiGet('projects', ['status' => 'nonsense'])->assertStatus(422);
        $this->apiGet('projects', ['per_page' => 999])->assertStatus(422);
    }

    public function test_projects_filter_by_start_date_and_sort_latest_first(): void
    {
        Project::factory()->create(['title' => 'January', 'start_date' => '2026-01-10']);
        Project::factory()->create(['title' => 'March', 'start_date' => '2026-03-10']);
        Project::factory()->create(['title' => 'June', 'start_date' => '2026-06-10']);

        // Sorted by start_date, latest first.
        $this->apiGet('projects')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'June')
            ->assertJsonPath('data.1.title', 'March')
            ->assertJsonPath('data.2.title', 'January');

        // from / to bound the start_date range, inclusive.
        $this->apiGet('projects', ['from' => '2026-02-01'])->assertOk()->assertJsonPath('total', 2);
        $this->apiGet('projects', ['to' => '2026-03-10'])->assertOk()->assertJsonPath('total', 2);
        $this->apiGet('projects', ['from' => '2026-02-01', 'to' => '2026-04-01'])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'March');

        $this->apiGet('projects', ['from' => 'not-a-date'])->assertStatus(422);
    }

    public function test_get_project_returns_the_judgment_tally(): void
    {
        $project = Project::factory()->create(['shares_target' => 1000]);
        $green = ProjectResult::factory()->for($project)->create(['name' => '위임(대면_서명)', 'color' => ResultColor::Green, 'sort_order' => 1]);
        ProjectShareholder::factory()->for($project)->create(['shares' => 300, 'result_id' => $green->id]);

        $this->apiGet('projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('judgment_tally.has_data', true)
            ->assertJsonPath('judgment_tally.groups.0.total_shares', 300)
            ->assertJsonPath('results.0.is_proxy', true);
    }

    public function test_a_deleted_project_is_404_not_leaked(): void
    {
        $project = Project::factory()->create();
        $project->markDeleted();

        $this->apiGet('projects/'.$project->id)->assertNotFound();
    }

    public function test_proxy_only_roster_matches_every_green_judgment(): void
    {
        $project = Project::factory()->create();
        $signed = ProjectResult::factory()->for($project)->create(['name' => '위임(대면)', 'color' => ResultColor::Green]);
        $sms = ProjectResult::factory()->for($project)->create(['name' => '위임(문자)', 'color' => ResultColor::Green]);
        $absent = ProjectResult::factory()->for($project)->create(['name' => '부재', 'color' => ResultColor::Gold]);
        foreach ([$signed, $sms, $absent] as $r) {
            ProjectShareholder::factory()->for($project)->create(['result_id' => $r->id]);
        }

        $this->apiGet('projects/'.$project->id.'/shareholders', ['proxy_only' => 'true'])
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_activity_data_and_receipts_and_users_respond(): void
    {
        $project = Project::factory()->create();
        ProjectResult::factory()->for($project)->create(['name' => '위임', 'color' => ResultColor::Green]);
        $assignment = ProjectShareholder::factory()->for($project)->create(['shares' => 100]);
        $worker = User::factory()->worker()->create();
        $assignment->workers()->attach($worker);
        Submission::factory()->forAssignment($assignment)->create(['user_id' => $worker->id, 'result' => '위임', 'date' => '2026-03-01']);
        // Pin the receipt to the existing worker; otherwise the factory mints a
        // second worker and the role=worker assertion below counts two.
        Receipt::factory()->create(['user_id' => $worker->id, 'category_name' => '식대', 'amount' => 10000]);

        $this->apiGet('projects/'.$project->id.'/activity-data')->assertOk()->assertJsonPath('is_empty', false);

        // days accepts a comma list or a repeated param; both restrict the data.
        $this->apiGet('projects/'.$project->id.'/activity-data', ['days' => '2026-03-01,2026-03-02'])
            ->assertOk()->assertJsonPath('days_included', ['2026-03-01']);
        $this->apiGet('projects/'.$project->id.'/activity-data', ['days' => ['2026-03-01']])
            ->assertOk()->assertJsonPath('days_included', ['2026-03-01']);
        $this->apiGet('projects/'.$project->id.'/activity-data', ['days' => '2026-01-01'])
            ->assertOk()->assertJsonPath('is_empty', true);

        $this->apiGet('submissions', ['project_id' => $project->id])->assertOk()->assertJsonPath('total', 1);
        $this->apiGet('receipts')->assertOk()->assertJsonPath('total_amount', 10000);
        $this->apiGet('users', ['role' => 'worker'])->assertOk()->assertJsonPath('total', 1);
    }

    public function test_registration_is_masked_and_contacts_never_returned(): void
    {
        $project = Project::factory()->create();
        $person = Shareholder::factory()->create([
            'name' => '박하늘',
            'registration' => '901231-1234567',
            'contact_info' => '010-9876-5432',
            'address' => '서울특별시 강남구 테헤란로 152',
        ]);
        ProjectShareholder::factory()->for($project)->create(['shareholder_id' => $person->id]);

        $response = $this->apiGet('projects/'.$project->id.'/shareholders')->assertOk();

        $response->assertJsonPath('data.0.registration_masked', '901231-1******')
            ->assertJsonPath('data.0.region', '서울특별시 강남구')
            ->assertJsonPath('data.0.has_contact', true);

        $body = $response->getContent();
        $this->assertStringNotContainsString('1234567', $body);
        $this->assertStringNotContainsString('010-9876-5432', $body);
        $this->assertStringNotContainsString('테헤란로', $body);
    }
}
