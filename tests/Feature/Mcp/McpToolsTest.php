<?php

namespace Tests\Feature\Mcp;

use App\Enums\ProjectStatus;
use App\Enums\ResultColor;
use App\Mcp\Servers\LeenmoreServer;
use App\Mcp\Tools\ActivityDataTool;
use App\Mcp\Tools\FetchTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListReceiptsTool;
use App\Mcp\Tools\ListShareholdersTool;
use App\Mcp\Tools\ListSubmissionsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\SearchTool;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Receipt;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class McpToolsTest extends McpTestCase
{
    use RefreshDatabase;

    /**
     * Call a tool and return its structured payload.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callTool(string $tool, array $arguments = []): array
    {
        return LeenmoreServer::tool($tool, $arguments)->assertOk()->payload();
    }

    public function test_it_lists_projects_with_counts_and_a_meeting_countdown(): void
    {
        $project = Project::factory()->create([
            'title' => 'Spring meeting',
            'status' => ProjectStatus::Publish,
            'end_date' => now()->addDays(10),
            'shares_target' => 5_000,
        ]);
        ProjectShareholder::factory()->count(3)->for($project)->create();

        $payload = $this->callTool(ListProjectsTool::class);

        $this->assertSame(1, $payload['total']);
        $this->assertSame('Spring meeting', $payload['data'][0]['title']);
        $this->assertSame(3, $payload['data'][0]['shareholder_count']);
        $this->assertSame(5_000, $payload['data'][0]['shares_target']);
        $this->assertSame(10, $payload['data'][0]['days_until_meeting']);
    }

    public function test_listing_projects_filters_by_status_and_title(): void
    {
        Project::factory()->create(['title' => 'Live one', 'status' => ProjectStatus::Publish]);
        Project::factory()->create(['title' => 'Old one', 'status' => ProjectStatus::Archived]);

        $this->assertSame('Live one', $this->callTool(ListProjectsTool::class, ['status' => 'active'])['data'][0]['title']);
        $this->assertSame('Old one', $this->callTool(ListProjectsTool::class, ['status' => 'archived'])['data'][0]['title']);
        $this->assertSame(1, $this->callTool(ListProjectsTool::class, ['search' => 'Live'])['total']);
    }

    public function test_deleted_projects_are_invisible_to_every_project_tool(): void
    {
        $project = Project::factory()->create();
        $project->markDeleted();

        $this->assertSame(0, $this->callTool(ListProjectsTool::class)['total']);

        // The id no longer resolves, so get-project reports an error rather than data.
        LeenmoreServer::tool(GetProjectTool::class, ['project_id' => $project->id])->assertHasErrors();
    }

    public function test_get_project_returns_the_judgment_tally_grouped_by_colour(): void
    {
        $project = Project::factory()->create(['shares_target' => 1_000]);
        $proxy = ProjectResult::factory()->for($project)->create(['name' => '위임(대면_서명)', 'color' => ResultColor::Green, 'sort_order' => 1]);
        $reserved = ProjectResult::factory()->for($project)->create(['name' => '유보', 'color' => ResultColor::Blue, 'sort_order' => 2]);

        ProjectShareholder::factory()->for($project)->create(['shares' => 300, 'result_id' => $proxy->id]);
        ProjectShareholder::factory()->for($project)->create(['shares' => 200, 'result_id' => $reserved->id]);
        ProjectShareholder::factory()->for($project)->create(['shares' => 500, 'result_id' => null]);

        $payload = $this->callTool(GetProjectTool::class, ['project_id' => $project->id]);

        $this->assertSame(3, $payload['shareholder_count']);
        $this->assertSame(2, $payload['judged_shareholder_count']);
        $this->assertSame(1, $payload['unjudged_shareholder_count']);
        $this->assertTrue($payload['judgment_tally']['has_data']);

        $groups = $payload['judgment_tally']['groups'];
        $this->assertSame('green', $groups[0]['color']);
        $this->assertSame(300, $groups[0]['total_shares']);
        // 300 of a 1,000-share target. Compared loosely: JSON round-tripping a whole
        // percentage can land as int or float and the numeric value is the point.
        $this->assertEqualsWithDelta(30, $groups[0]['total_percent_of_target'], 0.001);
        $this->assertSame('blue', $groups[1]['color']);
        $this->assertSame(200, $groups[1]['total_shares']);

        // is_proxy spells out the green rule so the model never has to infer it.
        $this->assertTrue(collect($payload['results'])->firstWhere('name', '위임(대면_서명)')['is_proxy']);
        $this->assertFalse(collect($payload['results'])->firstWhere('name', '유보')['is_proxy']);
    }

    public function test_proxy_only_matches_every_green_judgment_not_one_label(): void
    {
        $project = Project::factory()->create();
        $signed = ProjectResult::factory()->for($project)->create(['name' => '위임(대면_서명)', 'color' => ResultColor::Green]);
        $sms = ProjectResult::factory()->for($project)->create(['name' => '위임(비대면_문자)', 'color' => ResultColor::Green]);
        $absent = ProjectResult::factory()->for($project)->create(['name' => '단순부재', 'color' => ResultColor::Gold]);

        foreach ([$signed, $sms, $absent] as $result) {
            ProjectShareholder::factory()->for($project)->create(['result_id' => $result->id]);
        }

        $payload = $this->callTool(ListShareholdersTool::class, [
            'project_id' => $project->id,
            'proxy_only' => true,
        ]);

        $this->assertSame(2, $payload['total']);
        $this->assertEqualsCanonicalizing(
            ['위임(대면_서명)', '위임(비대면_문자)'],
            array_column($payload['data'], 'result'),
        );
        $this->assertTrue($payload['data'][0]['is_proxy']);
    }

    public function test_listing_a_roster_filters_by_judged_state_and_assigned_worker(): void
    {
        $project = Project::factory()->create();
        $result = ProjectResult::factory()->for($project)->create();
        $worker = User::factory()->worker()->create();

        $judged = ProjectShareholder::factory()->for($project)->create(['result_id' => $result->id]);
        ProjectShareholder::factory()->for($project)->create(['result_id' => null]);
        $judged->workers()->attach($worker);

        $this->assertSame(1, $this->callTool(ListShareholdersTool::class, ['project_id' => $project->id, 'judged' => true])['total']);
        $this->assertSame(1, $this->callTool(ListShareholdersTool::class, ['project_id' => $project->id, 'judged' => false])['total']);

        $byWorker = $this->callTool(ListShareholdersTool::class, ['project_id' => $project->id, 'worker_id' => $worker->id]);
        $this->assertSame(1, $byWorker['total']);
        $this->assertSame([$worker->name], $byWorker['data'][0]['workers']);
    }

    public function test_listing_activity_reports_filters_by_worker_and_date_range(): void
    {
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();
        $worker = User::factory()->worker()->create();

        Submission::factory()->forAssignment($assignment)->create(['user_id' => $worker->id, 'date' => '2026-03-01']);
        Submission::factory()->forAssignment($assignment)->create(['user_id' => $worker->id, 'date' => '2026-03-20']);
        Submission::factory()->forAssignment($assignment)->create(['date' => '2026-03-10']);

        $this->assertSame(2, $this->callTool(ListSubmissionsTool::class, ['worker_id' => $worker->id])['total']);
        $this->assertSame(2, $this->callTool(ListSubmissionsTool::class, ['from' => '2026-03-05'])['total']);
        $this->assertSame(1, $this->callTool(ListSubmissionsTool::class, ['from' => '2026-03-05', 'to' => '2026-03-15'])['total']);
    }

    public function test_activity_data_reports_per_worker_proxy_totals(): void
    {
        $project = Project::factory()->create();
        ProjectResult::factory()->for($project)->create(['name' => '위임(대면_서명)', 'color' => ResultColor::Green]);
        $assignment = ProjectShareholder::factory()->for($project)->create(['shares' => 400]);
        $worker = User::factory()->worker()->create(['name' => 'Kim']);
        $assignment->workers()->attach($worker);

        Submission::factory()->forAssignment($assignment)->create([
            'user_id' => $worker->id,
            'user_name' => 'Kim',
            'result' => '위임(대면_서명)',
            'date' => '2026-03-02',
        ]);

        $payload = $this->callTool(ActivityDataTool::class, ['project_id' => $project->id]);

        $this->assertFalse($payload['is_empty']);
        $this->assertSame(['2026-03-02'], $payload['all_activity_days']);
        $this->assertSame(1, $payload['summary']['proxyShareholders']);
        $this->assertSame(400, $payload['summary']['proxyShares']);

        $row = $payload['workers'][0];
        $this->assertSame('Kim', $row['name']);
        $this->assertSame(1, $row['total_reports']);
        $this->assertSame(400, $row['proxy_shares']);
        $this->assertSame(1, $row['days_worked']);
    }

    public function test_receipt_totals_cover_the_whole_filtered_set_not_just_the_page(): void
    {
        $worker = User::factory()->worker()->create();
        Receipt::factory()->count(3)->create([
            'user_id' => $worker->id,
            'category_name' => '식대',
            'amount' => 10_000,
            'date' => '2026-03-01',
        ]);
        Receipt::factory()->create(['category_name' => '교통비', 'amount' => 5_000, 'date' => '2026-03-01']);

        $payload = $this->callTool(ListReceiptsTool::class, ['per_page' => 1]);

        $this->assertCount(1, $payload['data']);
        $this->assertSame(4, $payload['total']);
        $this->assertSame(35_000, $payload['total_amount']);

        $byCategory = collect($payload['by_category'])->keyBy('category');
        $this->assertSame(30_000, $byCategory['식대']['amount']);
        $this->assertSame(3, $byCategory['식대']['count']);

        $mine = $this->callTool(ListReceiptsTool::class, ['worker_id' => $worker->id]);
        $this->assertSame(30_000, $mine['total_amount']);
    }

    public function test_listing_users_filters_by_role_and_active_state(): void
    {
        User::factory()->admin()->create();
        User::factory()->worker()->create();
        User::factory()->worker()->create(['deactivated_at' => now()]);

        $this->assertSame(2, $this->callTool(ListUsersTool::class, ['role' => 'worker'])['total']);
        $this->assertSame(2, $this->callTool(ListUsersTool::class, ['active' => true])['total']);
        $this->assertSame(1, $this->callTool(ListUsersTool::class, ['role' => 'worker', 'active' => false])['total']);
    }

    public function test_search_returns_prefixed_ids_that_fetch_accepts(): void
    {
        $project = Project::factory()->create(['title' => 'Hanul meeting']);
        $shareholder = Shareholder::factory()->create(['name' => 'Hanul Park']);
        ProjectShareholder::factory()->for($project)->create(['shareholder_id' => $shareholder->id]);
        User::factory()->worker()->create(['name' => 'Hanul Lee']);

        $results = collect($this->callTool(SearchTool::class, ['query' => 'Hanul'])['results']);

        $this->assertEqualsCanonicalizing(
            ['project', 'shareholder', 'user'],
            $results->pluck('type')->all(),
        );

        foreach ($results as $result) {
            $fetched = $this->callTool(FetchTool::class, ['id' => $result['id']]);
            $this->assertSame($result['id'], $fetched['id']);
            $this->assertSame($result['title'], $fetched['title']);
        }
    }

    public function test_fetch_rejects_a_malformed_id_and_a_missing_record(): void
    {
        LeenmoreServer::tool(FetchTool::class, ['id' => 'project-12'])->assertHasErrors();
        LeenmoreServer::tool(FetchTool::class, ['id' => 'invoice:12'])->assertHasErrors();
        LeenmoreServer::tool(FetchTool::class, ['id' => 'project:999999'])->assertHasErrors();
    }

    public function test_list_tools_cap_page_size_at_200(): void
    {
        Project::factory()->count(3)->create();

        LeenmoreServer::tool(ListProjectsTool::class, ['per_page' => 500])->assertHasErrors();

        $this->assertSame(200, $this->callTool(ListProjectsTool::class, ['per_page' => 200])['per_page']);
    }

    /**
     * Globs the directory rather than a hard-coded list, so a tool added later
     * without the annotation fails here instead of quietly advertising itself as
     * capable of writing.
     */
    public function test_every_tool_in_the_directory_is_annotated_read_only(): void
    {
        $tools = glob(app_path('Mcp/Tools/*.php')) ?: [];

        $this->assertNotEmpty($tools);

        foreach ($tools as $path) {
            $class = 'App\\Mcp\\Tools\\'.basename($path, '.php');
            $annotations = (new $class)->annotations();

            $this->assertTrue(
                $annotations['readOnlyHint'] ?? false,
                $class.' is missing the #[IsReadOnly] annotation.',
            );
        }
    }
}
