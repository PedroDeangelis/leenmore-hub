<?php

namespace Tests\Feature\Shareholders;

use App\Imports\ShareholderImporter;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShareholderExportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        '연번', '실명번호', '성별', '주주명', '주식수', '총소유주식수', '전자투표',
        '주소', '주소서치', '주소서치2', '구연락처', '연락처', '활동가',
        '구 판단', '구 멘트', '비고', '전자위임연락처', '전자위임날짜',
    ];

    public function test_admins_can_export_the_current_roster_as_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $person = Shareholder::factory()->create([
            'name' => '홍길동',
            'registration' => '9001011234567',
            'sex' => '남',
            'contact_info' => '010-1111-2222',
            'address' => '서울시 강남구',
        ]);
        $worker = User::factory()->worker()->create(['name' => '김워커']);
        $assignment = ProjectShareholder::factory()->for($project)->for($person)->create([
            'no' => 7,
            'shares' => 1000,
            'shares_total' => 2000,
            'electronic_voting' => true,
            'contact_info' => null,
        ]);
        $assignment->workers()->attach($worker);

        $response = $this->actingAs($admin)->get(route('projects.shareholders.export', $project));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        // Header row matches the importable template.
        $this->assertSame(self::HEADERS, ShareholderImporter::templateHeaders());
        $this->assertStringContainsString('연번,실명번호,성별,주주명', $content);
        $this->assertStringContainsString('전자위임날짜', $content);
        // Current data, formatted as the importer expects it.
        $this->assertStringContainsString('900101-1234567', $content);   // re-hyphenated RRN
        $this->assertStringContainsString('홍길동', $content);
        $this->assertStringContainsString('"1,000"', $content);          // thousands-formatted shares
        $this->assertStringContainsString('Y', $content);                // electronic_voting bool
        $this->assertStringContainsString('010-1111-2222', $content);    // effective contact (person fallback)
        $this->assertStringContainsString('김워커', $content);           // joined 활동가 names
    }

    public function test_office_users_can_export(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create();

        $this->actingAs($office)
            ->get(route('projects.shareholders.export', $project))
            ->assertOk();
    }

    public function test_workers_cannot_export(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->create();

        $this->actingAs($worker)
            ->get(route('projects.shareholders.export', $project))
            ->assertRedirect(route('worker.dashboard'));
    }

    public function test_export_round_trips_back_through_the_importer(): void
    {
        $admin = User::factory()->admin()->create();
        $source = Project::factory()->create();
        User::factory()->worker()->create(['name' => '김워커']);
        $importer = app(ShareholderImporter::class);

        // Seed the source roster via the importer so we compare against its own mapping.
        $importer->import($source, $this->writeCsv([self::HEADERS, [
            '7', '900101-1234567', '남', '홍길동', '1,000', '2,000', 'Y',
            '서울시 강남구', '010-1111-2222', '010-3333-4444', 'old-db', '담당자', '김워커',
            '위임', '좋은 멘트', '비고 내용', '010-5555-6666', '2026-01-03',
        ]]));

        // Export, then re-import the produced file into a fresh project.
        $content = $this->actingAs($admin)
            ->get(route('projects.shareholders.export', $source))
            ->streamedContent();

        $exported = sys_get_temp_dir().'/export_'.uniqid().'.csv';
        file_put_contents($exported, $content);
        $target = Project::factory()->create();
        $importer->import($target, $exported);

        $original = $source->shareholders()->with('shareholder', 'workers')->first();
        $reimported = $target->shareholders()->with('shareholder', 'workers')->first();

        $this->assertSame($original->shareholder->name, $reimported->shareholder->name);
        $this->assertSame($original->shareholder->registration, $reimported->shareholder->registration);
        $this->assertSame($original->no, $reimported->no);
        $this->assertSame($original->shares, $reimported->shares);
        $this->assertSame($original->shares_total, $reimported->shares_total);
        $this->assertSame($original->electronic_voting, $reimported->electronic_voting);
        $this->assertSame($original->contact_worker, $reimported->contact_worker);
        $this->assertSame($original->api_recipient_completion_date->toDateString(), $reimported->api_recipient_completion_date->toDateString());
        $this->assertSame(
            $original->workers->pluck('name')->all(),
            $reimported->workers->pluck('name')->all(),
        );
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = sys_get_temp_dir().'/roster_'.uniqid().'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
