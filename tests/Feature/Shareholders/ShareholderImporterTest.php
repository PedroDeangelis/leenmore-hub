<?php

namespace Tests\Feature\Shareholders;

use App\Imports\ShareholderImporter;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ShareholderImporterTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        '연번', '실명번호', '성별', '주주명', '주식수', '총소유주식수', '전자투표',
        '주소', '주소서치', '주소서치2', '구연락처', '연락처', '활동가',
        '구 판단', '구 멘트', '비고', '전자위임연락처', '전자위임날짜',
    ];

    private function sampleRow(): array
    {
        return [
            '1', '900101-1234567', 'M', '홍길동', '1,000', '2,000', 'yes',
            '서울시 강남구', '010-1111-2222', '010-3333-4444', 'old-db', '김활동', '김워커 / 이워커',
            '위임', '좋은 멘트', '비고 내용', '010-5555-6666', '2026-01-03',
        ];
    }

    public function test_it_imports_people_and_assignments_with_the_legacy_mapping(): void
    {
        $project = Project::factory()->create();
        // The 활동가 names resolve to real worker users.
        User::factory()->worker()->create(['name' => '김워커']);
        User::factory()->worker()->create(['name' => '이워커']);
        $path = $this->writeCsv([self::HEADERS, $this->sampleRow()]);

        $summary = app(ShareholderImporter::class)->import($project, $path);

        $this->assertSame(1, $summary->created);
        $this->assertSame(0, $summary->skipped);

        $person = Shareholder::firstWhere('registration', '9001011234567');
        $this->assertNotNull($person);
        $this->assertSame('홍길동', $person->name);
        $this->assertSame('900101', $person->date_of_birth_code);
        // 주소서치 seeds the person's base contact; 주소 the base address.
        $this->assertSame('010-1111-2222', $person->contact_info);
        $this->assertSame('서울시 강남구', $person->address);

        $assignment = $project->shareholders()->first();
        $this->assertSame(1, $assignment->no);
        $this->assertSame(1000, $assignment->shares);
        $this->assertSame(2000, $assignment->shares_total);
        $this->assertSame('010-1111-2222', $assignment->contact_info);       // 주소서치
        $this->assertSame('010-3333-4444', $assignment->contact_info_2);     // 주소서치2
        $this->assertSame('old-db', $assignment->source_database);           // 구연락처
        $this->assertSame('김활동', $assignment->contact_worker);            // 연락처
        // 활동가 names resolved to worker user ids in the pivot.
        $this->assertEqualsCanonicalizing(['김워커', '이워커'], $assignment->workers->pluck('name')->all());
        $this->assertTrue($assignment->electronic_voting);
        $this->assertSame('위임', $assignment->prev_result);
        $this->assertSame('2026-01-03', $assignment->api_recipient_completion_date->toDateString());
        $this->assertSame('010-1111-2222', $assignment->effective_contact);
    }

    public function test_worker_names_link_to_users_and_unmatched_or_ambiguous_are_skipped(): void
    {
        $project = Project::factory()->create();
        $kim = User::factory()->worker()->create(['name' => '김활동']);
        $lee = User::factory()->worker()->create(['name' => '이담당']);
        // Two workers share a name → ambiguous, must not be linked.
        User::factory()->worker()->create(['name' => '박미상']);
        User::factory()->worker()->create(['name' => '박미상']);

        $row = $this->sampleRow();
        $row[12] = '김활동 / 이담당 / 박미상 / 최없음';  // 활동가: 2 match, 1 ambiguous, 1 missing
        $path = $this->writeCsv([self::HEADERS, $row]);

        app(ShareholderImporter::class)->import($project, $path);

        $workers = $project->shareholders()->first()->workers;
        $this->assertEqualsCanonicalizing([$kim->id, $lee->id], $workers->pluck('id')->all());
    }

    public function test_reimport_syncs_the_worker_set(): void
    {
        $project = Project::factory()->create();
        $kim = User::factory()->worker()->create(['name' => '김활동']);
        $lee = User::factory()->worker()->create(['name' => '이담당']);
        $importer = app(ShareholderImporter::class);

        $first = $this->sampleRow();
        $first[12] = '김활동';
        $importer->import($project, $this->writeCsv([self::HEADERS, $first]));
        $this->assertSame([$kim->id], $project->shareholders()->first()->workers->pluck('id')->all());

        // Re-import the same shareholder with a different worker set → replaced.
        $second = $this->sampleRow();
        $second[12] = '이담당';
        $importer->import($project, $this->writeCsv([self::HEADERS, $second]));
        $this->assertSame([$lee->id], $project->shareholders()->first()->workers()->pluck('users.id')->all());
    }

    public function test_invalid_rows_are_skipped(): void
    {
        $project = Project::factory()->create();
        $blankName = $this->sampleRow();
        $blankName[3] = '';                 // no 주주명
        $blankReg = $this->sampleRow();
        $blankReg[1] = '';                  // no 실명번호

        $path = $this->writeCsv([self::HEADERS, $blankName, $blankReg, $this->sampleRow()]);

        $summary = app(ShareholderImporter::class)->import($project, $path);

        $this->assertSame(1, $summary->created);
        $this->assertSame(2, $summary->skipped);
        $this->assertSame(1, $project->shareholders()->count());
    }

    public function test_reimport_upserts_and_preserves_the_worker_set_result(): void
    {
        $project = Project::factory()->create();
        $result = ProjectResult::factory()->for($project)->create();
        $path = $this->writeCsv([self::HEADERS, $this->sampleRow()]);
        $importer = app(ShareholderImporter::class);

        $importer->import($project, $path);

        // A worker sets the current 판단 after the first import.
        $assignment = $project->shareholders()->first();
        $assignment->update(['result_id' => $result->id]);

        // Re-importing the same file updates rather than duplicates...
        $summary = $importer->import($project, $path);

        $this->assertSame(0, $summary->created);
        $this->assertSame(1, $summary->updated);
        $this->assertSame(1, Shareholder::count());
        $this->assertSame(1, $project->shareholders()->count());
        // ...and never clobbers the worker-driven result.
        $this->assertSame($result->id, $assignment->refresh()->result_id);
    }

    public function test_each_row_streams_mapped_rows_without_the_header(): void
    {
        $path = $this->writeCsv($this->manyRows(5));

        $rows = iterator_to_array(app(ShareholderImporter::class)->eachRow($path, 'csv'), false);

        $this->assertCount(5, $rows);
        $this->assertSame('주주1', $rows[0]['name']);
    }

    public function test_it_reads_xlsx_files(): void
    {
        $project = Project::factory()->create();
        $path = $this->writeXlsx([self::HEADERS, $this->sampleRow()]);

        $summary = app(ShareholderImporter::class)->import($project, $path);

        $this->assertSame(1, $summary->created);
        $this->assertNotNull(Shareholder::firstWhere('registration', '9001011234567'));
    }

    /**
     * Header + $n valid rows with unique registrations.
     *
     * @return array<int, array<int, string>>
     */
    private function manyRows(int $n): array
    {
        $rows = [self::HEADERS];
        for ($i = 1; $i <= $n; $i++) {
            $reg = str_pad((string) $i, 6, '0', STR_PAD_LEFT).'-1234567';
            $rows[] = [
                (string) $i, $reg, 'M', "주주{$i}", '100', '100', 'no',
                '서울', '010-0000-0000', '', '', '', '',
                '', '', '', '', '',
            ];
        }

        return $rows;
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

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeXlsx(array $rows): string
    {
        $path = sys_get_temp_dir().'/roster_'.uniqid().'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}
