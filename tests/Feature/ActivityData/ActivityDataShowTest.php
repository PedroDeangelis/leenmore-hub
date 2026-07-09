<?php

namespace Tests\Feature\ActivityData;

use App\Enums\ResultColor;
use App\Livewire\ActivityData\Show;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use App\Models\User;
use App\Support\ActivityData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityDataShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The shared fixture: a green (proxy) and a red 판단, two workers, two
     * shareholders. Alice's ps1 proxy on day 1 is superseded by a refusal on
     * day 2 (so it no longer counts); her ps2 proxy stands; Bob wins ps1 on
     * day 2.
     *
     * @return array{Project, User, User}
     */
    private function seedProject(): array
    {
        $project = Project::factory()->create(['title' => 'GST']);
        ProjectResult::factory()->for($project)->create(['name' => '위임(대면)', 'color' => ResultColor::Green, 'sort_order' => 0]);
        ProjectResult::factory()->for($project)->create(['name' => '거부', 'color' => ResultColor::Red, 'sort_order' => 1]);

        $alice = User::factory()->worker()->create(['name' => 'Alice']);
        $bob = User::factory()->worker()->create(['name' => 'Bob']);

        $first = ProjectShareholder::factory()->for($project)->create(['shares' => 100]);
        $second = ProjectShareholder::factory()->for($project)->create(['shares' => 50]);

        Submission::factory()->forAssignment($first)->create(['user_id' => $alice->id, 'user_name' => 'Alice', 'date' => '2026-07-01', 'result' => '위임(대면)']);
        Submission::factory()->forAssignment($first)->create(['user_id' => $alice->id, 'user_name' => 'Alice', 'date' => '2026-07-02', 'result' => '거부']);
        Submission::factory()->forAssignment($second)->create(['user_id' => $alice->id, 'user_name' => 'Alice', 'date' => '2026-07-01', 'result' => '위임(대면)']);
        Submission::factory()->forAssignment($first)->create(['user_id' => $bob->id, 'user_name' => 'Bob', 'date' => '2026-07-02', 'result' => '위임(대면)']);

        return [$project, $alice, $bob];
    }

    public function test_per_worker_stats_follow_the_legacy_semantics(): void
    {
        [$project] = $this->seedProject();

        $data = ActivityData::for($project->load('results'));

        $this->assertSame(['2026-07-01', '2026-07-02'], $data->allDays());

        [$alice, $bob] = $data->workers();

        // Raw contact count includes the repeat visit; shares dedupe to the
        // latest report per shareholder, where the newer refusal drops ps1's proxy.
        $this->assertSame('Alice', $alice['name']);
        $this->assertSame(3, $alice['totalReports']);
        $this->assertSame(150, $alice['sharesContacted']);
        $this->assertSame(1, $alice['proxyShareholders']);
        $this->assertSame(50, $alice['proxyShares']);
        $this->assertSame(2, $alice['daysWorked']);
        $this->assertSame(2, $alice['shareholderCount']);
        $this->assertSame(150, $alice['assignedShares']);

        $this->assertSame('Bob', $bob['name']);
        $this->assertSame(1, $bob['totalReports']);
        $this->assertSame(100, $bob['sharesContacted']);
        $this->assertSame(1, $bob['proxyShareholders']);
        $this->assertSame(100, $bob['proxyShares']);

        $this->assertSame(
            ['workers' => 2, 'proxyShareholders' => 2, 'proxyShares' => 150],
            $data->summary(),
        );
    }

    public function test_the_day_filter_narrows_the_stats(): void
    {
        [$project] = $this->seedProject();

        $data = ActivityData::for($project->load('results'), ['2026-07-01']);

        // Only Alice filed on day 1 — and within that day her ps1 proxy still counts.
        $this->assertCount(1, $data->workers());
        $alice = $data->workers()[0];
        $this->assertSame(2, $alice['totalReports']);
        $this->assertSame(2, $alice['proxyShareholders']);
        $this->assertSame(150, $alice['proxyShares']);

        // The full day list stays available for the dropdown.
        $this->assertSame(['2026-07-01', '2026-07-02'], $data->allDays());
        $this->assertSame(['2026-07-01'], $data->days());
    }

    public function test_the_excel_day_blocks_dedupe_within_each_day(): void
    {
        $project = Project::factory()->create();
        ProjectResult::factory()->for($project)->create(['name' => '위임(대면)', 'color' => ResultColor::Green]);

        $worker = User::factory()->worker()->create(['name' => 'Alice']);
        $assignment = ProjectShareholder::factory()->for($project)->create(['shares' => 100]);

        // Two same-day reports on the same shareholder.
        Submission::factory()->forAssignment($assignment)->create(['user_id' => $worker->id, 'date' => '2026-07-01', 'result' => '위임(대면)']);
        Submission::factory()->forAssignment($assignment)->create(['user_id' => $worker->id, 'date' => '2026-07-01', 'result' => '위임(대면)']);

        $data = ActivityData::for($project->load('results'));
        $row = $data->workers()[0];

        // Screen counts both contacts; the day block counts the shareholder once.
        $this->assertSame(2, $row['totalReports']);
        $this->assertSame(
            ['reports' => 1, 'sharesContacted' => 100, 'proxyShareholders' => 1, 'proxyShares' => 100],
            $data->dayStats($row['key'], '2026-07-01'),
        );
    }

    public function test_the_page_renders_filters_and_sorts(): void
    {
        [$project] = $this->seedProject();

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(Show::class, ['project' => $project])
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSeeInOrder(['Alice', 'Bob']);

        // proxyShares ascending puts Alice (50) before Bob (100); toggling flips it.
        $component->call('sort', 'proxyShares')
            ->assertSeeInOrder(['Alice', 'Bob'])
            ->call('sort', 'proxyShares')
            ->assertSeeInOrder(['Bob', 'Alice']);

        // Filtering to day 1 leaves only Alice's row.
        $component->call('toggleDay', '2026-07-01')
            ->assertSee('Alice')
            ->assertDontSee('Bob');

        $component->call('showAllDays')
            ->assertSee('Bob');
    }

    public function test_a_project_without_reports_shows_the_empty_state(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Show::class, ['project' => $project])
            ->assertSee(__('No activity reports yet.'));
    }
}
