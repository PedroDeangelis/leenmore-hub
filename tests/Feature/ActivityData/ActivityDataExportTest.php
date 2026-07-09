<?php

namespace Tests\Feature\ActivityData;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ActivityDataExportTest extends TestCase
{
    use RefreshDatabase;

    /**
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
        Submission::factory()->forAssignment($second)->create(['user_id' => $alice->id, 'user_name' => 'Alice', 'date' => '2026-07-01', 'result' => '거부']);
        Submission::factory()->forAssignment($first)->create(['user_id' => $bob->id, 'user_name' => 'Bob', 'date' => '2026-07-02', 'result' => '위임(대면)']);

        return [$project, $alice, $bob];
    }

    private function sheetXml(string $path): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $xml;
    }

    public function test_the_workbook_carries_the_layout_and_live_formulas(): void
    {
        [$project] = $this->seedProject();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('activity-data.export', $project))
            ->assertOk()
            ->assertDownload('activity-data-gst.xlsx');

        $xml = $this->sheetXml($response->baseResponse->getFile()->getPathname());

        // Header labels and both workers (inline strings).
        foreach (['총 활동가:', '총 활동일수:', '활동일수', '주주 수', '주식 수', '일 평균', '누 계', '담당 대비 위임률', '담당주주수 대비 위임률(%)', '활동가 목록', '접촉 횟수', '위임 주식수', 'Alice', 'Bob'] as $label) {
            $this->assertStringContainsString($label, $xml);
        }

        // Day headers (m월 d일).
        $this->assertStringContainsString('07월 01일', $xml);
        $this->assertStringContainsString('07월 02일', $xml);

        // Live formulas: worker count, 일 평균 = 누계/활동일수, 누계 = per-day sums,
        // delegation rate, totals + averages rows (N = 2 workers → rows 3-4).
        foreach ([
            '<f>COUNTA(B3:B4)</f>',
            '<f>K3/D3</f>',
            '<f>SUM(Q3,U3)</f>',
            '<f>(M3/E3)*100</f>',
            '<f>SUM(Q3:Q4)</f>',
            '<f>ROUND(SUM(E3:E4),0)</f>',
            '<f>ROUND(AVERAGE(O3:O4),1)</f>',
            '<f>ROUND(Q5/COUNTA(Q3:Q4),1)</f>',
        ] as $formula) {
            $this->assertStringContainsString($formula, $xml);
        }
    }

    public function test_the_days_filter_narrows_the_workbook(): void
    {
        [$project] = $this->seedProject();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('activity-data.export', [$project, 'days' => ['2026-07-01']]))
            ->assertOk();

        $xml = $this->sheetXml($response->baseResponse->getFile()->getPathname());

        // Only day 1's block — and only Alice filed that day.
        $this->assertStringContainsString('07월 01일', $xml);
        $this->assertStringNotContainsString('07월 02일', $xml);
        $this->assertStringContainsString('Alice', $xml);
        $this->assertStringNotContainsString('Bob', $xml);
    }

    public function test_a_project_without_reports_is_not_found(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('activity-data.export', $project))
            ->assertNotFound();
    }
}
