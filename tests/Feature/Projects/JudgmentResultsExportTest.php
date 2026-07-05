<?php

namespace Tests\Feature\Projects;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class JudgmentResultsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_download_a_real_xlsx_workbook(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['title' => 'Annual Meeting 2026']);

        $response = $this->actingAs($admin)->get(route('projects.judgment-results.export', $project));

        $response->assertOk();
        $response->assertDownload('judgment-results-annual-meeting-2026.xlsx');

        // A valid .xlsx is a ZIP container — confirm openspout actually wrote one.
        $content = file_get_contents($response->getFile()->getPathname());
        $this->assertStringStartsWith('PK', $content);
    }

    public function test_the_sheet_lays_out_the_heading_block_and_colour_grouped_rows(): void
    {
        $this->travelTo('2026-07-01');

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'title' => '테스트 발행회사',
            'end_date' => '2026-07-11',
            'shares_issued' => 100_000,
            'shares_target' => 50_000,
        ]);

        // Two consecutive green 판단 merge into one total group; red stands alone.
        $green1 = ProjectResult::factory()->for($project)->create(['name' => '위임(서면)', 'color' => ResultColor::Green, 'sort_order' => 0]);
        $green2 = ProjectResult::factory()->for($project)->create(['name' => '위임(전자)', 'color' => ResultColor::Green, 'sort_order' => 1]);
        $red = ProjectResult::factory()->for($project)->create(['name' => '거부', 'color' => ResultColor::Red, 'sort_order' => 2]);
        // A 판단 nobody landed on must not appear at all.
        ProjectResult::factory()->for($project)->create(['name' => '보류', 'color' => ResultColor::Gray, 'sort_order' => 3]);

        ProjectShareholder::factory()->for($project)->create(['shares' => 10_000, 'result_id' => $green1->id]);
        ProjectShareholder::factory()->for($project)->create(['shares' => 5_000, 'result_id' => $green2->id]);
        ProjectShareholder::factory()->for($project)->create(['shares' => 2_500, 'result_id' => $red->id]);
        // Unjudged rows stay out of the tally.
        ProjectShareholder::factory()->for($project)->create(['shares' => 99_999, 'result_id' => null]);

        $path = $this->actingAs($admin)
            ->get(route('projects.judgment-results.export', $project))
            ->assertOk()
            ->getFile()->getPathname();

        $rows = $this->readRows($path);

        // Heading block: title, subtitle, then the three meta rows (D: label, E: value).
        $this->assertSame('테스트 발행회사', $rows[1][0]);
        $this->assertSame('위임 권유 활동 현황 보고', $rows[2][0]);
        $this->assertSame('작성 일:', $rows[4][3]);
        $this->assertSame('2026-07-01', $rows[4][4]->format('Y-m-d'));
        $this->assertSame('주주총회 일:', $rows[5][3]);
        $this->assertSame('2026-07-11', $rows[5][4]->format('Y-m-d'));
        $this->assertSame('잔여 일:', $rows[6][3]);
        $this->assertEquals(10, $rows[6][4]);

        // Issued vs target shares.
        $this->assertSame('총 발행 주식 수', $rows[8][0]);
        $this->assertSame('목표 주식 수', $rows[8][4]);
        $this->assertEquals(100_000, $rows[9][0]);
        $this->assertEquals(50_000, $rows[9][4]);

        // The judgment table mirrors the dashboard panel's columns.
        $this->assertSame(['판단', '주식수', '각 판단별 진행 현황(%)', '총 합(주식수)', '총 합(%)'], $rows[11]);

        // Green group: totals (15,000 = 30%) on the first row only; the second
        // row's total cells sit under the vertical merge.
        $this->assertSame('위임(서면)', $rows[12][0]);
        $this->assertEquals(10_000, $rows[12][1]);
        $this->assertEqualsWithDelta(0.20, $rows[12][2], 0.0001);
        $this->assertEquals(15_000, $rows[12][3]);
        $this->assertEqualsWithDelta(0.30, $rows[12][4], 0.0001);

        $this->assertSame('위임(전자)', $rows[13][0]);
        $this->assertEquals(5_000, $rows[13][1]);
        $this->assertEqualsWithDelta(0.10, $rows[13][2], 0.0001);
        $this->assertEmpty($rows[13][3]);
        $this->assertEmpty($rows[13][4]);

        // Red group of one: its own total, and the empty 판단 never shows up.
        $this->assertSame('거부', $rows[14][0]);
        $this->assertEquals(2_500, $rows[14][1]);
        $this->assertEquals(2_500, $rows[14][3]);
        $this->assertArrayNotHasKey(15, $rows);
    }

    public function test_percent_cells_are_blank_without_a_target(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['shares_target' => null]);
        $result = ProjectResult::factory()->for($project)->create(['sort_order' => 0]);
        ProjectShareholder::factory()->for($project)->create(['shares' => 1_000, 'result_id' => $result->id]);

        $path = $this->actingAs($admin)
            ->get(route('projects.judgment-results.export', $project))
            ->assertOk()
            ->getFile()->getPathname();

        $rows = $this->readRows($path);

        $this->assertEquals(1_000, $rows[12][1]);
        $this->assertEmpty($rows[12][2]); // per-판단 percent
        $this->assertEmpty($rows[12][4]); // group percent
    }

    public function test_office_users_can_download(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create();

        $this->actingAs($office)
            ->get(route('projects.judgment-results.export', $project))
            ->assertOk();
    }

    public function test_workers_cannot_download(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->create();

        $this->actingAs($worker)
            ->get(route('projects.judgment-results.export', $project))
            ->assertRedirect(route('worker.dashboard'));
    }

    /**
     * Read the first sheet keeping empty spacer rows, so array keys line up
     * with the 1-based spreadsheet row numbers.
     *
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $path): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $rows[$index] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }
}
