<?php

namespace Tests\Feature\Projects;

use App\Exports\ActivityLogExporter;
use App\Models\EsignonShareholder;
use App\Models\Project;
use App\Models\ProjectResult;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ActivityLogExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_headers_span_a_per_day_block_for_the_whole_submission_period(): void
    {
        $project = Project::factory()->create();
        $assignment = ProjectShareholder::factory()->for($project)->create();

        // Two dated reports three days apart: the block must fill every day in
        // between (06/21, 06/22, 06/23), four columns each.
        Submission::factory()->forAssignment($assignment)->create(['date' => '2026-06-21']);
        Submission::factory()->forAssignment($assignment)->create(['date' => '2026-06-23']);

        $headers = ActivityLogExporter::for($project)->headers();

        $this->assertCount(20 + 4 * 3, $headers);
        $this->assertContains('담당자0621', $headers);
        $this->assertContains('멘트0622', $headers);
        $this->assertContains('연락처 정보0623', $headers);
        $this->assertContains('판단0623', $headers);
        // No reports outside the range, so no stray columns.
        $this->assertNotContains('담당자0620', $headers);
        $this->assertNotContains('담당자0624', $headers);
    }

    public function test_rows_carry_roster_columns_and_per_day_aggregates(): void
    {
        $today = now()->format('Y-m-d');

        $project = Project::factory()->create();
        $result = ProjectResult::factory()->for($project)->create(['name' => '위임(대면_서명)']);
        $person = Shareholder::factory()->create([
            'name' => '홍길동',
            'registration' => '9001011234567',
            'sex' => '남',
            'date_of_birth_code' => '900101',
            'address' => '서울시 강남구',
        ]);
        $worker = User::factory()->worker()->create(['name' => '김워커']);
        $assignment = ProjectShareholder::factory()->for($project)->for($person)->create([
            'no' => 7,
            'shares' => 1000,
            'shares_total' => 2000,
            'electronic_voting' => true,
            'contact_info' => '010-1111-2222',
            'contact_info_2' => '010-3333-4444',
            'source_database' => 'old-db',
            'contact_worker' => '담당연락',
            'result_id' => $result->id,
            'api_recipient_contact' => '010-5555-6666',
            'api_recipient_completion_date' => '2026-01-03',
        ]);
        $assignment->workers()->attach($worker);

        // Same-day pair: notes/contacts accumulate, the later report wins the
        // assignee + 판단; the consent flag latches on.
        Submission::factory()->forAssignment($assignment)->create([
            'date' => '2026-06-21', 'user_name' => '활동가갑', 'note' => '부재',
            'contact' => '010-1', 'result' => '단순부재', 'privacy_consent' => false,
        ]);
        Submission::factory()->forAssignment($assignment)->create([
            'date' => '2026-06-21', 'user_name' => '활동가을', 'note' => '재방문',
            'contact' => '010-2', 'result' => '위임(대면_서명)', 'privacy_consent' => true,
        ]);
        Submission::factory()->forAssignment($assignment)->create([
            'date' => '2026-06-23', 'user_name' => '활동가병', 'note' => '거절',
            'contact' => null, 'result' => '거부', 'privacy_consent' => false,
        ]);

        $rows = iterator_to_array(ActivityLogExporter::for($project)->rows());
        $this->assertCount(1, $rows);
        $row = $rows[0];

        // Roster block.
        $this->assertSame(7, $row[0]);                 // 연번
        $this->assertSame($project->title, $row[1]);   // 발행회사
        $this->assertSame('900101-1234567', $row[2]);  // 실명번호 (re-hyphenated)
        $this->assertSame('홍길동900101', $row[3]);     // 고유번호
        $this->assertSame('남', $row[4]);              // 성별
        $this->assertSame('홍길동', $row[5]);           // 주주명
        $this->assertSame(1000, $row[6]);              // 주식수
        $this->assertSame(2000, $row[7]);              // 총소유주식수
        $this->assertSame('Y', $row[8]);               // 전자투표
        $this->assertSame('서울시 강남구', $row[9]);    // 주소 (person fallback)
        $this->assertSame('010-1111-2222', $row[10]);  // 주소서치
        $this->assertSame('010-3333-4444', $row[11]);  // Contact 2
        $this->assertSame('old-db', $row[12]);         // 구연락처
        $this->assertSame('담당연락', $row[13]);        // 연락처
        $this->assertSame('김워커', $row[14]);          // 담당자 (worker)
        $this->assertSame($today, $row[15]);           // 제출 날짜 (latest created_at)
        $this->assertSame('위임(대면_서명)', $row[16]); // 판단 (current result)
        $this->assertSame('동의', $row[17]);            // 개인정보 동의
        $this->assertSame('010-5555-6666', $row[18]);  // 전자위임연락처
        $this->assertSame('2026-01-03', $row[19]);     // 전자위임날짜

        // 06/21 block (indices 20-23): latest-of-day assignee + result,
        // accumulated notes + contacts.
        $this->assertSame('활동가을', $row[20]);
        $this->assertSame('부재 / 재방문', $row[21]);
        $this->assertSame('010-1 / 010-2', $row[22]);
        $this->assertSame('위임(대면_서명)', $row[23]);

        // 06/22 block (indices 24-27): a quiet day, all blank.
        $this->assertSame(['', '', '', ''], array_slice($row, 24, 4));

        // 06/23 block (indices 28-31).
        $this->assertSame('활동가병', $row[28]);
        $this->assertSame('거절', $row[29]);
        $this->assertSame('', $row[30]);
        $this->assertSame('거부', $row[31]);
    }

    public function test_admins_download_a_real_xlsx_workbook(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['title' => 'Annual Meeting 2026']);
        $assignment = ProjectShareholder::factory()->for($project)->create();
        Submission::factory()->forAssignment($assignment)->create(['date' => '2026-06-21']);

        $response = $this->actingAs($admin)->get(route('projects.activity-log.export', $project));

        $response->assertOk();
        $response->assertDownload('activity-log-annual-meeting-2026.xlsx');

        // A valid .xlsx is a ZIP container — confirm openspout actually wrote one.
        $content = file_get_contents($response->getFile()->getPathname());
        $this->assertStringStartsWith('PK', $content);
    }

    public function test_office_users_can_download(): void
    {
        $office = User::factory()->office()->create();
        $project = Project::factory()->create();

        $this->actingAs($office)
            ->get(route('projects.activity-log.export', $project))
            ->assertOk();
    }

    public function test_unmatched_esignon_signers_get_a_missing_shareholders_sheet(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        EsignonShareholder::factory()->for($project)->create([
            'name' => '미가입주주', 'identifier' => '880808',
            'contact' => '010-9999-0000', 'completed_date' => '2026-02-10 09:30:00',
            'needs_sync' => true,
        ]);
        // An already-matched signer must NOT appear on the sheet.
        EsignonShareholder::factory()->for($project)->synced()->create(['name' => '이미가입']);

        $path = $this->actingAs($admin)
            ->get(route('projects.activity-log.export', $project))
            ->assertOk()
            ->getFile()->getPathname();

        [$sheetNames, $missingRows] = $this->readSheets($path, '주주명부 누락');

        $this->assertContains('주주명부 누락', $sheetNames);
        $this->assertSame(['이름', '생년월일', '전자위임시간', '전자위임연락처'], $missingRows[0]);
        $this->assertCount(2, $missingRows); // header + the one unmatched signer
        $this->assertSame('미가입주주', $missingRows[1][0]);
        $this->assertSame('880808', $missingRows[1][1]);
        $this->assertSame('010-9999-0000', $missingRows[1][3]);
    }

    public function test_no_missing_sheet_when_every_signer_matched(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        EsignonShareholder::factory()->for($project)->synced()->create();

        $path = $this->actingAs($admin)
            ->get(route('projects.activity-log.export', $project))
            ->assertOk()
            ->getFile()->getPathname();

        [$sheetNames] = $this->readSheets($path, '주주명부 누락');

        $this->assertNotContains('주주명부 누락', $sheetNames);
        $this->assertCount(1, $sheetNames); // the activity-log sheet only
    }

    /**
     * Read an xlsx: returns [all sheet names, rows of the named sheet as arrays].
     *
     * @return array{0: list<string>, 1: list<array<int, mixed>>}
     */
    private function readSheets(string $path, string $target): array
    {
        $reader = new Reader;
        $reader->open($path);

        $names = [];
        $targetRows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();

            if ($sheet->getName() === $target) {
                foreach ($sheet->getRowIterator() as $row) {
                    $targetRows[] = $row->toArray();
                }
            }
        }

        $reader->close();

        return [$names, $targetRows];
    }

    public function test_workers_cannot_download(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->create();

        $this->actingAs($worker)
            ->get(route('projects.activity-log.export', $project))
            ->assertRedirect(route('worker.dashboard'));
    }
}
